<?php declare(strict_types=1);

namespace Solido\PatchManager\Tests\Fixtures\JSONPointer;

class Ticket5775Object
{
    private $property;

    public function getProperty()
    {
        return $this->property;
    }

    private function setProperty(): void
    {
    }

    public function __set($property, $value): void
    {
        $this->$property = $value;
    }
}
