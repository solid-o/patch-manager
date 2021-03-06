<?php

declare(strict_types=1);

namespace Solido\PatchManager\Operation;

use Solido\PatchManager\JSONPointer\Accessor;

abstract class AbstractOperation implements OperationInterface
{
    protected Accessor $accessor;

    public function __construct(?Accessor $accessor = null)
    {
        $this->accessor = $accessor ?? new Accessor();
    }
}
