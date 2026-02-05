<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aviso: Proceso con Múltiples Instancias</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background-color: #17804E;
            color: white;
            padding: 25px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .header p {
            margin: 10px 0 0 0;
            font-size: 16px;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .alert-message {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 25px;
        }
        .alert-message h2 {
            margin: 0 0 15px 0;
            color: #856404;
            font-size: 18px;
            font-weight: 600;
        }
        .alert-message p {
            margin: 0;
            color: #856404;
            font-size: 16px;
        }
        .process-number {
            background-color: #e9ecef;
            padding: 20px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 25px;
        }
        .process-number strong {
            font-size: 20px;
            color: #495057;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 AVISO PROCESO JUDICIAL</h1>
            <p>Proceso con Múltiples Instancias Detectado</p>
        </div>

        <div class="content">
            <div class="alert-message">
                <h2>⚠️ Atención Requerida</h2>
                <p>Se ha detectado que el proceso judicial que está monitoreando tiene <strong>múltiples instancias</strong> en el sistema.</p>
            </div>

            <div class="process-number">
                <strong>Radicado #{{ $additionalData['filing_number'] }}</strong>
            </div>

            <p style="margin: 0; color: #6c757d; font-size: 14px;">
                <strong>Importante:</strong> Este es un mensaje automático del sistema de monitoreo judicial. 
                No responda a este correo.
            </p>
        </div>

        <div class="footer">
            <p><strong>Sistema de Monitoreo Judicial</strong></p>
            <p>Notificación enviada el {{ \Src\Application\Shared\Helpers\DateFormatHelper::formatDateTime($additionalData['detected_at'] ?? now()) }}</p>
        </div>
    </div>
</body>
</html>
