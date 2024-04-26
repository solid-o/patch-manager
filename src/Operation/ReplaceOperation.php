<?php

declare(strict_types=1);

namespace Solido\PatchManager\Operation;

use Solido\PatchManager\Exception\InvalidJSONException;
use stdClass;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;

/** @extends AbstractOperation<stdClass> */
class ReplaceOperation extends AbstractOperation
{
    /**
     * {@inheritDoc}
     */
    public function execute(&$subject, $operation): void
    {
        $path = ($this->pathFactory)($operation->path);
        $value = null;

        try {
            $value = $this->accessor->getValue($subject, $path);
        } catch (NoSuchPropertyException) {
            // @ignoreException
        }

        if ($value === null) {
            throw new InvalidJSONException('Element at path "' . (string) $path . '" does not exist.');
        }

        $this->accessor->setValue($subject, $operation->path, $operation->value);
    }
}
