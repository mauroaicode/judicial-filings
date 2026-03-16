<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Src\Domain\AppUser\Models\AppUser;

class ForgotPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly string $identification
    ) {
        $this->onQueue('emails_account');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        /** @var AppUser $notifiable */
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $encodedIdentification = base64_encode($this->identification);
        $resetUrl = "{$frontendUrl}/reset-password?token={$this->token}&id={$encodedIdentification}";

        return (new MailMessage)
            ->subject(__('auth.forgot_password_subject'))
            ->view('emails.auth.forgot-password', [
                'name' => $notifiable->name,
                'resetUrl' => $resetUrl,
                'expire' => config('auth.passwords.users.expire'),
            ]);
    }
}
