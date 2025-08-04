<?php

declare(strict_types=1);

namespace Core\BoundedContext\Admin\AppUser\Infrastructure\Controllers;

use Core\BoundedContext\Admin\AppUser\Application\Actions\DeleteAppUserUseCase;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

readonly class DeleteAppUserController
{
    public function __construct(
        private DeleteAppUserUseCase $deleteAppUserUseCase
    ) {}

    /**
     * Remove the specified resource from storage.
     */
    public function __invoke(string $id): Response
    {
        ($this->deleteAppUserUseCase)($id);

        return new Response(status: ResponseAlias::HTTP_NO_CONTENT);
    }
}
