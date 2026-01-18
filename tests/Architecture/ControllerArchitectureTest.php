<?php

declare(strict_types=1);

use Tests\Architecture\Services\NamespaceFileSearcherService;

beforeEach(function (): void {

    $this->baseNamespace = 'src\Application';
    $this->searchInNamespace = 'Controllers';

    $this->namespaceFileSearcherService = (new NamespaceFileSearcherService)
        ->baseNamespace($this->baseNamespace)
        ->searchInNamespace($this->searchInNamespace);

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
        expect($namespace)->toHaveSuffix('Controller');
    }
});

it('ensures that every controller class has a corresponding test', function (): void {
    $namespacesWithFiles = $this->namespaceFileSearcherService->getNamespacesWithFiles();

    ($this->skipIfEmptyNamespaces)($namespacesWithFiles);

    $missingTests = [];
    foreach ($namespacesWithFiles as $controllerPath) {

        $missingTest = null;

        $testPath = str_replace(
            ['Src\\', 'Controllers', '.php'],
            ['tests\\', 'Controllers', 'Test.php'],
            $controllerPath
        );

        $controllerFile = base_path(str_replace('\\', '/', $controllerPath));
        $testFile = base_path(str_replace('\\', '/', $testPath));

        if (! file_exists($testFile)) {
            $missingTest = str_replace(base_path('Src/'), '', $controllerFile);
        }

        if ($missingTest) {
            $missingTests[] = $missingTest;
        }
    }

    expect($missingTests)->toBeEmpty("The following controllers are missing tests:\n- ".implode("\n- ", $missingTests));
});

it('ensures no controllers exist in the default App namespace', function (): void {
    $appControllersPath = app_path('Http/Controllers');
    $foundControllers = [];

    if (is_dir($appControllersPath)) {
        $items = scandir($appControllersPath);
        foreach ($items as $item) {
            // Ignorar ., .., y el archivo base Controller.php
            if ($item === '.' || $item === '..' || $item === 'Controller.php') {
                continue;
            }

            $itemPath = $appControllersPath.DIRECTORY_SEPARATOR.$item;
            // Verificar si es un archivo PHP
            if (is_file($itemPath) && str_ends_with(strtolower($item), '.php')) {
                $foundControllers[] = $item;
            }
        }
    }

    expect($foundControllers)->toBeEmpty(
        "Controllers found in the default App namespace (app/Http/Controllers). All controllers should be placed within 'src/Application/*/*/Controllers':\n- ".implode("\n- ", $foundControllers)
    );
});
