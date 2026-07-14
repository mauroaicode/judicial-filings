@extends('emails.layouts.email')

@section('title', __('task.due_reminder_email_page_title'))

@section('content')
    <div style="margin-bottom: 8px; padding-top: 4px; border-top: 4px solid {{ $accentColor }}; border-radius: 4px 4px 0 0;"></div>

    <div style="margin-bottom: 28px; padding-top: 8px;">
        <div style="display: inline-block; background-color: #F4F0FA; color: {{ $accentColor }}; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; padding: 7px 14px; border-radius: 999px; margin-bottom: 18px; border: 1px solid {{ $accentColor }};">
            {{ $alert->daysRemaining === 0 ? __('task.due_reminder_badge_today') : __('task.due_reminder_badge', ['days' => $alert->daysRemaining]) }}
        </div>
        <h1 style="font-size: 24px; font-weight: 700; color: #24163E; margin: 0 0 12px 0; line-height: 1.35;">{{ $headline }}</h1>
        <p style="font-size: 15px; color: #6B7280; margin: 0; line-height: 1.65;">{{ $body }}</p>
    </div>

    @php
        use Src\Application\Shared\Helpers\DateFormatHelper;
        use Src\Application\Shared\Helpers\ProcessNumberFormatHelper;

        $detailRows = [
            [
                'label' => __('task.urgency_email_task_title'),
                'value' => $alert->task->title,
            ],
        ];

        if (filled($alert->task->description)) {
            $detailRows[] = [
                'label' => __('task.urgency_email_task_description'),
                'value' => $alert->task->description,
                'multiline' => true,
            ];
        }

        $detailRows[] = [
            'label' => __('task.urgency_email_process_number'),
            'value' => ProcessNumberFormatHelper::display($alert->processNumber()),
            'highlight' => true,
            'monospace' => true,
        ];

        $detailRows[] = [
            'label' => __('task.urgency_email_due_date'),
            'value' => $alert->task->due_date
                ? DateFormatHelper::formatDateWithDayOfWeek($alert->task->due_date)
                : __('task.no_process_associated'),
        ];

        $detailRows[] = [
            'label' => __('task.due_reminder_email_days_remaining'),
            'value' => (string) $alert->daysRemaining,
        ];
    @endphp

    @include('emails.partials.task-notification-details', [
        'sectionTitle' => __('task.due_reminder_email_task_details'),
        'rows' => $detailRows,
    ])

    <div style="text-align: center; margin-top: 8px;">
        <a href="{{ $alert->taskUrl }}"
           style="background: linear-gradient(135deg, #4B2A7D 0%, #24163E 100%); color: #FFFFFF; padding: 15px 32px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 15px; display: inline-block; box-shadow: 0 8px 20px rgba(36, 22, 62, 0.18);">
            {{ __('task.urgency_email_view_task_button') }}
        </a>
    </div>
@endsection
