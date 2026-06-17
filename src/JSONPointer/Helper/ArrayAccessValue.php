<?php

declare(strict_types=1);

namespace Solido\PatchManager\JSONPointer\Helper;

use ArrayAccess;

class ArrayAccessValue extends ObjectValue
{
    /** @var ArrayAccess<array-key, mixed> */
    public mixed $value;

    /** @var ArrayAccess<array-key, mixed> */
    public mixed $reference;
}
