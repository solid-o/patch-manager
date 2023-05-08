<?php

declare(strict_types=1);

namespace Solido\PatchManager\Operation;

use Solido\PatchManager\Exception\InvalidJSONException;
use stdClass;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;

/** @extends AbstractOperation<stdClass> */
class CopyOperation extends AbstractOperation
{
    /**
     * {@inheritdoc}
     */
    public function execute(&$subject, $operation): void
    {
        try {
            $value = $this->accessor->getValue($subject, $operation->from);
        } catch (NoSuchPropertyException $e) {
            throw new InvalidJSONException('Element at path "' . $operation->from . '" does not exist', 0, $e);
        }

        $this->accessor->setValue($subject, $operation->path, $value);
    }
}
