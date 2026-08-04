<?php

declare(strict_types=1);

namespace Larena\Telephony\Runtime;

use DateTimeImmutable;
use Larena\Telephony\Contracts\CallSessionStore;
use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\ValueObjects\CallParty;
use Larena\Telephony\ValueObjects\CallSession;
use Larena\Telephony\ValueObjects\EventEnvelope;
use Larena\Telephony\ValueObjects\SessionProjectionResult;

final readonly class CallSessionProjector
{
    public function __construct(
        private CallSessionStore $sessions,
        private CallTransitionPolicy $policy,
    ) {
    }

    public function project(EventEnvelope $event): SessionProjectionResult
    {
        $targetState = $this->policy->targetState($event->eventType);
        if ($targetState === null) {
            return SessionProjectionResult::ignored('event_does_not_project_call_state');
        }
        if ($event->providerCallRefHash === null) {
            return SessionProjectionResult::quarantined('provider_call_correlation_missing');
        }

        try {
            $parties = $this->parties($event);
        } catch (ContractViolation) {
            return SessionProjectionResult::quarantined('normalized_call_context_invalid');
        }

        $current = $this->sessions->findByProviderCallHash($event->tenantRef, $event->providerCallRefHash);
        if (!$current instanceof CallSession) {
            if ($targetState !== 'new' && !$this->policy->canTransition('new', $targetState)) {
                return SessionProjectionResult::quarantined('invalid_initial_call_transition');
            }

            $session = new CallSession(
                $this->callId($event),
                $event->tenantRef,
                $event->endpointRef,
                $event->adapterKey,
                $event->providerCallRefHash,
                $parties['direction'],
                $targetState,
                $parties['local'],
                $parties['remote'],
                $event->occurredAt,
                $targetState === 'answered' ? $event->occurredAt : null,
                $this->policy->isTerminal($targetState) ? $event->occurredAt : null,
                $event->correlationId,
                [],
                [],
                0,
            );
            $this->sessions->save($session, null);

            return SessionProjectionResult::created($session);
        }

        if ($event->eventType === 'call.created') {
            if ($event->occurredAt >= $current->startedAt) {
                return SessionProjectionResult::ignored('late_created_event_did_not_precede_timeline');
            }
            if ($current->answeredAt !== null && $event->occurredAt > $current->answeredAt) {
                return SessionProjectionResult::quarantined('late_created_event_conflicts_with_timeline');
            }

            return $this->persist(
                $current,
                startedAt: $event->occurredAt,
                reasonCode: 'started_at_reconciled',
            );
        }

        $reconciledAnsweredAt = $current->answeredAt;
        $answeredReconciled = false;
        if ($targetState === 'answered' && $reconciledAnsweredAt === null) {
            if ($event->occurredAt < $current->startedAt || ($current->endedAt !== null && $event->occurredAt > $current->endedAt)) {
                return SessionProjectionResult::quarantined('late_answered_event_conflicts_with_timeline');
            }
            $reconciledAnsweredAt = $event->occurredAt;
            $answeredReconciled = true;
        }

        if ($this->policy->isTerminal($current->state)) {
            if ($answeredReconciled) {
                return $this->persist(
                    $current,
                    answeredAt: $reconciledAnsweredAt,
                    reasonCode: 'answered_at_reconciled',
                );
            }

            return SessionProjectionResult::ignored('terminal_call_state_is_immutable');
        }

        if ($targetState === $current->state) {
            if ($answeredReconciled) {
                return $this->persist(
                    $current,
                    answeredAt: $reconciledAnsweredAt,
                    reasonCode: 'answered_at_reconciled',
                );
            }

            return SessionProjectionResult::ignored('duplicate_call_state');
        }

        if (!$this->policy->canTransition($current->state, $targetState)) {
            return SessionProjectionResult::ignored('call_state_regression_ignored');
        }

        $endedAt = $current->endedAt;
        if ($this->policy->isTerminal($targetState)) {
            if ($event->occurredAt < $current->startedAt || ($reconciledAnsweredAt !== null && $event->occurredAt < $reconciledAnsweredAt)) {
                return SessionProjectionResult::quarantined('terminal_event_conflicts_with_timeline');
            }
            $endedAt = $event->occurredAt;
        }

        return $this->persist(
            $current,
            state: $targetState,
            answeredAt: $reconciledAnsweredAt,
            endedAt: $endedAt,
        );
    }

    /** @return array{direction: string, local: CallParty, remote: CallParty} */
    private function parties(EventEnvelope $event): array
    {
        $payload = $event->normalizedPayload;
        $direction = $payload['direction'] ?? null;
        $local = $payload['local_party'] ?? null;
        $remote = $payload['remote_party'] ?? null;
        if (!is_string($direction) || !is_array($local) || !is_array($remote)) {
            throw new ContractViolation('normalized_call_context_invalid', 'Normalized call context is incomplete.');
        }

        return [
            'direction' => $direction,
            'local' => new CallParty((string) ($local['party_ref'] ?? ''), (string) ($local['kind'] ?? '')),
            'remote' => new CallParty((string) ($remote['party_ref'] ?? ''), (string) ($remote['kind'] ?? '')),
        ];
    }

    private function callId(EventEnvelope $event): string
    {
        return 'call.'.substr(hash('sha256', $event->tenantRef."\0".$event->providerCallRefHash), 0, 24);
    }

    private function persist(
        CallSession $current,
        ?string $state = null,
        ?DateTimeImmutable $startedAt = null,
        ?DateTimeImmutable $answeredAt = null,
        ?DateTimeImmutable $endedAt = null,
        ?string $reasonCode = null,
    ): SessionProjectionResult {
        $session = new CallSession(
            $current->callId,
            $current->tenantRef,
            $current->endpointRef,
            $current->adapterKey,
            $current->providerCallRefHash,
            $current->direction,
            $state ?? $current->state,
            $current->localParty,
            $current->remoteParty,
            $startedAt ?? $current->startedAt,
            $answeredAt ?? $current->answeredAt,
            $endedAt ?? $current->endedAt,
            $current->correlationId,
            $current->associationRefs,
            $current->artifactRefs,
            $current->version + 1,
        );
        $this->sessions->save($session, $current->version);

        return $reasonCode === null
            ? SessionProjectionResult::updated($session)
            : SessionProjectionResult::reconciled($session, $reasonCode);
    }
}
