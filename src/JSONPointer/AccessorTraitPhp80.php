<?php

declare(strict_types=1);

namespace Solido\PatchManager\JSONPointer;

use Symfony\Component\PropertyAccess\PropertyPathInterface;

// phpcs:disable Squiz.Classes.ClassFileName.NoMatch

trait AccessorTrait
{
    public function getValue(object | array $objectOrArray, PropertyPathInterface | string $propertyPath): mixed
    {
        return $this->doGetValue($objectOrArray, $propertyPath);
    }

    /**
     * @param object | array<array-key, mixed> $objectOrArray
     * @param PropertyPathInterface | string $propertyPath
     *
     * @return mixed
     */
    abstract protected function doGetValue($objectOrArray, $propertyPath);
}
