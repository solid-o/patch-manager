<?php

declare(strict_types=1);

namespace Solido\PatchManager\JSONPointer;

use Composer\InstalledVersions;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

use function version_compare;

use const PHP_VERSION_ID;

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

if (PHP_VERSION_ID >= 80000 && version_compare(InstalledVersions::getVersion('symfony/property-access') ?? '', '6.0.0', '>=')) {
    class_alias(AccessorTraitPhp80::class, AccessorTrait::class);
} else {
    class_alias(AccessorTraitPhp74::class, AccessorTrait::class);
}
