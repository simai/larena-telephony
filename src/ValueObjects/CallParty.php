<?php

declare(strict_types=1);

namespace Larena\Telephony\ValueObjects;

use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Support\Identity;

final readonly class CallParty
{
    public function __construct(
        public string $partyRef,
        public string $kind,
    ) {
        Identity::namespaced($partyRef, 'party_ref');
        if (!in_array($kind, ['extension', 'contact', 'external', 'service', 'unknown'], true)) {
            throw new ContractViolation('invalid_call_party_kind', 'Call party kind is invalid.');
        }
    }

    /** @return array{party_ref: string, kind: string} */
    public function toArray(): array
    {
        return [
            'party_ref' => $this->partyRef,
            'kind' => $this->kind,
        ];
    }
}
