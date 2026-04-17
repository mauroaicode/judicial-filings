@php use Illuminate\Support\Str; @endphp
@extends('emails.layouts.email')
@section('max_width', '1200px')
@section('title', __('process.consolidated_notifications_title'))

@section('content')
    <style>
        @media only screen and (max-width: 640px) {
            .mobile-hide { display: none !important; }
            .mobile-block { display: block !important; width: 100% !important; box-sizing: border-box !important; }
            .stack-table { border: none !important; min-width: 100% !important; }
            .stack-table thead { display: none !important; }
            .stack-table tr { 
                display: block !important; 
                margin-bottom: 25px !important; 
                border: 1px solid #E5E7EB !important; 
                border-radius: 12px !important; 
                background-color: #FFFFFF !important; 
                overflow: hidden !important;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
            }
            .stack-table td { 
                display: block !important; 
                width: 100% !important; 
                text-align: left !important; 
                padding: 12px 15px !important; 
                border: none !important; 
                border-bottom: 1px solid #F3F4F6 !important; 
                box-sizing: border-box !important;
            }
            .stack-table td:last-child { border-bottom: none !important; }
            .stack-table td:before { 
                content: attr(data-label); 
                display: block !important; 
                font-size: 10px !important; 
                font-weight: 800 !important; 
                color: #4B2A7D !important; 
                text-transform: uppercase !important; 
                margin-bottom: 5px !important; 
                letter-spacing: 0.5px !important;
            }
            .stack-table td .fijacion-badge { font-size: 8px !important; }
            .stack-table td .auto-badge { font-size: 8px !important; }
        }
    </style>
    <div style="margin-bottom: 25px;">
        <h1 style="font-size: 20px; font-weight: 700; color: #24163E; margin: 0 0 10px 0;">
            @php
                $uniqueProcessesCount = collect($data)->unique('process_number')->count();
                $dateString = now()->format('Y-m-d');
                $headerKey = $uniqueProcessesCount > 1
                    ? 'process.consolidated_notifications_header_plural'
                    : 'process.consolidated_notifications_header_singular';
            @endphp
            {{ __($headerKey, ['date' => $dateString]) }}
        </h1>
        <p style="font-size: 14px; color: #6B7280; margin: 0;">
            Estimado usuario, se han detectado nuevas actuaciones en sus procesos seguidos. A continuación el detalle
            consolidado:
        </p>
    </div>

    <div style="overflow-x: auto; margin-bottom: 30px;">
        <table class="stack-table"
            style="width: 100%; border-collapse: collapse; min-width: 800px; font-family: sans-serif; font-size: 12px; border: 1px solid #E5E7EB;">
            <thead>
            <tr style="background-color: #F3F4F6; text-align: left; border-bottom: 2px solid #E5E7EB;">
                <th style="padding: 10px; border: 1px solid #E5E7EB;">Juzgado</th>
                <th style="padding: 10px; border: 1px solid #E5E7EB;">Radicación</th>
                <th style="padding: 10px; border: 1px solid #E5E7EB;">Demandante</th>
                <th style="padding: 10px; border: 1px solid #E5E7EB;">Demandado</th>
                <th style="padding: 10px; border: 1px solid #E5E7EB;">Fecha Actuación</th>
                <th style="padding: 10px; border: 1px solid #E5E7EB;">Actuación</th>
                <th style="padding: 10px; border: 1px solid #E5E7EB;">Anotación</th>
                <th style="padding: 10px; border: 1px solid #E5E7EB;">Inicia</th>
                <th style="padding: 10px; border: 1px solid #E5E7EB;">Finaliza</th>
                <th style="padding: 10px; border: 1px solid #E5E7EB;">Registro</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data as $row)
                <tr style="border-bottom: 1px solid #E5E7EB; vertical-align: middle; {{ $row['is_alert'] ? 'background-color: #FEF2F2;' : '' }}">
                    <td data-label="Juzgado" style="padding: 8px; border: 1px solid #E5E7EB; text-align: justify;">
                        <div style="line-height: 1.4;">{{ Str::limit($row['court'], 45) }}</div>
                    </td>
                    <td data-label="Radicación" style="padding: 8px; border: 1px solid #E5E7EB; white-space: nowrap; text-align: center;">{{ $row['process_number'] }}</td>
                    <td data-label="Demandante" style="padding: 8px; border: 1px solid #E5E7EB; text-align: justify;">
                        <div style="line-height: 1.4;">{{ Str::limit($row['demandante'], 40) }}</div>
                    </td>
                    <td data-label="Demandado" style="padding: 8px; border: 1px solid #E5E7EB; text-align: justify;">
                        <div style="line-height: 1.4;">{{ Str::limit($row['demandado'], 40) }}</div>
                    </td>
                    <td data-label="Fecha Actuación" style="padding: 8px; border: 1px solid #E5E7EB; text-align: center;">{{ $row['action_date'] }}</td>
                    <td data-label="Actuación" style="padding: 8px; border: 1px solid #E5E7EB; text-align: justify;">
                        <div style="line-height: 1.4;">
                            @if(isset($row['is_merged']) && $row['is_merged'])
                                <div style="margin-bottom: 5px;">
                                    <span class="fijacion-badge" style="background-color: #F3E8FF; color: #6B21A8; font-size: 9px; padding: 2px 4px; border-radius: 3px; font-weight: 800; text-transform: uppercase;">Fijación</span>
                                    <span style="display: block; margin-top: 2px;">{{ Str::limit($row['action_text'], 55) }}</span>
                                </div>
                                <div>
                                    <span class="auto-badge" style="background-color: #FFF7ED; color: #C2410C; font-size: 9px; padding: 2px 4px; border-radius: 3px; font-weight: 800; text-transform: uppercase;">Auto</span>
                                    <span style="display: block; margin-top: 2px; font-weight: 600;">{{ Str::limit($row['linked_action_text'], 55) }}</span>
                                </div>
                            @else
                                {{ Str::limit($row['action_text'], 55) }}
                            @endif
                        </div>
                    </td>
                    <td data-label="Anotación" style="padding: 8px; border: 1px solid #E5E7EB; text-align: justify;">
                        <div style="line-height: 1.4;">
                            @if(isset($row['is_merged']) && $row['is_merged'] && (!isset($row['annotation']) || $row['annotation'] === '---' || empty($row['annotation'])))
                                {{ Str::limit($row['linked_annotation'] ?? '---', 60) }}
                            @else
                                {{ Str::limit($row['annotation'], 60) }}
                                @if(isset($row['is_merged']) && $row['is_merged'] && isset($row['linked_annotation']) && $row['linked_annotation'] !== '---' && $row['linked_annotation'] !== $row['annotation'])
                                    <div style="margin-top: 5px; border-top: 1px dashed #E5E7EB; padding-top: 5px;">
                                        {{ Str::limit($row['linked_annotation'], 60) }}
                                    </div>
                                @endif
                            @endif

                            @if($row['is_alert'] && !empty($row['matched_keywords']))
                                <div style="font-size: 10px; margin-top: 4px; color: #DC2626; font-style: italic; font-weight: 600;">
                                    Palabras clave: {{ $row['matched_keywords'] }}
                                </div>
                            @endif
                        </div>
                    </td>
                    <td data-label="Inicia" style="padding: 8px; border: 1px solid #E5E7EB; text-align: center;">{{ $row['term_start_date'] ?: '---' }}</td>
                    <td data-label="Finaliza" style="padding: 8px; border: 1px solid #E5E7EB; text-align: center;">{{ $row['term_end_date'] ?: '---' }}</td>
                    <td data-label="Registro" style="padding: 8px; border: 1px solid #E5E7EB; text-align: center;">{{ $row['registration_date'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{--    <div style="background-color: #F9FAFB; padding: 15px; border-radius: 8px; font-size: 13px; color: #4B5563; line-height: 1.5; border: 1px solid #E5E7EB;">--}}
    {{--        Estimado usuario, recuerde que la llegada o no de este correo no le exime de verificar la información ingresando a nuestro portal --}}
    {{--        <strong>www.estadosjudiciales.net.co</strong> con su usuario y contraseña y verificar sus movimientos.--}}
    {{--    </div>--}}

    <div style="text-align: center; margin-top: 30px;">
        <a href="{{ config('app.url') }}/processes"
           style="background-color: #24163E; color: #FFFFFF; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
            Ir a Mis Actuaciones Recientes
        </a>
    </div>
@endsection
