<?php

declare(strict_types=1);

namespace Solido\PatchManager\JSONPointer;

use Composer\InstalledVersions;

use function class_alias;
use function class_exists;
use function version_compare;

use const PHP_VERSION_ID;

if (! class_exists(AccessorTrait::class, false)) {
    $targetTrait = AccessorTraitPhp74::class;
    if (PHP_VERSION_ID >= 80000 && version_compare(InstalledVersions::getVersion('symfony/property-access') ?? '', '6.0.0', '>=')) {
        $targetTrait = AccessorTraitPhp80::class;
    }

    class_alias($targetTrait, AccessorTrait::class);
}

if (false) {
    trait AccessorTrait
    {
    }
}
