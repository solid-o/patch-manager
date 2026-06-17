<?php

declare(strict_types=1);

namespace Solido\PatchManager\JSONPointer\Helper;

class ArrayValue extends Value
{
    /** @var array<array-key, mixed> */
    public mixed $value;

    public mixed $reference;
}
