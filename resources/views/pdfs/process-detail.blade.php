@php
    use Illuminate\Support\Str;

    $detail = $detail ?? [];
    $subjects = $subjects ?? ['plaintiffs' => [], 'defendants' => [], 'others' => []];
    $instances = $instances ?? ['has_multiple' => false, 'count' => 1, 'current_court' => ''];
    $tasks = $tasks ?? [];
    $timeline = $timeline ?? [];
    $actions = $actions ?? [];
    $hasSubjects = count($subjects['plaintiffs']) + count($subjects['defendants']) + count($subjects['others']) > 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ __('process.process_pdf_title') }} {{ $processNumberFormatted }}</title>
    <style>
        @page { margin: 18px 20px 28px 20px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1F2937; line-height: 1.4; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .header-table td { vertical-align: middle; border: none; padding: 0; }
        .logo { height: 42px; width: auto; }
        .brand-block { text-align: right; }
        .brand-name { font-size: 15px; font-weight: bold; color: #24163E; margin: 0; }
        .brand-sub { font-size: 9px; color: #6B7280; margin: 2px 0 0 0; }
        .title { font-size: 17px; font-weight: bold; color: #24163E; margin: 0 0 2px 0; }
        .radicado { font-size: 13px; font-weight: bold; color: #111827; margin: 0 0 10px 0; }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; background: #F3F0F9; }
        .meta-table td { padding: 8px 10px; border: 1px solid #E5E7EB; vertical-align: top; width: 25%; }
        .meta-label { display: block; font-size: 8.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4px; color: #6B7280; margin-bottom: 3px; }
        .meta-value { font-size: 11px; font-weight: bold; color: #24163E; }
        .section { margin: 14px 0 8px 0; font-size: 12px; font-weight: bold; color: #24163E; border-bottom: 2px solid #24163E; padding-bottom: 3px; }
        .info-table, .data-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .info-table td { padding: 6px 8px; border: 1px solid #E5E7EB; vertical-align: top; width: 25%; }
        .info-label { display: block; font-size: 8px; font-weight: bold; text-transform: uppercase; color: #6B7280; margin-bottom: 2px; }
        .note { background: #F3F0F9; border: 1px solid #E5E7EB; padding: 8px 10px; margin-bottom: 10px; }
        .data-table thead th { background: #24163E; color: #FFFFFF; font-size: 8.5px; font-weight: bold; text-transform: uppercase; padding: 7px 5px; border: 1px solid #1A1030; text-align: left; }
        .data-table tbody td { padding: 6px 5px; border: 1px solid #E5E7EB; vertical-align: top; font-size: 10px; word-wrap: break-word; }
        .data-table tbody tr:nth-child(odd) td { background: #FFFFFF; }
        .data-table tbody tr:nth-child(even) td { background: #F3F4F6; }
        .subject-list { margin: 0; padding-left: 14px; }
        .subject-list li { margin-bottom: 2px; }
        .badge { display: inline-block; padding: 1px 4px; border-radius: 2px; font-size: 8px; font-weight: bold; text-transform: uppercase; margin-bottom: 2px; }
        .badge-fijacion { background: #F3E8FF; color: #6B21A8; }
        .badge-auto { background: #FFF7ED; color: #C2410C; }
        .badge-removed { background: #FEE2E2; color: #B91C1C; }
        .empty { text-align: center; padding: 12px; color: #6B7280; border: 1px solid #E5E7EB; }
        .footer { position: fixed; left: 0; right: 0; bottom: -18px; font-size: 8.5px; color: #9CA3AF; text-align: center; border-top: 1px solid #E5E7EB; padding-top: 6px; }
        .muted { color: #6B7280; font-weight: normal; font-size: 9px; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                @if($logoDataUri)
                    <img class="logo" src="{{ $logoDataUri }}" alt="NotiJudicial">
                @else
                    <p class="brand-name">NotiJudicial</p>
                @endif
            </td>
            <td class="brand-block" style="width: 45%;">
                <p class="brand-name">{{ config('app.name', 'NotiJudicial') }}</p>
                <p class="brand-sub">{{ __('process.process_pdf_document_label') }}</p>
            </td>
        </tr>
    </table>

    <h1 class="title">{{ __('process.process_pdf_title') }}</h1>
    <p class="radicado">{{ $processNumberFormatted }}</p>

    <table class="meta-table">
        <tr>
            <td>
                <span class="meta-label">{{ __('process.process_pdf_status') }}</span>
                <span class="meta-value">{{ $detail['status_label'] ?? '—' }}</span>
            </td>
            <td>
                <span class="meta-label">{{ __('process.process_pdf_alert_level') }}</span>
                <span class="meta-value">{{ $alertLevelLabel ?? '—' }}</span>
            </td>
            <td>
                <span class="meta-label">{{ __('process.process_pdf_role') }}</span>
                <span class="meta-value">{{ $detail['lawyer_role'] ?? '—' }}</span>
            </td>
            <td>
                <span class="meta-label">{{ __('process.process_pdf_last_action') }}</span>
                <span class="meta-value">{{ $detail['last_activity_date'] ?? '—' }}</span>
            </td>
        </tr>
    </table>

    @if(!empty($detail['semaphore']['paused']) && !empty($detail['semaphore']['message']))
        <div class="note">{{ $detail['semaphore']['message'] }}</div>
    @endif

    @if(!empty($instances['has_multiple']))
        <div class="note">
            <strong>{{ __('process.process_pdf_instances') }}:</strong>
            {{ __('process.process_pdf_instances_note', ['count' => $instances['count'], 'court' => $instances['current_court']]) }}
        </div>
    @endif

    <div class="section">{{ __('process.process_pdf_general') }}</div>
    <table class="info-table">
        <tr>
            <td>
                <span class="info-label">{{ __('process.process_pdf_radicado') }}</span>
                {{ $processNumberFormatted }}
            </td>
            <td>
                <span class="info-label">{{ __('process.process_pdf_speaker') }}</span>
                {{ $detail['speaker'] ?? '—' }}
            </td>
            <td>
                <span class="info-label">{{ __('process.process_pdf_court') }}</span>
                {{ $detail['court'] ?? '—' }}
            </td>
            <td>
                <span class="info-label">{{ __('process.process_pdf_process_type') }}</span>
                {{ $detail['process_type'] ?? '—' }}
            </td>
        </tr>
        <tr>
            <td>
                <span class="info-label">{{ __('process.process_pdf_process_class') }}</span>
                {{ $detail['process_class'] ?? '—' }}
            </td>
            <td>
                <span class="info-label">{{ __('process.process_pdf_subclass') }}</span>
                {{ $detail['subclass_process'] ?? '—' }}
            </td>
            <td>
                <span class="info-label">{{ __('process.process_pdf_location') }}</span>
                {{ $detail['location'] ?? '—' }}
            </td>
            <td>
                <span class="info-label">{{ __('process.process_pdf_filing_date') }}</span>
                {{ $detail['process_date'] ?? '—' }}
            </td>
        </tr>
        <tr>
            <td>
                <span class="info-label">{{ __('process.process_pdf_last_sync') }}</span>
                {{ $detail['last_api_update'] ?? '—' }}
            </td>
            <td>
                <span class="info-label">{{ __('process.process_pdf_private') }}</span>
                {{ !empty($detail['is_private']) ? __('process.process_pdf_yes') : __('process.process_pdf_no') }}
            </td>
            <td colspan="2">
                <span class="info-label">{{ __('process.process_pdf_current_instance') }}</span>
                {{ $detail['court'] ?? '—' }}
            </td>
        </tr>
    </table>

    <div class="section">{{ __('process.process_pdf_subjects') }}</div>
    @if(! $hasSubjects)
        <div class="empty">{{ __('process.process_pdf_empty_subjects') }}</div>
    @else
        <table class="info-table">
            <tr>
                <td>
                    <span class="info-label">{{ __('process.process_pdf_plaintiffs') }}</span>
                    @include('pdfs.partials.process-subjects', ['items' => $subjects['plaintiffs']])
                </td>
                <td>
                    <span class="info-label">{{ __('process.process_pdf_defendants') }}</span>
                    @include('pdfs.partials.process-subjects', ['items' => $subjects['defendants']])
                </td>
                <td colspan="2">
                    <span class="info-label">{{ __('process.process_pdf_other_subjects') }}</span>
                    @include('pdfs.partials.process-subjects', ['items' => $subjects['others']])
                </td>
            </tr>
        </table>
    @endif

    <div class="section">{{ __('process.process_pdf_tasks') }}</div>
    @if(count($tasks) === 0)
        <div class="empty">{{ __('process.process_pdf_empty_tasks') }}</div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 22%;">{{ __('process.process_pdf_tasks') }}</th>
                    <th style="width: 14%;">{{ __('process.process_pdf_task_type') }}</th>
                    <th style="width: 12%;">{{ __('process.process_pdf_task_status') }}</th>
                    <th style="width: 18%;">{{ __('process.process_pdf_task_due') }}</th>
                    <th style="width: 14%;">{{ __('process.process_pdf_task_reminder') }}</th>
                    <th style="width: 20%;">{{ __('process.process_pdf_task_urgency') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tasks as $task)
                    <tr>
                        <td>
                            <strong>{{ $task['title'] }}</strong>
                            @if(!empty($task['description']))
                                <div class="muted">{{ Str::limit(strip_tags((string) $task['description']), 160) }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $task['type_label'] }}
                            @if(!empty($task['pauses_process']))
                                <div class="muted">{{ __('process.process_pdf_task_pauses') }}</div>
                            @endif
                        </td>
                        <td>{{ $task['status_label'] }}</td>
                        <td>{{ $task['due_date'] ?? '—' }}</td>
                        <td>{{ trans_choice('process.process_pdf_task_reminder_days', (int) ($task['reminder_days'] ?? 0), ['count' => (int) ($task['reminder_days'] ?? 0)]) }}</td>
                        <td>
                            {{ $task['urgency_label'] ?? '—' }}
                            @if(($task['days_overdue'] ?? 0) > 0)
                                <div class="muted">{{ trans_choice('process.process_pdf_task_overdue', $task['days_overdue'], ['count' => $task['days_overdue']]) }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="section">{{ __('process.process_pdf_timeline') }}</div>
    @if(count($timeline) === 0)
        <div class="empty">{{ __('process.process_pdf_empty_timeline') }}</div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 22%;">{{ __('process.process_pdf_timeline') }}</th>
                    <th style="width: 22%;">{{ __('process.process_pdf_event') }}</th>
                    <th style="width: 40%;">{{ __('process.process_pdf_detail') }}</th>
                    <th style="width: 16%;">{{ __('process.process_pdf_source') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($timeline as $event)
                    <tr>
                        <td>{{ $event['occurred_at'] }}</td>
                        <td>{{ $event['title'] }}</td>
                        <td>
                            {{ $event['summary'] ?: '—' }}
                            @if(!empty($event['reason']))
                                <div class="muted">{{ $event['reason'] }}</div>
                            @endif
                        </td>
                        <td>{{ $event['source'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="section">{{ __('process.process_pdf_actions') }}</div>
    @if(count($actions) === 0)
        <div class="empty">{{ __('process.process_pdf_empty_actions') }}</div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 4%;">#</th>
                    <th style="width: 28%;">{{ __('process.consolidated_notifications_action') }}</th>
                    <th style="width: 32%;">{{ __('process.consolidated_notifications_annotation') }}</th>
                    <th style="width: 12%;">{{ __('process.consolidated_notifications_registration') }}</th>
                    <th style="width: 12%;">{{ __('process.consolidated_notifications_term_start') }}</th>
                    <th style="width: 12%;">{{ __('process.consolidated_notifications_term_end') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($actions as $index => $row)
                    @php
                        $annotation = $row['annotation'] ?? '---';
                        if (!empty($row['is_merged']) && ($annotation === '---' || $annotation === '')) {
                            $annotation = $row['linked_annotation'] ?? '---';
                        }
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            @if(!empty($row['removed_from_judicial_branch']))
                                <span class="badge badge-removed">{{ __('process.process_pdf_removed_action') }}</span>
                            @endif
                            @if(!empty($row['is_merged']))
                                <div>
                                    <span class="badge badge-fijacion">{{ __('process.consolidated_notifications_fijacion') }}</span>
                                    {{ $row['action_text'] ?? $row['action'] ?? '' }}
                                </div>
                                <div>
                                    <span class="badge badge-auto">{{ __('process.consolidated_notifications_auto') }}</span>
                                    <strong>{{ $row['linked_action_text'] ?? '' }}</strong>
                                </div>
                            @else
                                {{ $row['action_text'] ?? $row['action'] ?? '—' }}
                            @endif
                        </td>
                        <td>{{ $annotation && $annotation !== '---' ? $annotation : '—' }}</td>
                        <td>{{ $row['registration_date'] ?? '—' }}</td>
                        <td>{{ !empty($row['term_start_date']) ? $row['term_start_date'] : '—' }}</td>
                        <td>{{ !empty($row['term_end_date']) ? $row['term_end_date'] : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        {{ __('process.process_pdf_footer', ['app' => config('app.name', 'NotiJudicial'), 'generated' => $generatedAt]) }}
    </div>
</body>
</html>
