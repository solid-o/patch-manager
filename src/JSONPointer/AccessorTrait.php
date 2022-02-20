<?php

declare(strict_types=1);

namespace Solido\PatchManager\JSONPointer;

use Symfony\Component\PropertyAccess\PropertyPathInterface;

use const PHP_VERSION_ID;

if (PHP_VERSION_ID >= 80000) {
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
