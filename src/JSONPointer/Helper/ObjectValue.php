<?php

declare(strict_types=1);

namespace Solido\PatchManager\JSONPointer\Helper;

class ObjectValue extends Value
{
    /** @var object */
    public $value; // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint

    /** @var object|null */
    public $reference; // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
}
