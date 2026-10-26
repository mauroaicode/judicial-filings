# Contexto: visto de consolidados y listado admin

Documento para continuar el trabajo sin rehacer lo ya implementado. Complementa `docs/consolidados-notification-digests.md` (cómo se arma y se envía el consolidado). Este archivo cubre solo el **registro de visto** y el **listado admin**.

Fecha: 2026-10-23. La migración ya se ejecutó en el entorno local.

Repos tocados:

- Backend: `judicial-filings`
- App user (Angular): `judicial-filings-frontend`

El frontend admin (`judicial-filings-fronted-admin`) **no** se modificó.

---

## 1. Regla de negocio

Un consolidado es una fila de `notification_digests` y ya pertenece a una sola organización.

- Si **al menos un** `app_user` de esa organización abre el consolidado, `notification_digests.viewed_at` queda con la fecha de esa primera apertura. No se pisa en aperturas posteriores.
- Cada `app_user` que lo abre se guarda **una sola vez** en `notification_digest_views` (organización + usuario). Varios usuarios de la misma organización sí se guardan. El mismo usuario no se duplica aunque vuelva a abrir el detalle, cambie de página o entre primero por la app y después por el correo. Se conserva el `opened_via` de la **primera** apertura.
- Abrir el consolidado cuenta igual desde el módulo Actuaciones recientes y desde el enlace del correo.
- Listar el historial **no** marca visto. Descargar el PDF **no** marca visto: el modal de detalle ya lo hizo al cargar `GET .../notification-digests/{id}`.
- No hay pixel de apertura del correo. El correo solo cuenta cuando la persona autenticada entra al detalle por el CTA.

Lo que **no** está hecho y se dejó explícitamente para después:

- Endpoint admin que liste **quién** lo vio (las filas de `notification_digest_views`). El listado solo dice si está visto o no.
- Pantalla admin.
- Filtro `is_viewed` en el listado.

---

## 2. Esquema

Migración: `database/migrations/2026_10_23_091500_create_notification_digest_views_table.php`.

`notification_digests.viewed_at` (timestamp, nullable). `null` = nadie de la organización lo ha abierto.

`notification_digest_views`:

| Columna | Notas |
|---------|--------|
| `id` | uuid |
| `notification_digest_id` | FK cascade |
| `organization_id` | FK cascade. Denormalizado; el digest ya es de una org |
| `app_user_id` | FK cascade |
| `opened_via` | `app` o `email` (`NotificationDigestOpenedVia`) |
| `viewed_at` | cuándo lo abrió ese usuario |
| unique | (`notification_digest_id`, `app_user_id`) con nombre corto `digest_views_digest_user_unique` (MySQL rechaza el nombre autogenerado por longitud) |

Modelo: `Src\Domain\Notification\Models\NotificationDigestView`. Relación `NotificationDigest::views()`.

---

## 3. Cómo se marca el visto

Solo `GET /api/app-user/notification-digests/{id}`, después de resolver el detalle de esa organización.

`Src\Application\AppUser\Notification\Controllers\NotificationDigestController::show` llama a `RecordNotificationDigestViewService`.

Query opcional: `opened_via=app|email`. Cualquier otro valor, o la ausencia del parámetro, se trata como `app` (`NotificationDigestOpenedVia::fromRequest`).

El servicio:

1. Busca el digest de esa organización. Si no es de la org, no escribe nada (el detalle ya respondió 404).
2. Si ya existe la fila de ese usuario, termina. No reenvía el websocket.
3. Inserta la fila. Si hay carrera, `UniqueConstraintViolationException` y termina.
4. Si `notification_digests.viewed_at` es null, lo setea con un `UPDATE ... WHERE viewed_at IS NULL`.
5. Encola `NotificationDigestViewed` solo cuando la fila es nueva.

`GET /api/app-user/notification-digests/history` no llama al servicio.

### Correo

`ConsolidatedDigestEmailPresenter::resolveDigestUrl` ahora arma:

```
{origin}/actuaciones-recientes?digest={uuid}&from=email
```

El mismo helper lo usa el CTA del PDF (`ExportNotificationDigestPdfService`), así que el enlace del PDF también lleva `from=email`.

Plantilla: `resources/views/emails/consolidated-judicial-actions.blade.php`. El botón usa `$digestUrl`. No hace falta otra plantilla ni un pixel.

### App user (Angular)

`judicial-filings-frontend`:

- `NotificationDigestHistoryService.getDigestDetails` manda `opened_via`.
- `actuaciones-recientes.component.ts`:
  - Abrir una tarjeta: `opened_via=app` y quita `from` de la URL.
  - Entrar con `?digest=&from=email`: `opened_via=email`.
  - Paginar conserva `from=email` si el enlace del correo lo traía. La segunda página no crea otra fila.

El usuario tiene que estar autenticado. Si el correo lo manda al login, el guard conserva `returnUrl` (incluye el query) y, al volver, el detalle se pide y ahí se marca el visto.

---

## 4. Websocket

Evento: `Src\Domain\Notification\Events\NotificationDigestViewed` (`ShouldBroadcast`).

- Cola: `notifications` (`$broadcastQueue`).
- Canal: privado `admin.presence` (el mismo de la presencia admin; autorización en `routes/channels.php`: usuario `User` con rol `admin` del guard `admin`).
- Nombre de broadcast: `NotificationDigestViewed`.
- Se emite **una vez por app user**, no en cada paginación.

Payload:

```json
{
  "notification_digest_id": "uuid",
  "organization_id": "uuid",
  "organization_name": "Bufete Alfa",
  "app_user_id": "uuid",
  "app_user_name": "Ana",
  "app_user_last_name": "Pérez",
  "viewed_at": "2026-10-23T14:05:00+00:00",
  "opened_via": "app"
}
```

El admin frontend todavía no escucha este evento. `presence.component.ts` escucha otra cosa (`AppUserActivityRecorded`).

---

## 5. Listado admin

`GET /api/admin/notification-digests`

- Middleware: `auth:sanctum` + `admin.role`.
- Nombre de ruta: `admin.notification-digests.index` (el grupo `admin.` sale de `routes/api.php`).
- Archivo de ruta: `routes/api/admin/notification-digests.php`.
- Sin `organization_id` devuelve consolidados de **todas** las organizaciones, más recientes primero (`latest()` del query builder).

Piezas, siguiendo el patrón controller readonly + Data + Service + Resource:

| Pieza | Clase |
|-------|--------|
| Controller | `Src\Application\Admin\Notification\Controllers\NotificationDigestController` |
| Filtros | `Src\Application\Admin\Notification\Data\NotificationDigestFilterData` |
| Servicio | `Src\Application\Admin\Notification\Services\ListNotificationDigestsService` |
| Resource | `Src\Application\Admin\Notification\Resources\NotificationDigestHistoryResource` |

El resource admin **no** reimplementa el conteo de actuaciones. Llama a `AppUser\...\NotificationDigestHistoryResource::fromModel` y agrega organización y visto. El import va con alias porque las dos clases se llaman igual.

El servicio reutiliza `NotificationDigestQueryBuilder::filters()` con el DTO del app user. No se duplicó el `where` de radicado, fechas de actuación ni términos. Encima de eso aplica `whereOrganization` si viene `organization_id`.

### Filtros (query string)

Igual que el app user:

- `process_number`
- `registration_date_from`, `registration_date_to`
- `action_date_from`, `action_date_to`
- `created_at_from`, `created_at_to` (fecha del consolidado, no de la actuación)
- `term_start_date_from`, `term_start_date_to`
- `term_end_date_from`, `term_end_date_to`
- `per_page` (default 20), `page`

Extra:

- `organization_id` opcional, uuid que debe existir en `organizations`. Uuid inválido o inexistente: **422**.

### Respuesta (cada item de `data`)

Mismos campos del historial del app user, más organización y visto:

```json
{
  "id": "uuid",
  "organization_id": "uuid",
  "organization_name": "Bufete Alfa",
  "date": "15 de enero de 2030",
  "time": "8:00am",
  "period": "Mañana",
  "actions_count": 1,
  "is_notified": true,
  "email_notified_at": "15 de enero de 2030 8:00am",
  "whatsapp_notified_at": null,
  "sms_notified_at": null,
  "is_viewed": false,
  "viewed_at": null
}
```

`is_viewed` es `notification_digests.viewed_at !== null`. `viewed_at` va formateado con `DateFormatHelper::formatDateWithTime` (o `null`). `period` sale de `notification.morning|afternoon|night`.

Paginación Laravel estándar (`data`, `links`, `meta` / campos de `LengthAwarePaginator`).

Auth:

- Sin token: 401.
- Usuario sin rol `admin` (guard `admin`): 403.

Este listado **no** marca vistos.

Detalle de un consolidado de una organización, ya existente y distinto de este listado:

- `GET /api/admin/organizations/{organization}/notification-digests/{digest}`
- `GET /api/admin/organizations/{organization}/notification-digests/pending`

---

## 6. Tests

- `tests/Application/AppUser/Notification/Controllers/NotificationDigestViewControllerTest.php` — alta, correo, no duplicar, varios usuarios, el historial no marca, otra org no marca, un solo broadcast.
- `tests/Application/Admin/Notification/Controllers/NotificationDigestControllerTest.php` — 401, 403, todas las orgs, filtro por org, filtro por radicado, org inexistente.
- `tests/Application/Shared/Mail/ConsolidatedDigestEmailTest.php` — el CTA incluye `&from=email` (en el HTML el `&` va como `&amp;`).

```bash
php artisan test tests/Application/AppUser/Notification/Controllers/NotificationDigestViewControllerTest.php
php artisan test tests/Application/Admin/Notification/Controllers/NotificationDigestControllerTest.php
php artisan test tests/Application/Shared/Mail/ConsolidatedDigestEmailTest.php
```

---

## 7. Qué no rehacer

- No crear otro endpoint de presencia ni heartbeat. La presencia en vivo del app user es solo frontend (Echo `app-users.online`); el backend ya la tenía.
- No volver a marcar visto en el listado ni en el PDF.
- No unir `presence-` a mano en Echo; el canal de este aviso es `admin.presence` y lo autoriza el admin, no el app user.
- No cambiar el contrato del historial del app user: ese JSON no incluye `is_viewed`.
