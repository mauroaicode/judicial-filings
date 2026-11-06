<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Config\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Src\Application\Shared\Services\Notification\OrganizationNotificationChannelEmailVerificationService;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;

readonly class ConfirmOrganizationNotificationChannelEmailController
{
    public function __construct(
        private OrganizationNotificationChannelEmailVerificationService $emailVerificationService,
    ) {}

    public function __invoke(Request $request, string $channel): RedirectResponse
    {
        if (! URL::hasCorrectSignature($request)) {
            return $this->redirectToFrontend('invalid');
        }

        if (! URL::signatureHasNotExpired($request)) {
            return $this->redirectToFrontend('expired');
        }

        $model = OrganizationNotificationChannel::query()->find($channel);
        $email = (string) $request->query('email', '');

        if (! $model instanceof OrganizationNotificationChannel || ! $this->emailVerificationService->confirm($model, $email)) {
            return $this->redirectToFrontend('invalid');
        }

        return $this->redirectToFrontend('ok');
    }

    private function redirectToFrontend(string $status): RedirectResponse
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        return redirect()->away($base.'/confirmar-correo?estado='.$status);
    }
}
