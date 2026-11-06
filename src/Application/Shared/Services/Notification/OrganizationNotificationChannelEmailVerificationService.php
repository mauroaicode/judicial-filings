<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Src\Application\Shared\Mail\OrganizationNotificationChannelEmailVerificationMailable;
use Src\Domain\Notification\Enums\NotificationChannelType;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;
use Src\Domain\Notification\Models\ValidatedNotificationEmail;
use Throwable;

/**
 * Decides whether a notification email still needs a confirmation link.
 */
readonly class OrganizationNotificationChannelEmailVerificationService
{
    public function validatedAtFor(string $channelType, string $channelValue): ?Carbon
    {
        if ($channelType !== NotificationChannelType::Email->value) {
            return now();
        }

        return ValidatedNotificationEmail::contains($channelValue) ? now() : null;
    }

    public function rememberPrevious(OrganizationNotificationChannel $channel, string $nextValue): void
    {
        if (
            $channel->channel_type !== NotificationChannelType::Email->value
            || $channel->validated_at === null
            || strtolower($channel->channel_value) === strtolower($nextValue)
        ) {
            return;
        }

        ValidatedNotificationEmail::remember($channel->channel_value, $channel->validated_at);
    }

    public function sendIfPending(OrganizationNotificationChannel $channel): void
    {
        if ($channel->channel_type !== NotificationChannelType::Email->value || $channel->validated_at !== null) {
            return;
        }

        $this->send($channel);
    }

    public function confirm(OrganizationNotificationChannel $channel, string $email): bool
    {
        $email = strtolower(trim($email));

        if (
            $channel->channel_type !== NotificationChannelType::Email->value
            || strtolower($channel->channel_value) !== $email
        ) {
            return false;
        }

        $validatedAt = now();
        ValidatedNotificationEmail::remember($email, $validatedAt);

        OrganizationNotificationChannel::query()
            ->where('channel_type', NotificationChannelType::Email->value)
            ->whereRaw('LOWER(channel_value) = ?', [$email])
            ->whereNull('validated_at')
            ->update(['validated_at' => $validatedAt]);

        return true;
    }

    public function resend(OrganizationNotificationChannel $channel): void
    {
        if ($channel->channel_type !== NotificationChannelType::Email->value) {
            throw ValidationException::withMessages([
                'channel_type' => __('organization.notification_channel_email_verification_not_applicable'),
            ]);
        }

        if ($channel->validated_at !== null) {
            throw ValidationException::withMessages([
                'channel_value' => __('organization.notification_channel_email_already_validated'),
            ]);
        }

        $this->send($channel);
    }

    private function send(OrganizationNotificationChannel $channel): void
    {
        $hours = max(1, (int) config('organization.notification_channel_email_verification_ttl_hours', 72));
        $confirmUrl = URL::temporarySignedRoute(
            'notification-channels.email.confirm',
            now()->addHours($hours),
            [
                'channel' => $channel->id,
                'email' => $channel->channel_value,
            ],
        );

        try {
            Mail::to($channel->channel_value)->send(new OrganizationNotificationChannelEmailVerificationMailable(
                email: $channel->channel_value,
                confirmUrl: $confirmUrl,
                expiresInHours: $hours,
            ));
        } catch (Throwable $exception) {
            Log::error('Notification channel email verification failed', [
                'channel_id' => $channel->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
