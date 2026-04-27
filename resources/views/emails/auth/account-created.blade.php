@extends('emails.layouts.email')

@section('title', __('auth.account_created_subject'))

@section('content')
    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 24px; font-weight: 700; color: #24163E; margin: 0 0 10px 0; text-align: center;">{{ __('auth.account_created_title') }}</h1>
        <p style="font-size: 15px; color: #6B7280; text-align: center; margin: 0;">¡Bienvenido a la plataforma experto!</p>
    </div>

    <div style="margin-bottom: 30px;">
        <p style="font-size: 16px; color: #1F2937; margin: 0; line-height: 1.6;">
            {{ __('auth.account_created_line_1') }}
        </p>
    </div>

    <!-- Credentials Card -->
    <div style="background-color: #F9FAFB; border-radius: 12px; border: 1px solid #E5E7EB; overflow: hidden; margin-bottom: 30px;">
        <div style="background-color: #4B2A7D; padding: 12px 20px;">
            <p style="margin: 0; color: #FFFFFF; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Tus credenciales de acceso</p>
        </div>
        <div style="padding: 25px;">
            <div style="margin-bottom: 20px;">
                <p style="margin: 0 0 5px 0; font-size: 12px; color: #9CA3AF; text-transform: uppercase; font-weight: 600;">{{ __('data.identification') }}</p>
                <p style="margin: 0; font-size: 16px; color: #1F2937; font-family: 'SF Mono', 'Monaco', 'Consolas', monospace; font-weight: 500;">{{ $identification }}</p>
            </div>
            <div>
                <p style="margin: 0 0 5px 0; font-size: 12px; color: #9CA3AF; text-transform: uppercase; font-weight: 600;">{{ __('data.password') }}</p>
                <p style="margin: 0; font-size: 20px; color: #24163E; font-family: 'SF Mono', 'Monaco', 'Consolas', monospace; font-weight: 700; letter-spacing: 1px;">{{ $temporaryPassword }}</p>
            </div>
        </div>
    </div>

    <div style="background-color: #ECFDF5; border-left: 4px solid #10B981; padding: 15px 20px; border-radius: 4px; margin-bottom: 30px;">
        <p style="font-size: 14px; color: #065F46; margin: 0;">
            <strong>Seguridad:</strong> {{ __('auth.account_created_warning') }}
        </p>
    </div>

    <div style="text-align: center; margin: 40px 0;">
        <a href="{{ config('app.frontend_url')}}/sign-in" style="background-color: #24163E; color: #FFFFFF; padding: 16px 40px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 16px; display: inline-block;">
            Ingresar al Sistema
        </a>
    </div>

    <div style="border-top: 1px solid #E5E7EB; padding-top: 25px;">
        <p style="font-size: 14px; color: #6B7280; line-height: 1.6; margin: 0;">
            {{ __('auth.account_created_line_2') }}
        </p>
    </div>
@endsection
