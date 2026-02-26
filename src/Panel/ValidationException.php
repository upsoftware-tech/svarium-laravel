<?php

namespace Upsoftware\Svarium\Panel;

use Exception;

class ValidationException extends Exception
{
    public function __construct(
        public array $errors
    ) {
        parent::__construct('Validation failed');
    }
}
