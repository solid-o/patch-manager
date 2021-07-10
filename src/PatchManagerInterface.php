<?php

declare(strict_types=1);

namespace Solido\PatchManager;

use Solido\PatchManager\Exception\FormInvalidException;
use Solido\PatchManager\Exception\FormNotSubmittedException;
use Solido\PatchManager\Exception\InvalidJSONException;
use Solido\PatchManager\Exception\TypeError;

interface PatchManagerInterface
{
    /**
     * Executes the PATCH operations.
     *
     * @throws TypeError
     * @throws InvalidJSONException
     * @throws FormInvalidException
     * @throws FormNotSubmittedException
     */
    public function patch(PatchableInterface $patchable, object $request): void;
}
