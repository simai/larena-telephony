<?php

declare(strict_types=1);

namespace Larena\Telephony\ValueObjects;

use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Support\ReasonCode;

final readonly class EventIngressResult
{
    public function __construct(
        public string $status,
        public string $reasonCode,
        public ?EventEnvelope $envelope = null,
        public ?SessionProjectionResult $projection = null,
    ) {
        if (!in_array($status, ['accepted', 'duplicate', 'ignored', 'quarantined'], true)) {
            throw new ContractViolation('invalid_event_ingress_status', 'Event ingress status is invalid.');
        }
        ReasonCode::assert($reasonCode);
        if ($status === 'accepted' && (!$envelope instanceof EventEnvelope || !$projection instanceof SessionProjectionResult)) {
            throw new ContractViolation('invalid_event_ingress_shape', 'Accepted ingress requires an envelope and projection result.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'reason_code' => $this->reasonCode,
            'event' => $this->envelope?->toArray(),
            'projection' => $this->projection?->toArray(),
        ];
    }
}
