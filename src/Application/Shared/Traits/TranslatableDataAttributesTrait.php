<?php

declare(strict_types=1);

namespace Src\Application\Shared\Traits;

use ReflectionClass;
use ReflectionProperty;

trait TranslatableDataAttributesTrait
{
    /**
     * Generates a mapping of public attributes to their translated labels.
     *
     * @return array<string, string> An associative array where keys are attribute names and values are their translated labels.
     */
    public static function attributes(): array
    {
        $classReflection = new ReflectionClass(static::class);

        $attributeNames = array_filter(
            array_map(fn (\ReflectionProperty $property): string => $property->getName(), $classReflection->getProperties(ReflectionProperty::IS_PUBLIC)),
            fn ($attributeName): bool => ! in_array($attributeName, self::excludedAttributesFromTranslation(), true),
        );

        return array_combine(
            $attributeNames,
            array_map(
                fn ($attributeName) => __("data.{$attributeName}") !== $attributeName
                    ? __("data.{$attributeName}")
                    : ucfirst(str_replace('_', ' ', $attributeName)),
                $attributeNames,
            ),
        );
    }

    /**
     * Specifies attributes to be excluded from translation.
     *
     * @return array<string> A list of attribute names to exclude from translation.
     */
    protected static function excludedAttributesFromTranslation(): array
    {
        return ['token', 'id', 'hash', 'roles'];
    }
}
