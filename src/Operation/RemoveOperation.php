<?php

declare(strict_types=1);

namespace Solido\PatchManager\Operation;

use ArrayAccess;
use Solido\PatchManager\Exception\InvalidJSONException;
use Solido\PatchManager\JSONPointer\Path;
use Traversable;
use function assert;
use function is_array;
use function iterator_to_array;

class RemoveOperation extends AbstractOperation
{
    /**
     * {@inheritdoc}
     */
    public function execute(&$subject, $operation): void
    {
        $path = new Path($operation->path);
        $element = $path->getElement($path->getLength() - 1);

        $pathLength = $path->getLength();
        if ($pathLength > 1) {
            $parent = $path->getParent();
            assert($parent !== null);

            $value = $this->accessor->getValue($subject, $parent);
        } else {
            $value = &$subject;
        }

        if ($value === null) {
            return;
        }

        if (is_array($value) || $value instanceof ArrayAccess) {
            unset($value[$element]);
        } elseif ($value instanceof Traversable) {
            $value = iterator_to_array($value);
            unset($value[$element]);
        } elseif ($this->accessor->isWritable($subject, $path)) {
            $this->accessor->setValue($subject, $path, null);

            return;
        } else {
            throw new InvalidJSONException('Cannot remove "' . $element . '": path does not represents a collection.');
        }

        if ($pathLength <= 1) {
            return;
        }

        $parent = $path->getParent();
        assert($parent !== null);

        $this->accessor->setValue($subject, $parent, $value);
    }
}
