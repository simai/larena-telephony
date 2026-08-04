<?php

declare(strict_types=1);

namespace Larena\Telephony\Support;

final class TelephonyContract
{
    public const VERSION = '1.0.0';
    public const SUITE_VERSION = '1.0.0';

    /** @var list<string> */
    public const CAPABILITIES = [
        'events.ingest',
        'health.probe',
        'calls.originate',
        'calls.answer',
        'calls.hangup',
        'calls.transfer',
        'calls.hold',
        'calls.resume',
        'calls.dtmf',
    ];

    /** @var array<string, string> */
    public const COMMAND_CAPABILITIES = [
        'originate' => 'calls.originate',
        'answer' => 'calls.answer',
        'hangup' => 'calls.hangup',
        'transfer' => 'calls.transfer',
        'hold' => 'calls.hold',
        'resume' => 'calls.resume',
        'dtmf' => 'calls.dtmf',
    ];

    /** @var list<string> */
    public const EVENT_TYPES = [
        'connection.connected',
        'connection.degraded',
        'connection.disconnected',
        'call.created',
        'call.ringing',
        'call.answered',
        'call.bridged',
        'call.held',
        'call.resumed',
        'call.ended',
    ];

    /** @var list<string> */
    public const LIFECYCLE_STATUSES = ['active', 'deprecated', 'experimental'];

    /** @var list<string> */
    public const HEALTH_STATUSES = ['healthy', 'degraded', 'unavailable', 'unknown'];

    /** @var list<string> */
    public const COMMAND_RESULT_STATUSES = ['succeeded', 'denied', 'unsupported', 'uncertain', 'failed'];

    private function __construct()
    {
    }
}
