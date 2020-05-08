<?php declare(strict_types=1);

namespace Solido\PatchManager\Tests\Fixtures\JSONPointer;

class TestClassIsWritable
{
    /**
     * @var mixed
     */
    protected $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }
}
