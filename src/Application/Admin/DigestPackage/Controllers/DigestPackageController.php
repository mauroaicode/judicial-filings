<?php

declare(strict_types=1);

namespace Src\Application\Admin\DigestPackage\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Application\Admin\DigestPackage\Services\PreviewDigestPackageService;
use Src\Application\Admin\DigestPackage\Services\SendDigestPackageService;

readonly class DigestPackageController
{
    public function __construct(
        private PreviewDigestPackageService $previewService,
        private SendDigestPackageService $sendService,
    ) {}

    /**
     * Return a read-only preview of the pending digest package.
     * No notifications are sent or marked as notified.
     */
    public function preview(): JsonResponse
    {
        $resource = $this->previewService->handle();

        return response()->json($resource->toArray());
    }

    /**
     * Dispatch the pending digest package: one consolidated email per
     * organization that has unnotified actuaciones.
     */
    public function send(): JsonResponse
    {
        $resource = $this->sendService->handle();

        return response()->json($resource->toArray());
    }
}
