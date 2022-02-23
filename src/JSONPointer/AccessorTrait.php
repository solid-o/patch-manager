<?php

declare(strict_types=1);

namespace Solido\PatchManager\JSONPointer;

use Composer\InstalledVersions;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

use function version_compare;

use const PHP_VERSION_ID;

if (PHP_VERSION_ID >= 80000 && version_compare(InstalledVersions::getVersion('symfony/property-access') ?? '', '6.0.0', '>=')) {
    include __DIR__ . '/AccessorTraitPhp80.php';
} else {
    trait AccessorTrait
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
}
