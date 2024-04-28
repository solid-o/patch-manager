<?php

declare(strict_types=1);

namespace Solido\PatchManager\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;
use Throwable;

class ValidationFailedException extends InvalidJSONException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        Throwable|null $previous = null,
        public readonly ConstraintViolationListInterface|null $violations = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
