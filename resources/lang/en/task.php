<?php

return [
    'no_process_associated' => 'No associated process',

    'urgency_email_page_title' => 'Pending task alert',
    'urgency_email_badge_alert_1' => 'Alert (10 days)',
    'urgency_email_badge_alert_2' => 'High alert (15 days)',
    'urgency_email_badge_critical' => 'Critical (30+ days)',
    'urgency_email_badge_default' => 'Pending task',
    'urgency_email_task_details' => 'Task details',
    'urgency_email_task_title' => 'Title',
    'urgency_email_task_description' => 'Description',
    'urgency_email_process_number' => 'Filing number',
    'urgency_email_days_elapsed' => 'Days past due',
    'urgency_email_due_date' => 'Due date',
    'urgency_email_view_task_button' => 'View task',

    'urgency_email_subject_alert_1' => 'Reminder: pending task — :title',
    'urgency_email_subject_alert_2' => 'Alert: uncompleted task — :title',
    'urgency_email_subject_critical' => 'Urgent: task pending for over a month — :title',
    'urgency_email_subject_default' => 'Task alert — :title',

    'urgency_email_headline_alert_1' => 'Your task has been overdue for 10 days',
    'urgency_email_headline_alert_2' => 'Your task has been overdue for 15 days',
    'urgency_email_headline_critical' => 'Your task exceeded 30 days past due',
    'urgency_email_headline_default' => 'You have a pending task that requires attention',

    'urgency_email_body_alert_1' => 'Task «:title» has been overdue for :days days and has not been marked as completed. We recommend reviewing it soon.',
    'urgency_email_body_alert_2' => ':days days have passed since «:title» was due and it is still pending. Follow up to avoid delays.',
    'urgency_email_body_critical' => 'Task «:title» has been overdue for :days days, exceeding the one-month limit. Immediate action is required.',
    'urgency_email_body_default' => 'Task «:title» has been overdue for :days days.',

    'urgency_internal_title_alert_1' => 'Pending task — 10 days',
    'urgency_internal_title_alert_2' => 'Pending task — 15 days',
    'urgency_internal_title_critical' => 'Critical task — over 30 days',
    'urgency_internal_title_default' => 'Pending task alert',

    'urgency_internal_description_alert_1' => '«:title» (Filing: :process) has been overdue for :days days.',
    'urgency_internal_description_alert_2' => '«:title» (Filing: :process) has been overdue for :days days.',
    'urgency_internal_description_critical' => '«:title» (Filing: :process) exceeded 30 days past due (:days days).',
    'urgency_internal_description_default' => '«:title» (Filing: :process) requires attention.',

    'urgency_sms_message' => 'Pending task: :title (:days days). View: :url',
    'urgency_whatsapp_message' => 'Pending task: :title (:days days). View: :url',

    'due_reminder_email_page_title' => 'Task due date reminder',
    'due_reminder_email_task_details' => 'Task details',
    'due_reminder_email_days_remaining' => 'Days remaining',
    'due_reminder_badge' => ':days days remaining',
    'due_reminder_badge_today' => 'Due today',

    'due_reminder_email_subject' => ':days days left — :title',
    'due_reminder_email_subject_today' => 'Your task is due today — :title',
    'due_reminder_email_headline' => 'You have :days days left to complete this task',
    'due_reminder_email_headline_today' => 'Your task is due today',
    'due_reminder_email_body' => 'Task «:title» is due in :days days. Remember to mark it as completed when done.',
    'due_reminder_email_body_today' => 'Task «:title» is due today. Remember to mark it as completed when done.',

    'due_reminder_internal_title' => 'Due in :days days',
    'due_reminder_internal_title_today' => 'Task due today',
    'due_reminder_internal_description' => '«:title» (Filing: :process) is due in :days days.',
    'due_reminder_internal_description_today' => '«:title» (Filing: :process) is due today.',

    'due_reminder_sms_message' => 'Due: :title — :days days left. View: :url',
    'due_reminder_whatsapp_message' => 'Due: :title — :days days left. View: :url',
];
