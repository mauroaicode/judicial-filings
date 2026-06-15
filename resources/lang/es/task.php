<?php

return [
    'no_process_associated' => 'Sin proceso asociado',

    'urgency_email_page_title' => 'Alerta de tarea pendiente',
    'urgency_email_badge_alert_1' => 'Alerta (10 días)',
    'urgency_email_badge_alert_2' => 'Alerta alta (15 días)',
    'urgency_email_badge_critical' => 'Crítico (30+ días)',
    'urgency_email_badge_default' => 'Tarea pendiente',
    'urgency_email_task_details' => 'Detalle de la tarea',
    'urgency_email_task_title' => 'Título',
    'urgency_email_process_number' => 'Radicado',
    'urgency_email_days_elapsed' => 'Días transcurridos',
    'urgency_email_due_date' => 'Fecha de vencimiento',
    'urgency_email_view_task_button' => 'Ver tarea',

    'urgency_email_subject_alert_1' => 'Recordatorio: tarea pendiente — :title',
    'urgency_email_subject_alert_2' => 'Alerta: tarea sin cumplir — :title',
    'urgency_email_subject_critical' => 'Urgente: tarea pendiente por más de un mes — :title',
    'urgency_email_subject_default' => 'Alerta de tarea — :title',

    'urgency_email_headline_alert_1' => 'Su tarea lleva 10 días pendiente',
    'urgency_email_headline_alert_2' => 'Su tarea lleva 15 días sin cumplirse',
    'urgency_email_headline_critical' => 'Su tarea superó el límite de 30 días',
    'urgency_email_headline_default' => 'Tiene una tarea pendiente que requiere atención',

    'urgency_email_body_alert_1' => 'La tarea «:title» lleva :days días creada y aún no ha sido marcada como cumplida. Le recomendamos revisarla pronto.',
    'urgency_email_body_alert_2' => 'Han transcurrido :days días desde la creación de «:title» y sigue pendiente. Es importante dar seguimiento para evitar retrasos.',
    'urgency_email_body_critical' => 'La tarea «:title» lleva :days días pendiente, superando el límite máximo aceptable de un mes. Requiere acción inmediata.',
    'urgency_email_body_default' => 'La tarea «:title» lleva :days días pendiente.',

    'urgency_internal_title_alert_1' => 'Tarea pendiente — 10 días',
    'urgency_internal_title_alert_2' => 'Tarea pendiente — 15 días',
    'urgency_internal_title_critical' => 'Tarea crítica — más de 30 días',
    'urgency_internal_title_default' => 'Alerta de tarea pendiente',

    'urgency_internal_description_alert_1' => '«:title» (Radicado: :process) lleva :days días sin cumplirse.',
    'urgency_internal_description_alert_2' => '«:title» (Radicado: :process) acumula :days días pendiente.',
    'urgency_internal_description_critical' => '«:title» (Radicado: :process) superó los 30 días pendiente (:days días).',
    'urgency_internal_description_default' => '«:title» (Radicado: :process) requiere atención.',

    'urgency_sms_message' => 'Tarea pendiente: :title (:days días). Ver: :url',
    'urgency_whatsapp_message' => 'Tarea pendiente: :title (:days días). Ver: :url',

    'due_reminder_email_page_title' => 'Recordatorio de vencimiento de tarea',
    'due_reminder_email_task_details' => 'Detalle de la tarea',
    'due_reminder_email_days_remaining' => 'Días restantes',
    'due_reminder_badge' => ':days días restantes',
    'due_reminder_badge_today' => 'Vence hoy',

    'due_reminder_email_subject' => 'Le quedan :days días — :title',
    'due_reminder_email_subject_today' => 'Su tarea vence hoy — :title',
    'due_reminder_email_headline' => 'Le quedan :days días para cumplir esta tarea',
    'due_reminder_email_headline_today' => 'Su tarea vence hoy',
    'due_reminder_email_body' => 'La tarea «:title» vence en :days días. Recuerde marcarla como cumplida una vez finalizada.',
    'due_reminder_email_body_today' => 'La tarea «:title» vence hoy. Recuerde marcarla como cumplida una vez finalizada.',

    'due_reminder_internal_title' => 'Vencimiento en :days días',
    'due_reminder_internal_title_today' => 'Tarea vence hoy',
    'due_reminder_internal_description' => '«:title» (Radicado: :process) vence en :days días.',
    'due_reminder_internal_description_today' => '«:title» (Radicado: :process) vence hoy.',

    'due_reminder_sms_message' => 'Vencimiento: :title — :days días restantes. Ver: :url',
    'due_reminder_whatsapp_message' => 'Vencimiento: :title — :days días restantes. Ver: :url',
];
