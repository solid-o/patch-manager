<?php declare(strict_types=1);

namespace Solido\PatchManager\Tests\Fixtures\JSONPointer;

class CamelizedPropertyClass
{
    public $camelizedPropertyValue;

    public function __construct($value)
    {
        $this->camelizedPropertyValue = $value;
    }
}
