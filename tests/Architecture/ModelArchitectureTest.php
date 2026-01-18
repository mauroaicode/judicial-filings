<?php

declare(strict_types=1);

use Tests\Architecture\Services\ModelScopeCheckerService;
use Tests\Architecture\Services\NamespaceFileSearcherService;

beforeEach(function (): void {
    $this->baseNamespace = 'src\Domain';
    $this->searchInNamespace = 'Models';

    $this->namespaceFileSearcherService = (new NamespaceFileSearcherService)
        ->baseNamespace($this->baseNamespace)
        ->searchInNamespace($this->searchInNamespace);

    $this->modelScopeCheckerService = new ModelScopeCheckerService;

    $this->skipIfEmptyNamespaces = function (array $namespaces): void {
        if (empty($namespaces)) {
            test()->markTestSkipped("No {$this->searchInNamespace} were found in {$this->baseNamespace}.");
        }
    };
});

it('ensures that models do not use scopes and use custom query builders instead', function (): void {
    $namespacesWithFiles = $this->namespaceFileSearcherService->getNamespacesWithFiles();

    ($this->skipIfEmptyNamespaces)($namespacesWithFiles);

    /**
     * Lista de métodos scope que están permitidos (provenientes de paquetes como Spatie Permission)
     */
    $allowedScopes = [
        'scopeRole',
        'scopeWithoutRole',
        'scopePermission',
        'scopeWithoutPermission',
        'scopeOnGenericTrial',
        'scopeHasExpiredGenericTrial',
    ];

    $modelsWithScopes = $this->modelScopeCheckerService->findModelsWithScopes(
        $this->namespaceFileSearcherService,
        $allowedScopes
    );

    // Generar mensaje de error para los modelos con scopes
    $message = $this->modelScopeCheckerService->generateErrorMessage($modelsWithScopes);

    expect($modelsWithScopes)->toBeEmpty($message);
});
