<?php

declare(strict_types=1);

namespace Solido\PatchManager\JSONPointer;

use ArrayAccess;
use Psr\Cache\CacheItemPoolInterface;
use Solido\PatchManager\JSONPointer\Helper\AccessHelper;
use Solido\PatchManager\JSONPointer\Helper\ArrayAccessValue;
use Solido\PatchManager\JSONPointer\Helper\ArrayValue;
use Solido\PatchManager\JSONPointer\Helper\ObjectValue;
use Solido\PatchManager\JSONPointer\Helper\Value;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPathInterface;
use Throwable;
use Traversable;
use TypeError;

use function array_key_exists;
use function assert;
use function count;
use function get_class;
use function in_array;
use function is_array;
use function is_iterable;
use function is_object;
use function iterator_to_array;
use function property_exists;
use function rawurlencode;
use function Safe\sprintf;
use function str_replace;

class Accessor implements PropertyAccessorInterface
{
    use AccessorTrait;

    /**
     * @internal
     */
    public const ACCESS_HAS_PROPERTY = 0;

    /**
     * @internal
     */
    public const ACCESS_TYPE = 1;

    /**
     * @internal
     */
    public const ACCESS_NAME = 2;

    /**
     * @internal
     */
    public const ACCESS_REF = 3;

    /**
     * @internal
     */
    public const ACCESS_ADDER = 4;

    /**
     * @internal
     */
    public const ACCESS_REMOVER = 5;

    /**
     * @internal
     */
    public const ACCESS_TYPE_METHOD = 0;

    /**
     * @internal
     */
    public const ACCESS_TYPE_PROPERTY = 1;

    /**
     * @internal
     */
    public const ACCESS_TYPE_ADDER_AND_REMOVER = 3;

    /**
     * @internal
     */
    public const ACCESS_TYPE_NOT_FOUND = 4;

    /**
     * @internal
     */
    public const CACHE_PREFIX_READ = 'r';

    /**
     * @internal
     */
    public const CACHE_PREFIX_WRITE = 'w';

    /**
     * @internal
     */
    public const CACHE_PREFIX_PROPERTY_PATH = 'p';

    /**
     * @var array<string, array<int, mixed>>
     * @phpstan-var array<string, array{0: bool, 1: int, 2: string, 3?: bool, 4?: string, 5?: string}>
     */
    private array $readPropertyCache;

    /**
     * @var array<string, array<int, mixed>>
     * @phpstan-var array<string, array{0: bool, 1: int, 2?: string, 3?: bool, 4?: string, 5?: string}>
     */
    private array $writePropertyCache;

    private CacheItemPoolInterface $cacheItemPool;

    public function __construct(?CacheItemPoolInterface $cacheItemPool = null)
    {
        $this->cacheItemPool = $cacheItemPool ?? new ArrayAdapter();
        $this->readPropertyCache = [];
        $this->writePropertyCache = [];
    }

    /**
     * {@inheritdoc}
     *
     * @param array | object $objectOrArray
     * @param string | PropertyPathInterface $propertyPath
     * @param mixed $value
     */
    public function setValue(&$objectOrArray, $propertyPath, $value): void
    {
        $propertyPath = $this->getPath($propertyPath);
        $appendToArray = $propertyPath->getElement($propertyPath->getLength() - 1) === '-';

        $zval = Value::create($objectOrArray);
        $zval->reference = &$objectOrArray;

        $propertyValues = $this->readPropertiesUntil($zval, $propertyPath, $propertyPath->getLength() - 1);
        $overwrite = true;

        $propertiesCount = count($propertyValues);

        for ($i = $propertiesCount - 1; 0 <= $i; --$i) {
            $zval = $propertyValues[$i];
            unset($propertyValues[$i]);

            // You only need set value for current element if:
            // 1. it's the parent of the last index element
            // OR
            // 2. its child is not passed by reference
            //
            // This may avoid unnecessary value setting process for array elements.
            // For example:
            // '/a/b/c' => 'old-value'
            // If you want to change its value to 'new-value',
            // you only need set value for '/a/b/c' and it's safe to ignore '/a/b' and '/a'
            if ($overwrite) {
                $property = $propertyPath->getElement($i);
                $val = $zval->value;

                if (is_array($val) || $val instanceof ArrayAccess) {
                    $overwrite = ! isset($zval->reference);
                    if ($overwrite) {
                        $ref = &$zval->reference;
                        $ref = $zval->value;
                    }

                    if ($appendToArray && $propertiesCount - 1 === $i) {
                        $zval->reference[] = $value;
                        $appendToArray = false;
                    } elseif ($appendToArray && $propertiesCount - 2 === $i) {
                        throw new InvalidArgumentException('Cannot append to a non-array object');
                    } else {
                        $zval->reference[$property] = $value;
                    }

                    if ($overwrite) {
                        $zval->value = $zval->reference;
                    }
                } else {
                    if ($appendToArray && $propertiesCount - 1 === $i) {
                        continue;
                    }

                    if ($appendToArray && $propertiesCount - 2 === $i) {
                        $object = $zval->value;
                        $className = get_class($object);
                        assert($className !== false);

                        $access = $this->getWriteAccessInfo($className, $property, [$value]);

                        if (! isset($access[self::ACCESS_ADDER])) {
                            throw new InvalidArgumentException('Cannot append to a non-array object');
                        }

                        $adder = $access[self::ACCESS_ADDER];
                        $object->{$adder}($value);
                        $appendToArray = false;
                    } else {
                        assert($zval instanceof ObjectValue);
                        $this->writeProperty($zval, $property, $value);
                    }
                }

                // if current element is an object
                // OR
                // if current element's reference chain is not broken - current element
                // as well as all its ancients in the property path are all passed by reference,
                // then there is no need to continue the value setting process
                if ($zval instanceof ObjectValue || isset($zval->isRefChained)) {
                    break;
                }
            }

            $value = $zval->value;
        }
    }

    /**
     * @param object | array<array-key, mixed> $objectOrArray
     * @param PropertyPathInterface | string $propertyPath
     *
     * @return mixed
     */
    protected function doGetValue($objectOrArray, $propertyPath)
    {
        $propertyPath = $this->getPath($propertyPath);
        $propertyValues = $this->readPropertiesUntil(Value::create($objectOrArray), $propertyPath, $propertyPath->getLength());

        return $propertyValues[count($propertyValues) - 1]->value;
    }

    /**
     * {@inheritdoc}
     *
     * @param array | object $objectOrArray
     * @param string | PropertyPathInterface $propertyPath
     */
    public function isReadable($objectOrArray, $propertyPath): bool
    {
        $propertyPath = $this->getPath($propertyPath);

        try {
            $this->readPropertiesUntil(Value::create($objectOrArray), $propertyPath, $propertyPath->getLength());

            return true;
        } catch (TypeError | UnexpectedTypeException | NoSuchPropertyException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param array | object $objectOrArray
     * @param string | PropertyPathInterface $propertyPath
     */
    public function isWritable($objectOrArray, $propertyPath): bool
    {
        $propertyPath = $this->getPath($propertyPath);

        try {
            $propertyValues = $this->readPropertiesUntil(Value::create($objectOrArray), $propertyPath, $propertyPath->getLength() - 1);

            $i = count($propertyValues) - 1;
            $zval = $propertyValues[$i];

            if ($zval instanceof ArrayAccessValue || $zval instanceof ArrayValue) {
                return true;
            }

            if (! $this->isPropertyWritable($zval->value, $propertyPath->getElement($i))) {
                return false;
            }

            return $zval instanceof ObjectValue;
        } catch (TypeError | UnexpectedTypeException | NoSuchPropertyException $e) {
            return false;
        }
    }

    /**
     * Gets a PropertyPath instance and caches it.
     *
     * @param string|PropertyPathInterface $propertyPath
     */
    private function getPath($propertyPath): PropertyPathInterface
    {
        if ($propertyPath instanceof PropertyPathInterface) {
            // Don't call the copy constructor has it is not needed here
            return $propertyPath;
        }

        $item = $this->cacheItemPool->getItem(self::CACHE_PREFIX_PROPERTY_PATH . str_replace('/', '.', rawurlencode($propertyPath)));
        if ($item->isHit()) {
            return $item->get();
        }

        $propertyPathInstance = new Path($propertyPath);
        $item->set($propertyPathInstance);
        $this->cacheItemPool->save($item);

        return $propertyPathInstance;
    }

    /**
     * Reads the path from an object up to a given path index.
     *
     * @param Value $zval The object value containing the object or array to read from
     * @param PropertyPathInterface $propertyPath The property path to read
     * @param int $lastIndex The index up to which should be read
     *
     * @return Value[] The values read in the path
     *
     * @throws UnexpectedTypeException if a value within the path is neither object nor array.
     * @throws NoSuchIndexException    If a non-existing index is accessed.
     */
    private function readPropertiesUntil(Value $zval, PropertyPathInterface $propertyPath, int $lastIndex): array
    {
        if (! $zval instanceof ArrayValue && ! $zval instanceof ObjectValue) {
            throw new UnexpectedTypeException($zval->value, $propertyPath, 0);
        }

        // Add the root object to the list
        /** @var Value[] $propertyValues */
        $propertyValues = [$zval];

        for ($i = 0; $i < $lastIndex; ++$i) {
            $property = $propertyPath->getElement($i);

            if ($zval instanceof ArrayAccessValue || $zval instanceof ArrayValue) {
                // Create missing nested arrays on demand
                if (
                    ($zval instanceof ArrayAccessValue && ! $zval->value->offsetExists($property)) ||
                    ($zval instanceof ArrayValue && ! isset($zval->value[$property]) && ! array_key_exists($property, $zval->value))
                ) {
                    if ($i + 1 < $propertyPath->getLength()) {
                        if (isset($zval->reference)) {
                            $zval->value[$property] = [];
                            $zval->reference = $zval->value;
                        } else {
                            $zval->value = [$property => []];
                        }
                    }
                }

                $zval = $this->readIndex($zval, $property);
            } else {
                assert($zval instanceof ObjectValue);
                $zval = $this->readProperty($zval, $property);
            }

            // the final value of the path must not be validated
            if (! $zval instanceof ObjectValue && ! $zval instanceof ArrayValue && $i + 1 < $propertyPath->getLength()) {
                throw new UnexpectedTypeException($zval->value, $propertyPath, $i + 1);
            }

            if (isset($zval->reference) && ($i === 0 || isset($propertyValues[$i - 1]->isRefChained))) {
                // Set the IS_REF_CHAINED flag to true if:
                // current property is passed by reference and
                // it is the first element in the property path or
                // the IS_REF_CHAINED flag of its parent element is true
                // Basically, this flag is true only when the reference chain from the top element to current element is not broken
                $zval->isRefChained = true;
            }

            $propertyValues[] = $zval;
        }

        return $propertyValues;
    }

    /**
     * Reads a key from an array-like structure.
     *
     * @param ArrayValue|ArrayAccessValue $zval The array containing the array or \ArrayAccess object to read from
     * @param string|int $index The key to read
     *
     * @return Value The array containing the value of the key
     *
     * @throws NoSuchIndexException If the array does not implement \ArrayAccess or it is not an array.
     */
    private function readIndex(Value $zval, $index): Value
    {
        if (isset($zval->value[$index])) {
            $result = Value::create($zval->value[$index]);

            if (isset($zval->reference)) {
                if ($result instanceof ArrayValue) {
                    $result->reference = &$zval->reference[$index];
                } else {
                    $result->reference = $result->value;
                }
            }
        }

        return $result ?? Value::create(null);
    }

    /**
     * Reads the a property from an object.
     *
     * @param ObjectValue $zval The array containing the object to read from
     * @param string $property The property to read
     *
     * @return Value The array containing the value of the property
     *
     * @throws NoSuchPropertyException if the property does not exist or is not public.
     */
    private function readProperty(ObjectValue $zval, string $property): Value
    {
        $object = $zval->value;
        $access = $this->getReadAccessInfo(get_class($object), $property);
        $camelized = AccessHelper::camelize($property);

        if ($access[self::ACCESS_TYPE] === self::ACCESS_TYPE_METHOD) {
            $result = Value::create($object->{$access[self::ACCESS_NAME]}());
        } elseif ($access[self::ACCESS_TYPE] === self::ACCESS_TYPE_PROPERTY) {
            try {
                $result = Value::create($object->{$access[self::ACCESS_NAME]});

                if (! empty($access[self::ACCESS_REF]) && isset($zval->reference)) {
                    $result->reference = &$object->{$access[self::ACCESS_NAME]};
                }
            } catch (Throwable $e) {
                throw new NoSuchPropertyException($access[self::ACCESS_NAME], 0, $e);
            }
        } elseif (! $access[self::ACCESS_HAS_PROPERTY] && (property_exists($object, $property) || property_exists($object, $camelized))) {
            // Needed to support \stdClass instances. We need to explicitly
            // exclude $access[self::ACCESS_HAS_PROPERTY], otherwise if
            // a *protected* property was found on the class, property_exists()
            // returns true, consequently the following line will result in a
            // fatal error.
            if (property_exists($object, $camelized)) {
                $property = $camelized;
            }

            $result = Value::create($object->$property);
            if (isset($zval->reference)) {
                $result->reference = &$object->$property;
            }
        } else {
            throw new NoSuchPropertyException($access[self::ACCESS_NAME]);
        }

        // Objects are always passed around by reference
        if (isset($zval->reference) && $result instanceof ObjectValue) {
            $result->reference = $result->value;
        }

        return $result;
    }

    /**
     * Guesses how to read the property value.
     *
     * @phpstan-param class-string $class
     *
     * @return array<int, mixed>
     * @phpstan-return array{0: bool, 1: int, 2: string, 3?: bool, 4?: string, 5?: string}
     */
    private function getReadAccessInfo(string $class, string $property): array
    {
        $key = rawurlencode($class) . '..' . rawurlencode($property);

        if (isset($this->readPropertyCache[$key])) {
            return $this->readPropertyCache[$key];
        }

        $item = $this->cacheItemPool->getItem(self::CACHE_PREFIX_READ . str_replace('\\', '.', $key));
        if ($item->isHit()) {
            return $this->readPropertyCache[$key] = $item->get();
        }

        $helper = new AccessHelper($class, $property);
        $access = $helper->getReadAccessInfo();

        $this->cacheItemPool->save($item->set($access));

        return $this->readPropertyCache[$key] = $access;
    }

    /**
     * Sets the value of a property in the given object.
     *
     * @param ObjectValue $zval The value containing the object to write to
     * @param string $property The property to write
     * @param mixed $value The value to write
     *
     * @throws NoSuchPropertyException if the property does not exist or is not public.
     */
    private function writeProperty(ObjectValue $zval, string $property, $value): void
    {
        $object = $zval->value;
        $access = $this->getWriteAccessInfo(get_class($object), $property, $value);
        $camelized = AccessHelper::camelize($property);

        if ($access[self::ACCESS_TYPE] === self::ACCESS_TYPE_ADDER_AND_REMOVER) {
            assert(is_iterable($value) && isset($access[self::ACCESS_ADDER], $access[self::ACCESS_REMOVER]));
            $this->writeCollection($zval, $property, $value, $access[self::ACCESS_ADDER], $access[self::ACCESS_REMOVER]);

            return;
        }

        assert(isset($access[self::ACCESS_NAME]));
        if ($access[self::ACCESS_TYPE] === self::ACCESS_TYPE_METHOD) {
            $object->{$access[self::ACCESS_NAME]}($value);
        } elseif ($access[self::ACCESS_TYPE] === self::ACCESS_TYPE_PROPERTY) {
            $object->{$access[self::ACCESS_NAME]} = $value;
        } elseif (! $access[self::ACCESS_HAS_PROPERTY] && (property_exists($object, $property) || property_exists($object, $camelized))) {
            // Needed to support \stdClass instances. We need to explicitly
            // exclude $access[self::ACCESS_HAS_PROPERTY], otherwise if
            // a *protected* property was found on the class, property_exists()
            // returns true, consequently the following line will result in a
            // fatal error.
            if (property_exists($object, $camelized)) {
                $property = $camelized;
            }

            $object->$property = $value;
        } elseif ($access[self::ACCESS_TYPE] === self::ACCESS_TYPE_NOT_FOUND) {
            throw new NoSuchPropertyException(sprintf('Could not determine access type for property "%s".', $property));
        } else {
            throw new NoSuchPropertyException($access[self::ACCESS_NAME]);
        }
    }

    /**
     * Adjusts a collection-valued property by calling add*() and remove*() methods.
     *
     * @param ObjectValue $zval The array containing the object to write to
     * @param string $property The property to write
     * @param iterable<mixed> $collection The collection to write
     * @param string $addMethod The add*() method
     * @param string $removeMethod The remove*() method
     */
    private function writeCollection(ObjectValue $zval, string $property, iterable $collection, string $addMethod, string $removeMethod): void
    {
        // At this point the add and remove methods have been found
        $previousValue = $this->readProperty($zval, $property);
        $previousValue = $previousValue->value;

        if ($previousValue instanceof Traversable) {
            $previousValue = iterator_to_array($previousValue);
        }

        if ($previousValue && is_array($previousValue)) {
            if ($collection instanceof Traversable) {
                $collection = iterator_to_array($collection);
            }

            foreach ($previousValue as $key => $item) {
                if (in_array($item, $collection, true)) {
                    continue;
                }

                unset($previousValue[$key]);
                $zval->value->{$removeMethod}($item);
            }
        } else {
            $previousValue = false;
        }

        foreach ($collection as $item) {
            if ($previousValue && in_array($item, $previousValue, true)) {
                continue;
            }

            $zval->value->{$addMethod}($item);
        }
    }

    /**
     * Guesses how to write the property value.
     *
     * @param mixed $value
     * @phpstan-param class-string $class
     *
     * @return array<int, mixed>
     * @phpstan-return array{0: bool, 1: int, 2?: string, 3?: bool, 4?: string, 5?: string}
     */
    private function getWriteAccessInfo(string $class, string $property, $value): array
    {
        $key = rawurlencode($class) . '..' . rawurlencode($property);

        if (isset($this->writePropertyCache[$key])) {
            return $this->writePropertyCache[$key];
        }

        $item = $this->cacheItemPool->getItem(self::CACHE_PREFIX_WRITE . str_replace('\\', '.', $key));
        if ($item->isHit()) {
            return $this->writePropertyCache[$key] = $item->get();
        }

        $helper = new AccessHelper($class, $property);
        $access = $helper->getWriteAccessInfo($value);

        $this->cacheItemPool->save($item->set($access));

        return $this->writePropertyCache[$key] = $access;
    }

    /**
     * Returns whether a property is writable in the given object.
     *
     * @param mixed $object The object to write to
     * @param string $property The property to write
     *
     * @return bool Whether the property is writable
     */
    private function isPropertyWritable($object, string $property): bool
    {
        if (! is_object($object)) {
            return false;
        }

        $access = $this->getWriteAccessInfo(get_class($object), $property, []);

        return $access[self::ACCESS_TYPE] === self::ACCESS_TYPE_METHOD
            || $access[self::ACCESS_TYPE] === self::ACCESS_TYPE_PROPERTY
            || $access[self::ACCESS_TYPE] === self::ACCESS_TYPE_ADDER_AND_REMOVER
            || (! $access[self::ACCESS_HAS_PROPERTY] && property_exists($object, $property));
    }
}
