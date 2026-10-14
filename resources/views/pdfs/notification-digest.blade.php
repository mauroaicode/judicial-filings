@php
    use Illuminate\Support\Str;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ __('process.consolidated_pdf_title', ['date' => $date]) }}</title>
    <style>
        @page {
            margin: 18px 20px 28px 20px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #1F2937;
            line-height: 1.4;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .header-table td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }

        .logo {
            height: 42px;
            width: auto;
        }

        .brand-block {
            text-align: right;
        }

        .brand-name {
            font-size: 15px;
            font-weight: bold;
            color: #24163E;
            margin: 0;
        }

        .brand-sub {
            font-size: 9px;
            color: #6B7280;
            margin: 2px 0 0 0;
        }

        .title {
            font-size: 17px;
            font-weight: bold;
            color: #24163E;
            margin: 0 0 4px 0;
        }

        .org-line {
            font-size: 12px;
            color: #374151;
            margin: 0 0 10px 0;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            background: #F3F0F9;
        }

        .meta-table td {
            padding: 8px 10px;
            border: 1px solid #E5E7EB;
            vertical-align: top;
        }

        .meta-label {
            display: block;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #6B7280;
            margin-bottom: 3px;
        }

        .meta-value {
            font-size: 11px;
            font-weight: bold;
            color: #24163E;
        }

        .period-badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            background: #E0E7FF;
            color: #3730A3;
        }

        .intro {
            font-size: 10.5px;
            color: #4B5563;
            margin: 0 0 10px 0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .data-table thead th {
            background: #24163E;
            color: #FFFFFF;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            padding: 7px 5px;
            border: 1px solid #1A1030;
            text-align: left;
        }

        .data-table tbody td {
            padding: 7px 5px;
            border: 1px solid #E5E7EB;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
            font-size: 10px;
        }

        .data-table tbody tr:nth-child(odd) td {
            background: #FFFFFF;
        }

        .data-table tbody tr:nth-child(even) td {
            background: #F3F4F6;
        }

        .col-num { width: 3%; text-align: center; }
        .col-radicado { width: 16%; }
        .col-despacho { width: 16%; }
        .col-party { width: 12%; }
        .col-date { width: 9%; text-align: center; }
        .col-actuacion { width: 22%; }

        .radicado {
            font-weight: bold;
            font-size: 9.5px;
            color: #111827;
            word-break: break-all;
        }

        .role {
            display: block;
            margin-top: 2px;
            font-size: 8.5px;
            font-weight: bold;
            color: #24163E;
            text-transform: uppercase;
        }

        .badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .badge-fijacion {
            background: #F3E8FF;
            color: #6B21A8;
        }

        .badge-auto {
            background: #FFF7ED;
            color: #C2410C;
        }

        .badge-alert {
            background: #FEE2E2;
            color: #B91C1C;
        }

        .actuacion-block {
            margin-bottom: 4px;
        }

        .actuacion-text {
            display: block;
            margin-top: 1px;
        }

        .annotation {
            margin-top: 3px;
            font-size: 9px;
            color: #4B5563;
        }

        .keywords {
            margin-top: 3px;
            font-size: 8.5px;
            color: #DC2626;
            font-style: italic;
            font-weight: bold;
        }

        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -18px;
            font-size: 8.5px;
            color: #9CA3AF;
            text-align: center;
            border-top: 1px solid #E5E7EB;
            padding-top: 6px;
        }

        .empty {
            text-align: center;
            padding: 24px;
            color: #6B7280;
            border: 1px solid #E5E7EB;
        }

        .subject-name {
            display: block;
            margin-bottom: 2px;
        }

        .subject-list {
            margin: 0;
            padding-left: 12px;
        }

        .subject-list li {
            margin-bottom: 2px;
        }

        .cta-wrap {
            text-align: right;
            margin: 6px 0 0 0;
        }

        .cta-button {
            display: inline-block;
            background-color: #24163E;
            color: #FFFFFF;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            font-size: 9px;
        }
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
                <p class="brand-sub">{{ __('process.consolidated_pdf_document_label') }}</p>
                @if(!empty($digestUrl))
                    <div class="cta-wrap">
                        <a class="cta-button" href="{{ $digestUrl }}">
                            {{ __('process.consolidated_notifications_cta') }}
                        </a>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <h1 class="title">{{ __('process.consolidated_pdf_title', ['date' => $date]) }}</h1>
    <p class="org-line">
        <strong>{{ __('process.consolidated_pdf_organization') }}:</strong> {{ $organizationName }}
    </p>

    <table class="meta-table">
        <tr>
            <td style="width: 28%;">
                <span class="meta-label">{{ __('process.consolidated_pdf_published') }}</span>
                <span class="meta-value">
                    {{ $date }}, {{ $time }}
                    <span class="period-badge">{{ $period }}</span>
                </span>
            </td>
            <td style="width: 24%;">
                <span class="meta-label">{{ __('process.consolidated_pdf_movements') }}</span>
                <span class="meta-value">
                    {{ trans_choice('process.consolidated_notifications_summary_actions', $actionsCount, ['count' => $actionsCount]) }}
                </span>
            </td>
            <td style="width: 24%;">
                <span class="meta-label">{{ __('process.consolidated_pdf_processes') }}</span>
                <span class="meta-value">
                    {{ trans_choice('process.consolidated_notifications_summary_processes', max($processesCount, 1), ['count' => $processesCount]) }}
                    @if($alertsCount > 0)
                        · {{ trans_choice('process.consolidated_notifications_summary_alerts', $alertsCount, ['count' => $alertsCount]) }}
                    @endif
                </span>
            </td>
            <td style="width: 24%;">
                <span class="meta-label">{{ __('process.consolidated_pdf_notified') }}</span>
                <span class="meta-value">
                    @if($isNotified)
                        {{ __('process.consolidated_pdf_notified_yes') }}
                        @if($emailNotifiedAt)
                            <br><span style="font-weight: normal; font-size: 8px; color: #6B7280;">Email: {{ $emailNotifiedAt }}</span>
                        @endif
                    @else
                        {{ __('process.consolidated_pdf_notified_no') }}
                    @endif
                </span>
            </td>
        </tr>
    </table>

    <p class="intro">{{ __('process.consolidated_notifications_intro') }}</p>

    @if(count($rows) === 0)
        <div class="empty">{{ __('process.consolidated_pdf_empty') }}</div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-num">#</th>
                    <th class="col-radicado">{{ __('process.consolidated_notifications_radicacion') }}</th>
                    <th class="col-despacho">{{ __('process.consolidated_notifications_court') }}</th>
                    <th class="col-party">{{ __('process.consolidated_notifications_plaintiff') }}</th>
                    <th class="col-party">{{ __('process.consolidated_notifications_defendant') }}</th>
                    <th class="col-date">{{ __('process.consolidated_notifications_registration') }}</th>
                    <th class="col-date">{{ __('process.consolidated_notifications_term_start') }}</th>
                    <th class="col-date">{{ __('process.consolidated_notifications_term_end') }}</th>
                    <th class="col-actuacion">{{ __('process.consolidated_notifications_action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $index => $row)
                    @php
                        $plaintiffNames = $row['plaintiff_names'] ?? [];
                        $defendantNames = $row['defendant_names'] ?? [];
                        $annotation = $row['annotation'] ?? '---';
                        if (!empty($row['is_merged']) && ($annotation === '---' || $annotation === '' || $annotation === null)) {
                            $annotation = $row['linked_annotation'] ?? '---';
                        }
                        $isAlert = !empty($row['is_alert']);
                    @endphp
                    <tr>
                        <td class="col-num">{{ $index + 1 }}</td>
                        <td class="col-radicado">
                            <span class="radicado">{{ $row['process_number_formatted'] ?? '—' }}</span>
                            @if(!empty($row['lawyer_role']))
                                <span class="role">{{ $row['lawyer_role'] }}</span>
                            @endif
                        </td>
                        <td class="col-despacho">{{ $row['court'] ?? '—' }}</td>
                        <td class="col-party">
                            @if(count($plaintiffNames) === 0)
                                —
                            @elseif(count($plaintiffNames) === 1)
                                <span class="subject-name">{{ $plaintiffNames[0] }}</span>
                            @else
                                <ul class="subject-list">
                                    @foreach($plaintiffNames as $name)
                                        <li>{{ $name }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                        <td class="col-party">
                            @if(count($defendantNames) === 0)
                                —
                            @elseif(count($defendantNames) === 1)
                                <span class="subject-name">{{ $defendantNames[0] }}</span>
                            @else
                                <ul class="subject-list">
                                    @foreach($defendantNames as $name)
                                        <li>{{ $name }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                        <td class="col-date">{{ $row['registration_date'] ?? '—' }}</td>
                        <td class="col-date">{{ !empty($row['term_start_date']) ? $row['term_start_date'] : '—' }}</td>
                        <td class="col-date">{{ !empty($row['term_end_date']) ? $row['term_end_date'] : '—' }}</td>
                        <td class="col-actuacion">
                            @if($isAlert)
                                <span class="badge badge-alert">{{ __('process.consolidated_pdf_alert') }}</span>
                            @endif

                            @if(!empty($row['is_merged']))
                                <div class="actuacion-block">
                                    <span class="badge badge-fijacion">{{ __('process.consolidated_notifications_fijacion') }}</span>
                                    <span class="actuacion-text">{{ $row['action_text'] ?? '' }}</span>
                                </div>
                                <div class="actuacion-block">
                                    <span class="badge badge-auto">{{ __('process.consolidated_notifications_auto') }}</span>
                                    <span class="actuacion-text"><strong>{{ $row['linked_action_text'] ?? '' }}</strong></span>
                                </div>
                            @else
                                <span class="actuacion-text">{{ $row['action_text'] ?? '—' }}</span>
                            @endif

                            @if($annotation && $annotation !== '---')
                                <div class="annotation">
                                    <strong>{{ __('process.consolidated_notifications_annotation') }}:</strong>
                                    {{ Str::limit((string) $annotation, 180) }}
                                </div>
                            @endif

                            @if($isAlert && !empty($row['matched_keywords']))
                                <div class="keywords">
                                    {{ __('process.consolidated_notifications_keywords', ['keywords' => $row['matched_keywords']]) }}
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        {{ __('process.consolidated_pdf_footer', ['app' => config('app.name', 'NotiJudicial'), 'generated' => $generatedAt]) }}
    </div>
</body>
</html>
