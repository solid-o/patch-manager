<?php

declare(strict_types=1);

namespace Solido\PatchManager;

use Solido\DataMapper\DataMapperInterface;

/**
 * Represents an object that can be merge-patched.
 */
interface MergePatchableInterface extends PatchableInterface
{
    /**
     * Get a data mapper for the current object.
     */
    public function getDataMapper(): DataMapperInterface;
}
