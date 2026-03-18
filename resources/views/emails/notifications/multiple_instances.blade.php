@extends('emails.layouts.email')

@section('title', 'Aviso: Proceso con Múltiples Instancias')

@section('content')
    <div style="margin-bottom: 25px;">
        <h1 style="font-size: 20px; font-weight: 700; color: #24163E; margin: 0 0 8px 0; text-align: center;">⚠️ Aviso Proceso Judicial</h1>
        <p style="font-size: 14px; color: #6B7280; text-align: center; margin: 0;">Proceso con Múltiples Instancias Detectado</p>
    </div>

    <div style="background-color: #FFFBEB; border: 1px solid #FDE68A; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #FBB03B;">
        <h2 style="margin: 0 0 10px 0; color: #92400E; font-size: 17px; font-weight: 600;">⚠️ Atención Requerida</h2>
        <p style="margin: 0; color: #92400E; font-size: 15px;">Se ha detectado que el proceso judicial que está monitoreando tiene <strong>múltiples instancias</strong> en el sistema.</p>
    </div>

    <div style="background-color: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 25px;">
        <p style="margin: 0 0 5px 0; font-size: 11px; color: #9CA3AF; text-transform: uppercase; font-weight: 600; letter-spacing: 1px;">Número de Radicado</p>
        <p style="margin: 0; font-size: 20px; color: #24163E; font-weight: 700; font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;">{{ $additionalData['filing_number'] }}</p>
    </div>

    <div style="border-top: 1px solid #E5E7EB; padding-top: 20px;">
        <p style="font-size: 13px; color: #9CA3AF; margin: 0; text-align: center;">
            <strong>Importante:</strong> Este es un mensaje automático del sistema de monitoreo judicial. No responda a este correo.
        </p>
        <p style="font-size: 12px; color: #B0B7C3; margin: 8px 0 0 0; text-align: center;">
            Notificación enviada el {{ \Src\Application\Shared\Helpers\DateFormatHelper::formatDateTime($additionalData['detected_at'] ?? now()) }}
        </p>
    </div>
@endsection
