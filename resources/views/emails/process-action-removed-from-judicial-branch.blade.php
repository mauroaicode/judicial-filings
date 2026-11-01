@php
    use Src\Application\Shared\Helpers\DateFormatHelper;
    use Src\Application\Shared\Helpers\ProcessNumberFormatHelper;

    $formattedProcessNumber = ProcessNumberFormatHelper::display($process->process_number);
@endphp
@extends('emails.layouts.email')

@section('title', __('process.action_removed_from_judicial_branch_title'))

@section('content')
    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 22px; font-weight: 700; color: #24163E; margin: 0 0 10px 0;">
            {{ __('process.action_removed_from_judicial_branch_title') }}
        </h1>
        <p style="font-size: 15px; color: #6B7280; margin: 0;">
            {{ __('process.action_removed_from_judicial_branch_intro') }}
        </p>
    </div>

    <div
        style="background-color: #FFFFFF; border-radius: 12px; border: 1px solid #E5E7EB; overflow: hidden; margin-bottom: 25px;">
        <div style="background-color: #FEF3C7; padding: 12px 20px; border-bottom: 1px solid #E5E7EB;">
            <p style="margin: 0; color: #92400E; font-size: 12px; font-weight: 700; text-transform: uppercase;">
                {{ __('process.process_number') }}: {{ $formattedProcessNumber }}
            </p>
        </div>
        <div style="padding: 20px;">
            <p style="margin: 0 0 12px 0; font-size: 14px; color: #374151;">
                <strong>{{ __('process.action_date') }}:</strong>
                {{ DateFormatHelper::formatDate($action->action_date) }}
            </p>
            <p style="margin: 0 0 12px 0; font-size: 14px; color: #374151;">
                <strong>{{ __('process.action_description') }}:</strong> {{ $action->action }}
            </p>
            @if($reason)
                <p style="margin: 0; font-size: 14px; color: #374151;">
                    <strong>{{ __('process.removal_reason') }}:</strong> {{ $reason }}
                </p>
            @endif
        </div>
    </div>

    <div style="text-align: center;">
        <a href="{{ $ctaUrl }}"
           style="display: inline-block; background-color: #4B2A7D; color: #FFFFFF; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600;">
            {{ __('process.view_process') }}
        </a>
    </div>
@endsection
