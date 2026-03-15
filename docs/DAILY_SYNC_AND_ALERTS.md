# Sincronización Diaria y Alertas de Actuaciones

Este documento explica el funcionamiento del proceso cron encargado de mantener actualizados los procesos judiciales y disparar el motor de alertas por palabras clave.

## 🛠 Comandos de Infraestructura (Colas)

Para que el flujo de sincronización y alertas funcione, deben estar activos los siguientes workers:

| Cola | Comando | Función |
| :--- | :--- | :--- |
| **Sincronización** | `php artisan queue:work --queue=judicial-sync` | Consulta la API para buscar nuevas actuaciones y sincronizarlas. |
| **Notificaciones** | `php artisan queue:work --queue=notifications` | Despacha correos, alertas internas y registros de logs (SMS/WA). |

## ⚙️ Variables de Entorno (.env)

| Variable | Descripción |
| :--- | :--- |
| `JUDICIAL_BRANCH_PROXY_ENABLED` | (true/false) Activa el uso de proxies para evadir bloqueos de la API. |
| `JUDICIAL_BRANCH_TIMEOUT` | Tiempo de espera para la API de la Rama Judicial (segundos). |
| `IA_KEYWORD_DETECTION_ENABLED` | (true/false) Activa la IA como árbitro final en la detección de palabras clave. |
| `LOG_JUDICIAL_NOTIFICATIONS` | Canal de log para histórico de alertas (default: `judicial_sync_notifications`). |

---

## 🚀 Flujo del Proceso

### 1. Disparador (Cron)
El comando `SyncJudicialProcessesCommand` se ejecuta (ej: cada noche) y:
- Identifica todos los números de radicado (23 dígitos) activos en el sistema.
- Crea un `SyncProcessJob` por cada número de radicado.

### 2. Motor de Sincronización (`judicial-sync`)
El servicio `ProcessSyncService` gestiona la consulta con reglas de optimización:
- **Proceso con historial**: Solo consulta la **Página 1** de la API para detectar lo último registrado.
- **Proceso nuevo (o instancia vacía)**: Consulta **todas las páginas** para traer el historial completo.
- Crea las nuevas actuaciones en la base de datos y dispara el servicio de alertas.

### 3. Motor de Detección Híbrido
Por cada nueva actuación, el `ProcessActionKeywordDetectionService` analiza el texto:
1.  **Normalización**: Convierte a minúsculas y elimina acentos/caracteres especiales.
2.  **Match Exacto**: Busca coincidencias estrictas respetando límites de palabras.
3.  **Match Fuzzy (Fuzzy Matching)**: Si hay un error ortográfico leve (ej: "APLECION" vs "APELACIÓN"), el código lo valida automáticamente.
4.  **IA Umpire**: Si la similitud es dudosa, consulta a la IA (OpenAI/Ollama) para una decisión final de SI/NO.

### 4. Notificaciones Multitenant
El `ProcessActionAlertNotificationService` ejecuta la lógica por organización:
- **Notificación Obligatoria**: Se envía una alerta de "Nueva Actuación" a todos los interesados.
- **Alerta de Palabra Clave**: Solo se envía si la actuación coincide con las palabras que ese abogado configuró.
- **Highlights**: Se guardan las coordenadas (`start`, `end`) en la tabla `process_action_alert_highlights` asociadas a la organización para que el frontend las subraye en amarillo.

---

## 📂 Archivos de Logs para Verificación

Si el proceso falla o quieres ver las detecciones, revisa estos archivos:

1.  **`storage/logs/judicial_sync.log`**: Errores de conexión con la API Rama Judicial o problemas de proxy.
2.  **`storage/logs/judicial_sync_notifications.log`**: Aquí verás:
    - Confirmación de correos enviados.
    - Detección de palabras clave para cada organización.
    - **Simulación de SMS y WhatsApp** (aparecen como logs de texto con el mensaje).

---

## ⚖️ Reglas de Negocio Clave

- **Aislamiento**: La Org A nunca verá los subrayados amarillos ni las alertas de la Org B.
- **Doble Instancia**: Si un radicado tiene dos instancias (proceso 1 y proceso 2), el cron sincroniza ambos de forma independiente pero relacionada.
- **Uso de IA**: La IA solo se usa como "árbitro" cuando falla el código estricto, ahorrando tokens y tiempo de respuesta.
