<?php

declare(strict_types=1);

namespace Solido\PatchManager;

/**
 * Represents an object that can be merge patched.
 */
interface MergeablePatchableInterface extends PatchableInterface
{
    /**
     * Get type for the current object.
     */
    public function getTypeClass(): string;
}
