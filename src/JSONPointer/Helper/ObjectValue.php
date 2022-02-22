<?php

declare(strict_types=1);

namespace Solido\PatchManager\JSONPointer\Helper;

/**
 * @extends Value<object>
 */
class ObjectValue extends Value
{
    /** @var object */
    public $value; // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint

    public ?object $reference;
}
