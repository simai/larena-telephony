<?php

declare(strict_types=1);

namespace Larena\Telephony\Adapters\AsteriskAmi\ValueObjects;

use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Support\CanonicalJson;

final readonly class AmiAction
{
    /** @var array<string, list<string>> */
    private const ALLOWED_FIELDS = [
        'Originate' => ['Action', 'ActionID', 'Async', 'Channel', 'Context', 'Exten', 'Priority', 'Timeout'],
        'Hangup' => ['Action', 'ActionID', 'Channel'],
        'Redirect' => ['Action', 'ActionID', 'Channel', 'Context', 'Exten', 'Priority'],
        'PlayDTMF' => ['Action', 'ActionID', 'Channel', 'Digit', 'Duration'],
    ];

    /** @var array<string, list<string>> */
    private const REQUIRED_FIELDS = [
        'Originate' => ['Action', 'ActionID', 'Async', 'Channel', 'Context', 'Exten', 'Priority', 'Timeout'],
        'Hangup' => ['Action', 'ActionID', 'Channel'],
        'Redirect' => ['Action', 'ActionID', 'Channel', 'Context', 'Exten', 'Priority'],
        'PlayDTMF' => ['Action', 'ActionID', 'Channel', 'Digit'],
    ];

    /** @var array<string, string> */
    private array $fields;

    /** @param array<string, string|int|bool> $fields */
    public function __construct(
        public string $name,
        public string $actionId,
        array $fields,
    ) {
        if (!array_key_exists($name, self::ALLOWED_FIELDS)) {
            throw new ContractViolation('unsupported_ami_action', 'AMI action is outside the frozen G4.3 subset.');
        }
        if (preg_match('/^larena-[0-9a-f]{24}$/', $actionId) !== 1) {
            throw new ContractViolation('invalid_ami_action_id', 'AMI ActionID must be a deterministic Larena identity.');
        }

        $normalized = ['Action' => $name, 'ActionID' => $actionId];
        foreach ($fields as $field => $value) {
            if (!is_string($field) || in_array($field, ['Action', 'ActionID'], true) || !in_array($field, self::ALLOWED_FIELDS[$name], true)) {
                throw new ContractViolation('ami_action_field_not_allowed', "AMI field {$field} is not allowed for {$name}.");
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_int($value)) {
                $value = (string) $value;
            }
            if (!is_string($value) || $value === '' || strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                throw new ContractViolation('invalid_ami_action_value', "AMI field {$field} is not a bounded literal value.");
            }
            $normalized[$field] = $value;
        }

        foreach (self::REQUIRED_FIELDS[$name] as $required) {
            if (!array_key_exists($required, $normalized)) {
                throw new ContractViolation('ami_action_field_required', "AMI field {$required} is required for {$name}.");
            }
        }
        $this->assertFieldSemantics($normalized);
        ksort($normalized, SORT_STRING);
        $this->fields = $normalized;
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return $this->fields;
    }

    public function fingerprint(): string
    {
        return CanonicalJson::fingerprint($this->fields);
    }

    /** @param array<string, string> $fields */
    private function assertFieldSemantics(array $fields): void
    {
        if (preg_match('#^[A-Za-z0-9_-]+/[A-Za-z0-9_.@:+-]+$#', $fields['Channel']) !== 1) {
            throw new ContractViolation('invalid_ami_channel', 'AMI Channel must be an exact literal identity.');
        }
        foreach (['Context', 'Exten'] as $name) {
            if (isset($fields[$name]) && preg_match('/^[A-Za-z0-9_.@+-]{1,80}$/', $fields[$name]) !== 1) {
                throw new ContractViolation('invalid_ami_dialplan_target', "AMI {$name} must be a bounded literal identity.");
            }
        }
        if (isset($fields['Priority']) && (int) $fields['Priority'] < 1) {
            throw new ContractViolation('invalid_ami_priority', 'AMI Priority must be positive.');
        }
        if (isset($fields['Timeout']) && ((int) $fields['Timeout'] < 1000 || (int) $fields['Timeout'] > 120000)) {
            throw new ContractViolation('invalid_ami_timeout', 'AMI Timeout is outside the frozen fixture range.');
        }
        if (isset($fields['Async']) && !in_array($fields['Async'], ['true', 'false'], true)) {
            throw new ContractViolation('invalid_ami_async', 'AMI Async must be a boolean literal.');
        }
        if (isset($fields['Digit']) && preg_match('/^[0-9A-D#*]$/', $fields['Digit']) !== 1) {
            throw new ContractViolation('invalid_dtmf_digit', 'DTMF digit is outside the supported single-symbol alphabet.');
        }
        if (isset($fields['Duration']) && ((int) $fields['Duration'] < 80 || (int) $fields['Duration'] > 1000)) {
            throw new ContractViolation('invalid_dtmf_duration', 'DTMF duration is outside the supported fixture range.');
        }
    }
}
