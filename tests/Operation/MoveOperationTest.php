<?php declare(strict_types=1);

namespace Solido\PatchManager\Tests\Operation;

use Solido\PatchManager\Operation\MoveOperation;
use PHPUnit\Framework\TestCase;

class MoveOperationTest extends TestCase
{
    private MoveOperation $operation;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->operation = new MoveOperation();
    }

    public function testShouldMoveValue(): void
    {
        $obj = ['one' => 'foo'];
        $this->operation->execute($obj, (object) ['path' => '/two', 'from' => '/one']);

        self::assertArrayNotHasKey('one', $obj);
        self::assertEquals('foo', $obj['two']);
    }
}
