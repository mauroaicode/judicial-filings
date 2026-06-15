# Módulo de Tareas (Agenda Judicial)

Documento de referencia completo del módulo de **Tareas** del sistema **Judicial Filings**. Está pensado para ser usado como contexto por desarrolladores, diseñadores de producto e **IA** que implemente frontend, backend, jobs o integraciones.

---

## 1. Propósito del módulo

El módulo de Tareas funciona como una **Agenda Judicial** para abogados. Permite:

- Llevar control de **compromisos, términos y actuaciones** relacionadas con procesos legales.
- Asociar opcionalmente una tarea a un **radicado/proceso** de la organización.
- Definir **fecha de vencimiento** y **días de recordatorio**.
- Gestionar el **ciclo de vida** de la tarea: pendiente, borrador, cumplida, basurero y eliminación permanente.
- (Implementado) Mostrar un **semáforo visual de urgencia** según antigüedad de la tarea (calculado, no persistido).
- (Implementado) Enviar **recordatorios automáticos** según `reminder_days` y alertas de urgencia post-vencimiento.

> **Importante:** Este semáforo de tareas es **independiente** del semáforo de procesos documentado en `docs/semaforos.md`. Aquellos semáforos miden inactividad/alertas de **procesos judiciales**; el de tareas mide **antigüedad de una tarea pendiente sin cumplir**.

---

## 2. Contexto de producto (requerimientos originales)

### 2.1 Vista principal

- Listado de tareas en **tarjetas**.
- Por defecto muestra tareas en estado **`pending`** (pendientes).
- Las tareas **`completed`** no aparecen en la vista principal (archivadas por estado).
- Las tareas en **basurero** (`deleted_at` not null) tampoco aparecen en la vista principal.

### 2.2 Formulario crear/editar

Campos del formulario:

| Campo UI | Campo API | Obligatorio |
|----------|-----------|-------------|
| Título de la tarea | `title` | Sí |
| Descripción | `description` | Sí |
| Fecha de vencimiento | `due_date` | Sí |
| Días de recordatorio | `reminder_days` | Sí |
| Proceso asociado (opcional) | `process_id` | No |

> **Bug corregido:** al crear una tarea, el `process_id` del radicado seleccionado debe enviarse y persistirse en el **POST inicial**. Antes el usuario tenía que guardar vacío y editar después.

### 2.3 Semáforo visual (calculado — frontend y backend)

Reglas para calcular urgencia en tarjetas de tareas **pendientes no cumplidas**:

| Nivel API | Label UI (ES) | Condición | Descripción |
|-----------|---------------|-----------|-------------|
| `normal` | Normal | < 10 días desde `created_at` | Sin alerta |
| `alert_1` | **Alerta (10 días)** | ≥ 10 días | Primera alerta |
| `alert_2` | **Alerta alta (15 días)** | ≥ 15 días | Segunda alerta |
| `critical` | **Crítico (30+ días)** | ≥ 30 días | Límite máximo inaceptable |

**Cálculo:**

```
días_transcurridos = diferencia_en_días(hoy, created_at)  // startOfDay
```

Helper backend: `TaskUrgencyHelper::fromCreatedAt($createdAt)` → `TaskUrgencyLevel`.

Labels traducidos: `__('enums.task_urgency_level.*')` vía `TaskUrgencyLevel::getLabel()`.

- El semáforo **no se persiste en base de datos**; se calcula en tiempo real (frontend o helper en API).
- Solo aplica a tareas con `status = pending` (o posiblemente `draft`, según UX).
- No aplica a tareas `completed` ni a tareas en basurero.
- Para futuro entrenamiento de IA, lo valioso son los datos base (`created_at`, `due_date`, `status`, eventos) — no una columna de semáforo.

### 2.4 Recordatorios por vencimiento (`reminder_days`) — implementado

El usuario configura `reminder_days` (ej. 3). El sistema envía **un recordatorio diario** mientras:

```
0 <= días_restantes <= reminder_days
```

Ejemplo con `reminder_days = 3` y vencimiento el 17/jun:

| Día | Mensaje |
|-----|---------|
| 14/jun | "Le quedan 3 días" |
| 15/jun | "Le quedan 2 días" |
| 16/jun | "Le quedan 1 día" |
| 17/jun | "Vence hoy" |

**Comando:** `php artisan tasks:send-due-date-reminders`  
**Schedule:** diario a las **08:00**  
**Tracking:** columna `last_due_reminder_sent_on` (evita duplicar el mismo día)

Cuando pasa la fecha de vencimiento sin cumplir, **dejan de enviarse** estos recordatorios.

### 2.5 Alertas de urgencia por semáforo (10/15/30 días) — implementado

Solo aplican **después** de la fecha de vencimiento (`due_date < hoy`) y mientras la tarea siga `pending`.  
Si la tarea **no tiene** `due_date`, aplican desde el umbral de días desde creación.

| Nivel API | Label | Días desde creación | Frecuencia |
|-----------|-------|---------------------|------------|
| `alert_1` | Alerta (10 días) | ≥ 10 | Una vez |
| `alert_2` | Alerta alta (15 días) | ≥ 15 | Una vez |
| `critical` | Crítico (30+ días) | ≥ 30 | Una vez |

**Regla adicional:** si la tarea **ya venció** pero aún no cumple 10 días desde creación, se dispara al menos **`alert_1`** (evita hueco entre countdown y primera alerta).

**Comando:** `php artisan tasks:send-urgency-alerts`  
**Schedule:** diario a las **08:00** (`routes/console.php`)  
**Tracking:** columna `last_notified_urgency_level` (`alert_1`, `alert_2`, `critical`)

### Flujo completo

```
Crear tarea (pending)
    → Countdown diario (reminder_days antes del vencimiento)   ← tasks:send-due-date-reminders
    → Venció sin cumplir
    → Alertas semáforo 10 / 15 / 30 días desde creación        ← tasks:send-urgency-alerts
```

**Hueco resuelto:** entre el día posterior al vencimiento y el día 10 desde creación, la regla de vencimiento dispara `alert_1` aunque el semáforo por creación aún sea `normal`.

---

## 3. Arquitectura del código

### 3.1 Estructura de archivos

```
routes/api/app_user/tasks.php          # Rutas app-user
routes/api/admin/tasks.php             # Rutas admin (mismo controller, middleware admin)

src/Domain/Task/
  Models/Task.php
  Enums/TaskStatus.php
  Enums/TaskUrgencyLevel.php
  Data/TaskData.php
  Data/UpdateTaskStatusData.php
  QueryBuilders/TaskQueryBuilder.php

src/Application/Shared/Task/          # CRUD (controllers, services, resources)
src/Application/Shared/Helpers/
  TaskDueDateReminderHelper.php
  TaskUrgencyHelper.php
  ProcessNumberFormatHelper.php       # Formato radicado 76-001-33-33-018-2018-00247-01
  DateFormatHelper.php                # formatDateWithDayOfWeek() para emails

src/Application/Shared/Services/Task/
  ProcessPendingTaskDueDateRemindersService.php
  ProcessPendingTaskUrgencyAlertsService.php

src/Application/Shared/Services/Notification/
  TaskDueDateReminderNotificationService.php
  TaskUrgencyNotificationService.php
  Channels/                         # email, internal, sms, whatsapp (placeholders)

src/Application/Shared/DTOs/
  TaskDueDateReminderAlert.php
  TaskUrgencyAlert.php

src/Application/Shared/Notifications/
  TaskDueDateReminderMailNotification.php
  TaskDueDateReminderInternalNotification.php
  TaskUrgencyMailNotification.php
  TaskUrgencyInternalNotification.php

app/Console/Commands/
  SendTaskDueDateRemindersCommand.php   # tasks:send-due-date-reminders
  SendTaskUrgencyAlertsCommand.php      # tasks:send-urgency-alerts

config/tasks.php                      # umbrales, colas, URLs frontend

database/migrations/
  2026_04_13_175400_create_tasks_table.php
  2026_06_14_000000_add_due_date_and_reminder_days_to_tasks_table.php
  2026_06_14_010000_add_status_to_tasks_table.php
  2026_06_14_020000_add_deleted_at_to_tasks_table.php
  2026_06_14_030000_add_last_notified_urgency_level_to_tasks_table.php
  2026_06_14_040000_add_last_due_reminder_sent_on_to_tasks_table.php

resources/views/emails/
  task-due-date-reminder.blade.php
  task-urgency-alert.blade.php
  partials/task-notification-details.blade.php

resources/lang/{es,en}/
  task.php                            # textos de emails y notificaciones
  enums.php                           # task_status.*, task_urgency_level.*

tests/Application/Shared/
  Task/Controllers/TaskControllerTest.php
  Helpers/TaskDueDateReminderHelperTest.php
  Helpers/TaskUrgencyHelperTest.php
  Helpers/ProcessNumberFormatHelperTest.php
  Services/Task/ProcessPendingTaskDueDateRemindersServiceTest.php
  Services/Task/ProcessPendingTaskUrgencyAlertsServiceTest.php
```

### 3.2 Patrones del proyecto

- **Controller delgado** → delega a **Services**.
- Validación de entrada con **Spatie Laravel Data** (`TaskData`, `UpdateTaskStatusData`).
- Respuestas con **TaskResource**.
- Enums con labels traducidos vía `__('enums.task_status.*')`.
- Mismo `TaskController` compartido entre **app-user** y **admin**; el contexto cambia según autenticación y middleware.
- UUID como primary key (`Src\Domain\Shared\Traits\Uuid`).

---

## 4. Modelo de datos

### 4.1 Tabla `tasks`

| Columna | Tipo | Nullable | Default | Descripción |
|---------|------|----------|---------|-------------|
| `id` | UUID | No | auto | Primary key |
| `title` | string | No | — | Título de la tarea |
| `description` | text | No | — | Detalle/descripción |
| `due_date` | date | Sí* | — | Fecha de vencimiento (`YYYY-MM-DD`) |
| `reminder_days` | unsigned small int | Sí* | — | Días para recordatorio (≥ 0) |
| `status` | string | No | `pending` | Enum: `pending`, `completed`, `draft` |
| `is_admin` | boolean | No | `false` | `true` = tarea creada/gestionada desde panel admin |
| `process_id` | UUID | Sí | — | FK opcional a `processes` |
| `organization_id` | UUID | No | — | FK a `organizations` |
| `created_at` | timestamp | Sí | — | Fecha de creación |
| `updated_at` | timestamp | Sí | — | Última actualización |
| `deleted_at` | timestamp | Sí | null | Soft delete (basurero) |
| `last_notified_urgency_level` | string | Sí | null | Último nivel de urgencia notificado (`alert_1`, `alert_2`, `critical`) |
| `last_due_reminder_sent_on` | date | Sí | null | Último día en que se envió recordatorio por vencimiento |

\* En migración son nullable por compatibilidad con registros antiguos; en API de creación/edición son **requeridos**.

### 4.2 Relaciones

```
Organization 1 ──< * Task
Process      1 ──< * Task  (opcional, process_id nullable)
```

**Cascadas (FK):**

- Si se elimina un **proceso** → se eliminan sus tareas (hard cascade en BD).
- Si se elimina una **organización** → se eliminan todas sus tareas.
- Eliminar una tarea **no** elimina proceso ni organización.

### 4.3 Enum `TaskStatus`

Archivo: `src/Domain/Task/Enums/TaskStatus.php`

| Valor | Label ES | Label EN | Uso |
|-------|----------|----------|-----|
| `pending` | Pendiente | Pending | Default al crear. Vista principal. |
| `completed` | Cumplida | Completed | Archivada por cumplimiento. Sale del listado default. |
| `draft` | Borrador | Draft | Borrador; no aparece en listado default (`pending` only). |

Métodos útiles:

- `getLabel()` → label traducido según locale.
- `toArray()` → `[{ value, label }, ...]` para catálogos UI.
- `values()` → `['pending', 'completed', 'draft']`.

### 4.4 Soft delete (basurero)

- Modelo usa trait `SoftDeletes`.
- `DELETE /tasks/{id}` → mueve a basurero (`deleted_at` = now).
- Tareas en basurero **no** aparecen en operaciones normales (listado, show, update, complete, etc.) → **404**.
- Solo accesibles vía `GET /tasks/trash`, `POST /tasks/{id}/restore`, `DELETE /tasks/{id}/force`.

---

## 5. Ciclo de vida de una tarea

Existen **tres mecanismos distintos** de "salida" o archivo. No confundirlos:

```
                    ┌─────────────┐
                    │   CREAR     │
                    │  (pending)  │
                    └──────┬──────┘
                           │
         ┌─────────────────┼─────────────────┐
         ▼                 ▼                 ▼
   PATCH status       PATCH complete     DELETE (soft)
   → draft            → completed        → basurero
   → pending          (archivada)        (deleted_at)
   → completed              │                 │
         │                  │                 │
         └──────────────────┴─────────────────┘
                           │
              ┌────────────┴────────────┐
              ▼                         ▼
        Sigue en BD                 Basurero
   (filtrable por status)      POST restore → vuelve
                              DELETE force → borrado permanente
```

| Acción | Endpoint | Efecto en BD | ¿Recuperable? |
|--------|----------|--------------|---------------|
| Marcar cumplida | `PATCH .../complete` o `PATCH .../status` con `completed` | `status = completed` | Sí, con `PATCH .../status` → `pending` |
| Mover a borrador | `PATCH .../status` con `draft` | `status = draft` | Sí |
| Mover a basurero | `DELETE .../tasks/{id}` | `deleted_at` set | Sí, con `POST .../restore` |
| Eliminar permanentemente | `DELETE .../tasks/{id}/force` | Row eliminada | **No** |

**Reglas:**

- Al **crear** (`POST`), el status siempre queda en `pending`. No se envía en el body.
- Al **editar** (`PUT`), **no** se cambia el status. Usar endpoints de status.
- Tareas en basurero no pueden editarse ni cambiar status hasta restaurarlas.

---

## 6. API — App User

**Base URL:** `/api/app-user`  
**Autenticación:** `auth:sanctum` (Bearer token)  
**Organización:** el backend resuelve automáticamente la organización del usuario autenticado y fuerza `organization_id` e `is_admin = false` en create/update.

### 6.1 Listar tareas activas

```
GET /api/app-user/tasks
```

**Query params:**

| Param | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `status` | string | `pending` | Filtra por status válido: `pending`, `completed`, `draft` |
| `process_id` | UUID | — | Filtra por proceso |
| `per_page` | int | 20 | Paginación |
| `organization_id` | UUID | auto | App-user: lo sobreescribe el backend |

**Comportamiento default:** si no se envía `status`, solo devuelve tareas **`pending`**.  
**Excluye:** tareas en basurero (soft deleted) y tareas de otros status.

**Respuesta:** paginación Laravel estándar con items transformados por `TaskResource`.

---

### 6.2 Listar basurero

```
GET /api/app-user/tasks/trash
```

**Query params:** `per_page` (default 20).  
**Incluye:** solo tareas con `deleted_at` not null de la organización.  
**No filtra por status** — puede haber tareas cumplidas, borrador o pendientes en basurero.

---

### 6.3 Obtener catálogo de estados

```
GET /api/app-user/tasks/statuses
```

**Respuesta ejemplo:**

```json
[
  { "value": "pending", "label": "Pendiente" },
  { "value": "completed", "label": "Cumplida" },
  { "value": "draft", "label": "Borrador" }
]
```

---

### 6.4 Crear tarea

```
POST /api/app-user/tasks
```

**Body:**

```json
{
  "title": "Revisar términos del proceso",
  "description": "Se debe verificar la fecha límite para presentar el recurso de reposición.",
  "due_date": "2026-07-15",
  "reminder_days": 3,
  "is_admin": false,
  "organization_id": "c93bde00-8456-4b43-9bd4-07fac136c864",
  "process_id": "12505d91-1267-46f7-820c-b250875b7052"
}
```

**Validación (`TaskData`):**

| Campo | Reglas |
|-------|--------|
| `title` | required, string |
| `description` | required, string |
| `due_date` | required, date (`YYYY-MM-DD`) |
| `reminder_days` | required, integer, min 0 |
| `is_admin` | required, boolean (app-user: backend lo fuerza a `false`) |
| `organization_id` | required, uuid (app-user: backend lo fuerza al de la sesión) |
| `process_id` | optional, uuid |

**Reglas de negocio:**

- `organization_id` debe existir.
- Si `process_id` viene informado, el proceso debe pertenecer a la organización (tabla pivote `organization_processes`). Si no → **422** `process_id`.

**Resultado:** status = `pending` siempre. HTTP **201** + `TaskResource`.

---

### 6.5 Ver detalle

```
GET /api/app-user/tasks/{id}
```

Solo tareas **no** eliminadas (soft delete). Basurero → **404**.

---

### 6.6 Editar tarea

```
PUT /api/app-user/tasks/{id}
```

**Body:** mismo schema que POST (`TaskData`).  
**No modifica:** `status` (usar endpoint de status).  
**App-user:** `is_admin` se fuerza a `false` aunque el body envíe `true`.

---

### 6.7 Cambiar estado

```
PATCH /api/app-user/tasks/{id}/status
```

**Body:**

```json
{
  "status": "draft"
}
```

**Valores permitidos:** `pending`, `completed`, `draft`.

**Casos de uso:**

- Marcar cumplida: `{ "status": "completed" }`
- Reabrir: `{ "status": "pending" }`
- Pasar a borrador: `{ "status": "draft" }`

---

### 6.8 Atajo marcar cumplida

```
PATCH /api/app-user/tasks/{id}/complete
```

Equivalente a `PATCH .../status` con `"status": "completed"`.  
Idempotente: si ya está cumplida, devuelve la tarea sin error.

---

### 6.9 Mover a basurero

```
DELETE /api/app-user/tasks/{id}
```

**Soft delete.** HTTP **204** sin body.  
El registro permanece en BD con `deleted_at`.

---

### 6.10 Restaurar desde basurero

```
POST /api/app-user/tasks/{id}/restore
```

Solo tareas en basurero. HTTP **200** + `TaskResource` con `deleted_at: null`.

---

### 6.11 Eliminar permanentemente

```
DELETE /api/app-user/tasks/{id}/force
```

Solo tareas **en basurero**. Si la tarea está activa → **404**.  
HTTP **204**. Borrado irreversible.

---

## 7. API — Admin

**Base URL:** `/api/admin`  
**Autenticación:** `auth:sanctum` + middleware `admin.role`

Mismos endpoints y contratos que app-user, con diferencias:

- No fuerza automáticamente `organization_id` del usuario (admin puede operar cross-org según body).
- Puede crear tareas con `is_admin = true`.
- Rutas idénticas bajo `/api/admin/tasks/...`

---

## 8. Respuesta API (`TaskResource`)

Todas las operaciones que devuelven una tarea usan este shape:

```json
{
  "id": "uuid",
  "title": "Revisar términos del proceso",
  "description": "Se debe verificar...",
  "due_date": "2026-07-15",
  "reminder_days": 3,
  "status": "pending",
  "status_label": "Pendiente",
  "is_admin": false,
  "process_id": "uuid-or-null",
  "process_number": "76-001-40-03-012-2024-00370-00",
  "organization_id": "uuid",
  "created_at": "2026-06-13T14:30:00.000000Z",
  "deleted_at": null
}
```

| Campo | Notas |
|-------|-------|
| `due_date` | Formato `YYYY-MM-DD` |
| `reminder_days` | Entero ≥ 0 |
| `status` | Valor enum (`pending`, `completed`, `draft`) |
| `status_label` | Traducido según locale de la app |
| `process_number` | Radicado sin formatear (23 dígitos) desde relación `process`; null si no hay proceso. Para UI/emails usar `ProcessNumberFormatHelper::format()` |
| `created_at` | ISO 8601 |
| `deleted_at` | ISO 8601 en basurero; `null` en tareas activas |

**Listados paginados:** envoltorio Laravel estándar:

```json
{
  "data": [ /* TaskResource[] */ ],
  "current_page": 1,
  "last_page": 1,
  "per_page": 20,
  "total": 3,
  ...
}
```

---

## 9. Autorización y multi-tenancy

- **App-user:** todas las operaciones scopean por `organization_id` del usuario autenticado (`ResolveUserOrganizationService`).
- Si la tarea no pertenece a esa organización → **404** (no 403).
- Tareas en basurero: mismas reglas de scope en trash/restore/force.

---

## 10. Errores comunes

| HTTP | Situación |
|------|-----------|
| **401** | Sin autenticación |
| **404** | Tarea no existe, otra org, o está en basurero (para ops normales) |
| **404** | Force delete sobre tarea no en basurero |
| **422** | Validación fallida (campos requeridos, fecha inválida, status inválido) |
| **422** | `process_id` no pertenece a la organización |
| **422** | `organization_id` no existe (típico en admin) |

Mensajes de validación usan labels traducidos de `resources/lang/*/data.php`.

---

## 11. Semáforo de tareas — especificación para implementación

### 11.1 Reglas (frontend o helper calculado)

Implementar función que reciba `created_at` y `status`:

```javascript
function getTaskSemaphore(createdAt, status) {
  if (status !== 'pending') return null; // o 'none'

  const days = daysBetween(new Date(), new Date(createdAt));

  if (days < 10)  return 'normal';
  if (days < 15)  return 'alert_1';   // exactamente día 10+
  if (days < 30)  return 'alert_2';   // exactamente día 15+
  return 'critical';                   // día 30+
}
```

> Ajustar límites según interpretación exacta de "a los 10 días" (≥10 vs >10). Documentar la convención elegida en frontend.

### 11.2 Mapeo sugerido a UI

| Nivel API | Label ES | Días | Color sugerido |
|-----------|----------|------|----------------|
| `normal` | Normal | 0–9 | Verde |
| `alert_1` | Alerta (10 días) | 10–14 | Amarillo |
| `alert_2` | Alerta alta (15 días) | 15–29 | Naranja |
| `critical` | Crítico (30+ días) | ≥ 30 | Rojo |

Leyenda frontend acordada: *Normal (< 10 días) · Alerta (10 días) · Alerta alta (15 días) · Crítico (30+ días)*.

### 11.3 Opcional: exponer en API

Si el frontend prefiere no calcular, se puede agregar un campo calculado en `TaskResource`:

```json
"urgency_level": "alert_2",
"days_elapsed": 18
```

**No persistir en BD** salvo necesidad de analytics/IA con snapshots históricos.

---

## 12. Notificaciones — implementación completa

### 12.1 Comandos y schedule

| Comando | Propósito | Schedule |
|---------|-----------|----------|
| `php artisan tasks:send-due-date-reminders` | Countdown diario antes del vencimiento | 08:00 diario |
| `php artisan tasks:send-urgency-alerts` | Alertas semáforo post-vencimiento | 08:00 diario |

Opcional: `--organization={uuid}` para procesar una sola organización.

Requisitos en servidor: `schedule:run` en cron + **Horizon** activo.

### 12.2 Colas (Horizon)

| Canal | Cola | Supervisor Horizon |
|-------|------|-------------------|
| Notificación interna (campana) | `notifications` | `supervisor-notifications` |
| Email | `notifications-email` | `supervisor-notifications` |

Config: `config/tasks.php` → `queues.email` (default `notifications-email`).  
**No usar** la cola `emails` — no está registrada en Horizon.

### 12.3 Canales por organización

Usa `organization_notification_channels` (mismos canales que otras alertas del sistema):

- **internal** → campana app-user (`notifications` table + broadcast)
- **email** → correo a `channel_value`
- **sms / whatsapp** → drivers placeholder (cola `notifications`)

Registro en `organization_notifications` + historial por canal.

**Importante:** `organization_notifications` tiene PK compuesta `(organization_id, notifiable_id, notifiable_type, notification_type)`. Los servicios usan `firstOrCreate` + update para re-envíos (pruebas o re-ejecución manual).

### 12.4 Recordatorio por vencimiento (`reminder_days`)

**Servicio:** `ProcessPendingTaskDueDateRemindersService`  
**Helper:** `TaskDueDateReminderHelper::resolveNotifiableDaysRemaining()`

Condiciones:

- `status = pending`
- `due_date` not null
- `0 <= días_restantes <= reminder_days`
- No enviado hoy (`last_due_reminder_sent_on != hoy`)
- **No aplica** si ya venció (`días_restantes < 0`)

Tracking: `last_due_reminder_sent_on`.

### 12.5 Alertas de urgencia (semáforo)

**Servicio:** `ProcessPendingTaskUrgencyAlertsService`  
**Helper:** `TaskUrgencyHelper::resolveNotifiableLevel()`

Condiciones:

- `status = pending`
- Sin `due_date` **o** `due_date < hoy`
- Nivel actual > `last_notified_urgency_level`
- Si venció y semáforo aún es `normal` → escalar a `alert_1`

Tracking: `last_notified_urgency_level`.

### 12.6 Emails — plantillas y formato

| Plantilla | Uso |
|-----------|-----|
| `emails/task-due-date-reminder.blade.php` | Countdown vencimiento |
| `emails/task-urgency-alert.blade.php` | Alertas 10/15/30 días |
| `emails/partials/task-notification-details.blade.php` | Detalle compartido (tarjeta vertical) |

**Formato de fechas en emails:** `DateFormatHelper::formatDateWithDayOfWeek()`  
Ejemplo: *Viernes, 12 de Junio de 2026*

**Formato de radicado en emails:** `ProcessNumberFormatHelper::format()` / `display()`  
Ejemplo: `76001333301820180024701` → `76-001-33-33-018-2018-00247-01`  
Segmentos: **2-3-2-2-3-4-5-2** (23 dígitos).

**Badges de email** (no usar enum label crudo): claves en `resources/lang/*/task.php`:

- `urgency_email_badge_alert_1` → "Alerta (10 días)"
- `urgency_email_badge_alert_2` → "Alerta alta (15 días)"
- `urgency_email_badge_critical` → "Crítico (30+ días)"

Traducciones completas: `resources/lang/{es,en}/task.php`.

### 12.7 Probar manualmente

```bash
php artisan optimize:clear
php artisan tasks:send-due-date-reminders
php artisan tasks:send-urgency-alerts
php artisan queue:work redis --queue=notifications,notifications-email --stop-when-empty
```

Para re-probar urgencia en la misma tarea: resetear `last_notified_urgency_level` en BD (el registro en `organization_notifications` ya no rompe por duplicate key).

### 12.8 Configuración (`config/tasks.php`)

| Clave | Default | Descripción |
|-------|---------|-------------|
| `urgency_thresholds.alert_1` | 10 | Días para alerta 1 |
| `urgency_thresholds.alert_2` | 15 | Días para alerta 2 |
| `urgency_thresholds.critical` | 30 | Días para crítico |
| `queues.email` | `notifications-email` | Cola emails tareas |
| `queues.internal` | `notifications` | Cola campana/broadcast |
| `frontend.base_url` | `FRONTEND_URL` | Base URL botón "Ver tarea" |
| `frontend.tasks_path` | `/tareas` | Path detalle tarea |

Variables `.env`: `TASK_URGENCY_ALERT_1_DAYS`, `TASK_URGENCY_ALERT_2_DAYS`, `TASK_URGENCY_CRITICAL_DAYS`, `TASK_URGENCY_EMAIL_QUEUE`, etc.

---

## 13. Lo que NO está implementado aún

| Feature | Estado |
|---------|--------|
| Semáforo en `TaskResource` (campo API) | ❌ Calcular en frontend con `TaskUrgencyHelper` o client-side |
| Semáforo en frontend | 🟡 Leyenda/UI en progreso |
| Cron countdown + urgencia + emails/internal | ✅ Implementado |
| SMS / WhatsApp tareas | ❌ Drivers placeholder |
| Tabla de historial/eventos de tarea dedicada | ❌ Usa `organization_notifications` + `history_organizations_channels_notifications` |
| `completed_at` (timestamp de cumplimiento) | ❌ No existe |
| Campo `urgency_level` en API | ❌ No expuesto en `TaskResource` |

---

## 14. Consideraciones para entrenamiento de IA (futuro)

Datos útiles para modelos predictivos o recomendaciones:

| Dato | Disponible hoy | Recomendación |
|------|----------------|---------------|
| Texto título/descripción | ✅ | Features NLP |
| Proceso asociado | ✅ | Contexto judicial |
| Fechas creación/vencimiento | ✅ | Features temporales |
| Días recordatorio configurados | ✅ | Preferencia usuario |
| Status actual | ✅ | Label/outcome parcial |
| Basurero | ✅ (`deleted_at`) | Señal de abandono |
| Semáforo histórico | ❌ | Calcular offline o snapshots |
| Cuándo se cumplió | ❌ | Agregar `completed_at` |
| Recordatorios enviados | ✅ Parcial | `last_due_reminder_sent_on`, `last_notified_urgency_level`, historial canales |
| Evolución del semáforo día a día | ❌ | Snapshots o event log |

**Recomendación:** no persistir semáforo como columna única; preferir event log + timestamps de transiciones de estado.

---

## 15. Tests

```bash
php artisan test tests/Application/Shared/Task/
php artisan test tests/Application/Shared/Helpers/TaskDueDateReminderHelperTest.php
php artisan test tests/Application/Shared/Helpers/TaskUrgencyHelperTest.php
php artisan test tests/Application/Shared/Helpers/ProcessNumberFormatHelperTest.php
php artisan test tests/Application/Shared/Services/Task/
php artisan test tests/Domain/Task/
```

Cubre CRUD, status, basurero, helpers de vencimiento/urgencia, formateo de radicado y servicios de notificación.

---

## 16. Traducciones

**Estados y urgencia (`resources/lang/{es,en}/enums.php`):**

```php
'task_status' => [
    'pending' => 'Pendiente',
    'completed' => 'Cumplida',
    'draft' => 'Borrador',
],
'task_urgency_level' => [
    'normal' => 'Normal',
    'alert_1' => 'Alerta (10 días)',
    'alert_2' => 'Alerta alta (15 días)',
    'critical' => 'Crítico (30+ días)',
],
```

**Emails y notificaciones (`resources/lang/{es,en}/task.php`):**

- Asuntos, titulares, cuerpos por nivel de urgencia y countdown
- Badges: `urgency_email_badge_alert_1`, etc.
- Textos notificación interna (`urgency_internal_*`, `due_reminder_internal_*`)

**Labels de formulario (`resources/lang/{es,en}/data.php`):**

- `due_date` → Fecha de vencimiento / Due Date
- `reminder_days` → Días de recordatorio / Reminder Days
- `status` → Estado / Status
- `title`, `description`, `process_id`, etc.

---

## 17. Guía rápida para IA que implemente frontend

1. **Vista principal:** `GET /tasks` sin params → solo pendientes activas.
2. **Archivo cumplidas:** `GET /tasks?status=completed`.
3. **Borradores:** `GET /tasks?status=draft`.
4. **Basurero:** `GET /tasks/trash`.
5. **Crear:** `POST /tasks` con todos los campos incluido `process_id` si hay radicado.
6. **Editar:** `PUT /tasks/{id}` — no enviar status.
7. **Botón cumplida:** `PATCH /tasks/{id}/complete`.
8. **Cambio estado genérico:** `PATCH /tasks/{id}/status`.
9. **Eliminar → basurero:** `DELETE /tasks/{id}`.
10. **Restaurar:** `POST /tasks/{id}/restore`.
11. **Eliminar forever:** `DELETE /tasks/{id}/force` (solo desde basurero UI).
12. **Semáforo:** calcular client-side con `created_at` + `status === 'pending'` (ver §11 y labels §16).
13. **Radicado formateado:** patrón `76-001-33-33-018-2018-00247-01` (helper backend: `ProcessNumberFormatHelper`).
14. **Select de estados:** poblar desde `GET /tasks/statuses`.

---

## 18. Changelog funcional del módulo

| Fecha | Cambio |
|-------|--------|
| 2026-04 | Tabla base `tasks` |
| 2026-06 | `due_date`, `reminder_days` |
| 2026-06 | Enum `status` (pending, completed, draft) |
| 2026-06 | Soft delete / basurero / restore / force delete |
| 2026-06 | Endpoints status, complete, trash |
| 2026-06 | Cron countdown vencimiento + urgencia post-vencimiento |
| 2026-06 | Notificaciones email + internal (SOLID, drivers, colas Horizon) |
| 2026-06 | Helpers `ProcessNumberFormatHelper`, labels urgencia, plantillas email |
| 2026-06 | Tracking `last_due_reminder_sent_on`, `last_notified_urgency_level` |
| Pendiente | Semáforo en `TaskResource`, SMS/WhatsApp, `completed_at` |

---

*Última actualización: junio 2026 — refleja el estado del backend en la rama de desarrollo del repositorio Judicial Filings.*
