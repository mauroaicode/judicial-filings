<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

it('ensures all route URIs follow the kebab-case convention', function (): void {
    $invalidUris = [];

    // Update this array with URIs that should be ignored
    // for example, URIs that came from a package or a third-party library
    // that you can't control
    // and not routes that you created
    $ignoredUris = [
        '_ignition/execute-solution',
        '_ignition/health-check',
        '_ignition/handle-solution',
        '_ignition/update-config',
        'livewire/livewire.min.js',
        'livewire/livewire.js',
        'livewire/livewire.min.js.map',
        '_ignition/handle-solution',
    ];

    $routes = Route::getRoutes();
    foreach ($routes as $route) {
        $uri = $route->uri();

        if (in_array($uri, $ignoredUris, true)) {
            continue;
        }

        // Replace parameters between curly braces with a temporary placeholder
        // Example: 'users/{user}/edit' will be converted to 'users/param/edit'
        $uriWithoutParams = preg_replace('/\{[^}]+\}/', 'param', $uri);

        $hasCapitalLetter = (bool) preg_match('/[A-Z]/', $uriWithoutParams);

        $hasSnakeCaseCharaters = Str::contains($uriWithoutParams, '_');

        $hasSpaces = (bool) preg_match('/\s/', $uriWithoutParams);

        $hasInvalidCharacters = (bool) preg_match('/[^a-z0-9\/\-\{\}\$]/', $uriWithoutParams);

        if (
            $hasCapitalLetter ||
            $hasSnakeCaseCharaters ||
            $hasSpaces ||
            $hasInvalidCharacters
        ) {
            $invalidUris[] = $uri;
        }
    }

    $this->assertEmpty(
        $invalidUris,
        "The following route URIs dont follow the kebab-case convention:\n- ".implode("\n- ", $invalidUris),
    );
});

it('ensures no routes use anonymous functions', function (): void {

    $ignoredUris = [
        '/',
        'up',
        'storage/{path}',
    ];

    $invalidRoutes = [];

    $routes = Route::getRoutes();

    foreach ($routes as $route) {

        if (in_array($route->uri(), $ignoredUris, true)) {
            continue;
        }

        $action = $route->getAction();
        if (
            ! isset($action['controller']) ||
             Str::contains($action['controller'], 'Closure')
        ) {
            $invalidRoutes[] = $route->uri();
        }

    }

    $this->assertEmpty(
        $invalidRoutes,
        "The following routes don't use controllers:\n- ".implode("\n- ", $invalidRoutes),
    );
});
