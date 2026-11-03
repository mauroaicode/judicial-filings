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
      "radicado_filter": null
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
| `is_executable` | `false` para fuentes reservadas (p. ej. TYBA) aún no implementadas. |

`sources` siempre lista **todas** las fuentes del enum (`judicial_branch`, `samai`, `tyba`) para que el front no hardcodee la lista.

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
  "started_at": "2026-10-23T09:03:00-05:00"
}
```

Cuando `is_active` pasa a `false` (batch terminado / cancelado / sin procesos / dispatch fallido), quitar ese chip del header. Si `active_count` queda en 0, ocultar el banner.

Los updates de progreso mid-batch están **throttled** (~2 s por run, configurable con `JUDICIAL_SYNC_LIVE_STATUS_THROTTLE_SECONDS`). Inicio, encolado y fin siempre emiten de inmediato.

---

## 3. Comportamiento del header (UI)

1. Al cargar el shell admin: `GET /api/admin/judicial-sync/status`.
2. Suscribirse a `admin.judicial-sync`.
3. Banner visible solo si `has_active === true` (o `active.length > 0`).
4. **Una sola fuente activa:** mostrar un chip estático, sin carrusel.
5. **Dos o más fuentes activas:** carrusel / scroll horizontal infinito (Rama ↔ SAMAI ↔ …).
6. Texto sugerido por chip: `Sincronizando {data_source_label}: quedan {queue_pending_jobs} de {processes_queued}`.
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

## 5. Requisitos de entorno

- `BROADCAST_CONNECTION=reverb` (u otro driver compatible).
- Reverb corriendo en el entorno del admin.
- El token Sanctum del admin debe poder autenticar `/broadcasting/auth`.
