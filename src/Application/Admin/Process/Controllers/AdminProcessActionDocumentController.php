<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Src\Application\Admin\Process\Data\SyncMediaOrganizationsData;
use Src\Application\Admin\Process\Data\UploadProcessActionDocumentsData;
use Src\Application\Admin\Process\Services\DeleteProcessActionDocumentService;
use Src\Application\Admin\Process\Services\SyncMediaOrganizationsService;
use Src\Application\Admin\Process\Services\UploadProcessActionDocumentsService;
use Src\Application\Shared\Process\Resources\ProcessActionDocumentResource;
use Src\Domain\Media\Models\Media;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\User\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class AdminProcessActionDocumentController
{
    public function __construct(
        private UploadProcessActionDocumentsService $uploadProcessActionDocumentsService,
        private SyncMediaOrganizationsService $syncMediaOrganizationsService,
        private DeleteProcessActionDocumentService $deleteProcessActionDocumentService,
    ) {}

    /**
     * Stream autenticado (Sanctum admin). Inline para visor PDF; no usa /storage público.
     */
    public function download(int $mediaId): StreamedResponse
    {
        $media = Media::query()->find($mediaId);

        if (! $media instanceof Media
            || $media->collection_name !== Media::COLLECTION_ACTION_DOCUMENTS
            || ! $media->model instanceof ProcessAction
        ) {
            abort(404, __('process.document_not_found'));
        }

        $disk = Storage::disk($media->disk);

        if (! $disk->exists($media->getPathRelativeToRoot())) {
            abort(404, __('process.document_not_found'));
        }

        return $disk->response(
            $media->getPathRelativeToRoot(),
            $media->file_name,
            [
                'Content-Type' => $media->mime_type ?: 'application/pdf',
            ],
        );
    }

    public function store(
        string $processId,
        string $actionId,
        UploadProcessActionDocumentsData $data,
    ): JsonResponse {
        /** @var User|null $user */
        $user = auth()->user();

        $documents = $this->uploadProcessActionDocumentsService->handle(
            $processId,
            $actionId,
            $data,
            $user instanceof User ? $user : null,
        );

        return response()->json([
            'message' => __('process.documents_uploaded_successfully'),
            'documents' => $documents
                ->map(fn (Media $media): array => ProcessActionDocumentResource::fromMedia($media, true)->toArray())
                ->values()
                ->all(),
        ], 201);
    }

    public function syncOrganizations(int $mediaId, SyncMediaOrganizationsData $data): JsonResponse
    {
        $media = Media::query()->with('organizations')->find($mediaId);

        if (! $media instanceof Media
            || $media->collection_name !== Media::COLLECTION_ACTION_DOCUMENTS
            || ! $media->model instanceof ProcessAction
        ) {
            abort(404, __('process.document_not_found'));
        }

        $this->syncMediaOrganizationsService->handle($media, $data->organization_ids);
        $media->load('organizations');

        return response()->json([
            'message' => __('process.document_organizations_updated'),
            'document' => ProcessActionDocumentResource::fromMedia($media, true)->toArray(),
        ]);
    }

    public function destroy(int $mediaId): JsonResponse
    {
        $this->deleteProcessActionDocumentService->handle($mediaId);

        return response()->json([
            'message' => __('process.document_deleted_successfully'),
        ]);
    }
}
