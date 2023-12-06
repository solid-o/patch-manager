<?php

declare(strict_types=1);

namespace Solido\PatchManager\JSONPointer;

use Symfony\Component\PropertyAccess\PropertyPathInterface;

trait AccessorTraitPhp74
{
    /**
     * {@inheritDoc}
     */
    public function getValue($objectOrArray, $propertyPath)
    {
        return $this->doGetValue($objectOrArray, $propertyPath);
    }

    /** @param object | array<array-key, mixed> $objectOrArray */
    abstract protected function doGetValue(object|array $objectOrArray, PropertyPathInterface|string $propertyPath): mixed;
}
