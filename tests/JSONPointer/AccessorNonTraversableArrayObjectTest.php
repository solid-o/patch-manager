<?php declare(strict_types=1);

namespace Solido\PatchManager\Tests\JSONPointer;

use Solido\PatchManager\Tests\Fixtures\JSONPointer\NonTraversableArrayObject;

class AccessorNonTraversableArrayObjectTest extends AccessorArrayAccessTest
{
    /**
     * {@inheritdoc}
     */
    protected function getContainer(array $array)
    {
        return new NonTraversableArrayObject($array);
    }
}
