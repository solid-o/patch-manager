<?php

declare(strict_types=1);

namespace Solido\PatchManager\JSONPointer\Helper;

use ArrayAccess;

class ArrayAccessValue extends ObjectValue
{
    /** @var ArrayAccess */
    public $value; // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint

    /** @var ArrayAccess */
    public $reference; // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
}
