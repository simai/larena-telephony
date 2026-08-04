<?php

declare(strict_types=1);

namespace Larena\Telephony\Testing\AsteriskAmi;

use Larena\Telephony\Adapters\AsteriskAmi\Contracts\AmiTransport;
use Larena\Telephony\Adapters\AsteriskAmi\ValueObjects\AmiAction;
use Larena\Telephony\Adapters\AsteriskAmi\ValueObjects\AmiTransportResult;
use Larena\Telephony\Support\CanonicalJson;
use Larena\Telephony\ValueObjects\EndpointDescriptor;

final class FixtureAmiTransport implements AmiTransport
{
    /** @var list<AmiAction> */
    private array $actions = [];

    /** @param array<string, string> $outcomesByActionId */
    public function __construct(private readonly array $outcomesByActionId = [])
    {
    }

    public function send(EndpointDescriptor $endpoint, AmiAction $action): AmiTransportResult
    {
        $this->actions[] = $action;
        $outcome = $this->outcomesByActionId[$action->actionId] ?? 'acknowledged';
        $receiptHash = CanonicalJson::fingerprint([
            'action_fingerprint' => $action->fingerprint(),
            'endpoint_ref_hash' => 'sha256:'.hash('sha256', $endpoint->endpointId),
            'response' => $outcome === 'acknowledged' ? 'Success' : 'Failure',
        ]);

        return match ($outcome) {
            'acknowledged' => new AmiTransportResult('acknowledged', 'ami_response_success', $action->actionId, $receiptHash),
            'rejected' => new AmiTransportResult('rejected', 'ami_response_failure', $action->actionId, $receiptHash),
            'timeout' => new AmiTransportResult('timeout', 'ami_response_timeout'),
            'disconnected' => new AmiTransportResult('disconnected', 'ami_transport_disconnected'),
            'uncorrelated' => new AmiTransportResult('uncorrelated', 'ami_response_action_id_missing'),
            'mismatched' => new AmiTransportResult('acknowledged', 'ami_response_success', 'larena-'.str_repeat('f', 24), $receiptHash),
            default => new AmiTransportResult('uncorrelated', 'ami_fixture_outcome_unknown'),
        };
    }

    /** @return list<AmiAction> */
    public function recordedActions(): array
    {
        return $this->actions;
    }
}
