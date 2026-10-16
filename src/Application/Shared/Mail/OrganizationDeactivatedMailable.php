<?php

declare(strict_types=1);

namespace Src\Application\Shared\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Enums\OrganizationStatusMode;
use Src\Domain\Organization\Models\Organization;

/**
 * Friendly notice when an organization is fully deactivated or tracking is paused.
 */
class OrganizationDeactivatedMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Organization $organization,
        public readonly AppUser $appUser,
        public readonly OrganizationStatusMode $mode,
    ) {}

    public function envelope(): Envelope
    {
        $subjectKey = $this->mode === OrganizationStatusMode::TrackingPaused
            ? 'organization.tracking_paused_email_subject'
            : 'organization.deactivated_email_subject';

        return new Envelope(
            subject: __($subjectKey, [
                'organization' => $this->organization->name,
            ]),
        );
    }

    public function content(): Content
    {
        $userName = trim($this->appUser->name.' '.$this->appUser->last_name);
        $prefix = $this->mode === OrganizationStatusMode::TrackingPaused
            ? 'tracking_paused'
            : 'deactivated';

        return new Content(
            view: 'emails.organization-status-changed',
            with: [
                'organization' => $this->organization,
                'userName' => $userName !== '' ? $userName : $this->appUser->name,
                'supportEmail' => config('app.support_email'),
                'title' => __("organization.{$prefix}_email_title"),
                'intro' => __("organization.{$prefix}_email_intro", [
                    'organization' => $this->organization->name,
                ]),
                'alert' => __("organization.{$prefix}_email_alert", [
                    'organization' => $this->organization->name,
                ]),
                'statusValue' => __("organization.{$prefix}_email_status_value"),
                'nextBody' => __("organization.{$prefix}_email_next_body"),
                'closing' => __("organization.{$prefix}_email_closing"),
            ],
        );
    }
}
