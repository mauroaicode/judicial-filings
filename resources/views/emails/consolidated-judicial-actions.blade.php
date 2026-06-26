@php
    use Illuminate\Support\Str;

    /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $processGroups */
    $dateString = now()->format('Y-m-d');
    $headerKey = $totalProcessesCount > 1
        ? 'process.consolidated_notifications_header_plural'
        : 'process.consolidated_notifications_header_singular';
@endphp
@extends('emails.layouts.email')
@section('title', __('process.consolidated_notifications_title'))

@section('content')
    <div style="margin-bottom: 25px;">
        <h1 style="font-size: 20px; font-weight: 700; color: #24163E; margin: 0 0 10px 0;">
            {{ __($headerKey, ['date' => $dateString]) }}
        </h1>
        <p style="font-size: 14px; color: #6B7280; margin: 0 0 16px 0;">
            {{ __('process.consolidated_notifications_intro') }}
        </p>

        <div style="background-color: #F3F0F9; border: 1px solid #E5E7EB; border-radius: 10px; padding: 16px 18px;">
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
    </div>

    @foreach($processGroups as $group)
        <div style="border: 1px solid #E5E7EB; border-radius: 12px; overflow: hidden; margin-bottom: 20px; {{ $group['has_alert'] ? 'border-color: #FECACA;' : '' }}">
            <div style="background-color: {{ $group['has_alert'] ? '#FEF2F2' : '#F3F0F9' }}; padding: 14px 16px; border-bottom: 1px solid #E5E7EB;">
                <p style="margin: 0 0 4px 0; font-size: 10px; font-weight: 800; color: #4B2A7D; text-transform: uppercase; letter-spacing: 0.5px;">
                    {{ __('process.consolidated_notifications_radicacion') }}
                </p>
                <p style="margin: 0 0 10px 0; font-size: 14px; font-weight: 700; color: #24163E; word-break: break-all; overflow-wrap: break-word;">
                    {{ $group['process_number'] }}
                </p>
                <p style="margin: 0 0 8px 0; font-size: 13px; color: #374151; line-height: 1.4;">
                    <strong style="color: #4B2A7D;">{{ __('process.consolidated_notifications_court') }}:</strong>
                    {{ $group['court'] }}
                </p>
                <p style="margin: 0 0 4px 0; font-size: 12px; color: #6B7280; line-height: 1.4;">
                    <strong>{{ __('process.consolidated_notifications_plaintiff') }}:</strong> {{ $group['demandante'] }}
                </p>
                <p style="margin: 0; font-size: 12px; color: #6B7280; line-height: 1.4;">
                    <strong>{{ __('process.consolidated_notifications_defendant') }}:</strong> {{ $group['demandado'] }}
                </p>
            </div>

            @foreach($group['actions'] as $row)
                <div style="padding: 14px 16px; border-bottom: 1px solid #F3F4F6; {{ ($row['is_alert'] ?? false) ? 'background-color: #FFF7F7;' : 'background-color: #FFFFFF;' }}">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 0 12px 0 0; vertical-align: top; width: 110px;">
                                <p style="margin: 0; font-size: 10px; font-weight: 800; color: #4B2A7D; text-transform: uppercase;">{{ __('process.consolidated_notifications_action_date') }}</p>
                                <p style="margin: 4px 0 0 0; font-size: 12px; color: #374151;">{{ $row['action_date'] }}</p>
                            </td>
                            <td style="padding: 0; vertical-align: top;">
                                <p style="margin: 0; font-size: 10px; font-weight: 800; color: #4B2A7D; text-transform: uppercase;">{{ __('process.consolidated_notifications_action') }}</p>
                                <div style="margin-top: 4px; font-size: 13px; color: #111827; line-height: 1.45;">
                                    @if(!empty($row['is_merged']))
                                        <div style="margin-bottom: 6px;">
                                            <span style="background-color: #F3E8FF; color: #6B21A8; font-size: 9px; padding: 2px 4px; border-radius: 3px; font-weight: 800; text-transform: uppercase;">{{ __('process.consolidated_notifications_fijacion') }}</span>
                                            <span style="display: block; margin-top: 2px;">{{ $row['action_text'] }}</span>
                                        </div>
                                        <div>
                                            <span style="background-color: #FFF7ED; color: #C2410C; font-size: 9px; padding: 2px 4px; border-radius: 3px; font-weight: 800; text-transform: uppercase;">{{ __('process.consolidated_notifications_auto') }}</span>
                                            <span style="display: block; margin-top: 2px; font-weight: 600;">{{ $row['linked_action_text'] ?? '' }}</span>
                                        </div>
                                    @else
                                        {{ $row['action_text'] }}
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </table>

                    @php
                        $annotation = $row['annotation'] ?? '---';
                        if (!empty($row['is_merged']) && ($annotation === '---' || empty($annotation))) {
                            $annotation = $row['linked_annotation'] ?? '---';
                        }
                    @endphp
                    @if($annotation !== '---' && $annotation !== '')
                        <p style="margin: 10px 0 0 0; font-size: 12px; color: #6B7280; line-height: 1.4;">
                            <strong>{{ __('process.consolidated_notifications_annotation') }}:</strong> {{ $annotation }}
                        </p>
                    @endif

                    @if(($row['term_start_date'] ?? null) || ($row['term_end_date'] ?? null))
                        <p style="margin: 8px 0 0 0; font-size: 11px; color: #9CA3AF;">
                            {{ __('process.consolidated_notifications_term') }}:
                            {{ $row['term_start_date'] ?: '---' }} → {{ $row['term_end_date'] ?: '---' }}
                        </p>
                    @endif

                    @if(!empty($row['is_alert']) && !empty($row['matched_keywords']))
                        <p style="margin: 8px 0 0 0; font-size: 11px; color: #DC2626; font-style: italic; font-weight: 600;">
                            {{ __('process.consolidated_notifications_keywords', ['keywords' => $row['matched_keywords']]) }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach

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
