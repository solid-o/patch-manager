<?php

declare(strict_types=1);

namespace Solido\PatchManager\JSONPointer\Helper;

/** @extends Value<array> */
class ArrayValue extends Value
{
    /** @var array<array-key, mixed> */
    public $value; // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint

    /** @var array<array-key, mixed> */
    public $reference; // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
}
