<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withBroadcasting(
        '/broadcasting/auth',
        ['middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin.role' => \App\Http\Middleware\EnsureAdminRole::class,
            'app_user.organization_active' => \App\Http\Middleware\EnsureAppUserOrganizationActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response('', 404);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                if ($e->getStatusCode() === 404 && str_contains($e->getMessage(), 'No query results for model')) {
                    return response('', 404);
                }

                return response()->json(
                    data: [
                        'messages' => [__($e->getMessage())],
                        'code' => $e->getStatusCode(),
                    ],
                    status: $e->getStatusCode(),
                )->setStatusCode($e->getStatusCode());
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                $messages = $e->validator->getMessageBag()->getMessages();
                $mappedMessages = [];
                foreach ($messages as $fieldMessages) {
                    foreach ($fieldMessages as $errorMessage) {
                        $mappedMessages[] = $errorMessage;
                    }
                }

                // Get validation errors in the format Laravel expects
                $errors = $e->errors();

                // Laravel's assertJsonValidationErrors looks for errors in 'errors' key by default
                // But we also want to support direct field access, so we include both formats
                $responseData = [
                    'errors' => $errors, // For assertJsonValidationErrors
                ];

                // Also include errors at root level for direct access
                foreach ($errors as $key => $value) {
                    $responseData[$key] = $value;
                }

                $responseData['messages'] = $mappedMessages;
                $responseData['code'] = 422;

                return response()->json(
                    data: $responseData,
                    status: 422,
                );
            }
        });

        $exceptions->render(function (RouteNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                if (str_contains($e->getMessage(), 'login')) {
                    return response()->json(
                        [
                            'messages' => [__('auth.unauthorized')],
                            'code' => 401,
                        ],
                        status: 401,
                    );
                }

                return response()->json(
                    [
                        'messages' => ['Endpoint not found.'],
                        'code' => 404,
                    ],
                    status: 404,
                );
            }
        });
    })->create();
