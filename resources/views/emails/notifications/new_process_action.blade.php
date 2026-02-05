<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Actuación en Proceso</title>
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
        .info-message {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 25px;
        }
        .info-message h2 {
            margin: 0 0 15px 0;
            color: #0c5460;
            font-size: 18px;
            font-weight: 600;
        }
        .info-message p {
            margin: 0;
            color: #0c5460;
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
        .actions-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 25px;
        }
        .actions-section h3 {
            margin: 0 0 15px 0;
            color: #495057;
            font-size: 16px;
            font-weight: 600;
        }
        .action-item {
            background-color: white;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 10px;
            border-left: 4px solid #17804E;
        }
        .action-item:last-child {
            margin-bottom: 0;
        }
        .action-date {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        .action-description {
            font-size: 16px;
            color: #495057;
            font-weight: 500;
        }
        .action-annotation {
            font-size: 14px;
            color: #6c757d;
            margin-top: 5px;
            font-style: italic;
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
            <h1>📋 NUEVA ACTUACIÓN</h1>
            <p>Proceso Judicial Actualizado</p>
        </div>

        <div class="content">
            <div class="info-message">
                <h2>📋 Nueva Actuación Detectada</h2>
                <p>Se ha registrado una nueva actuación en el proceso judicial que está monitoreando.</p>
            </div>

            <div class="process-number">
                <strong>Radicado #{{ $process['process_number'] }}</strong>
            </div>

            @if(isset($additionalData['actions_data']) && count($additionalData['actions_data']) > 0)
                <div class="actions-section">
                    <h3>📝 Actuaciones Registradas ({{ $additionalData['new_actions_count'] ?? count($additionalData['actions_data']) }})</h3>
                    
                    @foreach($additionalData['actions_data'] as $action)
                        <div class="action-item">
                            <div class="action-date">
                                📅 {{ $action['action_date'] ?? 'Fecha no disponible' }}
                            </div>
                            <div class="action-description">
                                {{ $action['action'] ?? 'Descripción no disponible' }}
                            </div>
                            @if(isset($action['annotation']) && $action['annotation'])
                                <div class="action-annotation">
                                    {{ $action['annotation'] }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

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
