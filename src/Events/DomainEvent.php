<?php

namespace Upsoftware\Svarium\Events;

abstract class DomainEvent
{
    public readonly \DateTimeImmutable $occurredAt;

    public function __construct()
    {
        $this->occurredAt = new \DateTimeImmutable;
    }
}
