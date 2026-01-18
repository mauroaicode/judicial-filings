<?php

declare(strict_types=1);

namespace Tests\Architecture\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class NamespaceFileSearcherService
{
    private string $basePath;

    private string $baseNamespace;

    private string $searchInNamespace;

    /**
     * Set the base directory for the search.
     *
     * @return $this
     */
    public function baseNamespace(string $baseNamespace): self
    {
        $this->baseNamespace = $baseNamespace;

        return $this;
    }

    /**
     * Set the name of the directory or file to find.
     *
     * @return $this
     */
    public function searchInNamespace(string $searchInNamespace): self
    {
        $this->searchInNamespace = $searchInNamespace;

        return $this;
    }

    /**
     * Perform the search for the specified directory or files.
     */
    public function getNamespaces(): array
    {
        $results = [];
        $this->initializeBasePath();

        $this->processDirectories($this->basePath, $results);

        return array_map([$this, 'adjustSrcNamespace'], $results);
    }

    /**
     * Perform the search for directories and files.
     */
    public function getNamespacesWithFiles(): array
    {
        $results = [];
        $this->initializeBasePath();

        $this->processDirectoriesAndFiles($this->basePath, $results);

        return array_map([$this, 'adjustSrcNamespace'], $results);
    }

    /**
     * Formats a path by removing the base path and converting directory separators to backslashes.
     *
     * Example:
     * Input: '/path/to/project/Src/Application/Admin/Auth/Controllers/AuthController.php'
     * Output: 'Src\Application\Admin\Auth\Controllers\AuthController.php'
     */
    public function convertPathToNamespaceWithExtension(string $path): string
    {
        $basePath = realpath(base_path());

        $relativePath = Str::replaceFirst($basePath, '', $path);

        return trim(Str::replace('/', '\\', $relativePath), '\\');
    }

    public function removePhpExtensionFromNamespace(string $namespace): string
    {
        return preg_replace('/\.php$/', '', $namespace);
    }

    /**
     * Get the path of a missing test file corresponding to a controller.
     */
    public function findMissingTestForFile(string $controllerPath): ?string
    {
        $testPath = str_replace(
            ['Src\\', 'Controllers', '.php'],
            ['tests\\', 'Controllers', 'Test.php'],
            $controllerPath
        );

        $controllerFile = base_path(str_replace('\\', '/', $controllerPath));
        $testFile = base_path(str_replace('\\', '/', $testPath));

        if (! file_exists($testFile)) {
            return str_replace(base_path('Src/'), '', $controllerFile);
        }

        return null;
    }

    /**
     * Normalizes the namespace case by ensuring it starts with "Src"
     * if it begins with "src" (case-insensitive adjustment).
     *
     * @param  string  $namespace  The namespace to normalize.
     * @return string The normalized namespace.
     */
    public function adjustSrcNamespace(string $namespace): string
    {
        if (Str::startsWith($namespace, 'src')) {
            return 'Src'.substr($namespace, 3);
        }

        return $namespace;
    }

    /**
     * Gets the contents of a file from a relative path.
     *
     * @param  string  $filePath  Relative path to the file (e.g., 'database/migrations/file.php').
     * @return string The file's content.
     *
     * @throws RuntimeException If the file does not exist.
     */
    public function getFileContents(string $filePath): string
    {
        $normalizedPath = str_replace('\\', DIRECTORY_SEPARATOR, base_path($filePath));

        if (! file_exists($normalizedPath)) {
            throw new RuntimeException("File does not exist at path {$normalizedPath}");
        }

        return File::get($normalizedPath);
    }

    /**
     * Get a list of available locales that contain the specified translation file.
     *
     * @param  string  $fileName  The translation file name to search for (e.g., 'data.php').
     * @return array A list of available locales.
     */
    public function getAvailableLocalesForTranslationFile(string $fileName): array
    {
        $translationPath = resource_path('lang');
        $availableLocales = [];

        if (! is_dir($translationPath)) {
            throw new RuntimeException("The translations directory does not exist at path {$translationPath}");
        }

        foreach (File::directories($translationPath) as $localePath) {
            $locale = basename($localePath);

            if (file_exists("{$localePath}/{$fileName}")) {
                $availableLocales[] = $locale;
            }
        }

        return $availableLocales;
    }

    private function initializeBasePath(): void
    {
        $this->basePath = base_path(Str::replace('\\', '/', $this->baseNamespace));
    }

    /**
     * Recursively process directories and add matching paths to the results array.
     */
    private function processDirectories(string $path, array &$results): void
    {
        if ($this->shouldInclude($path)) {
            $results[] = $this->convertPathToNamespaceWithExtension($path);
        }

        foreach (File::directories($path) as $subDir) {
            $this->processDirectories($subDir, $results);
        }
    }

    /**
     * Recursively process directories and files, adding matching paths to the results array.
     */
    private function processDirectoriesAndFiles(string $path, array &$results): void
    {
        foreach (File::allFiles($path) as $file) {
            if ($this->shouldInclude($file->getPath())) {
                $results[] = $this->convertPathToNamespaceWithExtension($file->getPathname());
            }
        }
    }

    /**
     * Check if the path matches the baseNamespace or ends with searchInNamespace.
     */
    private function shouldInclude(string $path): bool
    {
        if (empty($this->searchInNamespace)) {
            return str_starts_with($path, $this->basePath);
        }

        return str_ends_with($path, DIRECTORY_SEPARATOR.$this->searchInNamespace);
    }
}
