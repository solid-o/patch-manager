<?php declare(strict_types=1);

namespace Solido\PatchManager\Tests\Operation;

use Doctrine\Common\Collections\ArrayCollection;
use Solido\PatchManager\Exception\InvalidJSONException;
use Solido\PatchManager\JSONPointer\Accessor;
use Solido\PatchManager\JSONPointer\Path;
use Solido\PatchManager\Operation\RemoveOperation;
use PHPUnit\Framework\TestCase;

class RemoveOperationTest extends TestCase
{
    private RemoveOperation $operation;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->operation = new RemoveOperation(new Accessor(), static fn (string $p) => new Path($p));
    }

    public function testShouldRemoveValue(): void
    {
        $obj = ['one' => 'bar'];
        $this->operation->execute($obj, (object) ['path' => '/one']);

        self::assertArrayNotHasKey('one', $obj);
    }

    public function testShouldRemoveValueNested(): void
    {
        $obj = ['one' => ['bar' => ['baz', 'two']]];
        $this->operation->execute($obj, (object) ['path' => '/one/bar/1']);

        self::assertCount(1, $obj['one']['bar']);
    }

    public function testShouldRemoveValueNestedCollection(): void
    {
        $obj = ['one' => new ArrayCollection(['bar' => ['baz', 'two']])];
        $this->operation->execute($obj, (object) ['path' => '/one/bar/1']);

        self::assertCount(1, $obj['one']['bar']);
    }

    public function testShouldRemoveValueNestedIterable(): void
    {
        $iterable = new class() implements \IteratorAggregate {
            public function getIterator(): \Traversable
            {
                return new \ArrayIterator(['baz', 'two']);
            }
        };

        $obj = ['one' => ['bar' => $iterable]];
        $this->operation->execute($obj, (object) ['path' => '/one/bar/1']);

        self::assertCount(1, $obj['one']['bar']);
    }

    public function testShouldRemoveValueNull(): void
    {
        $obj = ['one' => ['bar' => null]];
        $this->operation->execute($obj, (object) ['path' => '/one/bar/1']);

        self::assertNull($obj['one']['bar']);
    }

    public function testShouldRemoveValueFromObject(): void
    {
        $obj = (object) ['one' => 'bar'];
        $this->operation->execute($obj, (object) ['path' => '/one']);

        self::assertFalse(isset($obj->one));
    }

    public function testShouldRemoveWhenNullShouldNotThrow(): void
    {
        $obj = (object) ['one' => null];
        $this->operation->execute($obj, (object) ['path' => '/one']);

        self::assertFalse(isset($obj->one));
    }

    public function testShouldRemoveShouldUnsetIfObjectHasArrayAccess(): void
    {
        $obj = new \ArrayObject(['one' => 'bar']);
        $this->operation->execute($obj, (object) ['path' => '/one']);

        self::assertFalse(isset($obj['one']));
    }

    public function testShouldThrowIfPropertyIsNotAccessible(): void
    {
        $this->expectException(InvalidJSONException::class);
        $obj = new class() {
            private $elements = [];
        };
        $this->operation->execute($obj, (object) ['path' => '/one']);
    }
}
