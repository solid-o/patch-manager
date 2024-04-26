<?php

declare(strict_types=1);

namespace Solido\PatchManager\Operation;

use Closure;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @template T
 * @implements OperationInterface<T>
 */
abstract class AbstractOperation implements OperationInterface
{
    public function __construct(
        protected PropertyAccessorInterface $accessor,
        protected Closure $pathFactory,
    ) {
    }
}
