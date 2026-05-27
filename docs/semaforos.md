# Semáforos de Inactividad y Alertas Judiciales

Este documento describe el funcionamiento técnico, las reglas de negocio y los componentes del sistema de semáforos procesales y alertas por palabras clave.

## 1. Reglas de Negocio del Semáforo

El sistema clasifica los procesos y actuaciones en tres niveles de severidad (Colores), basados en dos motores: **Inactividad Cronológica** y **Detección Semántica**.

### A. Motor de Inactividad
Gestionado por el Job `CheckInactiveProcessesJob`. Evalúa el tiempo transcurrido desde la última actuación oficial (`last_activity_date`).

| Color | Rol del Abogado | Condición (Días Inactivo) | Significado | Notificación |
| :--- | :--- | :--- | :--- | :--- |
| 🔴 **Rojo** | Demandante | >= 90 días | **Alerta Crítica:** Riesgo de desistimiento o parálisis grave. | `inactividad_roja` |
| 🟡 **Amarillo** | Demandante | 45 - 89 días | **Alerta Temprana:** Seguimiento necesario para evitar mora. | `inactividad_amarilla` |
| 🟢 **Verde** | Demandado | >= 90 días | **Informativo Favorable:** El proceso en contra no avanza (buenas noticias). | `inactividad_verde` |

### B. Motor de Alertas (Keywords)
Gestionado por `ProcessActionAlertNotificationService`. Analiza el texto de las nuevas actuaciones judiciales.

*   **Prioridad de Color:** Si una actuación activa múltiples keywords, se asigna el color de mayor severidad detectado (**Rojo > Amarillo > Verde**).
*   **Ejemplos Comunes:**
    *   `Rojo`: Sentencia, Mandamiento, Terminación.
    *   `Amarillo`: Traslado, Memorial, Oficio.
    *   `Verde`: Informativo, Constancia, Archivo.

---

## 2. Definición Técnica de Base de Datos

### Tablas y Columnas Afectadas

#### `organization_processes` (Tabla Pivote)
Almacena el estado actual del semáforo para cada organización vinculada a un proceso.
*   `lawyer_role`: Enum (`plaintiff`, `defendant`). Define qué reglas de inactividad aplicar.
*   `inactivity_alert_level`: String (`red`, `yellow`, `green`, `null`). Almacena el último estado de inactividad detectado.

#### `keywords` y `alert_actions_keywords`
*   `severity_color`: String (`red`, `yellow`, `green`, `null`). Define el peso semántico de la palabra clave.

#### `organization_notifications`
*   `severity_color`: String. Persiste el color detectado en el momento de la alerta para su visualización en el dashboard/emails.

---

## 3. Automatización y Cron

### Comando Programado
El motor de inactividad se ejecuta automáticamente mediante el scheduler de Laravel.

*   **Comando:** `Schedule::job(new CheckInactiveProcessesJob)`
*   **Frecuencia:** Diariamente (`dailyAt('08:00')`).
*   **Archivo de Configuración:** `routes/console.php`.

### Reset Automático del Semáforo
Cuando el sistema detecta una **nueva actuación judicial** real a través del `ProcessSyncService`:
1.  Se actualiza `last_activity_date` en la tabla `processes`.
2.  Se limpia (`null`) la columna `inactivity_alert_level` en `organization_processes`.
3.  Esto reinicia el contador de días para el próximo ciclo de alertas de inactividad.

---

## 4. Gestión de Colas (Queues)

El sistema utiliza las siguientes colas para procesar las alertas sin bloquear el flujo principal de sincronización:

| Componente | Cola (Queue Name) | Responsabilidad |
| :--- | :--- | :--- |
| **Notificaciones** | `notifications` | Procesamiento y envío de alertas (Email, SMS, Webhook). |
| **Consolidados** | `digests` | (Opcional) Generación de resúmenes diarios de actuaciones. |
| **Sincronización** | `judicial-sync` | Consulta masiva a la API de la Rama Judicial. |

---

## 5. Pruebas y QA
Los tests asociados se encuentran en:
*   `tests/Application/Shared/Jobs/CheckInactiveProcessesJobTest.php`
*   `tests/Feature/ProcessActionAlertNotificationServiceTest.php`
