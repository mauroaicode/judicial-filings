# Estado en vivo de sincronización (header admin)

Contrato para el Angular admin (`judicial-filings-fronted-admin`) que muestra en el **header** (cualquier página) cuántos procesos faltan por sincronizar cuando corre el cron / sync manual de **Rama Judicial**, **SAMAI** u otras fuentes futuras.

Patrón igual que presencia: **HTTP para el snapshot** + **Reverb/Echo para updates**.

---

## 1. Snapshot (bootstrap / reconnect)

```http
GET /api/admin/judicial-sync/status
Authorization: Bearer {admin_token}
```

```json
{
  "has_active": true,
  "active_count": 2,
  "sources": [
    {
      "data_source": "judicial_branch",
      "data_source_label": "Rama Judicial",
      "is_active": true,
      "is_executable": true,
      "run_id": "uuid",
      "status": "batch_pending",
      "status_label": "En cola",
      "processes_queued": 9781,
      "queue_pending_jobs": 430,
      "failed_jobs_count": 2,
      "laravel_batch_id": "a30d0b78-...",
      "started_at": "2026-10-23T09:03:00-05:00",
      "radicado_filter": null,
      "organization_id": null,
      "organization_name": null
    },
    {
      "data_source": "samai",
      "data_source_label": "SAMAI",
      "is_active": true,
      "is_executable": true,
      "run_id": "uuid-2",
      "status": "batch_pending",
      "status_label": "En cola",
      "processes_queued": 28,
      "queue_pending_jobs": 12,
      "failed_jobs_count": 0,
      "laravel_batch_id": "...",
      "started_at": "2026-10-23T09:00:00-05:00",
      "radicado_filter": null
    },
    {
      "data_source": "tyba",
      "data_source_label": "TYBA",
      "is_active": false,
      "is_executable": false,
      "run_id": null,
      "status": null,
      "status_label": null,
      "processes_queued": 0,
      "queue_pending_jobs": null,
      "failed_jobs_count": null,
      "laravel_batch_id": null,
      "started_at": null,
      "radicado_filter": null
    }
  ],
  "active": [ /* solo los is_active: true */ ]
}
```

| Campo | Uso en UI |
| :--- | :--- |
| `has_active` | Si es `false`, ocultar el banner del header. |
| `active` / `active_count` | Fuentes concurrentes a mostrar. |
| `processes_queued` | Total encolado al inicio del batch. |
| `queue_pending_jobs` | **Cuántos faltan** (viene de `job_batches.pending_jobs`). |
| `data_source` / `data_source_label` | Rama vs SAMAI vs futuro. |
| `organization_id` / `organization_name` | `null` en el cron global. En un cron de organización traen el UUID y el nombre. |
| `is_executable` | `false` para fuentes reservadas (p. ej. TYBA) aún no implementadas. |

`sources` siempre lista **todas** las fuentes del enum (`judicial_branch`, `samai`, `tyba`) y solo el cron **global** de cada una (`organization_id` null).

Un cron `judicial:sync-processes-for-organization` no ocupa el puesto de Rama. Entra como ítem extra de `active`, con la misma forma y `organization_name` informado. El chip se identifica por `run_id`, no por `data_source`, para que no reemplace el cron global si ambos corren.

Texto sugerido cuando hay organización: `Sincronizando {data_source_label} · {organization_name}: quedan {queue_pending_jobs} de {processes_queued}`.

---

## 2. WebSocket (tiempo real)

Auth igual que el resto del admin:

```http
POST /broadcasting/auth
Authorization: Bearer {admin_token}
```

```js
Echo.private('admin.judicial-sync')
  .listen('.JudicialSyncStatusUpdated', (payload) => {
    // patch del chip de esa data_source
  });
```

### Payload del evento

```json
{
  "run_id": "uuid",
  "data_source": "judicial_branch",
  "data_source_label": "Rama Judicial",
  "status": "batch_pending",
  "status_label": "En cola",
  "is_active": true,
  "processes_queued": 9781,
  "queue_pending_jobs": 429,
  "failed_jobs_count": 2,
  "laravel_batch_id": "...",
  "started_at": "2026-10-23T09:03:00-05:00",
  "organization_id": null,
  "organization_name": null
}
```

Cuando `is_active` pasa a `false` (batch terminado / cancelado / sin procesos / dispatch fallido), quitar el chip de ese `run_id`. Si `active_count` queda en 0, ocultar el banner.

Si `organization_id` viene informado, el evento es de un cron de organización. No reemplazar el chip global de la misma `data_source`.

Los updates de progreso mid-batch están **throttled** (~2 s por run, configurable con `JUDICIAL_SYNC_LIVE_STATUS_THROTTLE_SECONDS`). Inicio, encolado y fin siempre emiten de inmediato.

---

## 3. Comportamiento del header (UI)

1. Al cargar el shell admin: `GET /api/admin/judicial-sync/status`.
2. Suscribirse a `admin.judicial-sync`.
3. Banner visible solo si `has_active === true` (o `active.length > 0`).
4. **Una sola fuente activa:** mostrar un chip estático, sin carrusel.
5. **Dos o más fuentes activas:** carrusel / scroll horizontal infinito (Rama ↔ SAMAI ↔ …).
6. Texto sugerido por chip global: `Sincronizando {data_source_label}: quedan {queue_pending_jobs} de {processes_queued}`. Si `organization_name` no es null: `Sincronizando {data_source_label} · {organization_name}: quedan {queue_pending_jobs} de {processes_queued}`.
7. Si `queue_pending_jobs` es `null` (status `started` antes de encolar el batch), mostrar “preparando…” o solo el label de la fuente.
8. Opcional: al recuperar foco / reconectar Echo, volver a pedir el snapshot.

El historial de la página `/admin/judicial-sync` sigue usando `GET /api/admin/judicial-sync/runs` como hoy; este contrato es solo para el banner global.

---

## 4. Backend relacionado

| Pieza | Ubicación |
| :--- | :--- |
| Endpoint | `GET /api/admin/judicial-sync/status` |
| Canal | private `admin.judicial-sync` |
| Evento | `JudicialSyncStatusUpdated` |
| Emisión | `JudicialSyncRun` (start / queued / progress / finish) vía `JudicialSyncLiveStatusBroadcaster` |
| Colas | Rama → `judicial-sync`; SAMAI → `samai-sync` (pueden correr en paralelo) |

---

## 5. Disparar el cron de una organización

```http
POST /api/admin/judicial-sync/organizations/{organization}
Authorization: Bearer {admin_token}
Content-Type: application/json
```

`{organization}` es el UUID. Body opcional:

```json
{ "radicado": "23 dígitos" }
```

Sin body sincroniza todos los radicados activos de esa organización en Rama Judicial, incluidos los compartidos con otra organización.

```json
{
  "jobs_dispatched": 2,
  "batch_dispatched": true,
  "radicado_filter": null,
  "data_source": "judicial_branch",
  "data_source_label": "Rama Judicial",
  "organization_id": "uuid",
  "organization_name": "Nombre"
}
```

`batch_dispatched: false` y `jobs_dispatched: 0` cuando esa organización no tiene radicados elegibles. `422` si el radicado no tiene 23 dígitos o si la organización no puede trackear procesos. `404` si el UUID no existe.

`POST /api/admin/judicial-sync` sigue siendo el cron global y no acepta `organization_id`.

El progreso de esta ejecución llega por el mismo `GET /api/admin/judicial-sync/status` y por `JudicialSyncStatusUpdated`, identificado por `run_id` y `organization_name`.

---

## 6. Requisitos de entorno

- `BROADCAST_CONNECTION=reverb` (u otro driver compatible).
- Reverb corriendo en el entorno del admin.
- El token Sanctum del admin debe poder autenticar `/broadcasting/auth`.
