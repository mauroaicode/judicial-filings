<?php


namespace Core\BoundedContext\Admin\Auth\Infrastructure\Controllers;

use Core\BoundedContext\Admin\Auth\Application\Actions\LoginUseCase;
use Core\BoundedContext\Admin\Auth\Application\Resources\LoginResource;
use Core\BoundedContext\Admin\Auth\Infrastructure\Data\LoginData;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

readonly class LoginController
{
    public function __construct(
        private LoginUseCase $loginUseCase
    ) {}

    /**
     * Handle user login
     */
    public function __invoke(LoginData $data): JsonResponse
    {

        $authResource = ($this->loginUseCase)($data->email, $data->password);

        return response()->json($authResource->toArray());
    }
}
