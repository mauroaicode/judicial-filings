@extends('emails.layouts.email')

@section('title', __('auth.forgot_password_title'))

@section('content')
    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 24px; font-weight: 700; color: #24163E; margin: 0 0 10px 0; text-align: center;">{{ __('auth.forgot_password_title') }}</h1>
        <p style="font-size: 15px; color: #6B7280; text-align: center; margin: 0;">Recuperación de acceso al sistema</p>
    </div>

    <div style="background-color: #F9FAFB; border-radius: 8px; padding: 25px; border: 1px dashed #D1D5DB; margin-bottom: 30px;">
        <p style="font-size: 16px; color: #1F2937; margin: 0;">
            Hola <strong>{{ $name }}</strong>,
        </p>
        <p style="font-size: 15px; color: #4B5563; margin-top: 15px; line-height: 1.6;">
            {{ __('auth.forgot_password_intro') }}
        </p>
    </div>

    <div style="text-align: center; margin: 35px 0;">
        <a href="{{ $resetUrl }}" style="background-color: #4B2A7D; color: #FFFFFF; padding: 16px 36px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 16px; display: inline-block; box-shadow: 0 4px 10px rgba(75, 42, 125, 0.25);">
            Restablecer Contraseña
        </a>
    </div>

    <div style="background-color: #FFFBEB; border-left: 4px solid #FBB03B; padding: 15px 20px; border-radius: 4px; margin-bottom: 30px;">
        <p style="font-size: 14px; color: #92400E; margin: 0;">
            <strong>Nota:</strong> Este enlace de recuperación expirará en <strong>{{ $expire }} minutos</strong> por motivos de seguridad.
        </p>
    </div>

    <div style="border-top: 1px solid #E5E7EB; padding-top: 25px;">
        <p style="font-size: 13px; color: #9CA3AF; margin-bottom: 10px;">Si tienes problemas al hacer clic en el botón, copia y pega el siguiente enlace en tu navegador:</p>
        <p style="font-size: 12px; word-break: break-all; color: #7C57B7; background-color: #F9FAFB; padding: 12px; border-radius: 6px; border: 1px solid #E5E7EB;">{{ $resetUrl }}</p>
    </div>
@endsection
