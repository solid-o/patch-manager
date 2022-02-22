<?php

declare(strict_types=1);

namespace Solido\PatchManager;

use Solido\DataMapper\Exception\MappingErrorException;
use Solido\PatchManager\Exception\InvalidJSONException;
use Solido\PatchManager\Exception\TypeError;

interface PatchManagerInterface
{
    /**
     * Executes the PATCH operations.
     *
     * @throws TypeError
     * @throws InvalidJSONException
     * @throws MappingErrorException
     */
    public function patch(PatchableInterface $patchable, object $request): void;
}
