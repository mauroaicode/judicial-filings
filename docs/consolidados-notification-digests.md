# Consolidados (Notification Digests)

Documento de contexto operativo y técnico sobre cómo NotiJudicial arma, envía y muestra los **consolidados** de actuaciones judiciales.

Última actualización: 2026-10-14.

---

## 1. Qué es un consolidado

Un **consolidado** es el correo (y registro en app) que agrupa las **novedades de actuaciones** pendientes de una organización y las envía juntas, en lugar de un email por cada actuación.

En código se llama **notification digest**:

| Concepto de negocio | Tabla / entidad |
|---------------------|-----------------|
| Consolidado enviado | `notification_digests` |
| Novedad pendiente / enviada | `organization_notifications` |
| Actuación judicial | `process_actions` (notifiable polimórfico) |

El consolidado **no vuelve a consultar la Rama** al armarse. Empaqueta notificaciones que ya quedaron pendientes (`is_email_notified = false`) por el sync, un import Excel, un alta de proceso, etc.

---

## 2. Flujo de punta a punta

```
Actuación nueva (sync / Excel / registro)
        │
        ▼
ProcessActionAlertNotificationService
  → organization_notifications (is_email_notified = false)
        │
        ▼
Campana app (database + broadcast)     ← independiente del email
        │
        ▼
DispatchOrganizationDigestsJob
  → SendOrganizationDigestJob (una por org)
        │
        ▼
NotificationDigestService::sendDigest()
  1. Selecciona pendientes elegibles
  2. Filtra ya enviadas (dedup fingerprint / par fijación-auto)
  3. prepareData() — una fila por contenido canónico
  4. groupAndMergeActions() — Fijación + Auto en una fila
  5. Crea notification_digests (JSON data)
  6. Marca notificaciones como enviadas + liga digest_id
  7. Envía email a todos los canales email activos de la org
```

---

## 3. Cómo se crean las notificaciones pendientes

Servicio principal:

- `src/Application/Shared/Services/Process/ProcessActionAlertNotificationService.php`

Entradas típicas:

| Origen | Qué dispara |
|--------|-------------|
| Sync diario Rama / SAMAI | Nueva actuación detectada en `ProcessSyncService` |
| Import Excel de actuaciones | `ProcessActuacionesExcelImportService` (puede usar `forceIncludeHistorical`) |
| Alta / registro de proceso | Alertas de registro recientes (`actuacion_registro`) |
| Split fijación combinada | `SplitCombinedFijacionEstadoActionsService` + import |

Tipos relevantes en `organization_notifications.notification_type`:

- `actuacion` — novedad normal para consolidado
- `actuacion_alerta` — con keywords / highlights
- `actuacion_registro` — al registrar un proceso (también puede recuperarse en digest si quedó sin `notification_digest_id`)
- `actuacion_documento` — subida de PDF (flujo aparte; correo inmediato, no es el consolidado de actuaciones)

Dedup al crear:

1. **Misma identidad de contenido** entre instancias del radicado (`ProcessActionIdentityHelper` + `whereMatchesContentIdentity`).
2. **Ya enviada en consolidado** (desde 2026-10-14): `AlreadySentDigestActuacionChecker` evita crear otra pendiente si esa novedad (o su par fijación/auto) ya salió en un digest enviado.

---

## 4. Quién dispara el envío

### 4.1 Automático (post-sync)

Config:

```php
// config/judicial-sync.php
'auto_digest_after_sync' => env('JUDICIAL_SYNC_AUTO_DIGEST', true),
```

Si está en `true`, al terminar el batch de `judicial:sync-processes` / `samai:sync-processes` se dispara `DispatchOrganizationDigestsJob` (a menudo con `dispatchSync` en el `finally` del batch).

Si está en `false`:

- El sync **sigue creando** pendientes.
- El email **no** sale solo.
- El admin envía desde el paquete de consolidados.

### 4.2 Manual (admin)

Rutas (`routes/api/admin/digest-packages.php`):

| Método | Ruta | Acción |
|--------|------|--------|
| GET | `/api/admin/digest-packages/preview` | Vista previa del paquete pendiente |
| POST | `/api/admin/digest-packages/send` | Encola un digest por org elegible |
| DELETE | `/api/admin/digest-packages/organizations/{organizationId}` | Descarta pendientes de esa org |

Servicios:

- `PreviewDigestPackageService`
- `SendDigestPackageService` → `SendOrganizationDigestJob`
- `DiscardDigestPackageOrganizationService` (marca `is_email_notified = true`, `notification_digest_id = null`)

UI admin: “Paquete de consolidados pendientes” (preview / enviar / limpiar por org).

### 4.3 Jobs y cola

| Job | Cola típica |
|-----|-------------|
| `DispatchOrganizationDigestsJob` | `notifications` |
| `SendOrganizationDigestJob` | `notifications` |

Log channel: `judicial_sync_notifications` (`JUDICIAL_SYNC_LOG_CHANNEL`).

---

## 5. Criterios de selección (qué entra al consolidado)

Implementado en `NotificationDigestService::sendDigest()` +
`OrganizationNotificationRegistrationCutoffService` +
`OrganizationNotificationQueryBuilder::forPendingDigestEligibleActuaciones()`.

Una notificación entra si cumple **todas** (salvo ramas especiales):

1. Organización **activa**.
2. `notifiable_type` = morph de `ProcessAction`.
3. Proceso ligado a la org con pivot **activo** (`forActiveOrganizationProcesses`).
4. Pendiente de email:
   - `is_email_notified = false`, **o**
   - recuperación: `notification_type = actuacion_registro` y `notification_digest_id` null.
5. **Cutoff de registration_date** (si ya hubo digests enviados):
   - floor = `MAX(registration_date)` de actuaciones ya incluidas en digests con `is_email_notified = true` y `notification_digest_id` not null (ignorando fechas futuras corruptas de Rama).
   - Entran actuaciones con `registration_date >= floor`, **o**
   - **descubiertas hoy** (`created_at` de hoy) con `registration_date` no más vieja que `discovered_today_max_age_days` (default 30), **o**
   - imports manuales Excel (`action_registration_id < 0`) que bypassan el floor.
6. **No ya enviada lógicamente** (`AlreadySentDigestActuacionChecker`):
   - mismo fingerprint de contenido ya en digest enviado para ese radicado + fecha, **o**
   - par fijación/auto (o edicto/sentencia) / fijación sola tras un consolidado que ya llevaba el par o el texto combinado.

### Configs clave

| Env / config | Default | Rol |
|--------------|---------|-----|
| `JUDICIAL_SYNC_AUTO_DIGEST` | `true` | Enviar al terminar sync |
| `JUDICIAL_SYNC_DISCOVERED_TODAY_MAX_AGE_DAYS` | `30` | Bypass “descubierta hoy” |
| `JUDICIAL_SYNC_NEW_INSTANCE_NOTIFY_DAYS` | `7` | Ventana campana app (no es el cutoff del digest) |
| `JUDICIAL_SYNC_REGISTRATION_ALERT_DAYS_FORWARD` | `1` | Al registrar proceso, solo actuaciones “recientes” van a digest |

---

## 6. Armado de filas y merge Fijación + Auto

### 6.1 `prepareData()`

- Agrupa notificaciones por `process_number|fingerprint`.
- Elige actuación canónica entre instancias duplicadas (`ProcessActionIdentityHelper::pickCanonical`).
- Arma fila: juzgado, radicado, demandante/demandado, fechas, texto, anotación, términos, flags de alerta / highlights.

### 6.2 `groupAndMergeActions()`

Usa `GroupProcessActionsService`:

- Empareja **Fijación / Notificación / Publicación Estado** con **Auto / Sentencia / …** del mismo radicado (fechas cercanas, anotación, `cons_action`).
- En el email, la fijación queda con `is_merged = true` y `linked_action_text` / `linked_annotation`.
- El Auto “reclamado” no sale como fila aparte.

**Limitación histórica (bug Edwin, oct 2026):** el merge solo ve filas **del mismo batch pendiente**. Si ayer se envió el par completo y hoy solo queda pendiente una fijación nueva (import/split), el consolidado salía como “Fijación Estado” incompleta. Eso lo cubre `AlreadySentDigestActuacionChecker`.

Excel a veces trae el texto combinado (`Fijación Estado Auto …`). `FijacionEstadoActionSplitter` lo parte en dos filas para alinear con Rama.

---

## 7. Persistencia y marcado

Al crear el digest:

1. Insert en `notification_digests`:
   - `organization_id`
   - `data` (JSON de filas ya mergeadas)
   - timestamps de canal (`email_sent_at`, etc.) se llenan **después** de cada envío exitoso
2. Update de las `organization_notifications` incluidas:
   - `is_email_notified = true`
   - `email_notified_at = now()`
   - `is_notified = true` / `notified_at`
   - `notification_digest_id = <digest>`

Si el email falla, el digest ya existe y las notificaciones ya están marcadas (evita doble envío al reintentar el job). Se puede reenviar correo con servicios de resend sin volver a meter las actuaciones como “nuevas”.

Duplicados suprimidos por el checker se marcan `is_email_notified = true` **sin** crear un digest nuevo (no reencolan).

---

## 8. Canales de entrega

### Email consolidado

- Mailable: `ConsolidatedJudicialActionsMailable`
- Vista: `resources/views/emails/consolidated-judicial-actions.blade.php`
- Destinatarios: **todos** los `organization_notification_channels` con `channel_type = email` y `is_active = true`, ordenados por `priority`.

### Campana in-app

- Independiente del consolidado.
- Lista: `OrganizationNotificationListService` / `organization-notifications`.
- Filtro de campana usa ventana `new_instance_notify_days`, no el cutoff del digest.

### Digests en la app (abogado)

Rutas app-user:

| Método | Ruta |
|--------|------|
| GET | `/api/app-user/notification-digests` |
| GET | `/api/app-user/notification-digests/{id}` |
| GET | `/api/app-user/notification-digests/history` |
| GET | `/api/app-user/notification-digests/{id}/export-pdf` |

- Index: tarjetas por digest (conteos, filtros).
- Detail: re-enriquece filas del JSON (`NotificationDigestResource`) — sujetos, `process_id`, highlights, merge fijación/auto, filtros de término.
- Export PDF: descarga landscape con logo NotiJudicial (`ExportNotificationDigestPdfService` + `barryvdh/laravel-dompdf`), scoped a la organización del app user.
- Filtros de **inicio/fin de término judicial**: excluyen filas sin fecha de término (`-`, vacías, null). El FE debe reenviar los query params al detail.

---

## 9. Admin vs abogado

| Actor | Qué ve / hace |
|-------|----------------|
| **Admin** | Preview/send/discard del paquete; no recibe el consolidado del cliente |
| **Abogado (org)** | Email del consolidado + digests en app + campana de actuaciones |

La notificación de campana **“Radicado importado”** (`ProcessDataImportedNotification`) **no es un consolidado**: va al `AppUser` que importó, con `type = process-imported` e `id` del proceso (sin URL; el FE navega).

---

## 10. Archivos clave

| Área | Path |
|------|------|
| Envío digest | `src/Application/Shared/Services/Notification/NotificationDigestService.php` |
| Dedup ya enviadas | `src/Application/Shared/Services/Notification/AlreadySentDigestActuacionChecker.php` |
| Cutoff fechas | `src/Application/Shared/Services/Notification/OrganizationNotificationRegistrationCutoffService.php` |
| Crear pendientes | `src/Application/Shared/Services/Process/ProcessActionAlertNotificationService.php` |
| Identidad actuación | `src/Application/Shared/Helpers/ProcessActionIdentityHelper.php` |
| Merge fijación/auto | `src/Domain/Process/Services/GroupProcessActionsService.php` |
| Split Excel combinado | `src/Domain/Process/Services/FijacionEstadoActionSplitter.php` |
| Dispatch global | `src/Application/Shared/Jobs/DispatchOrganizationDigestsJob.php` |
| Digest por org | `src/Application/Shared/Jobs/SendOrganizationDigestJob.php` |
| Paquete admin | `src/Application/Admin/DigestPackage/Services/*` |
| Export PDF app-user | `src/Application/AppUser/Notification/Services/ExportNotificationDigestPdfService.php` |
| Vista PDF | `resources/views/pdfs/notification-digest.blade.php` |
| API app digests | `src/Application/AppUser/Notification/Controllers/NotificationDigestController.php` |
| Config | `config/judicial-sync.php` |
| Tests dedup | `tests/Application/Shared/Services/Notification/AlreadySentDigestActuacionCheckerTest.php` |
| Tests cutoff / multi-email | `tests/Application/Shared/Services/Notification/OrganizationNotificationRegistrationCutoffServiceTest.php` |

---

## 11. Operación: cómo verificar en producción

### ¿Corrió el consolidado hoy?

```sql
SELECT id, organization_id, created_at, email_sent_at, JSON_LENGTH(data) AS rows
FROM notification_digests
WHERE created_at >= CURDATE()
ORDER BY created_at;
```

### ¿Hay pendientes sin enviar?

```sql
SELECT o.name, COUNT(*) AS pending
FROM organization_notifications n
JOIN organizations o ON o.id = n.organization_id
WHERE n.is_email_notified = 0
  AND o.is_active = 1
GROUP BY o.name
ORDER BY pending DESC;
```

### ¿Se repitió una actuación entre dos consolidados?

Comparar `data` JSON de digests de la misma org (ayer vs hoy) por `process_number` + `registration_date` + `action_text` / `is_merged` / `linked_action_text`.

Caso real 2026-10-13 PM → 2026-10-14 AM: ~15 repeticiones, casi todas “Fijación Estado” sin Auto (Edwin, Sarria & Micolta, Víctor Cortés, etc.).

### Logs

En el release donde corre Horizon:

`storage/logs/judicial_sync_notifications-YYYY-MM-DD.log`

Buscar:

- `DispatchOrganizationDigestsJob`
- `NotificationDigestService: Preparing digest`
- `suppressed_already_sent`
- `AlreadySentDigestActuacionChecker: suppressed already-sent actuaciones`

---

## 12. Problemas conocidos / historial

### 12.1 Fijación incompleta reenviada (oct 2026)

**Síntoma:** El consolidado de la tarde llevaba Fijación + Auto. Tras cargar autos (Excel) a la mañana siguiente, el consolidado nuevo reenviaba solo “Fijación Estado”.

**Causa:** Nueva `process_action` / notificación pendiente de la fijación sola; el Auto ya estaba `is_email_notified`; el merge no tenía pareja en el batch.

**Fix:** `AlreadySentDigestActuacionChecker` en selección del digest y al crear notificaciones.

### 12.2 Filtros de término con filas vacías

**Síntoma:** Filtrar por inicio/fin de término seguía mostrando secretariales con `-`.

**Fix:** parseo de fechas en español + excluir vacíos/null/`-` en detail e index cuando el filtro afecta filas.

### 12.3 Auto-digest apagado

Si `JUDICIAL_SYNC_AUTO_DIGEST=false`, el cron deja pendientes y el admin debe enviar el paquete. Las actuaciones **no se pierden**; solo no salen por correo hasta el send manual.

### 12.4 Descartar paquete

Discard marca pendientes como notificadas **sin** email. Útil para limpiar ruido, pero esas actuaciones no se reenviarán en el siguiente consolidado.

### 12.5 Alta de proceso vaciaba el paquete pendiente (oct 2026)

**Síntoma:** Con actuaciones en “consolidados pendientes”, si el cliente agregaba un radicado, se enviaba el consolidado completo de la org.

**Causa:** `ProcessSyncService::syncForRegistration()` llamaba `dispatchRegistrationDigestIfPending($organizationId)` **sin** limitar al radicado nuevo (SAMAI sí pasaba `[$processNumber]`).

**Fix:** el digest de registro Rama Judicial también se limita a `[$processNumber]`; el resto del paquete pendiente queda para el send del admin / fin de sync.

---

## 13. Qué NO es un consolidado

- Email inmediato de **documento subido** a una actuación.
- Notificación Discord de sync / alta manual.
- Campana “Radicado importado” / “Import fallido”.
- El listado de actuaciones del **detalle del proceso** (siempre muestra el historial guardado; puede verse “bien” aunque el consolidado haya ido incompleto).

---

## 14. Checklist mental para tickets de Edwin / ops

1. ¿La actuación está en `process_actions` del detalle? → sync/import OK.
2. ¿Existe `organization_notifications` pendiente o ya ligada a un digest? → pipeline de notificación.
3. ¿Hay un `notification_digests` de esa org con esa fila en `data`? → consolidado.
4. ¿`email_sent_at` null? → digest creado pero email falló.
5. ¿Volvió a salir incompleta? → dedup / split fijación (sección 12.1).
6. ¿`JUDICIAL_SYNC_AUTO_DIGEST`? → automático vs botón admin.

---

## 15. Resumen en una frase

El consolidado es el **empaquetado y envío por organización** de actuaciones ya notificadas como pendientes; se arma en memoria con merge fijación/auto, se guarda en `notification_digests`, marca las filas como enviadas y manda el correo a todos los emails activos de la org — disparado por el sync o por el admin.
