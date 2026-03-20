@extends('emails.layouts.email')
@section('max_width', '1200px')
@section('title', __('process.consolidated_notifications_title'))

@section('content')
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
            Estimado usuario, se han detectado nuevas actuaciones en sus procesos seguidos. A continuación el detalle consolidado:
        </p>
    </div>

    <div style="overflow-x: auto; margin-bottom: 30px;">
        <table style="width: 100%; border-collapse: collapse; min-width: 800px; font-family: sans-serif; font-size: 12px; border: 1px solid #E5E7EB;">
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
                <tr style="border-bottom: 1px solid #E5E7EB; vertical-align: top; {{ $row['is_alert'] ? 'background-color: #FEF2F2;' : '' }}">
                    <td style="padding: 8px; border: 1px solid #E5E7EB;">{{ $row['court'] }}</td>
                    <td style="padding: 8px; border: 1px solid #E5E7EB; white-space: nowrap;">{{ $row['process_number'] }}</td>
                    <td style="padding: 8px; border: 1px solid #E5E7EB;">{{ $row['demandante'] }}</td>
                    <td style="padding: 8px; border: 1px solid #E5E7EB;">{{ $row['demandado'] }}</td>
                    <td style="padding: 8px; border: 1px solid #E5E7EB; text-align: center;">{{ $row['action_date'] }}</td>
                    <td style="padding: 8px; border: 1px solid #E5E7EB;">{{ $row['action_text'] }}</td>
                    <td style="padding: 8px; border: 1px solid #E5E7EB; {{ $row['is_alert'] ? 'color: #B91C1C; font-weight: bold;' : '' }}">
                        {{ $row['annotation'] }}
                        @if($row['is_alert'] && !empty($row['matched_keywords']))
                            <div style="font-size: 10px; margin-top: 4px; color: #DC2626; font-style: italic;">
                                Palabras clave: {{ $row['matched_keywords'] }}
                            </div>
                        @endif
                    </td>
                    <td style="padding: 8px; border: 1px solid #E5E7EB; text-align: center;">{{ $row['start_date'] ?: '---' }}</td>
                    <td style="padding: 8px; border: 1px solid #E5E7EB; text-align: center;">{{ $row['end_date'] ?: '---' }}</td>
                    <td style="padding: 8px; border: 1px solid #E5E7EB; text-align: center;">{{ $row['registration_date'] }}</td>
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
        <a href="{{ config('app.url') }}/processes" style="background-color: #24163E; color: #FFFFFF; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
            Ir a Mis Actuaciones Recientes
        </a>
    </div>
@endsection
