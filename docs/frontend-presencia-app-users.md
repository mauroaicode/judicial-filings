# Presencia de app users

Contrato para el Angular del **app user** y del **admin**.

La API guarda **una fila por usuario por día** (quién entró, desde qué IP y, si se puede resolver, la ciudad). Quién está conectado **ahora** lo lleva Reverb: no se escribe en MySQL en cada request.

La ubicación sale de la IP (ciudad / región / país). No es GPS. En redes locales (`127.0.0.1`, `192.168.x`, `10.x`) `place` queda `null` y solo se ve la IP.

## App user

Al abrir la app con un token ya guardado, y también justo después del login, unirse al canal de presencia:

```js
Echo.join('app-users.online');
```

Eso marca al usuario como conectado y, como máximo cada 15 minutos, encola el registro del día. Quien ya tenía sesión y usa la API también queda en el historial del día aunque el socket tarde en conectar: cualquier request autenticado a `/api/app-user/*` dispara ese registro, con el mismo límite de 15 minutos.

Auth del socket: `POST /broadcasting/auth` con `Authorization: Bearer {token}`.

## Admin

```http
Authorization: Bearer {token}
```

### Quién entró en una fecha

```http
GET /api/admin/presence/today?date=2026-10-21&per_page=20&page=1
```

`date` es opcional y limita el listado a ese día. También se puede pedir un rango con `from` y `to` (`Y-m-d`) y filtrar una organización con `organization_id`. Sin fechas, el listado es el de hoy.

Cada fila trae `organization_id` y `organization_name`: la organización a la que pertenece ese registro. Si la persona está en más de una, hay una fila por organización.

```json
{
  "data": [
    {
      "id": "uuid-de-la-fila",
      "app_user_id": "uuid",
      "organization_id": "uuid",
      "organization_name": "Bufete",
      "name": "Ana",
      "last_name": "Pérez",
      "email": "ana@bufete.com",
      "identification": "1234567890",
      "organizations": [{ "id": "uuid", "name": "Bufete" }],
      "activity_date": "2026-10-21",
      "first_seen_at": "2026-10-21 08:12:00",
      "last_seen_at": "2026-10-21 09:40:00",
      "login_count": 1,
      "ip_address": "190.0.0.1",
      "country": "Colombia",
      "region": "Antioquia",
      "city": "Medellín",
      "place": "Medellín, Antioquia, Colombia",
      "is_online": true
    }
  ]
}
```

`login_count` solo sube cuando la persona escribe la contraseña. `first_seen_at` también se llena si entra con un token que ya tenía. `is_online` es `false` si Reverb no responde.

La respuesta usa la paginación de Laravel: `data`, `total`, `per_page`, `current_page` y `links`. `total` es cuántos registros hay en el rango, no cuántos están conectados ahora.

### Cuántas veces entró en un periodo

```http
GET /api/admin/presence/summary?organization_id={uuid}&from=2026-10-01&to=2026-10-21
```

Sin `from` ni `to`, el periodo es el mes en curso. `organization_id` es opcional.

```json
{
  "from": "2026-10-01",
  "to": "2026-10-21",
  "organization_id": "uuid",
  "totals": {
    "users": 1,
    "login_count": 3,
    "days_online": 8
  },
  "data": [
    {
      "app_user_id": "uuid",
      "organization_id": "uuid",
      "organization_name": "Bufete",
      "name": "Ana",
      "last_name": "Pérez",
      "email": "ana@bufete.com",
      "identification": "1234567890",
      "login_count": 3,
      "days_online": 8,
      "first_seen_at": "2026-10-01 08:12:00",
      "last_seen_at": "2026-10-21 09:40:00"
    }
  ]
}
```

`login_count` suma las veces que escribió la contraseña en el periodo. `days_online` es la cantidad de días en los que entró al sistema, con login nuevo o con la sesión que ya tenía. `totals` repite esas sumas para toda la organización, no solo la página.

### Quién está conectado ahora

```http
GET /api/admin/presence/online?organization_id={uuid}
```

`organization_id` es opcional y deja solo a quienes pertenecen a esa organización.

```json
{
  "presence_available": true,
  "total": 1,
  "users": [
    {
      "id": "uuid",
      "name": "Ana",
      "last_name": "Pérez",
      "email": "ana@bufete.com",
      "identification": "1234567890",
      "organizations": [{ "id": "uuid", "name": "Bufete" }],
      "ip_address": "190.0.0.1",
      "country": "Colombia",
      "region": "Antioquia",
      "city": "Medellín",
      "place": "Medellín, Antioquia, Colombia",
      "last_seen_at": "2026-10-21 09:40:00"
    }
  ]
}
```

`presence_available: false` significa que Reverb no contestó. En ese caso `users` viene vacío y no hay que tratarlo como “nadie está conectado”.

Para actualizar la lista sin recargar, el admin también se une al canal:

```js
Echo.join('app-users.online')
  .here((members) => { /* miembros actuales; ignorar los que tengan guard === 'admin' */ })
  .joining((member) => {})
  .leaving((member) => {});
```

El admin aparece en el canal con `guard: "admin"`. Esos miembros no salen en `GET /presence/online`.

Cuando se guarda un ingreso o una actividad del día, el backend emite `AppUserActivityRecorded` en el canal privado `admin.presence`. El admin se suscribe con su token y, al recibirlo, vuelve a pedir `online`, `today` y `summary` sin recargar la página:

```js
Echo.private('admin.presence').listen('.AppUserActivityRecorded', () => {
  // refetch silencioso de las tres listas
});
```

El payload trae `app_user_id`, `increment_login` y `activity_date`. `joining` y `leaving` actualizan quién está conectado; este evento actualiza los inicios de sesión.
