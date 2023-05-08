<?php

declare(strict_types=1);

namespace Solido\PatchManager\JSONPointer;

use Symfony\Component\PropertyAccess\PropertyPathInterface;

trait AccessorTraitPhp74
{
    /**
     * {@inheritdoc}
     */
    public function getValue($objectOrArray, $propertyPath)
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
