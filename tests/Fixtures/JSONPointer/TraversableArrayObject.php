<?php declare(strict_types=1);

namespace Solido\PatchManager\Tests\Fixtures\JSONPointer;

/**
 * This class is a hand written simplified version of PHP native `ArrayObject`
 * class, to show that it behaves differently than the PHP native implementation.
 */
class TraversableArrayObject implements \ArrayAccess, \IteratorAggregate, \Countable, \Serializable
{
    private array $array;

    public function __construct(?array $array = null)
    {
        $this->array = $array ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset): bool
    {
        return \array_key_exists($offset, $this->array);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->array[$offset];
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value): void
    {
        if (null === $offset) {
            $this->array[] = $value;
        } else {
            $this->array[$offset] = $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset): void
    {
        unset($this->array[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->array);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return \count($this->array);
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): string
    {
        return \serialize($this->array);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized): void
    {
        $this->array = (array) \unserialize((string) $serialized);
    }

    public function __serialize(): array
    {
        return ['data' => $this->array];
    }

    public function __unserialize(array $data): void
    {
        $this->array = $data['data'];
    }
}
