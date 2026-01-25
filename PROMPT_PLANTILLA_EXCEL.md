# 📋 PROMPT COMPLETO PARA GENERAR PLANTILLA EXCEL - IMPORTACIÓN PROCESOS JUDICIALES

## Instrucciones para la IA

Genera un archivo Excel con múltiples hojas para importar procesos judiciales completos. El archivo debe tener la siguiente estructura:

---

## 📊 ESTRUCTURA DEL EXCEL

### **HOJA 1: "Procesos"** (Nombre de la hoja: `Procesos`)

**Columnas en orden (fila 1 = encabezados):**

| Columna | Nombre | Tipo | Requerido | Descripción | Ejemplo |
|---------|--------|------|-----------|-------------|---------|
| A | process_number | Texto | ✅ SÍ | Número de radicado (exactamente 23 dígitos) | 76001333301320170009301 |
| B | process_id | Número | ✅ SÍ | ID interno de la API (número entero) | 1234567890 |
| C | court | Texto | ✅ SÍ | Despacho/Juzgado | JUZGADO 035 CIVIL MUNICIPAL DE BOGOTÁ |
| D | department | Texto | ✅ SÍ | Departamento | BOGOTÁ |
| E | process_type | Texto | ✅ SÍ | Tipo de proceso | Ordinario |
| F | process_class | Texto | ✅ SÍ | Clase de proceso | ACCION DE REPARACION DIRECTA |
| G | subclass_process | Texto | ❌ NO | Subclase de proceso (opcional) | Sin Subclase de Proceso |
| H | litigants | Texto | ❌ NO | Sujetos procesales resumido (opcional) | Juan Perez vs Empresa ABC |
| I | process_date | Fecha | ✅ SÍ | Fecha del proceso (formato: YYYY-MM-DD) | 2016-09-14 |
| J | last_activity_date | Fecha | ❌ NO | Fecha de última actuación (formato: YYYY-MM-DD) | 2025-08-05 |
| K | location | Texto | ❌ NO | Ubicación del proceso | Despacho |
| L | filing_content | Texto | ❌ NO | Contenido de radicación | Test content |
| M | is_private | Booleano | ✅ SÍ | Indica si el proceso es privado (TRUE/FALSE) | FALSE |
| N | has_multiple_instances | Booleano | ✅ SÍ | Indica si tiene múltiples instancias (TRUE/FALSE) | FALSE |

**Formato de la hoja:**
- Fila 1: Encabezados (en negrita, fondo azul claro)
- Fila 2 en adelante: Datos de ejemplo (2-3 filas de ejemplo)
- Validación de datos:
  - Columna A (process_number): Solo números, exactamente 23 caracteres
  - Columna B (process_id): Solo números enteros
  - Columna I (process_date): Formato fecha YYYY-MM-DD
  - Columna J (last_activity_date): Formato fecha YYYY-MM-DD
  - Columna M (is_private): Lista desplegable: TRUE, FALSE
  - Columna N (has_multiple_instances): Lista desplegable: TRUE, FALSE

---

### **HOJA 2: "Actuaciones"** (Nombre de la hoja: `Actuaciones`)

**Columnas en orden (fila 1 = encabezados):**

| Columna | Nombre | Tipo | Requerido | Descripción | Ejemplo |
|---------|--------|------|-----------|-------------|---------|
| A | process_number | Texto | ✅ SÍ | Número de radicado (debe existir en hoja Procesos) | 76001333301320170009301 |
| B | action_registration_id | Número | ✅ SÍ | ID de registro de actuación desde la API | 123456 |
| C | action_date | Fecha | ✅ SÍ | Fecha de la actuación (formato: YYYY-MM-DD) | 2025-01-15 |
| D | action | Texto | ✅ SÍ | Descripción de la actuación | AUTO PARA ADMISIÓN DE DEMANDA |
| E | annotation | Texto | ❌ NO | Anotación o detalles de la actuación | Se admite la demanda presentada |
| F | start_date | Fecha | ❌ NO | Fecha inicial del período (formato: YYYY-MM-DD) | 2025-01-01 |
| G | end_date | Fecha | ❌ NO | Fecha final del período (formato: YYYY-MM-DD) | 2025-01-31 |
| H | registration_date | Fecha | ✅ SÍ | Fecha de registro de la actuación (formato: YYYY-MM-DD) | 2025-01-15 |

**Formato de la hoja:**
- Fila 1: Encabezados (en negrita, fondo verde claro)
- Fila 2 en adelante: Datos de ejemplo (3-5 filas de ejemplo con diferentes process_number)
- Validación de datos:
  - Columna A (process_number): Debe existir en hoja "Procesos"
  - Columna B (action_registration_id): Solo números enteros, único
  - Columnas C, F, G, H: Formato fecha YYYY-MM-DD

---

### **HOJA 3: "Sujetos"** (Nombre de la hoja: `Sujetos`)

**Columnas en orden (fila 1 = encabezados):**

| Columna | Nombre | Tipo | Requerido | Descripción | Ejemplo |
|---------|--------|------|-----------|-------------|---------|
| A | process_number | Texto | ✅ SÍ | Número de radicado (debe existir en hoja Procesos) | 76001333301320170009301 |
| B | subject_registration_id | Número | ✅ SÍ | ID de registro del sujeto desde la API | 789012 |
| C | subject_type | Texto | ✅ SÍ | Tipo de sujeto (Demandante, Demandado, Tercero, etc.) | Demandante |
| D | is_cited | Booleano | ✅ SÍ | Indica si el sujeto está citado (TRUE/FALSE) | TRUE |
| E | identification | Texto | ❌ NO | Número de identificación del sujeto | 1234567890 |
| F | name_or_business_name | Texto | ✅ SÍ | Nombre o razón social del sujeto | Juan Perez Garcia |

**Formato de la hoja:**
- Fila 1: Encabezados (en negrita, fondo amarillo claro)
- Fila 2 en adelante: Datos de ejemplo (3-5 filas de ejemplo con diferentes process_number)
- Validación de datos:
  - Columna A (process_number): Debe existir en hoja "Procesos"
  - Columna B (subject_registration_id): Solo números enteros, único
  - Columna C (subject_type): Lista desplegable: Demandante, Demandado, Tercero, Otro
  - Columna D (is_cited): Lista desplegable: TRUE, FALSE

---

### **HOJA 4: "Organizaciones"** (Nombre de la hoja: `Organizaciones`) - OPCIONAL

**Columnas en orden (fila 1 = encabezados):**

| Columna | Nombre | Tipo | Requerido | Descripción | Ejemplo |
|---------|--------|------|-----------|-------------|---------|
| A | process_number | Texto | ✅ SÍ | Número de radicado (debe existir en hoja Procesos) | 76001333301320170009301 |
| B | organization_id | UUID | ✅ SÍ | ID de la organización (UUID) | 550e8400-e29b-41d4-a716-446655440000 |
| C | interest_date | Fecha | ✅ SÍ | Fecha de interés (formato: YYYY-MM-DD) | 2025-01-15 |
| D | is_active | Booleano | ✅ SÍ | Indica si está activo (TRUE/FALSE) | TRUE |

**Formato de la hoja:**
- Fila 1: Encabezados (en negrita, fondo naranja claro)
- Fila 2 en adelante: Datos de ejemplo (2-3 filas de ejemplo)
- Validación de datos:
  - Columna A (process_number): Debe existir en hoja "Procesos"
  - Columna B (organization_id): Formato UUID
  - Columna C (interest_date): Formato fecha YYYY-MM-DD
  - Columna D (is_active): Lista desplegable: TRUE, FALSE

**Nota:** Esta hoja es opcional. Si no se incluye, el proceso se creará sin asociar a ninguna organización.

---

## 🎨 FORMATO Y ESTILO DEL EXCEL

### Estilo General:
- **Encabezados (Fila 1):**
  - Fondo de color según la hoja (azul, verde, amarillo, naranja)
  - Texto en negrita
  - Texto centrado
  - Altura de fila: 25px

- **Columnas:**
  - Ancho automático según contenido
  - Texto alineado a la izquierda (excepto fechas y números, centrados)

- **Validación de Datos:**
  - Aplicar validaciones donde se indique
  - Mensajes de error descriptivos en español

- **Protección:**
  - NO proteger celdas (debe ser editable)

### Ejemplos de Datos:

**Hoja Procesos (2-3 filas de ejemplo):**
```
76001333301320170009301 | 1234567890 | JUZGADO 035 CIVIL MUNICIPAL DE BOGOTÁ | BOGOTÁ | Ordinario | ACCION DE REPARACION DIRECTA | Sin Subclase | Juan Perez vs Empresa ABC | 2016-09-14 | 2025-08-05 | Despacho | Test content | FALSE | FALSE
```

**Hoja Actuaciones (3-5 filas de ejemplo):**
```
76001333301320170009301 | 123456 | 2025-01-15 | AUTO PARA ADMISIÓN DE DEMANDA | Se admite la demanda | 2025-01-01 | 2025-01-31 | 2025-01-15
76001333301320170009301 | 123457 | 2025-02-01 | CITACIÓN | Se cita a las partes | NULL | NULL | 2025-02-01
```

**Hoja Sujetos (3-5 filas de ejemplo):**
```
76001333301320170009301 | 789012 | Demandante | TRUE | 1234567890 | Juan Perez Garcia
76001333301320170009301 | 789013 | Demandado | TRUE | 9876543210 | Empresa ABC S.A.S.
```

**Hoja Organizaciones (2-3 filas de ejemplo):**
```
76001333301320170009301 | 550e8400-e29b-41d4-a716-446655440000 | 2025-01-15 | TRUE
```

---

## 📝 INSTRUCCIONES ADICIONALES

1. **Nombre del archivo:** `plantilla_importacion_procesos.xlsx`

2. **Idioma:** Todo en español (encabezados, validaciones, mensajes)

3. **Formato de fechas:** YYYY-MM-DD (ejemplo: 2025-01-15)

4. **Formato de booleanos:** TRUE o FALSE (en mayúsculas)

5. **Validaciones críticas:**
   - `process_number` debe tener exactamente 23 dígitos
   - `process_number` en hojas Actuaciones, Sujetos y Organizaciones debe existir en hoja Procesos
   - `action_registration_id` debe ser único
   - `subject_registration_id` debe ser único

6. **Notas en el Excel:**
   - Agregar una nota en la celda A1 de la hoja "Procesos" explicando que esta es la hoja principal
   - Agregar notas en las otras hojas indicando que el `process_number` debe existir en la hoja "Procesos"

---

## ✅ CHECKLIST FINAL

- [ ] 4 hojas creadas con los nombres exactos
- [ ] Encabezados en negrita con colores de fondo
- [ ] 2-3 filas de datos de ejemplo en cada hoja
- [ ] Validaciones de datos aplicadas
- [ ] Formato de fechas: YYYY-MM-DD
- [ ] Formato de booleanos: TRUE/FALSE
- [ ] Validación de process_number (23 dígitos)
- [ ] Validación de referencias entre hojas
- [ ] Todo en español
- [ ] Archivo guardado como .xlsx

---

**Genera el archivo Excel con todas estas especificaciones.**
