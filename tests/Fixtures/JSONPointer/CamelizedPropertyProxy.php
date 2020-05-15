<?php declare(strict_types=1);

namespace Solido\PatchManager\Tests\Fixtures\JSONPointer;

use ProxyManager\Proxy\ProxyInterface;

class CamelizedPropertyProxy extends CamelizedPropertyClass implements ProxyInterface
{
    private \stdClass $holder;

    public function __construct($value)
    {
        $this->holder = new \stdClass();
        $this->holder->camelizedPropertyValue = $value;

        unset($this->camelizedPropertyValue);
    }

    public function __get($name)
    {
        if ($name === 'camelizedPropertyValue') {
            return $this->holder->$name;
        }

        throw new \Exception();
    }

    public function & __set($name, $value)
    {
        if ($name === 'camelizedPropertyValue') {
            $this->holder->$name = $value;
            return $value;
        }

        throw new \Exception();
    }
}
