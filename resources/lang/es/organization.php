<?php

return [
    'not_found' => 'Organización no encontrada.',
    'settings_updated' => 'Configuración de la organización actualizada.',
    'notification_channel_limits_updated' => 'Límites de canales de notificación actualizados.',
    'notification_channel_limit_reached' => 'Solo está permitido tener :limit :channel.',
    'notification_channel_invalid_type' => 'El canal no está disponible.',
    'notification_channel_invalid_email' => 'Ingresa un correo electrónico válido.',
    'notification_channel_invalid_phone' => 'Ingresa un número válido, de 8 a 15 dígitos. Puede incluir +.',
    'notification_channel_duplicate' => 'Ese destino ya está registrado en este canal.',
    'notification_channel_not_found' => 'Canal de notificación no encontrado.',
    'notification_channel_types' => [
        'email' => '{1} correo electrónico|[2,*] correos electrónicos',
        'whatsapp' => '{1} número de WhatsApp|[2,*] números de WhatsApp',
        'sms' => '{1} número de SMS|[2,*] números de SMS',
    ],

    'status_email_greeting' => 'Hola :name,',
    'status_email_details_title' => 'Detalle',
    'status_email_organization_label' => 'Organización',
    'status_email_status_label' => 'Estado',
    'status_email_next_title' => '¿Qué puedes hacer?',
    'status_email_support_label' => 'Puedes escribirnos a',

    'deactivated_email_subject' => 'Actualización sobre :organization',
    'deactivated_email_title' => 'Tu organización está temporalmente inactiva',
    'deactivated_email_intro' => 'Te escribimos para contarte un cambio importante sobre :organization. Queremos que lo sepas con claridad y con la mayor tranquilidad posible.',
    'deactivated_email_alert' => 'La organización :organization fue desactivada y, por ahora, el acceso a la plataforma asociado a ella quedó pausado. También se detuvo el seguimiento de sus procesos y las notificaciones.',
    'deactivated_email_status_value' => 'Inactiva (sin acceso)',
    'deactivated_email_next_body' => 'Para continuar usando NotiJudicial con esta organización, comunícate con el administrador o con nuestro equipo de soporte. Estaremos encantados de ayudarte a retomar el acceso cuando corresponda.',
    'deactivated_email_closing' => 'Gracias por confiar en nosotros. Seguimos aquí para acompañarte.',

    'tracking_paused_email_subject' => 'Seguimiento pausado en :organization',
    'tracking_paused_email_title' => 'El seguimiento de procesos está pausado',
    'tracking_paused_email_intro' => 'Te escribimos para contarte un cambio sobre :organization. Podrás seguir iniciando sesión, pero queremos que sepas qué implica este ajuste.',
    'tracking_paused_email_alert' => 'Por ahora no revisaremos ni notificaremos actuaciones de los procesos de :organization. Tu acceso a la plataforma se mantiene activo.',
    'tracking_paused_email_status_value' => 'Activa (seguimiento pausado)',
    'tracking_paused_email_next_body' => 'Si necesitas reactivar el seguimiento y las notificaciones, comunícate con el administrador o con nuestro equipo de soporte. Estaremos felices de ayudarte.',
    'tracking_paused_email_closing' => 'Gracias por confiar en nosotros. Seguimos aquí para acompañarte.',
];
