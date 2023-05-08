<?php

declare(strict_types=1);

namespace Solido\PatchManager\JSONPointer;

use Iterator;
use IteratorAggregate;
use Symfony\Component\PropertyAccess\Exception\InvalidPropertyPathException;
use Symfony\Component\PropertyAccess\Exception\OutOfBoundsException;
use Symfony\Component\PropertyAccess\PropertyPathInterface;
use Symfony\Component\PropertyAccess\PropertyPathIterator;

use function array_map;
use function array_pop;
use function count;
use function explode;
use function implode;
use function Safe\preg_match;
use function Safe\sprintf;
use function Safe\substr;
use function str_replace;
use function strpos;
use function urldecode;

class Path implements IteratorAggregate, PropertyPathInterface
{
    private int $length;

    /** @var string[] */
    private array $parts;

    public function __construct(string $path)
    {
        $this->decode($path);
    }

    /**
     * Gets the cleaned up path.
     */
    public function getPath(): string
    {
        return '/' . implode('/', array_map(fn ($val) => $this->escape($val), $this->parts));
    }

    /** @return Iterator<string> */
    public function getIterator(): Iterator
    {
        return new PropertyPathIterator($this);
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getParent(): ?PropertyPathInterface
    {
        if ($this->length <= 1) {
            return null;
        }

        $parent = clone $this;
        --$parent->length;
        array_pop($parent->parts);

        return $parent;
    }

    /**
     * {@inheritdoc}
     */
    public function getElements(): array
    {
        return $this->parts;
    }

    /**
     * {@inheritdoc}
     */
    public function getElement($index): string
    {
        if (! isset($this->parts[$index])) {
            throw new OutOfBoundsException(sprintf('The index %s is not within the property path', $index));
        }

        return $this->parts[$index];
    }

    /**
     * {@inheritdoc}
     */
    public function isProperty($index): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isIndex($index): bool
    {
        return false;
    }

    public function __toString(): string
    {
        return $this->getPath();
    }

    /**
     * Decodes and cleans up the path.
     */
    private function decode(string $path): void
    {
        if (strpos($path, '#') === 0) {
            $path = urldecode(substr($path, 1));
        }

        if (! empty($path) && $path[0] !== '/') {
            throw new InvalidPropertyPathException('Invalid path syntax');
        }

        $this->parts = array_map(fn ($val) => $this->unescape($val), explode('/', substr($path, 1)));
        $this->length = count($this->parts);
    }

    private function unescape(string $token): string
    {
        if (preg_match('/~[^01]/', $token)) {
            throw new InvalidPropertyPathException('Invalid path syntax');
        }

        return str_replace(['~1', '~0'], ['/', '~'], $token);
    }

    private function escape(string $token): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $token);
    }
}
