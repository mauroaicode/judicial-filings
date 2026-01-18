<?php

declare(strict_types=1);

use Tests\Architecture\Services\NamespaceFileSearcherService;

beforeEach(function (): void {
    $this->baseNamespace = 'database';
    $this->searchInNamespace = 'seeders';

    $this->namespaceFileSearcherService = (new NamespaceFileSearcherService)
        ->baseNamespace($this->baseNamespace)
        ->searchInNamespace($this->searchInNamespace);

    $this->skipIfEmptyNamespaces = function (array $namespaces): void {
        if (empty($namespaces)) {
            test()->markTestSkipped("No seeders were found in {$this->baseNamespace}.");
        }
    };
});

it('ensures DatabaseSeeder does not use factories', function (): void {
    $seederFiles = $this->namespaceFileSearcherService->getNamespacesWithFiles();

    ($this->skipIfEmptyNamespaces)($seederFiles);

    foreach ($seederFiles as $file) {
        if (str_contains($file, 'DatabaseSeeder.php')) {
            $contents = $this->namespaceFileSearcherService->getFileContents($file);

            expect($contents)->not->toContain('::factory(')
                ->and($contents)->not->toMatch('/->factory\(/');
        }
    }
});
