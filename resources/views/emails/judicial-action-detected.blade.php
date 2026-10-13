@php
    use Illuminate\Support\Str;
    use Src\Application\Shared\Helpers\DateFormatHelper;
@endphp
@extends('emails.layouts.email')

@php
    $emailTitle = match ($notificationType) {
        'actuacion_alerta' => __('process.alert_detected_title'),
        'actuacion_documento' => __('process.document_uploaded_title'),
        default => __('process.action_detected_title'),
    };
    $emailIntro = match ($notificationType) {
        'actuacion_alerta' => __('process.keyword_alert_intro'),
        'actuacion_documento' => __('process.document_uploaded_intro'),
        default => __('process.new_action_intro'),
    };
    $isDocument = $notificationType === 'actuacion_documento';
@endphp
@section('title', $emailTitle)

@section('content')
    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 22px; font-weight: 700; color: #24163E; margin: 0 0 10px 0;">
            {{ $emailTitle }}
        </h1>
        <p style="font-size: 15px; color: #6B7280; margin: 0;">
            {{ $emailIntro }}
        </p>
    </div>

    <!-- Process Info Card -->
    <div
        style="background-color: #FFFFFF; border-radius: 12px; border: 1px solid #E5E7EB; overflow: hidden; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
        <div style="background-color: #F3F0F9; padding: 12px 20px; border-bottom: 1px solid #E5E7EB;">
            <p style="margin: 0; color: #4B2A7D; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                Detalles del Proceso</p>
        </div>
        <div style="padding: 20px;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; font-size: 14px; color: #6B7280; width: 40%;">{{ __('process.process_number') }}
                        :
                    </td>
                    <td style="padding: 8px 0; font-size: 14px; color: #111827; font-weight: 600;">{{ $process->process_number }}</td>
                </tr>
                @if($isDocument && ! empty($originalFilename))
                    <tr>
                        <td style="padding: 8px 0; font-size: 14px; color: #6B7280;">{{ __('process.document_filename') }}:</td>
                        <td style="padding: 8px 0; font-size: 14px; color: #111827; font-weight: 600;">{{ $originalFilename }}</td>
                    </tr>
                @endif
                <tr>
                    <td style="padding: 8px 0; font-size: 14px; color: #6B7280;">{{ __('process.action_date') }}:</td>
                    <td style="padding: 8px 0; font-size: 14px; color: #111827;">{{ DateFormatHelper::formatDateWithDayOfWeek($action->action_date) }}</td>
                </tr>
                @if($action->start_date)
                    <tr>
                        <td style="padding: 8px 0; font-size: 14px; color: #6B7280;">{{ __('process.action_start_date') }}
                            :
                        </td>
                        <td style="padding: 8px 0; font-size: 14px; color: #111827;">{{ DateFormatHelper::formatDateWithDayOfWeek($action->start_date) }}</td>
                    </tr>
                @endif
                @if($action->end_date)
                    <tr>
                        <td style="padding: 8px 0; font-size: 14px; color: #6B7280;">{{ __('process.action_end_date') }}
                            :
                        </td>
                        <td style="padding: 8px 0; font-size: 14px; color: #111827;">{{ DateFormatHelper::formatDateWithDayOfWeek($action->end_date) }}</td>
                    </tr>
                @endif
                @if($notificationType === 'actuacion_alerta')
                    <tr>
                        <td style="padding: 8px 0; font-size: 14px; color: #EF4444; font-weight: 600;">Palabras Clave:
                        </td>
                        <td style="padding: 8px 0; font-size: 14px; color: #EF4444; font-weight: bold; background-color: #FEF2F2; padding-left: 10px; border-radius: 4px;">{{ $matchedKeywords ?? '---' }}</td>
                    </tr>
                @endif
            </table>
        </div>
    </div>

    <!-- Action Content -->
    <div style="margin-bottom: 30px;">
        <p style="font-size: 12px; color: #9CA3AF; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; margin-bottom: 10px;">{{ __('process.action_text') }}</p>
        <div
            style="background-color: #FFFFFF; border: 1px solid #E5E7EB; border-left: 4px solid #4B2A7D; padding: 20px; border-radius: 0 8px 8px 0; font-size: 15px; color: #374151; line-height: 1.6;">
            {{ Str::limit($action->action, 150) }}
        </div>
    </div>

    @if($action->annotation)
        <div style="margin-bottom: 35px;">
            <p style="font-size: 12px; color: #9CA3AF; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; margin-bottom: 10px;">{{ __('process.annotation_text') }}</p>
            <div
                style="background-color: #F9FAFB; border: 1px solid #E5E7EB; border-left: 4px solid {{ $notificationType === 'actuacion_alerta' ? '#EF4444' : '#9CA3AF' }}; padding: 18px; border-radius: 0 8px 8px 0; font-size: 14px; color: #4B5563; font-style: italic;">
                {{ Str::limit($action->annotation, 80) }}
            </div>
        </div>
    @endif

    <div style="text-align: center; margin-top: 40px;">
        <a href="{{ $ctaUrl }}"
           style="background-color: #24163E; color: #FFFFFF; padding: 16px 32px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 15px; display: inline-block;">
            {{ $ctaLabel }}
        </a>
    </div>
@endsection
