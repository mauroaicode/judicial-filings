# Sistema de Colas de Notificaciones por Canal

## 📋 **Resumen**

El sistema ahora utiliza colas separadas para cada tipo de canal de notificación, permitiendo mejor gestión, monitoreo y optimización de recursos.

## 🔧 **Configuración de Colas**

### **Colas Disponibles:**

1. **`notifications-email`** - Notificaciones por email
   - Delay: 60 segundos
   - Max attempts: 5
   - Timeout: 120 segundos
   - Retry after: 300 segundos

2. **`notifications-whatsapp`** - Notificaciones por WhatsApp
   - Delay: 30 segundos
   - Max attempts: 3
   - Timeout: 60 segundos
   - Retry after: 180 segundos

3. **`notifications-sms`** - Notificaciones por SMS
   - Delay: 45 segundos
   - Max attempts: 3
   - Timeout: 60 segundos
   - Retry after: 180 segundos

4. **`notifications-internal`** - Notificaciones internas
   - Delay: 10 segundos
   - Max attempts: 2
   - Timeout: 30 segundos
   - Retry after: 60 segundos

## 📝 **Logs Separados**

Cada canal tiene su propio archivo de log:

- `storage/logs/notifications-email.log`
- `storage/logs/notifications-whatsapp.log`
- `storage/logs/notifications-sms.log`
- `storage/logs/notifications-internal.log`

## 🚀 **Comandos de Uso**

### **Procesar Colas Específicas:**
```bash
# Procesar todas las colas con configuración por defecto
php artisan notifications:process-queues

# Procesar con configuración personalizada
php artisan notifications:process-queues --email-workers=3 --whatsapp-workers=2 --sms-workers=1 --internal-workers=2

# Procesar solo email y WhatsApp
php artisan notifications:process-queues --email-workers=2 --whatsapp-workers=1 --sms-workers=0 --internal-workers=0
```

### **Monitorear Colas:**
```bash
# Monitorear todas las colas
php artisan notifications:monitor-queues

# Monitorear colas específicas
php artisan notifications:monitor-queues --channels=email,whatsapp

# Monitorear con intervalo personalizado
php artisan notifications:monitor-queues --interval=10
```

### **Procesar Colas Individuales:**
```bash
# Email
php artisan queue:work --queue=notifications-email --timeout=120 --tries=5 --delay=60

# WhatsApp
php artisan queue:work --queue=notifications-whatsapp --timeout=60 --tries=3 --delay=30

# SMS
php artisan queue:work --queue=notifications-sms --timeout=60 --tries=3 --delay=45

# Internal
php artisan queue:work --queue=notifications-internal --timeout=30 --tries=2 --delay=10
```

## 📊 **Ventajas del Sistema**

### **1. Gestión Optimizada:**
- Cada canal tiene su propia configuración de timeouts y reintentos
- Mejor control de rate limiting por tipo de canal
- Procesamiento paralelo independiente

### **2. Monitoreo Específico:**
- Logs separados por canal
- Métricas individuales por tipo de notificación
- Debugging más eficiente

### **3. Escalabilidad:**
- Workers independientes por canal
- Configuración flexible de recursos
- Mejor distribución de carga

### **4. Rate Limiting:**
- Delays específicos por canal
- Configuración de reintentos personalizada
- Manejo independiente de errores

## 🔍 **Monitoreo y Debugging**

### **Verificar Estado de Colas:**
```bash
# Ver jobs pendientes por cola
php artisan queue:monitor

# Ver logs específicos
tail -f storage/logs/notifications-email.log
tail -f storage/logs/notifications-whatsapp.log
```

### **Métricas por Canal:**
- **Email**: Mayor delay, más reintentos, timeout largo
- **WhatsApp**: Configuración moderada
- **SMS**: Configuración moderada
- **Internal**: Procesamiento rápido

## 🚨 **Troubleshooting**

### **Problemas Comunes:**

1. **Rate Limiting en Email:**
   - Aumentar delay en configuración
   - Reducir número de workers
   - Verificar límites del proveedor

2. **Colas Saturadas:**
   - Aumentar workers para el canal específico
   - Verificar configuración de timeout
   - Revisar logs de errores

3. **Jobs Fallidos:**
   - Verificar logs específicos del canal
   - Revisar configuración de reintentos
   - Verificar conectividad con servicios externos

## 📈 **Optimización**

### **Configuración Recomendada por Entorno:**

**Desarrollo:**
- Email: 1 worker
- WhatsApp: 1 worker
- SMS: 1 worker
- Internal: 1 worker

**Producción:**
- Email: 2-3 workers
- WhatsApp: 1-2 workers
- SMS: 1-2 workers
- Internal: 2-3 workers

**Alto Volumen:**
- Email: 3-5 workers
- WhatsApp: 2-3 workers
- SMS: 2-3 workers
- Internal: 3-5 workers
