<?php declare(strict_types=1);

namespace Solido\PatchManager\Tests\JSONPointer;

use Solido\PatchManager\Tests\Fixtures\JSONPointer\TraversableArrayObject;

class AccessorTraversableArrayObjectTest extends AccessorCollectionTest
{
    /**
     * {@inheritdoc}
     */
    protected function getContainer(array $array)
    {
        return new TraversableArrayObject($array);
    }
}
