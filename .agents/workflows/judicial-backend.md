---
trigger: always_on
---

# 🚀 Prompt Cursor Backend - PROCESOS JUDICIALES COLOMBIA

**Always respond in Español**

---

## 🎯 CONTEXTO DEL PROYECTO

Estás desarrollando un sistema web integral para **monitoreo automático de radicados judiciales** en Colombia. 

**Flujo principal:**
1. **Organizaciones de abogados** crean cuentas
2. Asignan **AppUsers (abogados)** con límites de radicados 
3. **AppUsers** registran radicados de **EXACTAMENTE 23 dígitos**
4. Sistema sincroniza diariamente con **Rama Judicial**
5. Detecta nuevas **actuaciones** → **notificaciones multicanal** (email/WhatsApp/SMS)
6. **Alertas prioritarias** para:
   - "CONSULTA"/"APELACIÓN" en descripción
   - **Doble instancia** (mismo radicado, diferente judicial_id)

---

## 🗄️ BASE DE DATOS EXISTENTE (CRÍTICO - NO MODIFICAR)

```
organizations: id, name, email, phone, notification_config (JSON)
app_users: id, organization_id, name, email, radicado_limit
processes: id, organization_id, app_user_id, process_number(23d), judicial_id
process_actions: id, process_id, description, is_alert
notification_channels: organization_id, channel_type
audits: user_id, ip, action_type, model_id
```

---

## 👥 ROLES Y PERMISOS

| Rol | Permisos |
|-----|----------|
| **Super Admin** | Acceso total |
| **Admin** | Gestión organizations + límites |
| **AppUser** | Solo procesos propios |

---

## ⚡ CARACTERÍSTICAS CRÍTICAS

- ✅ **Radicado**: `Regex('/^\d{23}$/')` **EXACTO**
- ✅ **Límites**: `organization → app_user.radicado_limit`
- ✅ **Alertas**: `"CONSULTA"/"APELACIÓN"` + doble instancia
- ✅ **Sync**: Job diario Rama Judicial
- ✅ **Notificaciones**: Email/WhatsApp/SMS configurables

---

## 📦 MÓDULOS PRINCIPALES

1. **Autenticación**: Login AppUser/Admin
2. **Organizations**: CRUD + límites/canales  
3. **AppUsers**: CRUD en organization
4. **Processes**: CRUD 23 dígitos + límites
5. **ProcessActions**: Sync auto + alertas
6. **NotificationChannels**: CRUD
7. **JudicialSync**: Jobs Rama

---

## 🏗️ PRINCIPIOS DE DESARROLLO

### Tecnologías
```
Laravel 11 | PHP 8.2+ | Pest
NUNCA session() - API REST pura
PSR-12 | SOLID | Custom Query Builders
```

---

## 📁 ESTRUCTURA DEL PROYECTO (TRIBUNAL STYLE)

```
src/
├── Application/                    # Casos uso, Controllers, DTOs, Resources
│   ├── Admin/                      # Administración sistema
│   │   ├── Auth/
│   │   ├── Organization/
│   │   ├── AppUser/
│   │   ├── Process/
│   │   └── NotificationChannel/
│   └── Shared/
└── Domain/                         # Modelos, enums
    ├── Organization/
    ├── AppUser/
    ├── Process/
    ├── ProcessAction/
    └── Shared/
```

---

## ⚙️ CONVENCIONES DETALLADAS

### 1️⃣ Controladores
```
📍 Application/Admin/{Module}/Controllers/
📋 SingularController
🔄 index(): Collection | store(Data): Response | show(): Resource
❌ abort(422, __('validation.field'))
🧪 Testing OBLIGATORIO cada método
```

```php
class ProcessController {
    public function index(): Collection;
    public function store(StoreProcessData $data): Response;
    public function show(Process $process): ProcessResource;
}
```

### 2️⃣ Data Objects (DTOs) - **OBLIGATORIO**
```
📍 Application/Admin/{Module}/Data/
🔗 use Src\Application\Shared\Traits\TranslatableDataAttributesTrait;
```

**Radicado crítico**:
```php
#[Required, Regex('/^\d{23}$/')]
public string $process_number;

#[Unique(table: 'processes', column: 'process_number', 
    ignore: RouteParameterReference('process', 'id'))]
```

### 3️⃣ Resources - **OBLIGATORIO**
```
📍 Application/Admin/{Module}/Resources/
extends Spatie\LaravelData\Resource
show/index → SIEMPRE Resource
Fechas: Y-m-d | nullable por migración
```

### 4️⃣ Modelos
```
📍 Domain/{Module}/Models/{Model}.php
❌ NO new Model() | NO scopes
✅ Model::query()->where()
✅ $model->update(['field' => $value])
```

### 5️⃣ Enums
```
📍 Domain/{Module}/Enums/
```

```php
enum ProcessStatus: string {
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case CLOSED = 'closed';
}
```

### 6️⃣ Migraciones
```
📍 database/migrations/
✅ Plural tables | snake_case columns
✅ 1 migración = 1 cambio (atomicidad)
✅ Verificar existentes antes modificar
```

### 7️⃣ Testing - **OBLIGATORIO COMPLETO**

**Reglas generales**:
- ✅ Verificación: Correr test generado
- ✅ Idioma: Títulos inglés
- ❌ RefreshDatabase: NUNCA uses()
- ❌ Rutas: NO route('name')
- ✅ beforeEach + $model->update()

**Base estructura**:
```php
beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create(['organization_id' => $this->organization->id]);
});

it('shows active user correctly', function (): void {
    $this->user->update(['email_verified_at' => now()]);
});
```

**Comandos**:
```bash
vendor/bin/sail pest -c phpunit-local.xml
vendor/bin/sail artisan migrate:fresh --env=testing
```

**Convenciones nomenclatura**:
```
Archivos: UserControllerTest.php (sufijo Test.php)
Métodos: it() en inglés descriptivo
Estructura: tests/ = src/
```

**Assertions API**:
```php
// Éxito
assertStatus(201)
assertExactJson($expected)

// Error validación
assertJsonValidationErrors(['field' => 'error message'])

// Abort/Permisos
assertStatus(403)
assertJson(['message' => __('permiso.denied')])
```

**Manejo de datos**:
```php
// Usar factories
$user = User::factory()->create();
$org = Organization::factory()->create();

// Autenticación en tests
actingAs($this->appUser)

// Efectos secundarios
Event::fake();
Event::assertDispatched(ProcessCreated::class);

Queue::fake();
Queue::assertPushed(SyncJudicialJob::class);
```

**EJEMPLOS JUDICIALES - COPIAR/PEGAR**:

```php
// Test 1: Rechazar radicado con dígitos incorrectos
it('rejects radicado with wrong digits', function () {
    $response = $this->actingAs($this->appUser)
        ->postJson('/api/processes', [
            'process_number' => '123'  // solo 3 dígitos
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors([
        'process_number' => ['El radicado debe tener exactamente 23 dígitos.']
    ]);
});

// Test 2: Bloquear cuando supera límite
it('blocks when exceeds radicado limit', function () {
    $this->appUser->update(['radicado_limit' => 0]);

    $response = $this->actingAs($this->appUser)
        ->postJson('/api/processes', [
            'process_number' => '12345678901234567890123'
        ]);

    $response->assertStatus(422);
    expect(Process::count())->toBe(0);
});

// Test 3: Detectar doble instancia como alerta
it('detects double instance as alert', function () {
    $process1 = Process::factory()->create([
        'process_number' => '12345678901234567890123',
        'judicial_id' => 'ABC123'
    ]);

    $process2 = Process::factory()->create([
        'process_number' => '12345678901234567890123',
        'judicial_id' => 'DEF456'
    ]);

    // Verificar que segunda instancia disparó alerta
    $action = ProcessAction::where('process_id', $process2->id)->first();
    expect($action->is_alert)->toBe(true);
});

// Test 4: Crear radicado exitosamente
it('creates process with valid 23 digits', function () {
    Event::fake();
    Queue::fake();

    $data = [
        'process_number' => '12345678901234567890123',
        'organization_id' => $this->organization->id,
        'app_user_id' => $this->appUser->id
    ];

    $response = $this->actingAs($this->appUser)
        ->postJson('/api/processes', $data);

    $response->assertStatus(201);
    expect(Process::count())->toBe(1);
    Queue::assertPushed(SyncJudicialJob::class);
});

// Test 5: Notificación por palabra clave CONSULTA
it('generates alert notification for CONSULTA keyword', function () {
    Notification::fake();
    
    $process = Process::factory()->create();
    
    $action = ProcessAction::create([
        'process_id' => $process->id,
        'description' => 'Se abrió período de CONSULTA de pruebas',
        'is_alert' => true
    ]);

    // Verificar notificación disparada
    expect($action->is_alert)->toBe(true);
});

// Test 6: Validar organización activa
it('requires organization to be active', function () {
    $inactiveOrg = Organization::factory()->create(['is_active' => false]);
    $inactiveAppUser = AppUser::factory()->create(['organization_id' => $inactiveOrg->id]);

    $response = $this->actingAs($inactiveAppUser)
        ->postJson('/api/processes', [
            'process_number' => '12345678901234567890123'
        ]);

    $response->assertStatus(403);
});
```

### 8️⃣ Traducciones
```
📍 resources/lang/{es,en}/
✅ data.php (atributos DTOs)
✅ validation.php (mensajes validación)
```

---

## 🎯 REQUERIMIENTOS ESPECÍFICOS JUDICIALES

### ✅ Registrar Radicado (4 Validaciones Obligatorias)
```
1. ✓ Organization activa
2. ✓ radicado_limit disponible  
3. ✓ 23 dígitos EXACTOS
4. ✓ NO duplicado en organization
```

### 🚨 Alertas Automáticas (Doble lógica)
```php
// Lógica 1: Palabras clave
$isAlert = Str::contains($description, ['CONSULTA', 'APELACIÓN']);

// Lógica 2: Doble instancia
$secondInstance = Process::query()
    ->where('process_number', $number)
    ->where('judicial_id', '!=', $id)
    ->exists();
```

### 📲 Notificaciones Multicanal
```
- Organization configura canales activos
- AppUser puede personalizar preferencias
- ChannelNotificationDispatcherService resuelve canales
- Auditoría: canal, destinatario, timestamp
```

---

## 📋 ORDEN DE DESARROLLO SUGERIDO

| # | Fase | Descripción |
|---|------|-------------|
| 1 | ✅ | Estructura carpetas Application/Domain |
| 2 | ✅ | Modelos base (Organization, AppUser, Process) |
| 3 | 🔄 | Autenticación AppUser/Admin |
| 4 | 🔄 | CRUD Organizations/AppUsers |
| 5 | 🔄 | CRUD Processes (validación 23 dígitos) |
| 6 | 🔄 | JudicialBranchConsultService + inicial |
| 7 | 🔄 | ProcessActions + alert flags |
| 8 | 🔄 | NotificationChannels + Dispatcher |
| 9 | 🔄 | Testing completo (mínimo 85% coverage) |
| 10 | 🔄 | Jobs sync diario |
| 11 | 🔄 | Exportación PDF/Excel |

---

## 📚 REFERENCIAS TÉCNICAS

- **Custom Query Builders**: https://dev.to/rebelnii/how-to-build-a-custom-eloquent-builder-class-in-laravel-4bp8
- **Spatie Laravel Data**: https://spatie.be/docs/laravel-data/v4/as-a-resource/from-data-to-resource
- **Media Library**: https://spatie.be/docs/laravel-medialibrary/v11/basic-usage

---

## 🔍 CHECKLIST DESARROLLO

### Por módulo verificar:
- ✅ Controladores con métodos index/store/show
- ✅ DTOs con validaciones
- ✅ Resources para respuestas
- ✅ Modelos con relationships
- ✅ Tests happy path + errores
- ✅ Traducciones es/en
- ✅ Auditoría registro

### Antes de deploy:
- ✅ Tests al 85%+ coverage
- ✅ `vendor/bin/pint` formato
- ✅ Migraciones verificadas
- ✅ Factories creadas
- ✅ DTOs validando correctamente

---

*Prompt optimizado para Cursor AI - Listo para desarrollo backend Laravel 11*  
*Estructura idéntica Tribunal Ética Médica + Reglas Judiciales Colombia*

**📱 Ubicación Cali, Valle del Cauca - Colombia**  
**💾 Generado: 2026-01-12**
