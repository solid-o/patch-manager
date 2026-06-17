<?php

declare(strict_types=1);

namespace Solido\PatchManager\JSONPointer\Helper;

class ObjectValue extends Value
{
    /** @var object */
    public mixed $value;

    /** @var object|null */
    public mixed $reference;
}
