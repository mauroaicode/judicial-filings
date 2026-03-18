<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name'))</title>
</head>
<body style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1F2937; max-width: @yield('max_width', '600px'); margin: 0 auto; padding: 20px; background-color: #F3F0F9;">
    <div style="background-color: #FFFFFF; border-radius: 12px; box-shadow: 0 4px 12px rgba(36, 22, 62, 0.05); overflow: hidden; border: 1px solid #E5E7EB;">
        <!-- Header -->
        <div style="background-color: #24163E; color: #FFFFFF; padding: 40px 30px; text-align: center;">
            <img src="{{ config('app.url') }}/images/logo-notijudicial-grande.png" alt="{{ config('app.name') }}" style="height: 70px; width: auto; display: block; margin-left: auto; margin-right: auto;">
        </div>
        
        <!-- Content -->
        <div style="padding: 40px 30px;">
            @yield('content')
        </div>

        <!-- Footer -->
        <div style="background-color: #F9FAFB; padding: 25px 30px; text-align: center; border-top: 1px solid #E5E7EB; color: #9CA3AF; font-size: 12px;">
            <div style="margin-bottom: 15px;">
                <img src="{{ config('app.url') }}/images/logo-notijudicial.png" alt="NotiJudicial" style="height: 24px; opacity: 0.8;">
            </div>
            <p style="margin: 0; color: #4A4A4A; font-weight: 600;">{{ config('app.name') }} &copy; {{ date('Y') }}</p>
            <p style="margin: 5px 0 0 0;">Todos los derechos reservados. Este es un correo automático, por favor no respondas a esta dirección.</p>
        </div>
    </div>
</body>
</html>
