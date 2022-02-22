<?php

declare(strict_types=1);

namespace Solido\PatchManager\Operation;

use Solido\PatchManager\Exception\OperationNotAllowedException;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;

/**
 * @template T
 */
interface OperationInterface
{
    /**
     * Executes the operation.
     *
     * @param object | array<mixed, mixed> $subject
     * @param T $operation
     *
     * @throws OperationNotAllowedException Must be thrown if the operation cannot be performed on the subject.
     * @throws NoSuchPropertyException Must be thrown if the path contains a non-existent property.
     * @throws UnexpectedTypeException Must be thrown when trying to access property on a non-object.
     * @throws TransformationFailedException Must be thrown by data-binders (forms) if the data cannot be transformed into correct form.
     */
    public function execute(&$subject, $operation): void;
}
