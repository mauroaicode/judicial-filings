# Semáforos de Inactividad y Alertas Judiciales

Este documento describe el funcionamiento técnico, las reglas de negocio y los componentes del sistema de semáforos procesales y alertas por palabras clave.

## 1. Reglas de Negocio del Semáforo

El sistema clasifica los procesos en tres niveles de severidad (colores), basados en dos motores: **Inactividad Cronológica** (según rol del abogado) y **Detección Semántica** (keywords en actuaciones).

Todos los umbrales usan **días transcurridos desde la última actuación oficial** (`last_activity_date`).

### A. Motor de Inactividad — Demandante (`plaintiff`)

Gestionado por `CheckInactiveProcessesJob` (diario) y calculado en tiempo real por `ProcessAlertLevelHelper` para el listado.

| Color | Condición (días sin actuación) | Significado | Notificación |
| :--- | :--- | :--- | :--- |
| 🟢 **Verde** | **< 45 días** | Proceso avanzando; situación favorable. | — |
| 🟡 **Amarillo** | **45 – 89 días** | Alerta temprana: seguimiento necesario para evitar mora. | `inactividad_amarilla` |
| 🔴 **Rojo** | **≥ 90 días** | Alerta crítica: riesgo de desistimiento o parálisis grave. | `inactividad_roja` |

### B. Motor de Inactividad — Demandado (`defendant`)

**Lógica invertida respecto al demandante:** actividad reciente es mala (hay que estar pendientes); inactividad prolongada es favorable.

| Color | Condición (días sin actuación) | Significado | Notificación |
| :--- | :--- | :--- | :--- |
| 🔴 **Rojo** | **< 45 días** | El proceso en contra avanza; el abogado debe estar pendiente. | `actividad_roja` |
| 🟡 **Amarillo** | **45 – 89 días** | Actividad moderada; seguimiento recomendado. | `actividad_amarilla` |
| 🟢 **Verde** | **≥ 90 días** | Informativo favorable: el proceso en contra no avanza. | `inactividad_verde` |

### C. Motor de Alertas (Keywords)

Gestionado por `ProcessActionAlertNotificationService`. Analiza el texto de las nuevas actuaciones judiciales.

*   **Prioridad de Color:** Si una actuación activa múltiples keywords, se asigna el color de mayor severidad detectado (**Rojo > Amarillo > Verde**).
*   **Independiente del rol:** Aplica igual para demandante y demandado.
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
*   `inactivity_alert_level`: String (`red`, `yellow`, `green`, `null`). Persistido por el job diario y al registrar/actualizar configuración. El listado recalcula desde `last_activity_date` vía `ProcessAlertLevelHelper`.

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
3.  El semáforo visible se recalcula de inmediato según el nuevo `last_activity_date` y el `lawyer_role`.

---

## 4. Gestión de Colas (Queues)

El sistema utiliza las siguientes colas para procesar las alertas sin bloquear el flujo principal de sincronización:

| Componente | Cola (Queue Name) | Responsabilidad |
| :--- | :--- | :--- |
| **Notificaciones** | `notifications` | Procesamiento y envío de alertas (Email, SMS, Webhook). |
| **Consolidados** | `digests` | (Opcional) Generación de resúmenes diarios de actuaciones. |
| **Sincronización** | `judicial-sync` | Consulta masiva a la API de la Rama Judicial. |

---

## 5. Configuración

Umbrales en `config/semaphores.php`:

```php
'inactivity_thresholds' => [
    'plaintiff' => ['red' => 90, 'yellow' => 45],
    'defendant' => ['green' => 90, 'yellow' => 45],
],
```

---

## 6. Pruebas y QA
Los tests asociados se encuentran en:
*   `tests/Application/Shared/Helpers/ProcessAlertLevelHelperTest.php`
*   `tests/Application/Shared/Jobs/CheckInactiveProcessesJobTest.php`
*   `tests/Feature/ProcessActionAlertNotificationServiceTest.php`
