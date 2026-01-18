<?php

declare(strict_types=1);

namespace Tests\Architecture\Services;

/**
 * Servicio para verificar si hay clases que se usan entre diferentes contextos
 * cuando no deberían hacerlo, a menos que estén en carpetas 'Shared'.
 */
class CrossContextUsageCheckerService
{
    /**
     * Agrupa clases por contexto principal y subcontexto.
     *
     * @param  array  $namespaces  Lista de namespaces completos de clases
     * @param  string  $searchPattern  Patrón para identificar clases (ej: '\Resources\', '\Data\')
     * @return array Clases agrupadas por contexto
     */
    public function groupClassesByContext(array $namespaces, string $searchPattern): array
    {
        $classesByMainContext = [];

        foreach ($namespaces as $classFile) {
            // Extraer el contexto del namespace (ej: Admin\Appointments)
            preg_match('/Src\\\\Application\\\\([^\\\\]+)\\\\([^\\\\]+)/', $classFile, $matches);

            if (count($matches) >= 3) {
                $mainContext = $matches[1];  // Ej: Admin, App
                $subContext = $matches[2];   // Ej: Category, Brand
                $fullContext = "{$mainContext}\\{$subContext}";

                // Ignorar clases en carpetas 'Shared'
                if (stripos($fullContext, 'Shared') !== false) {
                    continue;
                }

                // Solo almacenar si es del tipo buscado
                if (str_contains($classFile, $searchPattern)) {
                    if (! isset($classesByMainContext[$mainContext])) {
                        $classesByMainContext[$mainContext] = [];
                    }
                    if (! isset($classesByMainContext[$mainContext][$subContext])) {
                        $classesByMainContext[$mainContext][$subContext] = [];
                    }

                    $classesByMainContext[$mainContext][$subContext][] = $classFile;
                }
            }
        }

        return $classesByMainContext;
    }

    /**
     * Busca usos de clases entre diferentes contextos.
     *
     * @param  array  $allFiles  Lista de todos los archivos PHP a verificar
     * @param  array  $classesByMainContext  Clases agrupadas por contexto
     * @return array Lista de violaciones encontradas
     */
    public function findCrossContextUsages(array $allFiles, array $classesByMainContext): array
    {
        $violations = [];

        foreach ($allFiles as $file) {
            $fileContents = file_get_contents($file);
            $fileNamespace = $this->extractNamespace($fileContents);

            if (empty($fileNamespace)) {
                continue;
            }

            // Extraer el contexto del archivo actual
            preg_match('/Src\\\\Application\\\\([^\\\\]+)\\\\([^\\\\]+)/', $fileNamespace, $matches);

            if (count($matches) < 3) {
                continue;
            }

            $currentMainContext = $matches[1];  // Ej: Admin
            $currentSubContext = $matches[2];   // Ej: Category
            $currentFullContext = "{$currentMainContext}\\{$currentSubContext}";

            // Ignorar archivos en carpetas 'Shared'
            if (stripos($currentFullContext, 'Shared') !== false) {
                continue;
            }

            // Verificar uso de clases de diferentes contextos principales
            foreach ($classesByMainContext as $mainContext => $subContexts) {
                // Si es un contexto diferente al actual
                if ($mainContext !== $currentMainContext) {
                    foreach ($subContexts as $subContext => $classes) {
                        foreach ($classes as $class) {
                            // Verificar si hay un import explícito de la clase
                            if (preg_match('/use\s+'.preg_quote($class, '/').'(\s|;|$)/', $fileContents)) {
                                $violations[] = [
                                    'file' => $this->getRelativePath($file),
                                    'context' => $currentFullContext,
                                    'using_class' => $class,
                                    'class_context' => "{$mainContext}\\{$subContext}",
                                    'type' => 'cross_context_import',
                                ];
                            }
                        }
                    }
                }
                // Si es el mismo contexto principal pero diferente subcontexto
                else {
                    foreach ($subContexts as $subContext => $classes) {
                        // Ignorar el mismo subcontexto
                        if ($subContext === $currentSubContext) {
                            continue;
                        }

                        foreach ($classes as $class) {
                            // Verificar si hay un import explícito de la clase
                            if (preg_match('/use\s+'.preg_quote($class, '/').'(\s|;|$)/', $fileContents)) {
                                $violations[] = [
                                    'file' => $this->getRelativePath($file),
                                    'context' => $currentFullContext,
                                    'using_class' => $class,
                                    'class_context' => "{$currentMainContext}\\{$subContext}",
                                    'type' => 'same_context_different_module',
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * Encuentra todos los archivos PHP en un directorio recursivamente.
     *
     * @param  string  $dir  Directorio a buscar
     * @return array Lista de rutas de archivos PHP
     */
    public function findAllPhpFiles(string $dir): array
    {
        $results = [];
        $files = scandir($dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir.'/'.$file;

            if (is_dir($path)) {
                $results = array_merge($results, $this->findAllPhpFiles($path));
            } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                $results[] = $path;
            }
        }

        return $results;
    }

    /**
     * Genera un mensaje de error para las violaciones encontradas.
     *
     * @param  array  $violations  Lista de violaciones
     * @param  string  $classType  Tipo de clase (Resource, Data Object, etc.)
     * @return string Mensaje de error formateado
     */
    public function generateErrorMessage(array $violations, string $classType): string
    {
        if (empty($violations)) {
            return '';
        }

        $errorMessage = "The following files use {$classType}s from different contexts:\n";
        foreach ($violations as $violation) {
            $errorMessage .= "- File in '{$violation['file']}' context: {$violation['context']}\n";
            $errorMessage .= "  Imports {$classType} from '{$violation['class_context']}' context: {$violation['using_class']}\n";
            $errorMessage .= '  Violation type: '.($violation['type'] === 'cross_context_import' ? 'Cross context import' : 'Same context but different module')."\n\n";
        }

        return $errorMessage;
    }

    /**
     * Extrae el namespace de un contenido de archivo PHP.
     *
     * @param  string  $fileContents  Contenido del archivo
     * @return string Namespace extraído
     */
    private function extractNamespace(string $fileContents): string
    {
        preg_match('/namespace\s+([^;]+);/', $fileContents, $matches);

        return $matches[1] ?? '';
    }

    /**
     * Obtiene la ruta relativa de un archivo desde la raíz del proyecto.
     *
     * @param  string  $path  Ruta completa del archivo
     * @return string Ruta relativa
     */
    private function getRelativePath(string $path): string
    {
        return str_replace(base_path().'/', '', $path);
    }
}
