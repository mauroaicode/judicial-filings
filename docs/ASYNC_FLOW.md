# Documentación de Flujo Asíncrono e Integración de IA

Este documento describe la arquitectura de colas, comunicación en tiempo real y el flujo de procesamiento de procesos judiciales en la plataforma.

## 🛠 Comandos de Infraestructura

Para que el sistema funcione correctamente, se deben ejecutar los siguientes servicios en segundo plano:

| Servicio | Comando | Descripción |
| :--- | :--- | :--- |
| **Sincronización Judicial** | `php artisan queue:work --queue=judicial-sync` | Procesa la importación inicial del radicado desde la API de la Rama Judicial. |
| **Resumen de IA** | `php artisan queue:work --queue=process-ai` | Envía los datos al motor RAG y genera el resumen ejecutivo. |
| **Notificaciones** | `php artisan queue:work --queue=notifications` | Gestiona el envío de notificaciones (Base de Datos y WebSockets). |
| **WebSockets (Reverb)** | `php artisan reverb:start` | Habilita la comunicación en tiempo real con el frontend. |

---

## ⚙️ Variables de Entorno (.env)

Configuraciones necesarias para el motor de IA y WebSockets:

### IA RAG Integration
* `IA_RAG_ENABLED`: (true/false) Define si se lanza el Job de resumen al finalizar la importación.
* `IA_RAG_BASE_URL`: URL del servidor de IA (ej: `http://localhost:8000`).
* `IA_RAG_TIMEOUT`: Tiempo de espera (en segundos) para peticiones HTTP (recomendado: `120`).
* `IA_RAG_TASK_MAX_ATTEMPTS`: Número máximo de reintentos al consultar estado de indexación.
* `IA_RAG_TASK_RETRY_DELAY`: Segundos de espera entre consultas de estado.
* `IA_RAG_SUMMARY_PROMPT`: Prompt personalizado para la generación del resumen.

### Colas (Queues)
* `IA_RAG_SYNC_QUEUE`: Nombre de la cola para importación judicial (ej: `process-sync` o `judicial-sync`).
* `IA_RAG_AI_QUEUE`: Nombre de la cola para procesamiento de IA (ej: `process-ai`).

### WebSockets (Reverb)
* `REVERB_HOST`: Host del servidor de websockets (ej: `127.0.0.1`).
* `REVERB_PORT`: Puerto (ej: `8080` u `8081`).

---

## 🚀 Flujo de Registro de un Radicado

### 1. Entrada y Validación
Cuando un usuario registra un radicado de **23 dígitos**:
- El sistema valida el formato y crea un registro en `process_registration_logs` con estado `pending`.
- Se responde de inmediato al usuario indicando que el proceso ha sido encolado.

### 2. Sincronización Judicial (`judicial-sync`)
El Job `SyncJudicialBranchJob` se encarga de:
- **Consulta API**: Solicita a la Rama Judicial la información histórica.
- **Creación de Datos**: Registra el proceso, todas sus **Actuaciones** y sus **Sujetos Procesales**.
- **Notificación**: Al terminar, envía la alerta `ProcessDataImportedNotification` (vía WebSocket/DB).
- **Disparo de IA**: Al finalizar con éxito, encola automáticamente el siguiente paso para el resumen.

### 3. Generación de Resumen IA (`process-ai`)
El Job `GenerateProcessAiSummaryJob` se encarga de:
- **Markdown**: Convierte toda la información (info básica + sujetos + actuaciones) en un archivo Markdown estructurado.
- **RAG Engine**: Sube el contenido al motor de IA y espera la indexación.
- **Prompting**: Solicita el resumen ejecutivo a la IA.
- **Persistencia**: Guarda el resultado en el campo `ai_summary` del proceso.
- **Notificación**: Envía la alerta `ProcessAiSummaryReadyNotification`.

---

## 📂 Manejo de Doble Instancia (Múltiples Procesos)

Es común que un solo radicado de 23 dígitos devuelva múltiples "IDs de Proceso" en la API (por ejemplo, Primera y Segunda Instancia).

- **Detección**: El servicio identifica si el array de la API contiene más de un registro.
- **Múltiples Registros**: El sistema crea un registro independiente en la tabla `processes` por cada instancia encontrada.
- **Marcado**: Se marca el campo `has_multiple_instances = true` en cada uno para que el usuario sepa que hay otros registros relacionados con el mismo número.
- **Vinculación**: Todos los procesos encontrados se vinculan automáticamente a la organización del usuario solicitante.

---

## 🔔 Notificaciones en Tiempo Real
Gracias a **Laravel Reverb**, el usuario recibe actualizaciones visuales instantáneas en su panel sin necesidad de refrescar la página, cubriendo los estados:
1. "Importando datos..."
2. "¡Datos importados con éxito!"
3. "IA generando resumen..."
4. "¡Resumen listo para leer!"
