<?php declare(strict_types=1);

namespace Solido\PatchManager\Tests\Operation;

use Solido\PatchManager\JSONPointer\Accessor;
use Solido\PatchManager\JSONPointer\Path;
use Solido\PatchManager\Operation\AddOperation;
use PHPUnit\Framework\TestCase;

class AddOperationTest extends TestCase
{
    private AddOperation $operation;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->operation = new AddOperation(new Accessor(), static fn (string $p) => new Path($p));
    }

    public function testShouldAddValue(): void
    {
        $obj = [];
        $this->operation->execute($obj, (object) ['path' => '/one', 'value' => 'foo']);

        self::assertEquals('foo', $obj['one']);
    }
}
