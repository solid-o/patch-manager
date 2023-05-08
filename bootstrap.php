<?php

use Composer\InstalledVersions;
use Solido\PatchManager\JSONPointer\AccessorTrait;
use Solido\PatchManager\JSONPointer\AccessorTraitPhp74;
use Solido\PatchManager\JSONPointer\AccessorTraitPhp80;

use function Safe\class_alias;

if (PHP_VERSION_ID >= 80000 && version_compare(InstalledVersions::getVersion('symfony/property-access') ?? '', '6.0.0', '>=')) {
    class_alias(AccessorTraitPhp80::class, AccessorTrait::class); // @phpstan-ignore-line
} else {
    class_alias(AccessorTraitPhp74::class, AccessorTrait::class); // @phpstan-ignore-line
}

