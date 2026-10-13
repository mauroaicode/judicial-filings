<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Src\Application\AppUser\Process\Resources\ProcessActionDocumentHistoryResource;
use Src\Application\AppUser\Process\Services\ListProcessActionDocumentsService;
use Src\Application\AppUser\Process\Services\ProcessDetailService;
use Src\Application\Shared\Process\Services\ProcessActionDocumentAccessService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Media\Models\Media;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class ProcessActionDocumentController
{
    public function __construct(
        private ProcessActionDocumentAccessService $processActionDocumentAccessService,
        private ProcessDetailService $processDetailService,
        private ListProcessActionDocumentsService $listProcessActionDocumentsService,
    ) {}

    /**
     * Paginated document history for a process (newest first), ACL-filtered for the org.
     */
    public function index(Request $request, string $processId): LengthAwarePaginator
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();
        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $process = $this->processDetailService->handle($processId, $organization->id);
        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $perPage = max(1, min(50, (int) $request->query('per_page', 12)));
        $page = max(1, (int) $request->query('page', 1));

        /** @var LengthAwarePaginator<int, Media> $paginator */
        $paginator = $this->listProcessActionDocumentsService->handle(
            $processId,
            $organization,
            $perPage,
            $page,
        );

        $paginator->through(
            fn (Media $media): ProcessActionDocumentHistoryResource => ProcessActionDocumentHistoryResource::fromMedia($media)
        );

        return $paginator;
    }

    public function download(int $mediaId): StreamedResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();
        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $media = Media::query()->find($mediaId);

        if (! $media instanceof Media
            || $media->collection_name !== Media::COLLECTION_ACTION_DOCUMENTS
            || ! $media->model instanceof ProcessAction
        ) {
            abort(404, __('process.document_not_found'));
        }

        if (! $this->processActionDocumentAccessService->handle($media, $organization)) {
            abort(404, __('process.document_not_found'));
        }

        $disk = Storage::disk($media->disk);

        if (! $disk->exists($media->getPathRelativeToRoot())) {
            abort(404, __('process.document_not_found'));
        }

        return $disk->download(
            $media->getPathRelativeToRoot(),
            $media->file_name,
            ['Content-Type' => $media->mime_type ?? 'application/pdf'],
        );
    }
}
