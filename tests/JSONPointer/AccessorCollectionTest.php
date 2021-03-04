<?php declare(strict_types=1);

namespace Solido\PatchManager\Tests\JSONPointer;

use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;

class AccessorCollectionTest_Car
{
    private $axes;

    public function __construct($axes = null)
    {
        $this->axes = $axes;
    }

    // In the test, use a name that StringUtil can't uniquely singularify
    public function addAxis($axis): void
    {
        $this->axes[] = $axis;
    }

    public function removeAxis($axis): void
    {
        foreach ($this->axes as $key => $value) {
            if ($value === $axis) {
                unset($this->axes[$key]);

                return;
            }
        }
    }

    public function getAxes()
    {
        return $this->axes;
    }
}

class AccessorCollectionTest_CarOnlyAdder
{
    public function addAxis($axis): void
    {
    }

    public function getAxes()
    {
    }
}

class AccessorCollectionTest_CarOnlyRemover
{
    public function removeAxis($axis): void
    {
    }

    public function getAxes()
    {
    }
}

class AccessorCollectionTest_CarNoAdderAndRemover
{
    public function getAxes()
    {
    }
}

class AccessorCollectionTest_CompositeCar
{
    public function getStructure()
    {
    }

    public function setStructure($structure): void
    {
    }
}

class AccessorCollectionTest_CarStructure
{
    public function addAxis($axis): void
    {
    }

    public function removeAxis($axis): void
    {
    }

    public function getAxes()
    {
    }
}

abstract class AccessorCollectionTest extends AccessorArrayAccessTest
{
    use ProphecyTrait;

    public function testSetValueCallsAdderAndRemoverForCollections(): void
    {
        $axesBefore = $this->getContainer([1 => 'second', 3 => 'fourth', 4 => 'fifth']);
        $axesMerged = $this->getContainer([1 => 'first', 2 => 'second', 3 => 'third']);
        $axesAfter = $this->getContainer([1 => 'second', 5 => 'first', 6 => 'third']);
        $axesMergedCopy = \is_object($axesMerged) ? clone $axesMerged : $axesMerged;

        // Don't use a mock in order to test whether the collections are
        // modified while iterating them
        $car = new AccessorCollectionTest_Car($axesBefore);

        $this->propertyAccessor->setValue($car, '/axes', $axesMerged);

        self::assertEquals($axesAfter, $car->getAxes());

        // The passed collection was not modified
        self::assertEquals($axesMergedCopy, $axesMerged);
    }

    public function testSetValueCallsAdderAndRemoverForNestedCollections(): void
    {
        $car = $this->prophesize(AccessorCollectionTest_CompositeCar::class);
        $structure = $this->prophesize(AccessorCollectionTest_CarStructure::class);
        $axesBefore = $this->getContainer([1 => 'second', 3 => 'fourth']);
        $axesAfter = $this->getContainer([0 => 'first', 1 => 'second', 2 => 'third']);

        $car->getStructure()->willReturn($structure);
        $structure->getAxes()->willReturn($axesBefore);
        $structure->removeAxis('fourth')->shouldBeCalled();
        $structure->addAxis('first')->shouldBeCalled();
        $structure->addAxis('third')->shouldBeCalled();

        $carMock = $car->reveal();
        $this->propertyAccessor->setValue($carMock, '/structure/axes', $axesAfter);
    }

    public function testSetValueFailsIfNoAdderNorRemoverFound(): void
    {
        $this->expectException(NoSuchPropertyException::class);
        $this->expectExceptionMessage('Could not determine access type for property "axes".');
        $car = $this->getMockBuilder(__CLASS__.'_CarNoAdderAndRemover')->getMock();
        $axesBefore = $this->getContainer([1 => 'second', 3 => 'fourth']);
        $axesAfter = $this->getContainer([0 => 'first', 1 => 'second', 2 => 'third']);

        $car
            ->method('getAxes')
            ->willReturn($axesBefore)
        ;

        $this->propertyAccessor->setValue($car, '/axes', $axesAfter);
    }

    public function testIsWritableReturnsTrueIfAdderAndRemoverExists(): void
    {
        $car = $this->getMockBuilder(__CLASS__.'_Car')->getMock();
        self::assertTrue($this->propertyAccessor->isWritable($car, '/axes'));
    }

    public function testIsWritableReturnsFalseIfOnlyAdderExists(): void
    {
        $car = $this->getMockBuilder(__CLASS__.'_CarOnlyAdder')->getMock();
        self::assertFalse($this->propertyAccessor->isWritable($car, '/axes'));
    }

    public function testIsWritableReturnsFalseIfOnlyRemoverExists(): void
    {
        $car = $this->getMockBuilder(__CLASS__.'_CarOnlyRemover')->getMock();
        self::assertFalse($this->propertyAccessor->isWritable($car, '/axes'));
    }

    public function testIsWritableReturnsFalseIfNoAdderNorRemoverExists(): void
    {
        $car = $this->getMockBuilder(__CLASS__.'_CarNoAdderAndRemover')->getMock();
        self::assertFalse($this->propertyAccessor->isWritable($car, '/axes'));
    }

    public function testSetValueFailsIfAdderAndRemoverExistButValueIsNotTraversable(): void
    {
        $this->expectException(NoSuchPropertyException::class);
        $car = $this->getMockBuilder(__CLASS__.'_Car')->getMock();

        $this->propertyAccessor->setValue($car, '/axes', 'Not an array or Traversable');
    }
}
