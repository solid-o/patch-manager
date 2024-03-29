<?php

declare(strict_types=1);

namespace Solido\PatchManager\Exception;

use function array_pop;
use function gettype;
use function implode;
use function is_array;
use function is_object;
use function sprintf;

class TypeError extends \TypeError
{
    /**
     * Creates and format an argument invalid error.
     *
     * @param int             $no       Argument number
     * @param string          $function Function generating the error
     * @param string|string[] $expected Expected type(s)
     * @param mixed           $given    Given value
     */
    public static function createArgumentInvalid(int $no, string $function, string|array $expected, mixed $given): self
    {
        $message = sprintf(
            'Argument %u passed to %s must be of type %s, %s given',
            $no,
            $function,
            self::formatExpected($expected),
            is_object($given) ? $given::class : gettype($given),
        );

        return new self($message);
    }

    /**
     * Formats "expected" value for exception message.
     *
     * @param string|string[] $expected
     */
    private static function formatExpected(string|array $expected): string
    {
        if (! is_array($expected)) {
            return $expected;
        }

        $last = array_pop($expected);

        return implode(', ', $expected) . ' or ' . $last;
    }
}
