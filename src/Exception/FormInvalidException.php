<?php

declare(strict_types=1);

namespace Solido\PatchManager\Exception;

use Symfony\Component\Form\FormInterface;
use Throwable;

class FormInvalidException extends BadRequestException
{
    private FormInterface $form;

    public function __construct(FormInterface $form, string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        $this->form = $form;
        parent::__construct($message, $code, $previous);
    }

    public function getForm(): FormInterface
    {
        return $this->form;
    }
}
