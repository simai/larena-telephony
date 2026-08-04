<?php

declare(strict_types=1);

namespace Larena\Telephony\Runtime;

final class CallTransitionPolicy
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'new' => ['dialing', 'ringing', 'answered', 'ending', 'ended', 'failed'],
        'dialing' => ['ringing', 'answered', 'ending', 'ended', 'failed'],
        'ringing' => ['answered', 'ending', 'ended', 'failed'],
        'answered' => ['held', 'ending', 'ended', 'failed'],
        'held' => ['answered', 'ending', 'ended', 'failed'],
        'ending' => ['ended', 'failed'],
        'ended' => [],
        'failed' => [],
        'unknown_quarantined' => [],
    ];

    /** @var array<string, string> */
    private const EVENT_STATES = [
        'call.created' => 'new',
        'call.dialing' => 'dialing',
        'call.ringing' => 'ringing',
        'call.answered' => 'answered',
        'call.bridged' => 'answered',
        'call.held' => 'held',
        'call.resumed' => 'answered',
        'call.ended' => 'ended',
        'call.failed' => 'failed',
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function isTerminal(string $state): bool
    {
        return in_array($state, ['ended', 'failed'], true);
    }

    public function targetState(string $eventType): ?string
    {
        return self::EVENT_STATES[$eventType] ?? null;
    }

    /** @return array<string, list<string>> */
    public function transitions(): array
    {
        return self::TRANSITIONS;
    }

    /** @return array<string, string> */
    public function eventStates(): array
    {
        return self::EVENT_STATES;
    }
}
