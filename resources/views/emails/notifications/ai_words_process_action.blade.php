@extends('emails.layouts.email')

@section('title', 'ALERTA CRÍTICA: Proceso Requiere Atención')

@section('content')
    <div style="margin-bottom: 25px;">
        <h1 style="font-size: 20px; font-weight: 700; color: #B91C1C; margin: 0 0 8px 0; text-align: center;">⚠️ Alerta Crítica</h1>
        <p style="font-size: 14px; color: #6B7280; text-align: center; margin: 0;">Proceso Requiere Atención Inmediata</p>
    </div>

    {{-- Aviso urgente --}}
    <div style="background-color: #FEF2F2; border: 2px solid #FCA5A5; border-radius: 8px; padding: 20px; margin-bottom: 20px; text-align: center;">
        <h3 style="margin: 0 0 8px 0; color: #991B1B; font-size: 17px; font-weight: 700;">🚨 Atención Urgente Requerida</h3>
        <p style="margin: 0; color: #B91C1C; font-size: 14px;">Se han detectado palabras clave críticas en las actuaciones de este proceso que requieren su atención inmediata.</p>
    </div>

    <div style="background-color: #FEE2E2; border: 1px solid #FECACA; padding: 18px; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="margin: 0 0 10px 0; color: #991B1B; font-size: 16px; font-weight: 600;">⚠️ Palabras Clave Detectadas</h2>
        <p style="margin: 0; color: #B91C1C; font-size: 14px;">El sistema ha identificado actuaciones que contienen términos que requieren seguimiento especial.</p>
    </div>

    <div style="background-color: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 20px;">
        <p style="margin: 0 0 5px 0; font-size: 11px; color: #9CA3AF; text-transform: uppercase; font-weight: 600; letter-spacing: 1px;">Número de Radicado</p>
        <p style="margin: 0; font-size: 20px; color: #24163E; font-weight: 700; font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;">{{ $process['process_number'] }}</p>
    </div>

    @if(isset($additionalData['detected_words']) && count($additionalData['detected_words']) > 0)
        <div style="background-color: #FFFBEB; border: 1px solid #FDE68A; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px;">
            <h3 style="margin: 0 0 10px 0; color: #92400E; font-size: 14px; font-weight: 700;">🔍 Palabras Clave Encontradas:</h3>
            <div>
                @foreach($additionalData['detected_words'] as $word)
                    <span style="display: inline-block; background-color: #FEF3C7; border: 1px solid #FDE68A; color: #92400E; padding: 4px 10px; border-radius: 20px; margin: 3px 4px; font-size: 13px; font-weight: 600;">{{ $word }}</span>
                @endforeach
            </div>
        </div>
    @endif

    @if(isset($additionalData['actions_data']) && count($additionalData['actions_data']) > 0)
        <div style="background-color: #F9FAFB; border-radius: 8px; padding: 20px; margin-bottom: 20px; border: 1px solid #E5E7EB;">
            <h3 style="margin: 0 0 15px 0; color: #24163E; font-size: 15px; font-weight: 700;">
                📝 Actuaciones con Palabras Clave ({{ $additionalData['ai_words_actions_count'] ?? count($additionalData['actions_data']) }})
            </h3>

            @foreach($additionalData['actions_data'] as $action)
                <div style="background-color: #FFFFFF; padding: 15px; border-radius: 6px; margin-bottom: 10px; border: 1px solid #E5E7EB; border-left: 4px solid #DC2626;">
                    <p style="font-size: 13px; color: #6B7280; margin: 0 0 5px 0;">📅 {{ $action['action_date'] ?? 'Fecha no disponible' }}</p>
                    <p style="font-size: 15px; color: #1F2937; font-weight: 600; margin: 0;">{{ $action['action'] ?? 'Descripción no disponible' }}</p>
                    @if(isset($action['annotation']) && $action['annotation'])
                        <p style="font-size: 13px; color: #6B7280; margin: 6px 0 0 0; font-style: italic;">{{ $action['annotation'] }}</p>
                    @endif
                    @if(isset($action['detected_words']) && count($action['detected_words']) > 0)
                        <div style="background-color: #FFFBEB; padding: 8px 12px; border-radius: 4px; margin-top: 8px; font-size: 13px; color: #92400E; font-weight: 500;">
                            🔍 Palabras detectadas: {{ implode(', ', $action['detected_words']) }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div style="border-top: 1px solid #E5E7EB; padding-top: 20px;">
        <p style="font-size: 13px; color: #9CA3AF; margin: 0; text-align: center;">
            <strong>Importante:</strong> Este es un mensaje automático. No responda a este correo. Revise el proceso inmediatamente.
        </p>
        <p style="font-size: 12px; color: #B0B7C3; margin: 8px 0 0 0; text-align: center;">
            Notificación enviada el {{ \Src\Application\Shared\Helpers\DateFormatHelper::formatDateTime($additionalData['detected_at'] ?? now()) }}
        </p>
    </div>
@endsection
