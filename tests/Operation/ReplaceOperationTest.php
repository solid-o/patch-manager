<?php declare(strict_types=1);

namespace Solido\PatchManager\Tests\Operation;

use Solido\PatchManager\Exception\InvalidJSONException;
use Solido\PatchManager\Operation\ReplaceOperation;
use PHPUnit\Framework\TestCase;

class ReplaceOperationTest extends TestCase
{
    private ReplaceOperation $operation;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->operation = new ReplaceOperation();
    }

    public function testShouldReplaceValueIfExists(): void
    {
        $obj = (object) ['one' => 'bar'];
        $this->operation->execute($obj, (object) ['path' => '/one', 'value' => 'foo']);

        self::assertEquals('foo', $obj->one);
    }

    public function testShouldThrowIfPathDoesNotExists(): void
    {
        $this->expectException(InvalidJSONException::class);
        $this->expectExceptionMessage('Element at path "/one" does not exist.');
        $obj = (object) [];
        $this->operation->execute($obj, (object) ['path' => '/one', 'value' => 'foo']);
    }
}
