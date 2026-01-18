<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Tests\Architecture\Services\CrossContextUsageCheckerService;
use Tests\Architecture\Services\NamespaceFileSearcherService;

beforeEach(function (): void {

    $this->baseNamespace = 'src\Application';
    $this->searchInNamespace = 'Data';
    $this->traitNamespace = 'Src\Application\Shared\Traits\TranslatableDataAttributesTrait';

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

it('ensures that all Data classes use the TranslatableDataAttributesTrait', function (): void {
    $namespaces = $this->namespaceFileSearcherService->getNamespaces();
    ($this->skipIfEmptyNamespaces)($namespaces);

    foreach ($namespaces as $namespace) {

        expect($namespace)->toUseTrait($this->traitNamespace);
    }
});

it('checks if data attributes have translations in all languages', function (): void {

    $locales = $this->namespaceFileSearcherService->getAvailableLocalesForTranslationFile('data.php');

    if (empty($locales)) {
        $this->fail('No translation files found for the specified languages.');
    }

    $namespacesWithFiles = $this->namespaceFileSearcherService->getNamespacesWithFiles();

    ($this->skipIfEmptyNamespaces)($namespacesWithFiles);

    $missingTranslations = [];
    $missingAttributesClasses = [];

    foreach ($namespacesWithFiles as $namespace) {
        $namespaceWithoutExtension = $this->namespaceFileSearcherService->removePhpExtensionFromNamespace($namespace);

        if (! class_exists($namespaceWithoutExtension)) {
            continue;
        }

        if (method_exists($namespaceWithoutExtension, 'attributes')) {
            $attributes = $namespaceWithoutExtension::attributes();

            foreach ($attributes as $attribute => $translation) {

                foreach ($locales as $locale) {

                    App::setLocale($locale);

                    $translationExists = Lang::hasForLocale("data.{$attribute}", $locale);

                    expect($translationExists)->toBeTrue("Translation for '{$attribute}' in class '{$namespaceWithoutExtension}' does not exist for locale '{$locale}'.");
                }
            }

            continue;
        }

        $missingAttributesClasses[] = $namespace;
    }

    if (! empty($missingAttributesClasses)) {
        $missingClasses = implode(PHP_EOL, $missingAttributesClasses);
        $this->fail("ALERT: The following classes do not have attributes:\n$missingClasses");
    }
});

it('ensures that all directories contain the specified directory suffix', function (): void {
    $namespaces = $this->namespaceFileSearcherService->getNamespaces();

    ($this->skipIfEmptyNamespaces)($namespaces);

    $namespacesWithFiles = $this->namespaceFileSearcherService->getNamespacesWithFiles();

    ($this->skipIfEmptyNamespaces)($namespacesWithFiles);

    foreach ($namespaces as $namespace) {
        expect($namespace)->toHaveSuffix('Data');
    }
});

it('ensures that all Data classes extend from Spatie\LaravelData\Data', function (): void {
    $namespacesWithFiles = $this->namespaceFileSearcherService->getNamespacesWithFiles();
    ($this->skipIfEmptyNamespaces)($namespacesWithFiles);

    $invalidClasses = [];

    foreach ($namespacesWithFiles as $namespace) {
        $namespaceWithoutExtension = $this->namespaceFileSearcherService->removePhpExtensionFromNamespace($namespace);

        if (! class_exists($namespaceWithoutExtension)) {
            continue;
        }

        $reflection = new ReflectionClass($namespaceWithoutExtension);

        if (! $reflection->isSubclassOf('Spatie\LaravelData\Data')) {
            $invalidClasses[] = $namespaceWithoutExtension;
        }
    }

    $this->assertEmpty(
        $invalidClasses,
        "The following Data classes don't extend from Spatie\LaravelData\Data or Spatie\LaravelData\Resource:\n- ".implode("\n- ", $invalidClasses),
    );
});

it('ensures that all Data class attributes are defined in snake_case', function (): void {
    $namespacesWithFiles = $this->namespaceFileSearcherService->getNamespacesWithFiles();
    ($this->skipIfEmptyNamespaces)($namespacesWithFiles);

    $invalidAttributes = [];

    foreach ($namespacesWithFiles as $namespace) {
        $namespaceWithoutExtension = $this->namespaceFileSearcherService->removePhpExtensionFromNamespace($namespace);

        if (! class_exists($namespaceWithoutExtension)) {
            continue;
        }

        $reflection = new ReflectionClass($namespaceWithoutExtension);

        // Skip if not a Data class
        if (! $reflection->isSubclassOf('Spatie\LaravelData\Data') && ! $reflection->isSubclassOf('Spatie\LaravelData\Resource')) {
            continue;
        }

        // Get constructor parameters
        $constructor = $reflection->getConstructor();

        if ($constructor) {
            $parameters = $constructor->getParameters();

            foreach ($parameters as $parameter) {
                $paramName = $parameter->getName();

                // Check if parameter is in snake_case
                // All lowercase and words separated by underscores
                $isSnakeCase = (
                    $paramName === strtolower($paramName) &&
                    ! preg_match('/[A-Z]/', $paramName)
                );

                if (! $isSnakeCase) {
                    $invalidAttributes[] = "{$namespaceWithoutExtension}::{$paramName}";
                }
            }
        }

        // Check public properties as well
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            // Skip static properties
            if ($property->isStatic()) {
                continue;
            }

            $propertyName = $property->getName();

            // Check if property is in snake_case
            $isSnakeCase = (
                $propertyName === strtolower($propertyName) &&
                ! preg_match('/[A-Z]/', $propertyName)
            );

            if (! $isSnakeCase) {
                $invalidAttributes[] = "{$namespaceWithoutExtension}::{$propertyName}";
            }
        }
    }

    $this->assertEmpty(
        $invalidAttributes,
        "The following Data class attributes are not in snake_case:\n- ".implode("\n- ", $invalidAttributes),
    );
});

it('ensures data objects are not shared between different contexts unless in shared folder', function (): void {
    $namespaces = $this->namespaceFileSearcherService->getNamespaces();

    ($this->skipIfEmptyNamespaces)($namespaces);

    $namespacesWithFiles = $this->namespaceFileSearcherService->getNamespacesWithFiles();

    ($this->skipIfEmptyNamespaces)($namespacesWithFiles);

    // Obtener todos los archivos PHP en src/Application
    $basePath = base_path('src/Application');
    $allFiles = $this->crossContextChecker->findAllPhpFiles($basePath);

    // Agrupar objetos Data por contexto principal y subcontexto
    $dataObjectsByMainContext = $this->crossContextChecker->groupClassesByContext(
        $namespacesWithFiles,
        '\\Data\\'
    );

    // Buscar usos de objetos Data entre diferentes contextos del mismo nivel
    $violations = $this->crossContextChecker->findCrossContextUsages($allFiles, $dataObjectsByMainContext);

    // Generar mensaje de error
    $errorMessage = $this->crossContextChecker->generateErrorMessage($violations, 'Data Object');

    expect($violations)->toBeEmpty($errorMessage);
});
