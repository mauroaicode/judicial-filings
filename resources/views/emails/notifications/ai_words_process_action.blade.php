<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALERTA CRÍTICA: Proceso Requiere Atención</title>
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
            background-color: #dc3545;
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
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 25px;
        }
        .alert-message h2 {
            margin: 0 0 15px 0;
            color: #721c24;
            font-size: 18px;
            font-weight: 600;
        }
        .alert-message p {
            margin: 0;
            color: #721c24;
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
        .detected-words {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
        }
        .detected-words h3 {
            margin: 0 0 10px 0;
            color: #856404;
            font-size: 16px;
            font-weight: 600;
        }
        .detected-words .words {
            color: #856404;
            font-size: 16px;
            font-weight: 500;
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
            border-left: 4px solid #dc3545;
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
        .detected-words-in-action {
            background-color: #fff3cd;
            padding: 8px 12px;
            border-radius: 4px;
            margin-top: 8px;
            font-size: 14px;
            color: #856404;
            font-weight: 500;
        }
        .urgent-notice {
            background-color: #f8d7da;
            border: 2px solid #dc3545;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            text-align: center;
        }
        .urgent-notice h3 {
            margin: 0 0 10px 0;
            color: #721c24;
            font-size: 18px;
            font-weight: 600;
        }
        .urgent-notice p {
            margin: 0;
            color: #721c24;
            font-size: 16px;
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
            <h1>⚠️ ALERTA CRÍTICA</h1>
            <p>Proceso Requiere Atención Inmediata</p>
        </div>

        <div class="content">
            <div class="urgent-notice">
                <h3>🚨 ATENCIÓN URGENTE REQUERIDA</h3>
                <p>Se han detectado palabras clave críticas en las actuaciones de este proceso que requieren su atención inmediata.</p>
            </div>

            <div class="alert-message">
                <h2>⚠️ Palabras Clave Detectadas</h2>
                <p>El sistema ha identificado actuaciones que contienen términos que requieren seguimiento especial.</p>
            </div>

            <div class="process-number">
                <strong>Radicado #{{ $process['process_number'] }}</strong>
            </div>

            @if(isset($additionalData['detected_words']) && count($additionalData['detected_words']) > 0)
                <div class="detected-words">
                    <h3>🔍 Palabras Clave Encontradas:</h3>
                    <div class="words">
                        @foreach($additionalData['detected_words'] as $word)
                            <span style="background-color: #fff3cd; padding: 4px 8px; border-radius: 4px; margin-right: 8px; font-weight: 600;">{{ $word }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(isset($additionalData['actions_data']) && count($additionalData['actions_data']) > 0)
                <div class="actions-section">
                    <h3>📝 Actuaciones con Palabras Clave ({{ $additionalData['ai_words_actions_count'] ?? count($additionalData['actions_data']) }})</h3>
                    
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
                            @if(isset($action['detected_words']) && count($action['detected_words']) > 0)
                                <div class="detected-words-in-action">
                                    🔍 Palabras detectadas: {{ implode(', ', $action['detected_words']) }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <p style="margin: 0; color: #6c757d; font-size: 14px;">
                <strong>Importante:</strong> Este es un mensaje automático del sistema de monitoreo judicial. 
                No responda a este correo. Revise el proceso inmediatamente.
            </p>
        </div>

        <div class="footer">
            <p><strong>Sistema de Monitoreo Judicial - Alerta Crítica</strong></p>
            <p>Notificación enviada el {{ \Src\Application\Shared\Helpers\DateFormatHelper::formatDateTime($additionalData['detected_at'] ?? now()) }}</p>
        </div>
    </div>
</body>
</html>
