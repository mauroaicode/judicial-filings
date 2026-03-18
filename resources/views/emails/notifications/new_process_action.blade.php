@extends('emails.layouts.email')

@section('title', 'Nueva Actuación en Proceso')

@section('content')
    <div style="margin-bottom: 25px;">
        <h1 style="font-size: 20px; font-weight: 700; color: #24163E; margin: 0 0 8px 0; text-align: center;">📋 Nueva Actuación Detectada</h1>
        <p style="font-size: 14px; color: #6B7280; text-align: center; margin: 0;">Proceso Judicial Actualizado</p>
    </div>

    <div style="background-color: #D1ECF1; border: 1px solid #BEE5EB; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
        <h2 style="margin: 0 0 10px 0; color: #0C5460; font-size: 17px; font-weight: 600;">📋 Nueva Actuación Registrada</h2>
        <p style="margin: 0; color: #0C5460; font-size: 15px;">Se ha registrado una nueva actuación en el proceso judicial que está monitoreando.</p>
    </div>

    <div style="background-color: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 25px;">
        <p style="margin: 0 0 5px 0; font-size: 11px; color: #9CA3AF; text-transform: uppercase; font-weight: 600; letter-spacing: 1px;">Número de Radicado</p>
        <p style="margin: 0; font-size: 20px; color: #24163E; font-weight: 700; font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;">{{ $process['process_number'] }}</p>
    </div>

    @if(isset($additionalData['actions_data']) && count($additionalData['actions_data']) > 0)
        <div style="background-color: #F9FAFB; border-radius: 8px; padding: 20px; margin-bottom: 25px; border: 1px solid #E5E7EB;">
            <h3 style="margin: 0 0 15px 0; color: #24163E; font-size: 15px; font-weight: 700;">
                📝 Actuaciones Registradas ({{ $additionalData['new_actions_count'] ?? count($additionalData['actions_data']) }})
            </h3>

            @foreach($additionalData['actions_data'] as $action)
                <div style="background-color: #FFFFFF; padding: 15px; border-radius: 6px; margin-bottom: 10px; border-left: 4px solid #17804E; border: 1px solid #E5E7EB; border-left: 4px solid #17804E;">
                    <p style="font-size: 13px; color: #6B7280; margin: 0 0 5px 0;">📅 {{ $action['action_date'] ?? 'Fecha no disponible' }}</p>
                    <p style="font-size: 15px; color: #1F2937; font-weight: 600; margin: 0;">{{ $action['action'] ?? 'Descripción no disponible' }}</p>
                    @if(isset($action['annotation']) && $action['annotation'])
                        <p style="font-size: 13px; color: #6B7280; margin: 6px 0 0 0; font-style: italic;">{{ $action['annotation'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div style="border-top: 1px solid #E5E7EB; padding-top: 20px;">
        <p style="font-size: 13px; color: #9CA3AF; margin: 0; text-align: center;">
            <strong>Importante:</strong> Este es un mensaje automático del sistema de monitoreo judicial. No responda a este correo.
        </p>
        <p style="font-size: 12px; color: #B0B7C3; margin: 8px 0 0 0; text-align: center;">
            Notificación enviada el {{ \Src\Application\Shared\Helpers\DateFormatHelper::formatDateTime($additionalData['detected_at'] ?? now()) }}
        </p>
    </div>
@endsection
