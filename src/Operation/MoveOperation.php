<?php

declare(strict_types=1);

namespace Solido\PatchManager\Operation;

use stdClass;

/** @extends AbstractOperation<stdClass> */
class MoveOperation extends AbstractOperation
{
    /**
     * {@inheritDoc}
     */
    public function execute(&$subject, $operation): void
    {
        $copyOp = new CopyOperation($this->accessor);
        $copyOp->execute($subject, $operation);

        $removeOp = new RemoveOperation($this->accessor);
        $operation = clone $operation;
        $operation->path = $operation->from;
        $removeOp->execute($subject, $operation);
    }
}
