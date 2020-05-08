<?php

declare(strict_types=1);

namespace Solido\PatchManager\Operation;

class AddOperation extends AbstractOperation
{
    /**
     * {@inheritdoc}
     */
    public function execute(&$subject, $operation): void
    {
        $this->accessor->setValue($subject, $operation->path, $operation->value);
    }
}
