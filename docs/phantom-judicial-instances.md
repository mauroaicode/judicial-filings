# Instancias fantasma en Rama Judicial

## El problema

Al consultar la Rama Judicial, a veces el mismo **número de radicado** aparece varias veces en los resultados. Todas las filas muestran el mismo despacho, departamento y fechas, pero **solo una** trae sujetos procesales; las demás están vacías.

Eso no es una segunda instancia real (apelación, casación, etc. con actuaciones distintas). Es un **error de carga en el portal**: varias carpetas (`idProceso`) duplicadas para el mismo expediente.

### Qué hace nuestro sistema antes del fix

1. **Discovery** crea un `Process` por cada `idProceso` que devuelve la API.
2. El **sync** (cron, registro manual o importación) consulta actuaciones en **cada** instancia.
3. Rama asigna **distinto `idRegActuacion`** por carpeta, aunque el contenido sea el mismo.
4. Se guardan varias actuaciones idénticas y se **notifica varias veces** la misma novedad.

### Ejemplo real

Radicado `76001400303420230073500`: 4 filas en Rama, 3 sin sujetos, 1 con demandante/demandado. Una actuación nueva (ej. *Fijación estado*) se replica en las 4 carpetas → **3 alertas de más** para el cliente.

---

## Solución en código (prevención)

Desde el fix en `ProcessSyncService` y `ProcessActionAlertNotificationService`:

| Capa | Qué hace |
|------|----------|
| **Instancias fantasma** | Si una instancia no tiene sujetos/`litigants` y otra del mismo radicado sí (mismo despacho y fecha), **no se sincronizan actuaciones** en la carpeta vacía. |
| **Dedupe por contenido** | Al guardar y al notificar, se compara `fecha + actuación + anotación + fecha registro` dentro del mismo `process_number`. Si ya existe, no se crea actuación ni notificación. |
| **Dedupe por `idRegActuacion`** | Sigue activo cuando Rama reutiliza el mismo ID entre carpetas. |

Configuración (`config/judicial-sync.php`):

```env
JUDICIAL_SYNC_SKIP_PHANTOM_INSTANCES=true
JUDICIAL_SYNC_DEDUPE_ACTIONS_BY_CONTENT=true
```

### ¿Sigue funcionando el flujo de múltiples instancias reales?

**Sí.** Las instancias reales se distinguen porque:

- Tienen **distinto despacho**, **distinto departamento** o **distinta fecha de radicación**, **o**
- **Todas** tienen sujetos o `litigants` con contenido (ninguna es “carpeta vacía” frente a una “rica”).

En esos casos el sync sigue trayendo actuaciones de cada instancia y notificando por instancia cuando el contenido es distinto.

### ¿Cubre cron, registro manual e importación Excel?

| Flujo | Prevención automática |
|-------|------------------------|
| **Cron** (`SyncProcessJob` → `syncByProcessNumber`) | Sí |
| **Registro manual** (`RegisterProcessService` → `syncForRegistration`) | Sí |
| **Importación masiva / Excel** (mismo `syncForRegistration` tras adjuntar) | Sí |

**Nota:** El discovery sigue creando registros `Process` por cada `idProceso` de Rama (no eliminamos carpetas duplicadas de la API). Lo que evitamos es **llenar actuaciones duplicadas** y **notificar varias veces**. Las instancias fantasma pueden seguir existiendo en BD como filas, pero sin actuaciones propias repetidas.

---

## Comando de reparación (datos ya dañados)

Para radicados que **ya** tienen actuaciones y notificaciones duplicadas antes del deploy del fix:

```bash
php artisan judicial:repair-phantom-instances --radicado=76001400303420230073500 --dry-run
php artisan judicial:repair-phantom-instances --radicado=76001400303420230073500
```

### Opciones

| Opción | Descripción |
|--------|-------------|
| `--radicado=` | Repara un número de radicado. |
| `--process=` | UUID de un proceso; repara el radicado completo asociado. |
| `--all` | Escanea y repara todos los radicados afectados. |
| `--dry-run` | Solo muestra qué se eliminaría, sin escribir en BD. |
| `--force` | Con `--all`, omite la confirmación interactiva. |

### Qué hace el comando

1. Agrupa actuaciones del radicado por **contenido** (misma huella que el sync).
2. En cada grupo duplicado, **conserva una actuación canónica** (prioriza instancia “rica” con sujetos, luego menor `idRegActuacion`).
3. Elimina las actuaciones duplicadas y sus highlights/keywords.
4. Elimina o consolida **notificaciones** duplicadas (`actuacion`, `actuacion_alerta`, `actuacion_registro`) por organización y tipo.
5. Actualiza `last_activity_date` de cada instancia.

**No elimina** filas de `processes` ni desvincula organizaciones. Solo limpia actuaciones y notificaciones repetidas.

### Comandos relacionados

| Comando | Uso |
|---------|-----|
| `judicial:repair-phantom-instances` | Duplicados por carpetas fantasma (este documento). |
| `judicial:repair-duplicate-subjects` | Sujetos repetidos entre instancias del mismo radicado. |
| `judicial:repair-instance-actions` | Actuaciones que no pertenecen al `idProceso` de esa instancia (contaminación cruzada). |

---

## Flujo recomendado para un radicado problemático

```bash
# 1. Ver impacto
php artisan judicial:repair-phantom-instances --radicado=76001400303420230073500 --dry-run

# 2. Reparar
php artisan judicial:repair-phantom-instances --radicado=76001400303420230073500

# 3. (Opcional) alinear sujetos entre instancias
php artisan judicial:repair-duplicate-subjects --radicado=76001400303420230073500
```

Tras desplegar el fix de prevención, los siguientes syncs no deberían volver a triplicar alertas en ese radicado.
