<?php

declare(strict_types=1);

namespace Larena\Telephony\ValueObjects;

use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Support\DiagnosticRedactor;
use Larena\Telephony\Support\ReasonCode;

final readonly class EventMappingResult
{
    /** @param array<string, mixed> $diagnostic */
    private function __construct(
        public string $status,
        public ?EventEnvelope $envelope,
        public ?string $reasonCode,
        public array $diagnostic,
    ) {
        if (!in_array($status, ['accepted', 'quarantined'], true)) {
            throw new ContractViolation('invalid_mapping_status', 'Event mapping status is invalid.');
        }
        if (($status === 'accepted') !== ($envelope instanceof EventEnvelope)) {
            throw new ContractViolation('invalid_mapping_shape', 'Accepted event mapping requires exactly one normalized envelope.');
        }
        if ($status === 'quarantined' && ($reasonCode === null || $reasonCode === '')) {
            throw new ContractViolation('invalid_quarantine_shape', 'Quarantined event requires a reason code.');
        }
        if ($reasonCode !== null) {
            ReasonCode::assert($reasonCode);
        }
    }

    public static function accepted(EventEnvelope $envelope): self
    {
        return new self('accepted', $envelope, null, []);
    }

    /** @param array<string, mixed> $diagnostic */
    public static function quarantined(string $reasonCode, array $diagnostic = []): self
    {
        return new self('quarantined', null, $reasonCode, DiagnosticRedactor::redact($diagnostic));
    }
}
