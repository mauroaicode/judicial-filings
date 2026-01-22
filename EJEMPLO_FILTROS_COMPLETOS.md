# Ejemplo Completo: Uso de Todos los Filtros

## 📋 Ejemplo de Request con TODOS los Filtros

### URL Completa
```
GET /api/app-user/processes?
    process_number=11001400300420170082500&
    court=006&
    process_class=Ejecutivo&
    plaintiff=JUAN PEREZ&
    defendant=EMPRESA ABC&
    created_at=2024-01-15&
    created_at_from=2024-01-01&
    created_at_to=2024-01-31&
    process_date=2017-08-11&
    process_date_from=2017-01-01&
    process_date_to=2017-12-31&
    last_api_update_from=2024-01-01&
    last_api_update_to=2024-01-31&
    status=active&
    has_multiple_instances=true&
    per_page=20&
    page=1
```

### Formato Legible (código)
```php
// Ejemplo en PHP usando HttpClient
$response = $httpClient->get('/api/app-user/processes', [
    'query' => [
        // Filtros de texto (LIKE)
        'process_number' => '11001400300420170082500',  // Busca este número o parte de él
        'court' => '006',                               // Busca despachos que contengan "006"
        'process_class' => 'Ejecutivo',                 // Busca clases que contengan "Ejecutivo"
        
        // Filtros de sujetos procesales
        'plaintiff' => 'JUAN PEREZ',                    // Busca en demandantes (nombre o identificación)
        'defendant' => 'EMPRESA ABC',                   // Busca en demandados (nombre o identificación)
        
        // Filtros de fecha exacta
        'created_at' => '2024-01-15',                   // Fecha exacta cuando TU ORGANIZACIÓN registró el proceso (organization_processes.created_at)
        'process_date' => '2017-08-11',                 // Fecha exacta del proceso (processes.process_date)
        
        // Filtros de rango de fechas
        'created_at_from' => '2024-01-01',              // Desde fecha cuando TU ORGANIZACIÓN registró el proceso (organization_processes.created_at)
        'created_at_to' => '2024-01-31',                // Hasta fecha cuando TU ORGANIZACIÓN registró el proceso (organization_processes.created_at)
        'process_date_from' => '2017-01-01',             // Desde fecha del proceso
        'process_date_to' => '2017-12-31',              // Hasta fecha del proceso
        'last_api_update_from' => '2024-01-01',         // Desde última actualización API
        'last_api_update_to' => '2024-01-31',           // Hasta última actualización API
        
        // Filtros de estado
        'status' => 'active',                           // 'active' | 'inactive'
        'has_multiple_instances' => 'true',             // 'true' | 'false'
        
        // Paginación
        'per_page' => 20,
        'page' => 1,
    ],
]);
```

### Ejemplo en JavaScript/TypeScript (Frontend)
```typescript
// Ejemplo en Angular/React/Vue
const filters = {
  // Filtros de texto (LIKE)
  process_number: '11001400300420170082500',
  court: '006',
  process_class: 'Ejecutivo',
  
  // Filtros de sujetos procesales
  plaintiff: 'JUAN PEREZ',
  defendant: 'EMPRESA ABC',
  
  // Filtros de fecha exacta
  created_at: '2024-01-15',  // ⚠️ Fecha cuando TU ORGANIZACIÓN registró el proceso (organization_processes.created_at)
  process_date: '2017-08-11', // Fecha del proceso (processes.process_date)
  
  // Filtros de rango de fechas
  created_at_from: '2024-01-01',  // ⚠️ Desde fecha cuando TU ORGANIZACIÓN registró el proceso
  created_at_to: '2024-01-31',    // ⚠️ Hasta fecha cuando TU ORGANIZACIÓN registró el proceso
  process_date_from: '2017-01-01',
  process_date_to: '2017-12-31',
  last_api_update_from: '2024-01-01',
  last_api_update_to: '2024-01-31',
  
  // Filtros de estado
  status: 'active',
  has_multiple_instances: 'true',
  
  // Paginación
  per_page: 20,
  page: 1,
};

// Construir query string
const queryParams = new URLSearchParams();
Object.entries(filters).forEach(([key, value]) => {
  if (value !== null && value !== undefined && value !== '') {
    queryParams.append(key, value.toString());
  }
});

const url = `/api/app-user/processes?${queryParams.toString()}`;
```

### Ejemplo en cURL
```bash
curl -X GET "https://api.example.com/api/app-user/processes?\
process_number=11001400300420170082500&\
court=006&\
process_class=Ejecutivo&\
plaintiff=JUAN%20PEREZ&\
defendant=EMPRESA%20ABC&\
created_at=2024-01-15&\
created_at_from=2024-01-01&\
created_at_to=2024-01-31&\
process_date=2017-08-11&\
process_date_from=2017-01-01&\
process_date_to=2017-12-31&\
last_api_update_from=2024-01-01&\
last_api_update_to=2024-01-31&\
status=active&\
has_multiple_instances=true&\
per_page=20&\
page=1" \
-H "Authorization: Bearer YOUR_TOKEN" \
-H "Accept: application/json"
```

---

## 🔍 Qué hace este ejemplo

Este ejemplo busca procesos que cumplan **TODAS** estas condiciones simultáneamente:

1. ✅ **Número de radicado** contiene `11001400300420170082500`
2. ✅ **Despacho** contiene `006` (ej: "Juzgado 006...")
3. ✅ **Clase de proceso** contiene `Ejecutivo` (ej: "Ejecutivo con Título Prendario")
4. ✅ **Demandante** tiene nombre o identificación que contiene `JUAN PEREZ`
5. ✅ **Demandado** tiene nombre o identificación que contiene `EMPRESA ABC`
6. ✅ **Fecha de registro por tu organización** es exactamente `2024-01-15` **Y** está en el rango `2024-01-01` a `2024-01-31` (usa `organization_processes.created_at`)
7. ✅ **Fecha del proceso** es exactamente `2017-08-11` **Y** está en el rango `2017-01-01` a `2017-12-31`
8. ✅ **Última actualización API** está en el rango `2024-01-01` a `2024-01-31`
9. ✅ **Estado** es `active` (activo)
10. ✅ **Tiene múltiples instancias** es `true`

---

## 📝 Notas Importantes

### 1. **Filtros de fecha exacta vs rango**
- Si usas `created_at` (fecha exacta), los rangos `created_at_from` y `created_at_to` se ignoran
  - ⚠️ **IMPORTANTE**: `created_at` filtra por `organization_processes.created_at` (cuándo tu organización registró el proceso), NO por `processes.created_at`
- Si usas `process_date` (fecha exacta), los rangos `process_date_from` y `process_date_to` se ignoran
- Para rangos, usa solo `_from` y `_to` sin la fecha exacta

### 2. **Filtros de texto (LIKE)**
- Todos los filtros de texto son case-insensitive y buscan en cualquier parte del campo
- `court=006` encontrará: "Juzgado 006", "006 Civil", etc.
- `process_class=Ejecutivo` encontrará: "Ejecutivo con Título", "Ejecutivo", etc.

### 3. **Filtros de sujetos procesales**
- Buscan en la relación `subjects` del proceso
- Buscan tanto en `name_or_business_name` como en `identification`
- Un proceso puede tener múltiples demandantes/demandados, si alguno coincide, el proceso aparece

### 4. **Combinación de filtros**
- Todos los filtros se combinan con **AND** (deben cumplirse todos)
- Los filtros son opcionales (puedes usar solo los que necesites)

---

## 🎯 Ejemplos Prácticos Reales

### Ejemplo 1: Buscar procesos activos de un despacho específico
```php
GET /api/app-user/processes?court=006&status=active
```

### Ejemplo 2: Buscar procesos donde un demandante específico está involucrado
```php
GET /api/app-user/processes?plaintiff=JUAN PEREZ&status=active
```

### Ejemplo 3: Buscar procesos de una clase específica creados en un rango de fechas
```php
GET /api/app-user/processes?
    process_class=REPARACION&
    created_at_from=2024-01-01&
    created_at_to=2024-01-31
```

### Ejemplo 4: Buscar procesos con múltiples instancias actualizados recientemente
```php
GET /api/app-user/processes?
    has_multiple_instances=true&
    last_api_update_from=2024-01-01&
    status=active
```

### Ejemplo 5: Búsqueda completa (todos los filtros)
```php
GET /api/app-user/processes?
    process_number=11001400300420170082500&
    court=006&
    process_class=Ejecutivo&
    plaintiff=JUAN&
    defendant=EMPRESA&
    created_at_from=2024-01-01&
    created_at_to=2024-01-31&
    process_date_from=2017-01-01&
    process_date_to=2017-12-31&
    last_api_update_from=2024-01-01&
    last_api_update_to=2024-01-31&
    status=active&
    has_multiple_instances=true
```

---

## 📊 Respuesta Esperada

```json
{
  "data": [
    {
      "index": 1,
      "id": "cbdbb857-1e85-48bd-9aa8-16988b8a00a5",
      "process_number": "11001400300420170082500",
      "court": "Juzgado 006 Civil Municipal de Ejecución de Sentencias de Bogotá",
      "process_class": "Ejecutivo con Título Prendario",
      "subclass_process": "Sin Subclase de Proceso",
      "process_date": "2017-08-11",
      "last_activity_date": null,
      "is_private": false,
      "has_multiple_instances": true,
      "status_label": "Activo",
      "created_at": "2024-01-18 04:37:36",
      "plaintiff": "Juan Perez Garcia",
      "defendant": "Empresa ABC S.A.S."
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 1,
  "last_page": 1,
  "from": 1,
  "to": 1
}
```
