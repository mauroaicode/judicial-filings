@extends('emails.layouts.email')

@section('title', __('process.became_private_title'))

@section('content')
    <div style="margin-bottom: 25px;">
        <h1 style="font-size: 20px; font-weight: 700; color: #24163E; margin: 0 0 10px 0; line-height: 1.35;">
            {{ __('process.became_private_title') }}
        </h1>
        <p style="font-size: 14px; color: #6B7280; margin: 0; line-height: 1.6;">
            {{ __('process.became_private_intro') }}
        </p>
    </div>

    <div style="background-color: #F3F0F9; border: 1px solid #E5E7EB; border-radius: 10px; padding: 16px 18px; margin-bottom: 25px;">
        <p style="margin: 0; font-size: 14px; color: #24163E; font-weight: 600; line-height: 1.5;">
            {{ __('process.became_private_alert') }}
        </p>
    </div>

    <div style="background-color: #FFFFFF; border-radius: 12px; border: 1px solid #E5E7EB; overflow: hidden; margin-bottom: 25px;">
        <div style="background-color: #F3F0F9; padding: 12px 20px; border-bottom: 1px solid #E5E7EB;">
            <p style="margin: 0; color: #4B2A7D; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                {{ __('process.process_details') }}
            </p>
        </div>
        <div style="padding: 20px;">
            <table role="presentation" style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; font-size: 14px; color: #6B7280; width: 40%; vertical-align: top;">
                        {{ __('process.process_number') }}:
                    </td>
                    <td style="padding: 8px 0; font-size: 14px; color: #111827; font-weight: 600; word-break: break-all;">
                        {{ $process->process_number }}
                    </td>
                </tr>
                @if($process->court)
                    <tr>
                        <td style="padding: 8px 0; font-size: 14px; color: #6B7280; vertical-align: top;">
                            {{ __('process.consolidated_notifications_court') }}:
                        </td>
                        <td style="padding: 8px 0; font-size: 14px; color: #111827;">
                            {{ $process->court }}
                        </td>
                    </tr>
                @endif
                @if($process->process_class)
                    <tr>
                        <td style="padding: 8px 0; font-size: 14px; color: #6B7280; vertical-align: top;">
                            {{ __('process.process_class_label') }}:
                        </td>
                        <td style="padding: 8px 0; font-size: 14px; color: #111827;">
                            {{ $process->process_class }}
                        </td>
                    </tr>
                @endif
                @if($process->became_private_at)
                    <tr>
                        <td style="padding: 8px 0; font-size: 14px; color: #6B7280; vertical-align: top;">
                            {{ __('process.became_private_at') }}:
                        </td>
                        <td style="padding: 8px 0; font-size: 14px; color: #24163E; font-weight: 600;">
                            {{ ucfirst($process->became_private_at->locale(app()->getLocale())->isoFormat('dddd D [de] MMMM [de] YYYY')) }},
                            {{ $process->became_private_at->locale(app()->getLocale())->isoFormat('h:mm a') }}
                        </td>
                    </tr>
                @endif
            </table>
        </div>
    </div>

    <div style="margin-bottom: 30px;">
        <p style="font-size: 12px; color: #9CA3AF; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; margin: 0 0 10px 0;">
            {{ __('process.became_private_what_happens_title') }}
        </p>
        <div style="background-color: #FFFFFF; border: 1px solid #E5E7EB; border-left: 4px solid #4B2A7D; padding: 18px 20px; border-radius: 0 8px 8px 0; font-size: 14px; color: #374151; line-height: 1.6;">
            {{ __('process.became_private_what_happens_body') }}
        </div>
    </div>

    <div style="text-align: center; margin-top: 35px;">
        <a href="{{ rtrim(config('app.frontend_url'), '/') }}/gestion-procesos/{{ $process->id }}"
           style="background-color: #24163E; color: #FFFFFF; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
            {{ __('process.view_process') }}
        </a>
    </div>
@endsection
