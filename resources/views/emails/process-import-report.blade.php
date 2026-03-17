@extends('emails.layouts.email')

@section('title', __('process.import_report_subject'))

@section('content')
    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 22px; font-weight: 700; color: #24163E; margin: 0 0 10px 0;">{{ __('process.import_report_title') }}</h1>
        <p style="font-size: 15px; color: #6B7280; margin: 0;">{{ __('process.import_report_intro') }}</p>
    </div>

    <!-- Summary Card -->
    <div style="background-color: #FFFFFF; border-radius: 12px; border: 1px solid #E5E7EB; overflow: hidden; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
        <div style="background-color: #24163E; padding: 12px 20px;">
            <p style="margin: 0; color: #FFFFFF; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Resumen de la Importación</p>
        </div>
        <div style="padding: 20px;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 10px 0; font-size: 14px; color: #6B7280;">{{ __('process.import_report_organization') }}:</td>
                    <td style="padding: 10px 0; font-size: 14px; color: #111827; font-weight: 600; text-align: right;">{{ $report->organizationName }}</td>
                </tr>
                <tr style="border-top: 1px solid #F3F4F6;">
                    <td style="padding: 10px 0; font-size: 14px; color: #6B7280;">{{ __('process.import_report_total') }}:</td>
                    <td style="padding: 10px 0; font-size: 14px; color: #111827; font-weight: 600; text-align: right;">{{ $report->excelTotalCount }}</td>
                </tr>
            </table>

            <div style="display: table; width: 100%; margin-top: 15px; background-color: #F9FAFB; border-radius: 8px; padding: 15px 0;">
                <div style="display: table-cell; width: 33.33%; text-align: center; border-right: 1px solid #E5E7EB;">
                    <p style="margin: 0; font-size: 11px; color: #9CA3AF; text-transform: uppercase;">{{ __('process.import_report_success') }}</p>
                    <p style="margin: 5px 0 0 0; font-size: 18px; color: #10B981; font-weight: 700;">{{ $report->successCount }}</p>
                </div>
                <div style="display: table-cell; width: 33.33%; text-align: center; border-right: 1px solid #E5E7EB;">
                    <p style="margin: 0; font-size: 11px; color: #9CA3AF; text-transform: uppercase;">Múltiples</p>
                    <p style="margin: 5px 0 0 0; font-size: 18px; color: #4B2A7D; font-weight: 700;">{{ $report->multipleInstancesCount }}</p>
                </div>
                <div style="display: table-cell; width: 33.33%; text-align: center;">
                    <p style="margin: 0; font-size: 11px; color: #9CA3AF; text-transform: uppercase;">{{ __('process.import_report_failed') }}</p>
                    <p style="margin: 5px 0 0 0; font-size: 18px; color: {{ $report->failedCount > 0 ? '#EF4444' : '#10B981' }}; font-weight: 700;">{{ $report->failedCount }}</p>
                </div>
            </div>
        </div>
    </div>

    @if(count($report->errors) > 0)
    <div style="margin-bottom: 30px;">
        <h2 style="font-size: 16px; font-weight: 700; color: #24163E; margin-bottom: 12px;">{{ __('process.import_report_failed_list') }}</h2>
        <div style="border: 1px solid #E5E7EB; border-radius: 8px; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background-color: #F9FAFB;">
                        <th style="padding: 10px 15px; text-align: left; color: #4B5563; border-bottom: 1px solid #E5E7EB;">{{ __('process.import_report_radicado') }}</th>
                        <th style="padding: 10px 15px; text-align: left; color: #4B5563; border-bottom: 1px solid #E5E7EB;">{{ __('process.import_report_reason') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report->errors as $err)
                    <tr>
                        <td style="padding: 10px 15px; color: #111827; border-bottom: 1px solid #F3F4F6;">{{ $err['process_number'] }}</td>
                        <td style="padding: 10px 15px; color: #EF4444; border-bottom: 1px solid #F3F4F6;">{{ $err['reason'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div style="margin-top: 30px; border-top: 1px solid #E5E7EB; padding-top: 20px;">
        <p style="font-size: 12px; color: #9CA3AF; margin: 0;">
            {{ __('process.import_report_completed_at') }}: {{ $report->completedAt->format('d/m/Y H:i') }}
        </p>
    </div>
@endsection
