<?php

declare(strict_types=1);

namespace Larena\Telephony\Runtime;

use Larena\Telephony\ValueObjects\EventEnvelope;

final class InMemoryEventInbox
{
    /** @var array<string, EventEnvelope> */
    private array $events = [];

    public function accept(EventEnvelope $event): bool
    {
        $key = hash('sha256', $event->tenantRef."\0".$event->idempotencyKey);
        if (isset($this->events[$key])) {
            return false;
        }

        $this->events[$key] = $event;

        return true;
    }

    /** @return list<EventEnvelope> */
    public function ordered(): array
    {
        $events = array_values($this->events);
        usort($events, static function (EventEnvelope $left, EventEnvelope $right): int {
            $time = $left->occurredAt->format('U.u') <=> $right->occurredAt->format('U.u');

            return $time !== 0 ? $time : $left->eventId <=> $right->eventId;
        });

        return $events;
    }
}
