<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Keyword\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Src\Application\AppUser\Keyword\Data\KeywordData;
use Src\Application\AppUser\Keyword\Data\KeywordFilterData;
use Src\Application\AppUser\Keyword\Resources\KeywordResource;
use Src\Application\AppUser\Keyword\Services\CreateKeywordService;
use Src\Application\AppUser\Keyword\Services\DeleteKeywordService;
use Src\Application\AppUser\Keyword\Services\ListKeywordService;
use Src\Application\AppUser\Keyword\Services\UpdateKeywordService;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Keyword\Models\Keyword;

readonly class KeywordController
{
    public function __construct(
        private ListKeywordService $listKeywordService,
        private CreateKeywordService $createKeywordService,
        private UpdateKeywordService $updateKeywordService,
        private DeleteKeywordService $deleteKeywordService
    ) {}

    /**
     * Handle the keyword listing.
     */
    public function index(KeywordFilterData $filters): LengthAwarePaginator
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();
        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $perPage = (int) request()->query('per_page', 20);
        $page = (int) request()->query('page', 1);

        return $this->listKeywordService->handle($filters, $organization->id, $perPage, $page);
    }

    /**
     * Display the specified keyword.
     */
    public function show(string $id): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();
        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        /** @var Keyword $keyword */
        $keyword = Keyword::query()
            ->whereOrganization($organization->id)
            ->findOrFail($id);

        return response()->json(KeywordResource::fromModel($keyword)->toArray());
    }

    /**
     * Handle the keyword creation.
     */
    public function store(KeywordData $data): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();
        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $keyword = $this->createKeywordService->handle($data, $organization->id);

        return response()->json(KeywordResource::fromModel($keyword)->toArray(), 201);
    }

    /**
     * Handle the keyword update.
     */
    public function update(string $id, KeywordData $data): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();
        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $keyword = $this->updateKeywordService->handle($id, $data, $organization->id);

        return response()->json(KeywordResource::fromModel($keyword)->toArray());
    }

    /**
     * Handle the keyword deletion.
     */
    public function destroy(string $id): JsonResponse
    {
        /** @var AppUser $appUser */
        $appUser = auth()->user();
        $organization = $appUser->organizations()->first();

        if (! $organization) {
            abort(422, __('process.user_has_no_organization'));
        }

        $this->deleteKeywordService->handle($id, $organization->id);

        return response()->json(null, 204);
    }
}
