@php
    use Illuminate\Support\Str;

    $dateString = now()->format('Y-m-d');
    $headerKey = $totalProcessesCount > 1
        ? 'process.consolidated_notifications_header_plural'
        : 'process.consolidated_notifications_header_singular';
@endphp
@extends('emails.layouts.email')
@section('max_width', '1200px')
@section('content_padding', '32px 16px')
@section('title', __('process.consolidated_notifications_title'))

@section('styles')
    <style type="text/css">
        .consolidated-table-clip {
            display: block !important;
            width: 100% !important;
            max-width: 100% !important;
            overflow: hidden !important;
            box-sizing: border-box !important;
        }

        .consolidated-table-scroll {
            display: block !important;
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            -webkit-overflow-scrolling: touch !important;
            box-sizing: border-box !important;
        }

        .consolidated-table {
            width: 100% !important;
            border-collapse: collapse;
            font-family: sans-serif;
            font-size: 12px;
            border: 1px solid #E5E7EB;
            table-layout: auto;
        }

        .consolidated-radicacion {
            word-break: break-all;
            overflow-wrap: break-word;
            white-space: normal !important;
        }

        .consolidated-header {
            word-break: break-word !important;
            overflow-wrap: break-word !important;
            hyphens: auto;
        }

        @media only screen and (max-width: 640px) {
            .consolidated-scroll-hint {
                display: block !important;
            }

            .email-content-pad {
                padding: 24px 12px !important;
            }

            .consolidated-table-scroll {
                overflow-x: scroll !important;
            }

            .consolidated-table {
                min-width: 900px !important;
            }
        }
    </style>
@endsection

@section('content')
    <div style="margin-bottom: 25px; max-width: 100%; overflow: hidden;">
        <h1 class="consolidated-header" style="font-size: 20px; font-weight: 700; color: #24163E; margin: 0 0 10px 0; word-break: break-word; overflow-wrap: break-word;">
            {{ __($headerKey, ['date' => $dateString]) }}
        </h1>
        <p style="font-size: 14px; color: #6B7280; margin: 0 0 16px 0;">
            {{ __('process.consolidated_notifications_intro') }}
        </p>

        <div style="background-color: #F3F0F9; border: 1px solid #E5E7EB; border-radius: 10px; padding: 16px 18px; margin-bottom: 16px;">
            <p style="margin: 0; font-size: 15px; color: #24163E; font-weight: 700;">
                {{ trans_choice('process.consolidated_notifications_summary_actions', $totalActionsCount, ['count' => $totalActionsCount]) }}
            </p>
            <p style="margin: 6px 0 0 0; font-size: 13px; color: #6B7280;">
                {{ trans_choice('process.consolidated_notifications_summary_processes', $totalProcessesCount, ['count' => $totalProcessesCount]) }}
                @if($alertsCount > 0)
                    · {{ trans_choice('process.consolidated_notifications_summary_alerts', $alertsCount, ['count' => $alertsCount]) }}
                @endif
            </p>
        </div>

        <p class="consolidated-scroll-hint" style="display: none; margin: 0 0 10px 0; font-size: 12px; color: #9CA3AF; font-style: italic;">
            {{ __('process.consolidated_notifications_scroll_hint') }}
        </p>
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; margin-bottom: 30px; width: 100%; max-width: 100%;">
        <tr>
            <td style="padding: 0; width: 100%; max-width: 100%; overflow: hidden;">
                <!--[if mso]>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td>
                <![endif]-->
                <div class="consolidated-table-clip" style="display: block; width: 100%; max-width: 100%; overflow: hidden; box-sizing: border-box;">
                    <div class="consolidated-table-scroll" style="display: block; width: 100%; max-width: 100%; overflow-x: auto; overflow-y: hidden; -webkit-overflow-scrolling: touch; box-sizing: border-box;">
                        <table class="consolidated-table" role="presentation" style="width: 100%; border-collapse: collapse; font-family: sans-serif; font-size: 12px; border: 1px solid #E5E7EB;">
                            <thead>
                            <tr style="background-color: #F3F4F6; text-align: left; border-bottom: 2px solid #E5E7EB;">
                                <th style="padding: 10px; border: 1px solid #E5E7EB; white-space: nowrap;">Juzgado</th>
                                <th style="padding: 10px; border: 1px solid #E5E7EB; white-space: nowrap;">Radicación</th>
                                <th style="padding: 10px; border: 1px solid #E5E7EB; white-space: nowrap;">Demandante</th>
                                <th style="padding: 10px; border: 1px solid #E5E7EB; white-space: nowrap;">Demandado</th>
                                <th style="padding: 10px; border: 1px solid #E5E7EB; white-space: nowrap;">Fecha Actuación</th>
                                <th style="padding: 10px; border: 1px solid #E5E7EB; white-space: nowrap;">Actuación</th>
                                <th style="padding: 10px; border: 1px solid #E5E7EB; white-space: nowrap;">Anotación</th>
                                <th style="padding: 10px; border: 1px solid #E5E7EB; white-space: nowrap;">Inicia</th>
                                <th style="padding: 10px; border: 1px solid #E5E7EB; white-space: nowrap;">Finaliza</th>
                                <th style="padding: 10px; border: 1px solid #E5E7EB; white-space: nowrap;">Registro</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($displayedRows as $row)
                                <tr style="border-bottom: 1px solid #E5E7EB; vertical-align: middle; {{ ($row['is_alert'] ?? false) ? 'background-color: #FEF2F2;' : '' }}">
                                    <td style="padding: 8px; border: 1px solid #E5E7EB; text-align: justify;">
                                        <div style="line-height: 1.4;">{{ Str::limit($row['court'], 45) }}</div>
                                    </td>
                                    <td class="consolidated-radicacion" style="padding: 8px; border: 1px solid #E5E7EB; text-align: center; word-break: break-all; overflow-wrap: break-word; white-space: normal;">
                                        {{ $row['process_number'] }}
                                    </td>
                                    <td style="padding: 8px; border: 1px solid #E5E7EB; text-align: justify;">
                                        <div style="line-height: 1.4;">{{ Str::limit($row['demandante'], 40) }}</div>
                                    </td>
                                    <td style="padding: 8px; border: 1px solid #E5E7EB; text-align: justify;">
                                        <div style="line-height: 1.4;">{{ Str::limit($row['demandado'], 40) }}</div>
                                    </td>
                                    <td style="padding: 8px; border: 1px solid #E5E7EB; text-align: center; white-space: nowrap;">{{ $row['action_date'] }}</td>
                                    <td style="padding: 8px; border: 1px solid #E5E7EB; text-align: justify;">
                                        <div style="line-height: 1.4;">
                                            @if(!empty($row['is_merged']))
                                                <div style="margin-bottom: 5px;">
                                                    <span style="background-color: #F3E8FF; color: #6B21A8; font-size: 9px; padding: 2px 4px; border-radius: 3px; font-weight: 800; text-transform: uppercase;">{{ __('process.consolidated_notifications_fijacion') }}</span>
                                                    <span style="display: block; margin-top: 2px;">{{ Str::limit($row['action_text'], 55) }}</span>
                                                </div>
                                                <div>
                                                    <span style="background-color: #FFF7ED; color: #C2410C; font-size: 9px; padding: 2px 4px; border-radius: 3px; font-weight: 800; text-transform: uppercase;">{{ __('process.consolidated_notifications_auto') }}</span>
                                                    <span style="display: block; margin-top: 2px; font-weight: 600;">{{ Str::limit($row['linked_action_text'] ?? '', 55) }}</span>
                                                </div>
                                            @else
                                                {{ Str::limit($row['action_text'], 55) }}
                                            @endif
                                        </div>
                                    </td>
                                    <td style="padding: 8px; border: 1px solid #E5E7EB; text-align: justify;">
                                        <div style="line-height: 1.4;">
                                            @php
                                                $annotation = $row['annotation'] ?? '---';
                                                if (!empty($row['is_merged']) && ($annotation === '---' || empty($annotation))) {
                                                    $annotation = $row['linked_annotation'] ?? '---';
                                                }
                                            @endphp
                                            {{ Str::limit($annotation, 60) }}
                                            @if(!empty($row['is_alert']) && !empty($row['matched_keywords']))
                                                <div style="font-size: 10px; margin-top: 4px; color: #DC2626; font-style: italic; font-weight: 600;">
                                                    {{ __('process.consolidated_notifications_keywords', ['keywords' => $row['matched_keywords']]) }}
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td style="padding: 8px; border: 1px solid #E5E7EB; text-align: center; white-space: nowrap;">{{ $row['term_start_date'] ?: '---' }}</td>
                                    <td style="padding: 8px; border: 1px solid #E5E7EB; text-align: center; white-space: nowrap;">{{ $row['term_end_date'] ?: '---' }}</td>
                                    <td style="padding: 8px; border: 1px solid #E5E7EB; text-align: center; white-space: nowrap;">{{ $row['registration_date'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <!--[if mso]>
                </td></tr></table>
                <![endif]-->
            </td>
        </tr>
    </table>

    @if($remainingActionsCount > 0)
        <div style="background-color: #FFFBEB; border: 1px solid #FDE68A; border-radius: 10px; padding: 14px 16px; margin-bottom: 24px;">
            <p style="margin: 0; font-size: 13px; color: #92400E; line-height: 1.5;">
                {{ trans_choice('process.consolidated_notifications_remaining', $remainingActionsCount, ['count' => $remainingActionsCount]) }}
            </p>
        </div>
    @endif

    <div style="text-align: center; margin-top: 10px;">
        <a href="{{ $digestUrl }}"
           style="background-color: #24163E; color: #FFFFFF; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
            {{ __('process.consolidated_notifications_cta') }}
        </a>
    </div>
@endsection
