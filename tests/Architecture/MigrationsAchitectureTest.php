<?php

declare(strict_types=1);
//
// declare(strict_types=1);
//
// use Tests\Architecture\Services\NamespaceFileSearcherService;
//
// beforeEach(function (): void {
//    $this->baseNamespace = 'database';
//    $this->searchInNamespace = 'migrations';
//
//    $this->namespaceFileSearcherService = (new NamespaceFileSearcherService)
//        ->baseNamespace($this->baseNamespace)
//        ->searchInNamespace($this->searchInNamespace);
//
//    $this->skipIfEmptyNamespaces = function (array $namespaces): void {
//        if (empty($namespaces)) {
//            test()->markTestSkipped("No {$this->searchInNamespace} were found in {$this->baseNamespace}.");
//        }
//    };
// });
//
// it('ensures no migrations use Eloquent models', function (): void {
//    $migrationFiles = $this->namespaceFileSearcherService->getNamespacesWithFiles();
//
//    ($this->skipIfEmptyNamespaces)($migrationFiles);
//
//    $errors = [];
//
//    foreach ($migrationFiles as $file) {
//        $contents = $this->namespaceFileSearcherService->getFileContents($file);
//
//        // Verificar uso de modelos importados con "use"
//        if (preg_match('/use\s+Src\\\Domain\\\.+\\\Models\\\/i', $contents)) {
//            $errors[] = "Error in {$file}: Eloquent models cannot be imported in migrations. Use raw database operations instead.";
//        }
//
//        // Verificar referencias a modelos
//        if (preg_match('/\\\\Src\\\\Domain\\\\.+\\\\Models\\\\.+::/i', $contents)) {
//            $errors[] = "Error in {$file}: Direct references to Eloquent models are not allowed in migrations. Use raw values instead.";
//        }
//    }
//
//    // Si hay errores, mostrar todos juntos
//    expect($errors)->toBeEmpty("The following migrations use Eloquent models which is not allowed:\n".implode("\n", $errors));
// });
//
// it('ensures no migrations use PHP enums', function (): void {
//    $migrationFiles = $this->namespaceFileSearcherService->getNamespacesWithFiles();
//
//    ($this->skipIfEmptyNamespaces)($migrationFiles);
//
//    $errors = [];
//
//    foreach ($migrationFiles as $file) {
//        $contents = $this->namespaceFileSearcherService->getFileContents($file);
//
//        // Verificar uso de enums importados con "use"
//        if (preg_match('/use\s+Src\\\Domain\\\.+\\\Enums\\\/i', $contents)) {
//            $errors[] = "- {$file}";
//        }
//
//        // Verificar referencias a enums
//        if (preg_match('/\\\\Src\\\\Domain\\\\.+\\\\Enums\\\\.+::/i', $contents)) {
//            $errors[] = "- {$file}";
//        }
//
//        // Verificar declaración de enums
//        if (preg_match('/enum\s+\w+/i', $contents)) {
//            $errors[] = "- {$file}";
//        }
//    }
//
//    expect($errors)->toBeEmpty("The following migrations use PHP enums which is not allowed:\n".implode("\n", $errors));
// });
//
// it('ensures no migrations use database enums', function (): void {
//    $migrationFiles = $this->namespaceFileSearcherService->getNamespacesWithFiles();
//
//    ($this->skipIfEmptyNamespaces)($migrationFiles);
//
//    $errors = [];
//
//    foreach ($migrationFiles as $file) {
//        $contents = $this->namespaceFileSearcherService->getFileContents($file);
//
//        if (preg_match('/\->enum\(/i', $contents)) {
//            $errors[] = "- {$file}";
//        }
//    }
//
//    expect($errors)->toBeEmpty("The following migrations use database enums ('->enum()'), which is not allowed. Use PHP enums in the Application or Domain layer instead:\n".implode("\n", $errors));
// });
//
// it('ensures no migrations use default values except whitelisted ones', function (): void {
//    $migrationFiles = $this->namespaceFileSearcherService->getNamespacesWithFiles();
//
//    ($this->skipIfEmptyNamespaces)($migrationFiles);
//
//    $allowedMigrations = [
//        'database\migrations\2025_03_29_025517_add_google2fa_columns_to_app_users_table.php',
//        'database\migrations\2025_04_06_100000_create_payment_providers_table.php',
//    ];
//
//    $errors = [];
//
//    foreach ($migrationFiles as $file) {
//        $relativePath = str_replace(base_path().'/', '', $file);
//
//        if (in_array($relativePath, $allowedMigrations, true)) {
//            continue;
//        }
//
//        $contents = $this->namespaceFileSearcherService->getFileContents($file);
//
//        if (preg_match('/->default\(/i', $contents)) {
//            $errors[] = "- {$relativePath}";
//        }
//    }
//
//    expect($errors)->toBeEmpty("The following migrations use ->default(), which is generally disallowed to keep default logic in the application layer. Check the whitelist in the test if needed:\n".implode("\n",
//        $errors));
// });
//
// it('ensures no migrations use factories', function (): void {
//    $migrationFiles = $this->namespaceFileSearcherService->getNamespacesWithFiles();
//
//    ($this->skipIfEmptyNamespaces)($migrationFiles);
//
//    $errors = [];
//
//    foreach ($migrationFiles as $file) {
//        $contents = $this->namespaceFileSearcherService->getFileContents($file);
//        $relativePath = str_replace(base_path().'/', '', $file);
//
//        // Verificar importación de factories
//        if (preg_match('/use\\s+Database\\\\Factories\\\\.+\\\Factory;/i', $contents)) {
//            $errors[] = "- {$relativePath} (imports factory)";
//        }
//
//        // Verificar uso del helper factory() (legado)
//        if (preg_match('/\\bfactory\\(/i', $contents)) {
//            $errors[] = "- {$relativePath} (uses factory() helper)";
//        }
//
//        // Verificar uso de Model::factory()
//        if (preg_match('/::factory\\(/i', $contents)) {
//            $errors[] = "- {$relativePath} (uses Model::factory())";
//        }
//    }
//
//    // Eliminar duplicados si una migración coincide con múltiples patrones
//    $uniqueErrors = array_unique($errors);
//
//    expect($uniqueErrors)->toBeEmpty("The following migrations use factories, which is not allowed as they might have dev dependencies:\n".implode("\n", $uniqueErrors));
// });
