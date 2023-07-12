<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Solido\PatchManager\JSONPointer\AccessorTrait;
use Solido\PatchManager\JSONPointer\AccessorTraitPhp74;
use Solido\PatchManager\JSONPointer\AccessorTraitPhp80;

if (!class_exists(AccessorTrait::class, false)) {
    $targetTrait = AccessorTraitPhp74::class;
    if (PHP_VERSION_ID >= 80000 && version_compare(InstalledVersions::getVersion('symfony/property-access') ?? '', '6.0.0', '>=')) {
        $targetTrait = AccessorTraitPhp80::class;
    }

    class_alias($targetTrait, AccessorTrait::class); // @phpstan-ignore-line
}

