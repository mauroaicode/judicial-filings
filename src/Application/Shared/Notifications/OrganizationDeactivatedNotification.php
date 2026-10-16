<?php

declare(strict_types=1);

namespace Src\Application\Shared\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;
use Src\Application\Shared\Mail\OrganizationDeactivatedMailable;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Enums\OrganizationStatusMode;
use Src\Domain\Organization\Models\Organization;

/**
 * Queued email to an app user when their organization is deactivated or tracking is paused.
 */
class OrganizationDeactivatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Organization $organization,
        private readonly OrganizationStatusMode $mode,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return [
            'mail' => 'notifications-email',
        ];
    }

    public function toMail(object $notifiable): OrganizationDeactivatedMailable
    {
        if (! $notifiable instanceof AppUser) {
            throw new InvalidArgumentException('OrganizationDeactivatedNotification expects an AppUser notifiable.');
        }

        $mailable = new OrganizationDeactivatedMailable(
            $this->organization,
            $notifiable,
            $this->mode,
        );

        /** @var string|array<int|string, string>|null $recipients */
        $recipients = $notifiable->routeNotificationFor('mail', $this);

        if (empty($recipients)) {
            return $mailable;
        }

        return $mailable->to($recipients);
    }
}
