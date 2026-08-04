<?php

declare(strict_types=1);

namespace Larena\Telephony\ValueObjects;

final readonly class ConformanceCaseResult
{
    /** @param array<string, mixed> $observed */
    public function __construct(
        public string $caseId,
        public string $status,
        public array $observed,
        public ?string $failure = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'case_id' => $this->caseId,
            'status' => $this->status,
            'observed' => $this->observed,
            'failure' => $this->failure,
        ];
    }
}
