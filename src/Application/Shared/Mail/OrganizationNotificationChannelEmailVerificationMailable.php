<?php

declare(strict_types=1);

namespace Src\Application\Shared\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationNotificationChannelEmailVerificationMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $email,
        public readonly string $confirmUrl,
        public readonly int $expiresInHours,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('organization.notification_channel_email_verification_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification-channel-email-verification',
        );
    }
}
