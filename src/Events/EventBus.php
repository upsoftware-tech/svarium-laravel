<?php

namespace Upsoftware\Svarium\Events;

class EventBus
{
    protected array $listeners = [];

    public function listen(string $eventClass, string $listenerClass): void
    {
        $this->listeners[$eventClass][] = $listenerClass;
    }

    public function dispatch(DomainEvent $event): void
    {
        $eventClass = get_class($event);

        foreach ($this->listeners[$eventClass] ?? [] as $listenerClass) {

            $listener = app($listenerClass);

            if (method_exists($listener, 'handle')) {
                $listener->handle($event);
            }
        }
    }
}
