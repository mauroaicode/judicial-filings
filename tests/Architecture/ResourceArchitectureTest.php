<?php

declare(strict_types=1);

use Tests\Architecture\Services\CrossContextUsageCheckerService;
use Tests\Architecture\Services\NamespaceFileSearcherService;

beforeEach(function (): void {
    $this->baseNamespace = 'src\Application';
    $this->searchInNamespace = 'Resources';

    $this->namespaceFileSearcherService = (new NamespaceFileSearcherService)
        ->baseNamespace($this->baseNamespace)
        ->searchInNamespace($this->searchInNamespace);

    $this->crossContextChecker = new CrossContextUsageCheckerService;

    $this->skipIfEmptyNamespaces = function (array $namespaces): void {
        if (empty($namespaces)) {
            test()->markTestSkipped("No {$this->searchInNamespace} were found in {$this->baseNamespace}.");
        }
    };
});

it('ensures that all directories contain the specified directory suffix', function (): void {
    $namespaces = $this->namespaceFileSearcherService->getNamespaces();

    ($this->skipIfEmptyNamespaces)($namespaces);

    $namespacesWithFiles = $this->namespaceFileSearcherService->getNamespacesWithFiles();

    ($this->skipIfEmptyNamespaces)($namespacesWithFiles);

    foreach ($namespaces as $namespace) {
        expect($namespace)->toHaveSuffix('Resource');
    }
});

it('ensures that all resources extend the Resource class', function (): void {
    $namespaces = $this->namespaceFileSearcherService->getNamespaces();

    ($this->skipIfEmptyNamespaces)($namespaces);

    $namespacesWithFiles = $this->namespaceFileSearcherService->getNamespacesWithFiles();

    $invalidResources = [];

    foreach ($namespacesWithFiles as $namespacesWithFile) {
        $resource = $this->namespaceFileSearcherService->removePhpExtensionFromNamespace($namespacesWithFile);

        try {
            expect($resource)->toExtend(Spatie\LaravelData\Resource::class);
        } catch (Exception $e) {
            $invalidResources[] = $resource;
        }
    }

    $this->assertEmpty(
        $invalidResources,
        "The following resources do not extend the Spatie\LaravelData\Resource class:\n- ".implode("\n- ", $invalidResources),
    );
});

it('ensures that all resources attributes are in snake_case', function (): void {
    $namespaces = $this->namespaceFileSearcherService->getNamespaces();

    ($this->skipIfEmptyNamespaces)($namespaces);

    $namespacesWithFiles = $this->namespaceFileSearcherService->getNamespacesWithFiles();

    $allowedAttributes = [
        'google_2fa_enabled',
        'requires_2fa',
        '_dataContext',
    ];
    $invalidResources = [];
    $invalidResourcesAttributes = [];

    foreach ($namespacesWithFiles as $namespacesWithFile) {
        $resource = $this->namespaceFileSearcherService->removePhpExtensionFromNamespace($namespacesWithFile);

        try {

            $reflectionClass = new ReflectionClass($resource);
            $properties = $reflectionClass->getProperties();

            $nonSnakeCaseAttributes = [];

            foreach ($properties as $property) {
                $propertyName = $property->getName();

                if (in_array($propertyName, $allowedAttributes, true)) {
                    continue;
                }

                try {
                    expect($propertyName)->toBeSnakeCase();
                } catch (Exception $e) {
                    $nonSnakeCaseAttributes[] = $propertyName;
                }
            }

            if (! empty($nonSnakeCaseAttributes)) {
                $invalidResources[] = $resource;
                $invalidResourcesAttributes[$resource] = $nonSnakeCaseAttributes;
            }

        } catch (Exception $e) {
            $invalidResources[] = $resource.' (Error: '.$e->getMessage().')';
        }
    }

    $errorMessage = '';
    if (! empty($invalidResources)) {
        $errorMessage = "The following resources have attributes that are not in snake_case:\n";
        foreach ($invalidResourcesAttributes as $resource => $attributes) {
            $errorMessage .= "- $resource: ".implode(', ', $attributes)."\n";
        }
    }
    $this->assertEmpty($invalidResources, $errorMessage);
});

it('ensures resources are not shared between different contexts unless in shared folder', function (): void {
    $namespaces = $this->namespaceFileSearcherService->getNamespaces();

    ($this->skipIfEmptyNamespaces)($namespaces);

    $namespacesWithFiles = $this->namespaceFileSearcherService->getNamespacesWithFiles();

    ($this->skipIfEmptyNamespaces)($namespacesWithFiles);

    // Obtener todos los archivos PHP en src/Application
    $basePath = base_path('src/Application');
    $allFiles = $this->crossContextChecker->findAllPhpFiles($basePath);

    // Agrupar recursos por contexto principal y subcontexto
    $resourcesByMainContext = $this->crossContextChecker->groupClassesByContext(
        $namespacesWithFiles,
        '\\Resources\\'
    );

    // Buscar usos de recursos entre diferentes contextos del mismo nivel
    $violations = $this->crossContextChecker->findCrossContextUsages($allFiles, $resourcesByMainContext);

    // Generar mensaje de error
    $errorMessage = $this->crossContextChecker->generateErrorMessage($violations, 'Resource');

    expect($violations)->toBeEmpty($errorMessage);
});

function getShortClassName(string $fullyQualifiedClassName): string
{
    $parts = explode('\\', $fullyQualifiedClassName);

    return end($parts);
}
