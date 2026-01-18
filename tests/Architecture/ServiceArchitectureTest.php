<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $servicesPath = base_path('src/Application');

    // Check if any Services directories exist
    $hasServices = false;
    if (File::exists($servicesPath)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($servicesPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir() && $file->getFilename() === 'Services') {
                $hasServices = true;
                break;
            }
        }
    }

    if (! $hasServices) {
        test()->markTestSkipped('No Services directories found in Application layer.');
    }

    $this->arch()->ignore([
        'Src\Application\AppUser\Chat\Services\OpenAIThreadManager',
        'Src\Application\AppUser\Chat\Services\OpenAIAssistantRunner',
    ]);
});

arch('ensure services in the Application layer use prefix "Service"')
    ->expect('Src\Application\*\*\Services')
    ->toBeClasses()
    ->toHaveSuffix('Service');

arch('ensure services have only \'handle\' or \'__construct\' as public methods')
    ->expect('Src\Application\*\*\Services')
    ->toBeClasses()
    ->toHaveSuffix('Service')
    ->not->toHavePublicMethodsBesides(['__construct', 'handle']);
