<?php declare(strict_types=1);

namespace Solido\PatchManager\Tests\JSONPointer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Solido\PatchManager\JSONPointer\Accessor;

abstract class AccessorArrayAccessTest extends TestCase
{
    protected Accessor $propertyAccessor;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->propertyAccessor = new Accessor();
    }

    abstract protected function getContainer(array $array);

    abstract protected static function createContainer(array $array);

    public static function getValidPropertyPaths(): iterable
    {
        return [
            [static::createContainer(['firstName' => 'Bernhard']), '/firstName', 'Bernhard'],
            [static::createContainer(['person' => static::createContainer(['firstName' => 'Bernhard'])]), '/person/firstName', 'Bernhard'],
        ];
    }

    #[DataProvider("getValidPropertyPaths")]
    public function testGetValue($collection, string $path, string $value): void
    {
        self::assertSame($value, $this->propertyAccessor->getValue($collection, $path));
    }

    #[DataProvider("getValidPropertyPaths")]
    public function testSetValue($collection, string $path, string $_value): void
    {
        $this->propertyAccessor->setValue($collection, $path, 'Updated');

        self::assertSame('Updated', $this->propertyAccessor->getValue($collection, $path));
    }

    #[DataProvider("getValidPropertyPaths")]
    public function testIsReadable($collection, string $path, string $_value): void
    {
        self::assertTrue($this->propertyAccessor->isReadable($collection, $path));
    }

    #[DataProvider("getValidPropertyPaths")]
    public function testIsWritable($collection, string $path, string $_value): void
    {
        self::assertTrue($this->propertyAccessor->isWritable($collection, $path));
    }
}
