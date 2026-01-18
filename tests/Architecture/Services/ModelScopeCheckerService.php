<?php

declare(strict_types=1);

namespace Tests\Architecture\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

class ModelScopeCheckerService
{
    /**
     * Lista de métodos scope que están permitidos (provenientes de paquetes como Spatie Permission)
     */
    private array $allowedScopes = [
        'scopeRole',
        'scopeWithoutRole',
        'scopePermission',
        'scopeWithoutPermission',
    ];

    /**
     * Verifica si los modelos usan métodos scope que deberían usar custom query builders
     */
    public function findModelsWithScopes(NamespaceFileSearcherService $searcherService, array $allowedScopes = []): array
    {
        $namespacesWithFiles = $searcherService->getNamespacesWithFiles();
        $modelsWithScopes = [];

        foreach ($namespacesWithFiles as $modelPath) {
            // Skip non-php files or directories
            if (! Str::endsWith($modelPath, '.php')) {
                continue;
            }

            // Convert file path to class name
            $className = $searcherService->removePhpExtensionFromNamespace($modelPath);

            // Skip if the class doesn't exist
            if (! class_exists($className)) {
                continue;
            }

            // Get reflection class for the model
            try {
                $reflectionClass = new ReflectionClass($className);

                // Skip if not a model
                if (! $reflectionClass->isSubclassOf(Model::class)) {
                    continue;
                }

                // Skip abstract classes
                if ($reflectionClass->isAbstract()) {
                    continue;
                }

                // Check for scope methods defined directly in the model (not from traits)
                $scopeMethods = $this->findScopeMethods($reflectionClass, $allowedScopes);

                // Add to list if scope methods found
                if (! empty($scopeMethods)) {
                    $modelsWithScopes[$className] = $scopeMethods;
                }
            } catch (ReflectionException $e) {
                // Skip if reflection fails
                continue;
            }
        }

        return $modelsWithScopes;
    }

    /**
     * Generates an error message for models with scopes
     */
    public function generateErrorMessage(array $modelsWithScopes): string
    {
        if (empty($modelsWithScopes)) {
            return '';
        }

        $message = "The following models use scopes instead of custom query builders:\n";

        foreach ($modelsWithScopes as $model => $scopes) {
            $message .= "- {$model} has scope methods: ".implode(', ', $scopes)."\n";
        }

        $message .= "\nConsider using custom query builders instead of scopes as per project conventions.\n";
        $message .= 'See: https://dev.to/rebelnii/how-to-build-a-custom-eloquent-builder-class-in-laravel-4bp8';

        return $message;
    }

    /**
     * Finds scope methods that are defined directly in a model class (not from traits/parent classes)
     */
    private function findScopeMethods(ReflectionClass $reflectionClass, array $allowedScopes): array
    {
        $scopeMethods = [];

        // Get all methods from the class
        $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if (in_array($method->getName(), $allowedScopes, true)) {
                // Si el método es uno de los permitidos, lo ignoramos
                continue;
            }

            // Verificar si es un método scope (comienza con "scope")
            if (! Str::startsWith($method->getName(), 'scope')) {
                continue;
            }

            // Verificar si el método está definido directamente en la clase
            // y no en una clase padre o trait
            if ($method->getDeclaringClass()->getName() !== $reflectionClass->getName()) {
                // Si es de otra clase, lo ignoramos
                continue;
            }

            // Es un método scope definido directamente en el modelo, lo añadimos a la lista
            $scopeMethods[] = $method->getName();
        }

        return $scopeMethods;
    }
}
