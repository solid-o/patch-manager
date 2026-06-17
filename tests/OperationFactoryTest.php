<?php declare(strict_types=1);

namespace Solido\PatchManager\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Solido\PatchManager\Exception\UnknownOperationException;
use Solido\PatchManager\OperationFactory;

class OperationFactoryTest extends TestCase
{
    private OperationFactory $factory;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->factory = new OperationFactory();
    }

    public static function getOperations(): iterable
    {
        foreach (OperationFactory::OPERATION_MAP as $operationType => $operationClass) {
            yield [$operationType, $operationClass];
        }
    }

    #[DataProvider("getOperations")]
    public function testFactoryShouldReturnAnOperationObject(string $operationType, string $operationClass): void
    {
        self::assertInstanceOf($operationClass, $this->factory->factory($operationType));
    }

    public function testFactoryShouldThrowIfOperationIsUnknown(): void
    {
        $this->expectException(UnknownOperationException::class);
        $this->factory->factory('non-existent');
    }
}
