@extends('emails.layouts.email')

@section('title', $title)

@section('content')
    <div style="margin-bottom: 25px;">
        <h1 style="font-size: 20px; font-weight: 700; color: #24163E; margin: 0 0 10px 0; line-height: 1.35;">
            {{ $title }}
        </h1>
        <p style="font-size: 14px; color: #6B7280; margin: 0; line-height: 1.6;">
            {{ __('organization.status_email_greeting', ['name' => $userName]) }}
        </p>
    </div>

    <div style="margin-bottom: 25px;">
        <p style="font-size: 15px; color: #1F2937; margin: 0; line-height: 1.6;">
            {{ $intro }}
        </p>
    </div>

    <div style="background-color: #FFFBEB; border: 1px solid #FDE68A; border-radius: 10px; padding: 16px 18px; margin-bottom: 25px;">
        <p style="margin: 0; font-size: 14px; color: #92400E; font-weight: 600; line-height: 1.5;">
            {{ $alert }}
        </p>
    </div>

    <div style="background-color: #FFFFFF; border-radius: 12px; border: 1px solid #E5E7EB; overflow: hidden; margin-bottom: 25px;">
        <div style="background-color: #F3F0F9; padding: 12px 20px; border-bottom: 1px solid #E5E7EB;">
            <p style="margin: 0; color: #4B2A7D; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                {{ __('organization.status_email_details_title') }}
            </p>
        </div>
        <div style="padding: 20px;">
            <table role="presentation" style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; font-size: 14px; color: #6B7280; width: 40%; vertical-align: top;">
                        {{ __('organization.status_email_organization_label') }}:
                    </td>
                    <td style="padding: 8px 0; font-size: 14px; color: #111827; font-weight: 600;">
                        {{ $organization->name }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-size: 14px; color: #6B7280; vertical-align: top;">
                        {{ __('organization.status_email_status_label') }}:
                    </td>
                    <td style="padding: 8px 0; font-size: 14px; color: #111827;">
                        {{ $statusValue }}
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div style="margin-bottom: 30px;">
        <p style="font-size: 12px; color: #9CA3AF; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; margin: 0 0 10px 0;">
            {{ __('organization.status_email_next_title') }}
        </p>
        <div style="background-color: #FFFFFF; border: 1px solid #E5E7EB; border-left: 4px solid #4B2A7D; padding: 18px 20px; border-radius: 0 8px 8px 0; font-size: 14px; color: #374151; line-height: 1.6;">
            {{ $nextBody }}
        </div>
    </div>

    @if(! empty($supportEmail))
        <div style="background-color: #F9FAFB; border-radius: 10px; border: 1px solid #E5E7EB; padding: 18px 20px; margin-bottom: 10px; text-align: center;">
            <p style="margin: 0 0 8px 0; font-size: 13px; color: #6B7280;">
                {{ __('organization.status_email_support_label') }}
            </p>
            <a href="mailto:{{ $supportEmail }}"
               style="color: #4B2A7D; font-size: 15px; font-weight: 600; text-decoration: none;">
                {{ $supportEmail }}
            </a>
        </div>
    @endif

    <div style="border-top: 1px solid #E5E7EB; padding-top: 25px; margin-top: 25px;">
        <p style="font-size: 14px; color: #6B7280; line-height: 1.6; margin: 0;">
            {{ $closing }}
        </p>
    </div>
@endsection
