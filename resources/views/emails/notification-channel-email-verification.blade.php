@extends('emails.layouts.email')

@section('title', __('organization.notification_channel_email_verification_title'))

@section('content')
    <div style="margin-bottom: 25px;">
        <h1 style="font-size: 22px; font-weight: 700; color: #24163E; margin: 0 0 10px 0; text-align: center; line-height: 1.35;">
            {{ __('organization.notification_channel_email_verification_title') }}
        </h1>
        <p style="font-size: 15px; color: #6B7280; text-align: center; margin: 0; line-height: 1.6;">
            {{ __('organization.notification_channel_email_verification_intro') }}
        </p>
    </div>

    <div style="background-color: #F3F0F9; border: 1px solid #E5E7EB; border-radius: 10px; padding: 16px 18px; margin-bottom: 25px; text-align: center;">
        <p style="margin: 0 0 6px 0; font-size: 12px; color: #4B2A7D; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase;">
            {{ __('organization.notification_channel_email_verification_address_label') }}
        </p>
        <p style="margin: 0; font-size: 16px; color: #24163E; font-weight: 700; word-break: break-all;">
            {{ $email }}
        </p>
    </div>

    <div style="background-color: #FFFBEB; border-left: 4px solid #FBB03B; padding: 15px 20px; border-radius: 4px; margin-bottom: 28px;">
        <p style="font-size: 14px; color: #92400E; margin: 0; line-height: 1.6;">
            {{ __('organization.notification_channel_email_verification_alert') }}
        </p>
    </div>

    <div style="text-align: center; margin: 10px 0 30px;">
        <a href="{{ $confirmUrl }}" style="background-color: #4B2A7D; color: #FFFFFF; padding: 16px 36px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 16px; display: inline-block; box-shadow: 0 4px 10px rgba(75, 42, 125, 0.25);">
            {{ __('organization.notification_channel_email_verification_button') }}
        </a>
    </div>

    <p style="font-size: 14px; color: #4B5563; margin: 0 0 24px 0; line-height: 1.6;">
        {{ __('organization.notification_channel_email_verification_expire', ['hours' => $expiresInHours]) }}
        {{ __('organization.notification_channel_email_verification_ignore') }}
    </p>

    <div style="border-top: 1px solid #E5E7EB; padding-top: 25px;">
        <p style="font-size: 13px; color: #9CA3AF; margin-bottom: 10px;">
            {{ __('organization.notification_channel_email_verification_fallback') }}
        </p>
        <p style="font-size: 12px; word-break: break-all; color: #7C57B7; background-color: #F9FAFB; padding: 12px; border-radius: 6px; border: 1px solid #E5E7EB;">{{ $confirmUrl }}</p>
    </div>
@endsection
