<?php

declare(strict_types=1);

namespace Solido\PatchManager\Operation;

use Solido\PatchManager\JSONPointer\Accessor;

/**
 * @template T
 * @implements OperationInterface<T>
 */
abstract class AbstractOperation implements OperationInterface
{
    protected Accessor $accessor;

    public function __construct(Accessor|null $accessor = null)
    {
        $this->accessor = $accessor ?? new Accessor();
    }
}
