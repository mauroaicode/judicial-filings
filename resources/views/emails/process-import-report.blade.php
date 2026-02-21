@extends('emails.layouts.email')

@section('title', __('process.import_report_subject'))

@push('styles')
<style>
    .title-section { margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0; }
    .title { font-size: 20px; font-weight: 600; color: #1a202c; margin: 0; }
    .stats { display: table; width: 100%; margin: 20px 0; border-collapse: collapse; }
    .stats td { padding: 12px 16px; border: 1px solid #e2e8f0; }
    .stats .label { color: #718096; font-size: 14px; }
    .stats .value { font-weight: 600; color: #1a202c; }
    .failed-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    .failed-table th, .failed-table td { padding: 10px 12px; text-align: left; border: 1px solid #e2e8f0; }
    .failed-table th { background: #f7fafc; color: #4a5568; font-size: 13px; }
    .failed-table td { font-size: 14px; color: #2d3748; }
    .section-title { font-size: 16px; font-weight: 600; margin: 24px 0 12px 0; color: #1a202c; }
</style>
@endpush

@section('content')
    <div class="title-section">
        <h1 class="title">{{ __('process.import_report_title') }}</h1>
    </div>

    <p>{{ __('process.import_report_intro') }}</p>

    <p><strong>{{ __('process.import_report_organization') }}:</strong> {{ $report->organizationName }}</p>
    <p><strong>{{ __('process.import_report_total') }}:</strong> {{ $report->totalCount }}</p>

    <table class="stats">
        <tr>
            <td class="label">{{ __('process.import_report_success') }}</td>
            <td class="value">{{ $report->successCount }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('process.import_report_failed') }}</td>
            <td class="value">{{ $report->failedCount }}</td>
        </tr>
    </table>

    @if(count($report->errors) > 0)
        <p class="section-title">{{ __('process.import_report_failed_list') }}</p>
        <table class="failed-table">
            <thead>
                <tr>
                    <th>{{ __('process.import_report_radicado') }}</th>
                    <th>{{ __('process.import_report_reason') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report->errors as $err)
                    <tr>
                        <td>{{ $err['process_number'] }}</td>
                        <td>{{ $err['reason'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($report->completedAt)
        <p style="margin-top: 24px; font-size: 13px; color: #718096;">
            {{ __('process.import_report_title') }} completado: {{ $report->completedAt->format('d/m/Y H:i') }}
        </p>
    @endif
@endsection
