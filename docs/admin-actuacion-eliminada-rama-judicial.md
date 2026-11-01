# Admin: actuación eliminada en Rama Judicial

Documento de contexto para implementar la funcionalidad en **frontend admin** (y para alinear con backend ya existente en el repo `judicial-filings` / NotiJudicial).

---

## Problema de negocio

A veces el **juzgado publica por error** una actuación en el portal de la **Rama Judicial**. NotiJudicial la sincroniza, crea la fila en BD y puede **notificar** (consolidado por correo y/o alertas).

Luego el juzgado **elimina** esa actuación en Rama. Nuestro cron **no detecta eliminaciones**: el sync es **solo aditivo** (inserta actuaciones nuevas por `idRegActuacion`; no compara BD vs API para borrar o marcar retiros).

**Solución acordada:** corrección **manual desde admin**: marcar la actuación como **retirada en Rama** (soft flag, no borrado físico), silenciar pendientes de consolidado, opcionalmente avisar por correo, y mostrar en UI/consolidados históricos que fue retirada.

Automatizar la detección en cron se consideró **riesgosa** (respuestas incompletas de la API, solo página 1 en sync diario, correos ya enviados no se revierten).

---

## Comportamiento del sync (referencia)

- Servicio: `ProcessSyncService::syncActuaciones()`.
- Si el proceso ya tiene actuaciones, el cron suele traer **solo la primera página** de actuaciones de la API (`onlyFirstPage = true`).
- No existe reconciliación “si ya no viene en API → eliminar/marcar”.

---

## Qué implementó el backend (estado actual)

### Migración

Archivo: `database/migrations/2026_10_30_120000_add_removed_from_judicial_branch_to_process_actions.php`

Columnas en `process_actions`:

| Columna | Tipo | Descripción |
|--------|------|-------------|
| `removed_from_judicial_branch_at` | timestamp nullable | Cuándo se marcó como retirada en Rama |
| `removed_from_judicial_branch_by` | uuid nullable FK `users` | Admin que marcó |
| `removed_from_judicial_branch_reason` | text nullable | Motivo opcional (ej. error del juzgado) |

**Deploy:** `php artisan migrate` en producción (Forge).

### Endpoint admin

```
POST /api/admin/processes/{processId}/actions/{actionId}/mark-removed-from-judicial-branch
```

- Auth: `auth:sanctum` + middleware `admin.role`
- UUIDs en `processId` y `actionId`

**Body (JSON):**

```json
{
  "notify_organizations": false,
  "reason": "Texto opcional, máx 2000 caracteres"
}
```

| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `notify_organizations` | boolean | `false` | Si `true`, dispara correo + notificación interna a orgs interesadas activas con tracking |
| `reason` | string \| null | null | Motivo mostrado en correo y guardado en BD |

**Respuesta 200 (ejemplo de forma):**

```json
{
  "message": "... traducción process.action_marked_removed_from_judicial_branch ...",
  "action": { "... ProcessActionResource ..." },
  "notified_consolidados": [
    {
      "organization_id": "uuid",
      "organization_name": "Nombre org",
      "notification_digest_id": "uuid",
      "notification_type": "actuacion",
      "consolidado_date": "30/10/2026",
      "email_sent_at": "30/10/2026 8:30am"
    }
  ],
  "pending_notifications_silenced": 1,
  "organizations_notified": 0
}
```

**Errores:**

- `404` proceso o actuación no encontrada
- `422` si la actuación **ya** estaba marcada (`process.action_already_removed_from_judicial_branch`)

### Qué hace el servicio al marcar

Clase: `MarkProcessActionRemovedFromJudicialBranchService`

1. Marca `removed_*` en la actuación y en **otras instancias del mismo radicado** con el mismo contenido (misma lógica de identidad que duplicados cross-instancia).
2. **Silencia** `organization_notifications` **pendientes** de consolidado para esa actuación:
   - `is_email_notified = true`, `is_notified = true`, timestamps, `notification_digest_id = null` (patrón “silenciado sin enviar”, igual que descartar org del paquete de consolidado).
3. **No toca** notificaciones que ya tienen `notification_digest_id` (consolidado ya enviado); el historial sigue enlazado.
4. Excluye actuaciones marcadas de la **cola pendiente** de consolidados (filtros en `OrganizationNotificationQueryBuilder` + `whereNull(removed_from_judicial_branch_at)` en subconsultas sobre `process_actions`).
5. Si `notify_organizations: true`:
   - Crea notificación tipo `actuacion_eliminada_rama` (constante `MarkProcessActionRemovedFromJudicialBranchService::NOTIFICATION_TYPE`)
   - Correo inmediato (no va al consolidado): `ProcessActionRemovedFromJudicialBranchMailable`, vista `emails/process-action-removed-from-judicial-branch`
   - Notificación interna app: `JudicialActionDetectedNotification` con título/descripción dedicados

### Lectura en APIs existentes (admin)

**Detalle del proceso**

```
GET /api/admin/processes/{id}
```

En `process`:

- **`removed_from_judicial_branch_count`**: entero, cantidad de actuaciones del proceso marcadas como retiradas en Rama.

**Listado de actuaciones**

```
GET /api/admin/processes/{id}/actions?removed_from_judicial_branch=true
```

Query opcional:

| Param | Valores | Efecto |
|-------|---------|--------|
| `removed_from_judicial_branch` | omitido | Todas las actuaciones |
| `removed_from_judicial_branch` | `true` / `1` | Solo las marcadas como retiradas en Rama |
| `removed_from_judicial_branch` | `false` / `0` | Solo las que **no** están marcadas |

Cada ítem del listado (misma forma que `ProcessActionResource`, agrupado fijación/auto como hoy) incluye:

| Campo | Tipo | Notas |
|-------|------|--------|
| `removed_from_judicial_branch` | boolean | |
| `removed_from_judicial_branch_at` | string \| null | Formato legible (helper de fechas del API) |
| `removed_from_judicial_branch_reason` | string \| null | |
| `notified_consolidados` | array | Solo relevante si está marcada; lista consolidados donde ya se notificó (vía `notification_digest_id`) |

**Consolidados (detalle histórico)**

Al enriquecer filas del JSON del digest (`NotificationDigestResource::formatItemsForPage` / `enrichItemData`), cada fila puede traer:

- `removed_from_judicial_branch` (boolean)
- `removed_from_judicial_branch_at`
- `removed_from_judicial_branch_reason`

Sirve para admin y app-user al abrir un consolidado ya enviado.

### Archivos clave (backend)

| Área | Ruta |
|------|------|
| Ruta | `routes/api/admin/processes.php` |
| Controller | `src/Application/Admin/Process/Controllers/AdminProcessActionController.php` → `markRemovedFromJudicialBranch` |
| Servicio marcar | `src/Application/Admin/Process/Services/MarkProcessActionRemovedFromJudicialBranchService.php` |
| Consolidados enviados | `src/Application/Admin/Process/Services/ResolveProcessActionNotifiedConsolidadosService.php` |
| DTO request | `src/Application/Admin/Process/Data/MarkProcessActionRemovedFromJudicialBranchData.php` |
| Resource actuación | `src/Application/Shared/Process/Resources/ProcessActionResource.php` |
| Resource detalle proceso | `src/Application/Shared/Process/Resources/ProcessDetailResource.php` |
| Detalle admin carga count | `src/Application/Admin/Process/Services/AdminProcessDetailService.php` |
| Digest enriquecido | `src/Application/AppUser/Notification/Resources/NotificationDigestResource.php` |
| Cola consolidado excluye retiradas | `src/Domain/Notification/QueryBuilders/OrganizationNotificationQueryBuilder.php` |
| Modelo | `src/Domain/Process/Models/ProcessAction.php` |
| Tests | `tests/Application/Admin/Process/Controllers/MarkProcessActionRemovedFromJudicialBranchControllerTest.php` |

### Traducciones (es/en)

Claves en `resources/lang/{es,en}/process.php`:

- `action_marked_removed_from_judicial_branch`
- `action_already_removed_from_judicial_branch`
- `action_removed_from_judicial_branch_subject` / `_title` / `_intro`
- `removal_reason`, `action_description`
- `internal_action_removed_from_judicial_branch_title` / `_description`

---

## UX sugerida (frontend admin)

1. **Detalle del proceso**
   - Mostrar `removed_from_judicial_branch_count` en información general (ej. “Actuaciones retiradas en Rama: N”).
2. **Tabla/lista de actuaciones**
   - Badge o fila atenuada si `removed_from_judicial_branch === true`.
   - Mostrar fecha/motivo y, si aplica, enlace o lista de `notified_consolidados` (org + id digest para ir al historial de consolidados).
3. **Acción por actuación** (solo si **no** está marcada)
   - Botón: “Marcar como eliminada en Rama Judicial”.
   - Modal:
     - Textarea motivo (opcional).
     - Checkbox: “Notificar por correo a las organizaciones interesadas”.
     - Confirmación clara: no borra el historial; evita que salga en próximos consolidados; el consolidado ya enviado sigue visible pero con etiqueta de retirada.
   - POST al endpoint; mostrar `message` y refrescar detalle + listado de actuaciones.
4. **Consolidado histórico**
   - Si `removed_from_judicial_branch` en la fila, mostrar etiqueta tipo “Retirada en Rama Judicial”.

**No implementado en backend:** “deshacer” marca, ni borrado físico de `process_actions`, ni edición automática del JSON snapshot del digest (solo enriquecimiento al leer).

---

## Relación con consolidados (concepto)

- **Pendiente:** `organization_notifications` sin digest / no email notificado → al marcar retirada se **silencian** (no entran al próximo paquete).
- **Ya enviado:** filas con `notification_digest_id` → se listan en `notified_consolidados` al marcar y al listar actuaciones admin; el detalle del digest muestra flags de retirada leyendo `process_actions`.

Documento relacionado: `docs/consolidados-notification-digests.md`.

---

## Relación con organizaciones

Un proceso puede tener **varias organizaciones** interesadas; cada una puede tener su propio consolidado (`notification_digests`) y notificaciones (`organization_notifications`). Por eso `notified_consolidados` es un **array** (una entrada por org/tipo/digest relevante).

Documento relacionado: `docs/organizaciones-procesos-usuarios.md`.

---

## Mensaje tipo para el socio (Edwin)

> Cuando el juzgado se equivoca y quita una actuación en Rama, en admin la marcamos como retirada: deja de salir en consolidados pendientes, el historial muestra que fue retirada, y si quieren les mandamos un correo avisando. No borramos el registro para no perder trazabilidad de lo que alguna vez notificamos.

---

## Checklist deploy / QA

- [ ] Migración ejecutada
- [ ] Admin puede marcar actuación y ver flags en listado y contador en detalle
- [ ] Actuación marcada no aparece en preview/pending de consolidado de esa org
- [ ] Consolidado ya enviado muestra etiqueta de retirada al abrir detalle
- [ ] Con `notify_organizations: true` y canales email activos, llega correo de retirada
- [ ] Segundo intento de marcar misma actuación → 422

---

## Comando de tests (local)

```bash
herd php artisan test tests/Application/Admin/Process/Controllers/MarkProcessActionRemovedFromJudicialBranchControllerTest.php
```

(Requiere base de datos de tests configurada.)
