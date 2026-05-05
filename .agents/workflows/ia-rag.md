---
description: 
---

Rol y Objetivo: Eres un Arquitecto de Software Experto en integraciones de Inteligencia Artificial, Laravel (PHP) y Bases de Datos Vectoriales/Grafos. Necesito que me diseñes la arquitectura de integración y el flujo de trabajo (Jobs) entre mi backend principal y mi microservicio de IA llamado rag-api.
Contexto de mi Proyecto (El Backend): He construido una plataforma LegalTech SaaS (estilo Monolegal) en Colombia. El sistema automatiza la vigilancia de procesos judiciales.
Los abogados (AppUsers) pertenecen a firmas (Organizations - Multi-tenant).
Registran procesos usando un número estricto de 23 dígitos.
Un CRON diario hace web scraping a la Rama Judicial, detecta nuevas "actuaciones" (movimientos), y lanza alertas si detecta palabras clave o inactividad.
Los enlaces a los documentos judiciales (PDFs, Word) son públicos, pero están protegidos por Cloudflare, por lo que quizas usaremos proxies residenciales y técnicas anti-bot para acceder a ellos o simplemente ser inteligentes descargan ciertos archivos por minuto.
Contexto del Microservicio de IA (rag-api): Tengo un microservicio independiente en Python llamado rag-api. No necesitas saber cómo procesa internamente la información, solo debes tratarlo como una "Caja Negra" con las siguientes características:
Motor: Está basado en LightRAG (usa tanto bases de datos vectoriales como bases de datos de grafos de conocimiento).
Multi-tenant: Soporta aislamiento total de datos por inquilino (tenant_id), lo cual encaja perfecto con el organization_id de mi backend.
Formatos Soportados: Puede ingerir de forma masiva o individual archivos PDF, Word, Excel, PPT, Imágenes (PNG, JPG), y texto plano o Markdown.
Endpoints de Ingesta: Cuenta con un endpoint para subir un solo documento (Upload Document) y otro para subida masiva (Batch Upload que soporta hasta 100 archivos por lote por cola asíncrona). Para textos planos o Markdown, hace inserción directa súper rápida saltándose el parser visual.
El Flujo de Trabajo que necesito que diseñes: Necesito diseñar la lógica de un Job en Laravel que se encargue de alimentar esta IA cada vez que se importe un proceso judicial nuevo (ej. por carga masiva de Excel) o cuando el scraper detecte una nueva actuación con documento adjunto. Ademas de los datos que se guarda en nuestra base de datos o los datos que retorna la api de la rama judicial.
Las reglas del flujo que debes utilizar para tu diseño son:
Descarga Segura: El Job debe usar nuestra infraestructura de proxies residenciales (o sin proxys) para saltar la seguridad de la Rama Judicial y descargar el archivo original temporalmente al disco de nuestro servidor backend.
Envío a rag-api: El Job debe enviar este archivo temporal (o el texto en Markdown si es un reporte de secretaría escrito) a los endpoints de ingesta del rag-api, asociándolo al tenant_id de la firma de abogados correspondiente.
Limpieza (Eficiencia): Una vez rag-api confirme la recepción/indexación, el backend debe eliminar el archivo físico local para no saturar el disco duro del servidor.
Persistencia de la URL: En nuestra base de datos relacional (MySQL), no guardaremos el archivo ni los vectores. Solo guardaremos la "URL pública original" del documento de la Rama Judicial, ya que sirve como referencia para que el usuario final lo consulte. rag-api se encargará de guardar los vectores (Qdrant) y los grafos (Memgraph) por su cuenta.
Tu Tarea: Basado en este contexto, por favor:
Diseña la arquitectura del flujo de datos (Paso a paso lógico del Job de Laravel).
Explícame cómo deberíamos estructurar el envío de la metadata a la base de datos vectorial de rag-api (ej. enviar el radicado de 23 dígitos, fecha, etc., como etiquetas).
Dame un seudocódigo o esqueleto de cómo se vería la clase del Job en Laravel manejando la descarga temporal con proxies, el envío HTTP al endpoint de rag-api, la eliminación del archivo y el registro de la URL en la base de datos.
Indícame qué precauciones tomar con los Timeouts o límites de cuota (Rate Limits) al hacer este proceso de forma masiva.