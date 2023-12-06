<?php

declare(strict_types=1);

namespace Solido\PatchManager\JSONPointer\Helper;

use ArrayAccess;

use function is_array;
use function is_object;

/**
 * @template T
 * @property T|null $reference
 */
class Value
{
    /** @var T */
    public $value;

    /** @var T|null */
    public $reference;

    public bool|null $isRefChained;

    public static function create(mixed $value): self
    {
        if (is_array($value)) {
            $zval = new ArrayValue();
        } elseif ($value instanceof ArrayAccess) {
            $zval = new ArrayAccessValue();
        } elseif (is_object($value)) {
            $zval = new ObjectValue();
        } else {
            $zval = new self();
        }

        $zval->value = $value;

        return $zval;
    }
}
