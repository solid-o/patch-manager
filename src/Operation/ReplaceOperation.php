<?php

declare(strict_types=1);

namespace Solido\PatchManager\Operation;

use Solido\PatchManager\Exception\InvalidJSONException;
use Solido\PatchManager\JSONPointer\Path;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;

class ReplaceOperation extends AbstractOperation
{
    /**
     * {@inheritdoc}
     */
    public function execute(&$subject, $operation): void
    {
        $path = new Path($operation->path);
        $value = null;

        try {
            $value = $this->accessor->getValue($subject, $path);
        } catch (NoSuchPropertyException $e) {
            // @ignoreException
        }

        if ($value === null) {
            throw new InvalidJSONException('Element at path "' . (string) $path . '" does not exist.');
        }

        $this->accessor->setValue($subject, $operation->path, $operation->value);
    }
}
