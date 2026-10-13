<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Src\Application\Admin\Process\Data\UploadProcessActionDocumentsData;
use Src\Domain\Media\Models\Media;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\User\Models\User;

readonly class UploadProcessActionDocumentsService
{
    public function __construct(
        private SyncMediaOrganizationsService $syncMediaOrganizationsService,
        private NotifyProcessActionDocumentUploadedService $notifyProcessActionDocumentUploadedService,
    ) {}

    /**
     * @return Collection<int, Media>
     */
    public function handle(
        string $processId,
        string $actionId,
        UploadProcessActionDocumentsData $data,
        ?User $uploader = null,
    ): Collection {
        $process = Process::query()->where('id', $processId)->first();
        if (! $process instanceof Process) {
            abort(404, __('process.not_found'));
        }

        $action = ProcessAction::query()
            ->where('id', $actionId)
            ->where('process_id', $processId)
            ->first();

        if (! $action instanceof ProcessAction) {
            abort(404, __('process.action_not_found'));
        }

        $organizationIds = collect($data->organization_ids)->unique()->values()->all();

        /** @var Collection<int, Media> $created */
        $created = DB::transaction(function () use ($action, $data, $organizationIds, $uploader): Collection {
            $items = collect();

            /** @var UploadedFile $file */
            foreach ($data->files as $file) {
                $media = $action
                    ->addMedia($file)
                    ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'document')
                    ->usingFileName($file->getClientOriginalName())
                    ->withCustomProperties([
                        'uploaded_by' => $uploader?->id,
                    ])
                    ->toMediaCollection(Media::COLLECTION_ACTION_DOCUMENTS);

                /** @var Media $media */
                $this->syncMediaOrganizationsService->handle($media, $organizationIds);
                $media->load('organizations');
                $items->push($media);
            }

            return $items;
        });

        $this->notifyProcessActionDocumentUploadedService->handle($action, $created);

        return $created;
    }
}
