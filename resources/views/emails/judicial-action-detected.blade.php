@extends('emails.layouts.email')

@section('title', $notificationType === 'actuacion_alerta' ? __('process.alert_detected_title') : __('process.action_detected_title'))

@push('styles')
<style>
    .title-section { margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0; }
    .title { font-size: 20px; font-weight: 600; color: #1a202c; margin: 0; }
    .alert-title { color: #c53030; }
    .meta-box { background: #f7fafc; padding: 16px; border-radius: 8px; margin: 20px 0; border: 1px solid #e2e8f0; }
    .meta-item { margin: 8px 0; font-size: 14px; color: #4a5568; }
    .label { font-weight: 600; color: #2d3748; min-width: 120px; display: inline-block; }
    .content-section { margin-top: 24px; }
    .section-label { font-size: 14px; font-weight: 600; color: #718096; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
    .text-content { font-size: 15px; color: #2d3748; line-height: 1.6; background: #ffffff; padding: 12px; border-left: 4px solid #e2e8f0; }
    .alert-border { border-left-color: #fc8181; background: #fff5f5; }
    .button-container { margin-top: 32px; text-align: center; }
    .btn { background: #3182ce; color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-block; }
</style>
@endpush

@section('content')
    <div class="title-section">
        <h1 class="title {{ $notificationType === 'actuacion_alerta' ? 'alert-title' : '' }}">
            {{ $notificationType === 'actuacion_alerta' ? __('process.alert_detected_title') : __('process.action_detected_title') }}
        </h1>
    </div>

    <p>{{ $notificationType === 'actuacion_alerta' ? __('process.keyword_alert_intro') : __('process.new_action_intro') }}</p>

    <div class="meta-box">
        <div class="meta-item"><span class="label">{{ __('process.process_number') }}:</span> {{ $process->process_number }}</div>
        <div class="meta-item"><span class="label">{{ __('process.action_date') }}:</span> {{ $action->action_date->format('d/m/Y') }}</div>
        @if($notificationType === 'actuacion_alerta')
            <div class="meta-item"><span class="label">{{ __('process.matched_keywords') }}:</span> 
                <span style="color: #c53030; font-weight: bold;">{{ $matchedKeywords ?? '---' }}</span>
            </div>
        @endif
    </div>

    <div class="content-section">
        <div class="section-label">{{ __('process.action_text') }}</div>
        <div class="text-content">{{ $action->action }}</div>
    </div>

    @if($action->annotation)
        <div class="content-section">
            <div class="section-label">{{ __('process.annotation_text') }}</div>
            <div class="text-content {{ $notificationType === 'actuacion_alerta' ? 'alert-border' : '' }}">
                {{ $action->annotation }}
            </div>
        </div>
    @endif

    <div class="button-container">
        <a href="{{ config('app.url') }}/processes/{{ $process->id }}" class="btn">
            {{ __('process.view_process') }}
        </a>
    </div>
@endsection
