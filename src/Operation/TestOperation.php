<?php

declare(strict_types=1);

namespace Solido\PatchManager\Operation;

use Solido\PatchManager\Exception\InvalidJSONException;
use stdClass;

use function is_array;
use function is_bool;
use function is_object;
use function json_decode;
use function json_encode;
use function ksort;

use const JSON_THROW_ON_ERROR;

/** @extends AbstractOperation<stdClass> */
class TestOperation extends AbstractOperation
{
    /**
     * {@inheritDoc}
     */
    public function execute(&$subject, $operation): void
    {
        $value = $this->accessor->getValue($subject, $operation->path);

        if (! $this->isEqual($value, $operation->value)) {
            throw new InvalidJSONException('Test operation on "' . $operation->path . '" failed.');
        }
    }

    private function isEqual(mixed $objectValue, mixed $value): bool
    {
        if ($value === 'true') {
            $value = true;
        }

        if ($value === 'false') {
            $value = false;
        }

        if (is_bool($value)) {
            return is_bool($objectValue) && $objectValue === $value;
        }

        // phpcs:disable
        if ($objectValue == $value) {
            // Easy: int/float to numeric string
            return true;
        }
        // phpcs:enable

        if (is_object($value)) {
            $value = json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        }

        if (is_array($value)) {
            if (is_object($objectValue)) {
                $objectValue = json_decode(json_encode($objectValue, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            }

            $this->sort($value);
            $this->sort($objectValue);

            return $value === $objectValue;
        }

        return false;
    }

    /**
     * Recursive key-sorting to have comparable arrays.
     *
     * @param array<mixed, mixed> $json
     */
    private function sort(array &$json): void
    {
        ksort($json);

        foreach ($json as &$value) {
            if (! is_array($value)) {
                continue;
            }

            $this->sort($value);
        }
    }
}
