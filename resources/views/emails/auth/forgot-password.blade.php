@extends('emails.layouts.email')

@section('title', __('auth.forgot_password_subject'))

@push('styles')
<style>
    .title-section {
        margin-bottom: 28px;
        padding-bottom: 20px;
        border-bottom: 1px solid #e2e8f0;
    }
    .title {
        font-size: 22px;
        font-weight: 600;
        color: #1a202c;
        margin: 0 0 8px 0;
        line-height: 1.3;
        letter-spacing: 0.01em;
    }
    .subtitle {
        font-size: 14px;
        color: #718096;
        margin: 0;
        line-height: 1.5;
    }
    .body-text {
        font-size: 15px;
        color: #4a5568;
        line-height: 1.7;
        margin-bottom: 28px;
    }
    .action-section {
        text-align: center;
        margin: 32px 0;
    }
    .btn {
        display: inline-block;
        background-color: #2d3748;
        color: #ffffff !important;
        padding: 14px 32px;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 600;
        font-size: 15px;
        letter-spacing: 0.5px;
        transition: background-color 0.2s;
    }
    .notice-section {
        background-color: #f7fafc;
        border-left: 4px solid #718096;
        padding: 18px 20px;
        margin: 28px 0;
        border-radius: 0 4px 4px 0;
    }
    .notice-text {
        font-size: 14px;
        color: #4a5568;
        line-height: 1.6;
        margin: 0;
    }
    .info-text {
        font-size: 13px;
        color: #718096;
        line-height: 1.6;
        margin-top: 24px;
        padding-top: 24px;
        border-top: 1px solid #e2e8f0;
    }
</style>
@endpush

@section('content')
    <div class="title-section">
        <h1 class="title">{{ __('auth.forgot_password_subject') }}</h1>
        <p class="subtitle">Recuperación de acceso al sistema</p>
    </div>

    <div class="body-text">
        Hola, <strong>{{ $name }}</strong>.
        <br><br>
        Has solicitado restablecer tu contraseña. Haz clic en el siguiente botón para continuar con el proceso:
    </div>

    <div class="action-section">
        <a href="{{ $resetUrl }}" class="btn">Restablecer Contraseña</a>
    </div>

    <div class="notice-section">
        <p class="notice-text">
            Este enlace de recuperación expirará en {{ $expire }} minutos.
        </p>
    </div>

    <div class="notice-section" style="border-left-color: #e53e3e;">
        <p class="notice-text">
            Si no solicitaste este cambio, puedes ignorar este correo de forma segura. Tu contraseña seguirá siendo la misma.
        </p>
    </div>

    <div class="info-text">
        Si tienes problemas con el botón, copia y pega la siguiente URL en tu navegador:
        <br>
        <span style="word-break: break-all; color: #3182ce;">{{ $resetUrl }}</span>
    </div>
@endsection
