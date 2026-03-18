@extends('emails.layouts.email')

@section('title', 'Notificación Judicial')

@section('content')
    <div style="margin-bottom: 25px;">
        <h1 style="font-size: 20px; font-weight: 700; color: #24163E; margin: 0 0 8px 0; text-align: center;">📋 Notificación Judicial</h1>
        <p style="font-size: 14px; color: #6B7280; text-align: center; margin: 0;">Sistema de Monitoreo</p>
    </div>

    <div style="background-color: #D1ECF1; border: 1px solid #BEE5EB; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
        <h2 style="margin: 0 0 12px 0; color: #0C5460; font-size: 17px; font-weight: 600;">📋 Actualización de Proceso</h2>
        <p style="margin: 0; color: #0C5460; font-size: 15px;">Se ha detectado una actualización en el proceso judicial que está monitoreando.</p>
    </div>

    <div style="background-color: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 25px;">
        <p style="margin: 0 0 5px 0; font-size: 11px; color: #9CA3AF; text-transform: uppercase; font-weight: 600; letter-spacing: 1px;">Número de Radicado</p>
        <p style="margin: 0; font-size: 20px; color: #24163E; font-weight: 700; font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;">{{ $process['process_number'] ?? 'N/A' }}</p>
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
