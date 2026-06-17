<?php

declare(strict_types=1);

namespace Solido\PatchManager;

use Closure;
use Solido\PatchManager\Exception\UnknownOperationException;
use Solido\PatchManager\JSONPointer\Accessor;
use Solido\PatchManager\JSONPointer\Path;
use Solido\PatchManager\Operation\AddOperation;
use Solido\PatchManager\Operation\CopyOperation;
use Solido\PatchManager\Operation\MoveOperation;
use Solido\PatchManager\Operation\OperationInterface;
use Solido\PatchManager\Operation\RemoveOperation;
use Solido\PatchManager\Operation\ReplaceOperation;
use Solido\PatchManager\Operation\TestOperation;
use stdClass;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class OperationFactory
{
    public const string TEST_OPERATION = 'test';
    public const string REMOVE_OPERATION = 'remove';
    public const string ADD_OPERATION = 'add';
    public const string REPLACE_OPERATION = 'replace';
    public const string COPY_OPERATION = 'copy';
    public const string MOVE_OPERATION = 'move';
    public const array OPERATION_MAP = [
        self::TEST_OPERATION => TestOperation::class,
        self::REMOVE_OPERATION => RemoveOperation::class,
        self::ADD_OPERATION => AddOperation::class,
        self::REPLACE_OPERATION => ReplaceOperation::class,
        self::COPY_OPERATION => CopyOperation::class,
        self::MOVE_OPERATION => MoveOperation::class,
    ];

    private PropertyAccessorInterface $accessor;
    private Closure $pathFactory;

    public function __construct(PropertyAccessorInterface|null $accessor = null, Closure|null $pathFactory = null)
    {
        $this->accessor = $accessor ?? new Accessor();
        $this->pathFactory = $pathFactory ?? static fn (string $path) => new Path($path);
    }

    /**
     * Creates a new Operation object.
     *
     * @throws UnknownOperationException
     */

    /** @return OperationInterface<stdClass> */
    public function factory(string $type): OperationInterface
    {
        if (! isset(self::OPERATION_MAP[$type])) {
            throw new UnknownOperationException('Unknown operation "' . $type . '" has been requested.');
        }

        $class = self::OPERATION_MAP[$type];

        return new $class($this->accessor, $this->pathFactory);
    }
}
