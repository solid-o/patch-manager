<?php

declare(strict_types=1);

namespace Solido\PatchManager;

/**
 * Represents an object that can be patched (and not merge patched).
 */
interface PatchableInterface
{
    /**
     * Commit modifications to the underlying object.
     */
    public function commit(): void;
}
