<?php

namespace Upsoftware\Svarium\Http;

use Symfony\Component\HttpFoundation\Response;

interface OperationResult
{
    public function toResponse(): Response;
}
