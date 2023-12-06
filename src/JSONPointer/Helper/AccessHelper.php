<?php

declare(strict_types=1);

namespace Solido\PatchManager\JSONPointer\Helper;

use Doctrine\Common\Persistence\Proxy as CommonProxy;
use Doctrine\Persistence\Proxy as PersistenceProxy;
use ProxyManager\Proxy\ProxyInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use Solido\PatchManager\JSONPointer\Accessor;
use Symfony\Component\Inflector\Inflector;
use Symfony\Component\String\Inflector\EnglishInflector;
use Symfony\Component\String\Inflector\InflectorInterface;

use function array_map;
use function assert;
use function class_exists;
use function gettype;
use function implode;
use function interface_exists;
use function is_iterable;
use function is_object;
use function lcfirst;
use function sprintf;
use function str_replace;
use function ucwords;

/** @internal */
final class AccessHelper
{
    private ReflectionClass $reflectionClass;
    private ReflectionProperty|null $reflectionProperty;
    private string $camelized;

    /** @phpstan-var InflectorInterface */
    private object $inflector;

    /**
     * @phpstan-param class-string $class
     *
     * @throws ReflectionException
     */
    public function __construct(string $class, private string $property)
    {
        $this->reflectionClass = new ReflectionClass($class);
        if (
            (interface_exists(ProxyInterface::class) && $this->reflectionClass->implementsInterface(ProxyInterface::class)) ||
            (interface_exists(PersistenceProxy::class) && $this->reflectionClass->implementsInterface(PersistenceProxy::class)) ||
            (interface_exists(CommonProxy::class) && $this->reflectionClass->implementsInterface(CommonProxy::class))
        ) {
            $reflectionClass = $this->reflectionClass->getParentClass();
            assert($reflectionClass !== false);

            $this->reflectionClass = $reflectionClass;
        }

        $this->camelized = self::camelize($property);
        $this->reflectionProperty = null;

        if ($this->reflectionClass->hasProperty($property)) {
            $this->reflectionProperty = $this->reflectionClass->getProperty($property);
        } elseif ($this->reflectionClass->hasProperty($this->camelized)) {
            $this->reflectionProperty = $this->reflectionClass->getProperty($this->camelized);
        }

        if (! class_exists(EnglishInflector::class) && ! class_exists(Inflector::class)) {
            throw new RuntimeException('One of Symfony String or Symfony Inflector must be installed to make patch manager to work');
        }

        /** @phpstan-ignore-next-line */
        $this->inflector = class_exists(EnglishInflector::class) ? new EnglishInflector() : new class {
            /** @return string[] */
            public function singularize(string $plural): array
            {
                return (array) Inflector::singularize($plural);
            }

            /** @return string[] */
            public function pluralize(string $singular): array
            {
                return (array) Inflector::pluralize($singular);
            }
        };
    }

    /**
     * Gets the read access information.
     *
     * @return array<int, mixed>
     * @phpstan-return array{0: bool, 1: int, 2: string, 3?: bool, 4?: string, 5?: string}
     */
    public function getReadAccessInfo(): array
    {
        $hasProperty = $this->reflectionProperty !== null;

        $methods = ['get' . $this->camelized, $this->camelized, 'is' . $this->camelized, 'has' . $this->camelized];
        foreach ($methods as $method) {
            if (! $this->reflectionClass->hasMethod($method) || ! $this->reflectionClass->getMethod($method)->isPublic()) {
                continue;
            }

            return [
                Accessor::ACCESS_HAS_PROPERTY => $hasProperty,
                Accessor::ACCESS_TYPE => Accessor::ACCESS_TYPE_METHOD,
                Accessor::ACCESS_NAME => $method,
            ];
        }

        if ($this->reflectionClass->hasMethod('__get') && $this->reflectionClass->getMethod('__get')->isPublic()) {
            return [
                Accessor::ACCESS_HAS_PROPERTY => $hasProperty,
                Accessor::ACCESS_TYPE => Accessor::ACCESS_TYPE_PROPERTY,
                Accessor::ACCESS_NAME => $this->property,
                Accessor::ACCESS_REF => false,
            ];
        }

        if ($this->reflectionProperty !== null && $this->reflectionProperty->isPublic()) {
            return [
                Accessor::ACCESS_HAS_PROPERTY => true,
                Accessor::ACCESS_TYPE => Accessor::ACCESS_TYPE_PROPERTY,
                Accessor::ACCESS_NAME => $this->reflectionProperty->name,
                Accessor::ACCESS_REF => true,
            ];
        }

        if ($this->reflectionClass->hasProperty($this->camelized) && $this->reflectionClass->getProperty($this->camelized)->isPublic()) {
            return [
                Accessor::ACCESS_HAS_PROPERTY => true,
                Accessor::ACCESS_TYPE => Accessor::ACCESS_TYPE_PROPERTY,
                Accessor::ACCESS_NAME => $this->camelized,
                Accessor::ACCESS_REF => true,
            ];
        }

        return [
            Accessor::ACCESS_HAS_PROPERTY => $hasProperty,
            Accessor::ACCESS_TYPE => Accessor::ACCESS_TYPE_NOT_FOUND,
            Accessor::ACCESS_NAME => sprintf(
                'Neither the property "%s" nor one of the methods "%s()" ' .
                'exist and have public access in class "%s".',
                $this->property,
                implode('()", "', $methods),
                $this->reflectionClass->name,
            ),
        ];
    }

    /**
     * Gets the write access information.
     *
     * @return array<int, mixed>
     * @phpstan-return array{0: bool, 1: int, 2?: string, 3?: bool, 4?: string, 5?: string}
     */
    public function getWriteAccessInfo(mixed $value): array
    {
        $hasProperty = $this->reflectionProperty !== null;

        if (is_iterable($value)) {
            $methods = $this->findAdderAndRemover();

            if ($methods !== null) {
                return [
                    Accessor::ACCESS_HAS_PROPERTY => $hasProperty,
                    Accessor::ACCESS_TYPE => Accessor::ACCESS_TYPE_ADDER_AND_REMOVER,
                    Accessor::ACCESS_ADDER => $methods[0],
                    Accessor::ACCESS_REMOVER => $methods[1],
                ];
            }
        }

        $methods = ['set' . $this->camelized, $this->camelized];
        foreach ($methods as $method) {
            if (! $this->isMethodAccessible($method, 1)) {
                continue;
            }

            return [
                Accessor::ACCESS_HAS_PROPERTY => $hasProperty,
                Accessor::ACCESS_TYPE => Accessor::ACCESS_TYPE_METHOD,
                Accessor::ACCESS_NAME => $method,
            ];
        }

        if ($this->isMethodAccessible('__set', 2)) {
            return [
                Accessor::ACCESS_HAS_PROPERTY => $hasProperty,
                Accessor::ACCESS_TYPE => Accessor::ACCESS_TYPE_PROPERTY,
                Accessor::ACCESS_NAME => $this->property,
            ];
        }

        if ($this->reflectionProperty !== null && $this->reflectionProperty->isPublic()) {
            return [
                Accessor::ACCESS_HAS_PROPERTY => true,
                Accessor::ACCESS_TYPE => Accessor::ACCESS_TYPE_PROPERTY,
                Accessor::ACCESS_NAME => $this->reflectionProperty->name,
                Accessor::ACCESS_REF => true,
            ];
        }

        $adderRemover = $this->findAdderAndRemover();
        if ($adderRemover !== null) {
            return [
                Accessor::ACCESS_HAS_PROPERTY => $hasProperty,
                Accessor::ACCESS_TYPE => Accessor::ACCESS_TYPE_NOT_FOUND,
                Accessor::ACCESS_NAME => sprintf(
                    'The property "%s" in class "%s" can be defined with the methods "%s()" but ' .
                    'the new value must be an array or an instance of \Traversable, ' .
                    '"%s" given.',
                    $this->property,
                    $this->reflectionClass->name,
                    implode('()", "', $adderRemover),
                    is_object($value) ? $value::class : gettype($value),
                ),
            ];
        }

        return [
            Accessor::ACCESS_HAS_PROPERTY => $hasProperty,
            Accessor::ACCESS_TYPE => Accessor::ACCESS_TYPE_NOT_FOUND,
            Accessor::ACCESS_NAME => sprintf(
                'Neither the property "%s" nor one of the methods %s"%s()", ' .
                '"__set()" or "__call()" exist and have public access in class "%s".',
                $this->property,
                implode('', array_map(static function ($singular) {
                    return '"add' . $singular . '()"/"remove' . $singular . '()", ';
                }, $this->inflector->singularize($this->camelized))),
                implode('()", "', $methods),
                $this->reflectionClass->name,
            ),
        ];
    }

    /**
     * Searches for add and remove methods.
     *
     * @return array<string>|null An array containing the adder and remover when found, null otherwise
     * @phpstan-return array{string, string}|null
     */
    private function findAdderAndRemover(): array|null
    {
        $singulars = $this->inflector->singularize($this->camelized);

        foreach ($singulars as $singular) {
            $addMethod = 'add' . $singular;
            $removeMethod = 'remove' . $singular;

            if ($this->isMethodAccessible($addMethod, 1) && $this->isMethodAccessible($removeMethod, 1)) {
                return [$addMethod, $removeMethod];
            }
        }

        return null;
    }

    /**
     * Returns whether a method is public and has the number of required parameters.
     *
     * @param string $methodName The method name
     * @param int    $parameters The number of parameters
     *
     * @return bool Whether the method is public and has $parameters required parameters
     */
    private function isMethodAccessible(string $methodName, int $parameters): bool
    {
        if (! $this->reflectionClass->hasMethod($methodName)) {
            return false;
        }

        $method = $this->reflectionClass->getMethod($methodName);

        return $method->isPublic()
            && $method->getNumberOfRequiredParameters() <= $parameters
            && $method->getNumberOfParameters() >= $parameters;
    }

    /**
     * Camelizes a given string.
     *
     * @param string $string Some string
     *
     * @return string The camelized version of the string
     */
    public static function camelize(string $string): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $string))));
    }
}
