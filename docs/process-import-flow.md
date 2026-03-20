# Flujo de Importación de Radicados

## Visión general

El usuario sube un archivo Excel con números de radicados (23 dígitos). El sistema los valida, filtra los ya registrados y pone en cola un job por radicado que consulta la API de Portal Judicial y los registra. Al finalizar todos los jobs se envían notificaciones al administrador y a la organización.

---

## 1. Endpoint

```
POST /api/admin/processes/import
Content-Type: multipart/form-data

organization_id  UUID de la organización (required, must exist in DB)
file             Archivo .xlsx o .xls (required)
```

---

## 2. Clases involucradas (en orden de ejecución)

```
HTTP Request
    │
    ▼
ProcessImportController::import()
    Valida la request vía ProcessImportFromExcelData (Spatie Data).
    Extrae organization_id y file como primitivos.
    │
    ▼
ProcessImportService::handle()
    Verifica que la organización esté activa.
    Delega la preparación de datos y el despacho.
    │
    ├─► ProcessImportDataService::handle()
    │       Lee el Excel con ProcessImportExcelReader.
    │       Filtra radicados ya registrados.
    │       Devuelve ProcessImportDataResult (lista toEnqueue + skipped).
    │
    └─► ProcessImportBatchService::dispatch()
            Crea registro ProcessImportBatch (estado: PROCESSING).
            Construye un ImportRadicadoJob por radicado con delay escalonado.
            Despacha el Laravel Batch.
            Registra el callback then() para cuando el batch complete.
            Devuelve HTTP 202 con batch_id.
```

### Procesamiento asíncrono (queue)

```
Cola: process-import  (PROCESS_IMPORT_QUEUE)
    │
    ▼  (un job por radicado)
ImportRadicadoJob::handle()
    Llama RegisterProcessService::handle(processNumber, organizationId).
    Actualiza success_count o failed_count en ProcessImportBatch.
    │
    └─► RegisterProcessService
            Consulta la API de Portal Judicial via JudicialBranchConsultService.
            JudicialBranchConsultService::throttle() bloquea con sleep()
            si se supera el rate limit interno — NO lanza excepción.
            Registra el proceso y sus actuaciones.
```

### Reintentos del job

| Escenario | Reintento | Espera |
|-----------|-----------|--------|
| 403 / 429 real de la API | Sí, hasta `PROCESS_IMPORT_TRIES` | `PROCESS_IMPORT_RETRY_RELEASE_RATE_LIMIT` + jitter 20 % |
| Radicado no existe en Rama (transitorio) | Sí, hasta `PROCESS_IMPORT_RETRY_MAX_ATTEMPTS_NOT_FOUND` | `PROCESS_IMPORT_RETRY_RELEASE_SECONDS_NOT_FOUND` |
| 200 con procesos vacíos | Sí, hasta `PROCESS_IMPORT_RETRY_MAX_ATTEMPTS_EMPTY` | `PROCESS_IMPORT_RETRY_RELEASE_SECONDS_EMPTY` |
| Otro error | Sí, hasta `PROCESS_IMPORT_RETRY_MAX_ATTEMPTS` | `PROCESS_IMPORT_RETRY_RELEASE_SECONDS` |

> **¿Por qué reintentar el 200 vacío?**  
> Portal Judicial puede devolver HTTP 200 con array vacío de forma **transitoria** cuando está bajo carga (comportamiento observado en logs). Si tras todos los reintentos sigue vacío, se trata como fallo definitivo: el radicado genuinamente no existe en Portal Judicial.

### Completado del batch

Cuando el último job termina, Laravel llama al callback `then()`:

```
ProcessImportBatchService::onBatchCompleted()
    Marca ProcessImportBatch → COMPLETED.
    Construye ProcessImportReport DTO.
    │
    ├─► ImportReportNotificationService::notifyAdmin()
    │       Email al ADMIN_PROCESS_IMPORT_REPORT_EMAIL (si está configurado).
    │       Notificación internal → log (WebSocket futuro).
    │       ⚠ Sin registros en DB — admin no es una organización.
    │
    └─► ImportReportNotificationService::notifyOrganization()
            1. Crea OrganizationNotification (is_notified=false, notifiable=ProcessImportBatch).
            2. Despacha canal internal siempre (ignora is_active).
               Si existe registro en organization_notification_channels → guarda historial.
            3. Por cada canal activo (is_active=true) de la organización:
                email    → EmailImportReportChannelDriver
                whatsapp → WhatsAppImportReportChannelDriver  (placeholder)
                sms      → SmsImportReportChannelDriver       (placeholder)
               Cada intento (exitoso o fallido) crea un registro en
               HistoryOrganizationChannelNotification.
            4. Si al menos un canal tuvo éxito →
               OrganizationNotification.is_notified = true + notified_at = now().
```

---

## 3. Colas que deben estar corriendo

| Cola | Propósito | Variable .env |
|------|-----------|---------------|
| `process-import` | Jobs de consulta a Portal Judicial | `PROCESS_IMPORT_QUEUE` |
| `emails_import_report` | Envío del email de reporte al admin | — (hardcoded en `ProcessImportReportNotification`) |

```bash
# Levantar ambas colas (workers separados recomendado en producción)
php artisan queue:work --queue=process-import --sleep=1 --tries=120
php artisan queue:work --queue=emails_import_report
```

> **Sin proxies:** Un solo worker en `process-import` es suficiente y recomendado.
> El throttle interno bloquea con `sleep()` hasta liberar cupo.
> Con múltiples workers se agota el rate limit más rápido.
>
> **Con proxies activos:** Se pueden levantar múltiples workers ya que cada
> request sale desde una IP diferente. Se recomienda 2–4 workers en paralelo.
> `PROCESS_IMPORT_DELAY_SECONDS=3` garantiza tiempo para persistir en BD.

---

## 3.1 Proxy Pool — rotación de IPs

El `ProxyPoolService` gestiona un pool de proxies HTTP que se rota aleatoriamente
en cada request a Portal Judicial, eliminando el rate limit por IP.

### Arquitectura

```
ImportRadicadoJob
    └─► RegisterProcessService
            └─► JudicialBranchConsultService
                    ├─ throttle()         → sleep 3s (con proxy) o RateLimiter (sin proxy)
                    ├─ buildHttpClient()  → inyecta proxy aleatorio del pool
                    │       └─► ProxyPoolService::next()
                    │               ├─ lee pool del caché (o lo carga)
                    │               └─ array_rand($proxies) → ip:port aleatoria
                    └─ request HTTP → Portal Judicial (vía proxy)
```

### Proveedores disponibles

| Variable | Valor | Descripción |
|----------|-------|-------------|
| `JUDICIAL_BRANCH_PROXY_PROVIDER` | `proxyscrape` | Proxies HTTP datacenter. Respuesta: texto plano `ip:port` por línea. Soportan CONNECT tunneling (HTTPS puerto 448). **Recomendado.** |
| `JUDICIAL_BRANCH_PROXY_PROVIDER` | `geonode` | Proxies HTTP gratuitos. Respuesta: JSON `{ data: [ { ip, port } ] }`. Calidad variable. |

### Activar proxies (configuración rápida)

```dotenv
JUDICIAL_BRANCH_PROXY_ENABLED=true
JUDICIAL_BRANCH_PROXY_PROVIDER=proxyscrape   # o geonode
PROCESS_IMPORT_DELAY_SECONDS=3               # tiempo para persistir en BD entre requests
```

### Desactivar proxies (volver a rate limit interno)

```dotenv
JUDICIAL_BRANCH_PROXY_ENABLED=false
PROCESS_IMPORT_DELAY_SECONDS=15              # delay escalonado sin proxies
JUDICIAL_BRANCH_RATE_LIMIT_PER_MINUTE=8
```

### Comportamiento del throttle según modo

| Modo | Throttle aplicado |
|------|-------------------|
| **Sin proxy** | `RateLimiter` interno: bloquea con `sleep()` hasta liberar slot. Requiere 1 solo worker. |
| **Con proxy** | Sleep fijo de `PROCESS_IMPORT_DELAY_SECONDS` segundos (default 3s) para dar tiempo a la BD. Soporta múltiples workers. |

### Selección de proxy

Se usa `array_rand()` para seleccionar una IP aleatoria del pool en cada request.
No requiere contadores compartidos (evita el bug de `Cache::increment` con driver `database`).

### Caché del pool

El listado de proxies se cachea `JUDICIAL_BRANCH_PROXY_CACHE_TTL_MINUTES` minutos
(default: 60). Para forzar recarga: `php artisan cache:forget judicial_proxy_pool`.

### Logs

Cada request registra la IP usada (o "direct connection") en el canal `process_import`:

```
[ProxyPool]    Proxy pool loaded {"provider":"proxyscrape","count":1000}
[JudicialBranch] Using proxy {"proxy":"http://104.207.46.209:3129","pool_count":1000}
[JudicialBranch] HTTP 403 from Portal Judicial {"context":"fetchProcesses","proxy_mode":"proxy pool [1000 IPs]"}
```

---

## 4. Variables de entorno relevantes

```dotenv
# ── Portal Judicial API ──────────────────────────────────────────────────────────
JUDICIAL_BRANCH_API_URL=https://consultaprocesos.ramajudicial.gov.co:448/api/v2
JUDICIAL_BRANCH_TIMEOUT_SECONDS=60
JUDICIAL_BRANCH_LOG_CHANNEL=process_import

# Rate limit interno (solo aplica cuando JUDICIAL_BRANCH_PROXY_ENABLED=false)
JUDICIAL_BRANCH_RATE_LIMIT_PER_MINUTE=8   # máx peticiones HTTP/min a Portal Judicial

# ── Proxy Pool ─────────────────────────────────────────────────────────────────
JUDICIAL_BRANCH_PROXY_ENABLED=false        # true = activar rotación de IPs

# Proveedor: "proxyscrape" o "geonode"
JUDICIAL_BRANCH_PROXY_PROVIDER=proxyscrape

# URL ProxyScrape (respuesta: texto plano ip:port por línea)
# JUDICIAL_BRANCH_PROXY_PROXYSCRAPE_URL=https://api.proxyscrape.com/v2/account/...

# URL Geonode (respuesta: JSON { data: [ { ip, port } ] })
# JUDICIAL_BRANCH_PROXY_GEONODE_URL=https://proxylist.geonode.com/api/proxy-list?...

# TTL de caché del pool (minutos)
# JUDICIAL_BRANCH_PROXY_CACHE_TTL_MINUTES=60

# ── Colas e importación ────────────────────────────────────────────────────────
PROCESS_IMPORT_QUEUE=process-import
PROCESS_IMPORT_DELAY_SECONDS=3   # ≥3 con proxy; 15 sin proxy
PROCESS_IMPORT_TRIES=30
PROCESS_IMPORT_JOB_TIMEOUT=600

# Reintentos — 403/429 real de la API
PROCESS_IMPORT_RETRY_RELEASE_RATE_LIMIT=180

# Reintentos — otros errores genéricos
PROCESS_IMPORT_RETRY_MAX_ATTEMPTS=2
PROCESS_IMPORT_RETRY_RELEASE_SECONDS=120

# Reintentos — radicado no encontrado en Portal Judicial (puede ser transitorio)
PROCESS_IMPORT_RETRY_MAX_ATTEMPTS_NOT_FOUND=10
PROCESS_IMPORT_RETRY_RELEASE_SECONDS_NOT_FOUND=300

# Reintentos — 200 OK con array vacío (Portal Judicial bajo carga devuelve vacío transitoriamente)
PROCESS_IMPORT_RETRY_MAX_ATTEMPTS_EMPTY=3
PROCESS_IMPORT_RETRY_RELEASE_SECONDS_EMPTY=120

# ── Notificaciones ─────────────────────────────────────────────────────────────
ADMIN_PROCESS_IMPORT_REPORT_EMAIL=admin@ejemplo.com
```

---

## 5. Modelos de base de datos implicados

| Modelo | Tabla | Rol |
|--------|-------|-----|
| `ProcessImportBatch` | `process_import_batches` | Registro del lote: estado, contadores y errores |
| `Process` | `processes` | Radicado registrado |
| `ProcessAction` | `process_actions` | Actuaciones del radicado |
| `OrganizationNotificationChannel` | `organization_notification_channels` | Canales configurados de la organización (email, whatsapp, sms, internal) |
| `OrganizationNotification` | `organization_notifications` | Cabecera de notificación por lote; registra si fue entregada (`is_notified`) y si fue vista (`is_viewed`) |
| `HistoryOrganizationChannelNotification` | `history_organizations_channels_notifications` | Auditoría por canal: cada fila es un intento de entrega con su timestamp y resultado |

### Relación de tablas al completar el batch

```
process_import_batches (id = batchId)
    └─ organization_notifications
           notifiable_type = ProcessImportBatch
           notifiable_id   = batchId
           notification_type = 'import_report'
           is_notified     = true/false
           └─ history_organizations_channels_notifications  (una fila por canal)
                  organization_notification_channel_id = channel.id
                  is_notified = true/false
                  notified_at = timestamp o null
```

---

## 6. Logs

Todos los eventos se registran en el canal configurado con `PROCESS_IMPORT_LOG_CHANNEL` (default: `process_import`).

```
storage/logs/process_import-YYYY-MM-DD.log
```

Mensajes clave: `Import batch dispatched`, `Import radicado job started`,
`Import radicado finished successfully`, `Import batch completed`,
`Internal import report notification recorded`,
`Admin import report email queued`, `Import report channel dispatch failed`.

---

## 7. Agregar un nuevo canal de notificación

1. Crear `XxxImportReportChannelDriver` implementando `ImportReportChannelDriverInterface`.
2. Registrar el canal en `ImportReportNotificationService::CHANNEL_DRIVERS`:
   ```php
   private const CHANNEL_DRIVERS = [
       'email'    => EmailImportReportChannelDriver::class,
       'whatsapp' => WhatsAppImportReportChannelDriver::class,
       'sms'      => SmsImportReportChannelDriver::class,
       'xxx'      => XxxImportReportChannelDriver::class, // nuevo
   ];
   ```
3. Asegurarse de que la organización tenga un registro en `organization_notification_channels` con `channel_type = 'xxx'` e `is_active = true`.
