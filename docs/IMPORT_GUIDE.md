# 🏛️ Manual de Operación: Importación Judicial

Este documento detalla el funcionamiento del motor de importación masiva y sincronización de procesos de la Portal Judicial.

## 1. Arquitectura del Flujo
La importación es un proceso asíncrono diseñado para manejar grandes volúmenes de datos sin bloquear el servidor.

1.  **Carga (Excel)**: El administrador sube un archivo con radicados (23 dígitos).
2.  **Despacho**: Cada radicado se convierte en un Job en la cola `process-import`.
3.  **Extracción (Scraping)**: El Job consulta la API (Puerto 448) usando proxies residenciales.
4.  **Notificación**: Al finalizar un lote (batch), se envía un reporte por la cola `emails_import_report`.
5.  **Historial**: Todas las actuaciones y sujetos se guardan de forma única por instancia procesal.

---

## 2. Configuración de Red (.env)
Para evitar el "bloqueo silencioso" y el error `cURL 97`, los proxies deben estar configurados así:

```env
JUDICIAL_BRANCH_PROXY_ENABLED=true
JUDICIAL_BRANCH_PROXY_PROVIDER=proxyscrape
JUDICIAL_BRANCH_PROXY_PROTOCOL=http

# Credenciales Premium (ProxyScrape)
JUDICIAL_BRANCH_PROXY_HOST=rp.scrapegw.com
JUDICIAL_BRANCH_PROXY_PORT=6060
JUDICIAL_BRANCH_PROXY_USERNAME=0h4vq9nbevfld56
JUDICIAL_BRANCH_PROXY_PASSWORD=b8ednleaqn58oh8
JUDICIAL_BRANCH_PROXY_ENABLE_SESSION_MUTATION=false
JUDICIAL_BRANCH_PROXY_MAX_CONNECTION_RETRIES=2
JUDICIAL_BRANCH_PROXY_CONNECTION_RETRY_BASE_MS=700
JUDICIAL_BRANCH_PROXY_CONNECTION_CIRCUIT_BREAKER_MS=3000

# Optimización de Tiempos (Human-Like Jitter)
JUDICIAL_BRANCH_PROXY_CALL_DELAY_MIN_MS=1000
JUDICIAL_BRANCH_PROXY_CALL_DELAY_MAX_MS=2500
JUDICIAL_BRANCH_PROXY_TIMEOUT=60
```

---

## 3. Gestión de Colas (Workers)
Para un procesamiento eficiente de 1000+ radicados, se deben correr los siguientes comandos en terminales separadas:

| Cola | Función | Comando |
| :--- | :--- | :--- |
| **`process-import`** | Importación Masiva (Admin) | `php artisan queue:work --queue=process-import --sleep=1 --tries=120` |
| **`judicial-sync`** | Cron Diario (Sync General) | `php artisan queue:work --queue=judicial-sync` |
| **`notifications`** | Alertas y Notificaciones Push | `php artisan queue:work --queue=notifications` |
| **`emails_import_report`** | Reportes de confirmación | `php artisan queue:work --queue=emails_import_report` |

> [!IMPORTANT]
> **Recomendación de Escalado**: Para procesar 1000 radicados en minutos, abre **10 terminales** corriendo el worker de `process-import`. El sistema repartirá la carga automáticamente.

---

## 4. Mejores Prácticas y Mantenimiento
*   **Sticky Sessions**: Mantén `JUDICIAL_BRANCH_PROXY_ENABLE_SESSION_MUTATION=false` en ProxyScrape salvo confirmación explícita del formato soportado por tu plan.
*   **Aislamiento de Doble Instancia**: Las actuaciones se validan por UUID de proceso, permitiendo que el Juzgado y el Tribunal tengan sus registros completos sin pisarse.
*   **Logs**: Monitorea fallos en `storage/logs/process_import-YYYY-MM-DD.log`.
*   **Retries**: El sistema tiene 6 reintentos internos por cada IP fallida del proxy antes de marcar el Job como fallido.
