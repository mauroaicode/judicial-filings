@extends('emails.layouts.email')

@section('title', __('auth.account_created_subject'))

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
    .credentials-section {
        background-color: #f7fafc;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        padding: 24px 24px;
        margin: 28px 0;
    }
    .credential-row { margin-bottom: 18px; }
    .credential-row:last-child { margin-bottom: 0; }
    .credential-label {
        font-size: 11px;
        color: #718096;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
    }
    .credential-value {
        font-size: 16px;
        font-weight: 500;
        color: #1a202c;
        font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
        word-break: break-all;
    }
    .password-value {
        font-size: 18px;
        font-weight: 600;
        color: #2d3748;
        letter-spacing: 2px;
        font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
        line-height: 1.3;
    }
    .notice-section {
        background-color: #f7fafc;
        border-left: 4px solid #2d3748;
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
    @media only screen and (max-width: 600px) {
        .title { font-size: 20px; }
        .password-value { font-size: 16px; letter-spacing: 1px; }
        .credentials-section { padding: 20px 18px; }
    }
</style>
@endpush

@section('content')
    <div class="title-section">
        <h1 class="title">{{ __('auth.account_created_subject') }}</h1>
        <p class="subtitle">Credenciales de acceso al sistema</p>
    </div>

    <div class="body-text">
        {{ __('auth.account_created_line_1') }}
    </div>

    <div class="credentials-section">
        <div class="credential-row">
            <div class="credential-label">{{ __('data.email') }}</div>
            <div class="credential-value">{{ $email }}</div>
        </div>
        <div class="credential-row">
            <div class="credential-label">{{ __('data.password') }}</div>
            <div class="password-value">{{ $temporaryPassword }}</div>
        </div>
    </div>

    <div class="notice-section">
        <p class="notice-text">
            {{ __('auth.account_created_warning') }}
        </p>
    </div>

    <div class="info-text">
        {{ __('auth.account_created_line_2') }}
    </div>
@endsection
