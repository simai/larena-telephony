<?php

declare(strict_types=1);

namespace Larena\Telephony\Registry;

use Larena\Telephony\Contracts\TelephonyAdapter;
use Larena\Telephony\Exceptions\AdapterRegistrationRejected;
use Larena\Telephony\Support\ContractVersion;
use Larena\Telephony\Support\TelephonyContract;

final class AdapterRegistry
{
    /** @var array<string, TelephonyAdapter> */
    private array $adapters = [];

    public function register(TelephonyAdapter $adapter): void
    {
        $manifest = $adapter->manifest();
        if (isset($this->adapters[$manifest->adapterKey])) {
            throw new AdapterRegistrationRejected('duplicate_adapter_key', "Adapter {$manifest->adapterKey} is already registered.");
        }
        if (!ContractVersion::supports($manifest->contractVersion)) {
            throw new AdapterRegistrationRejected('incompatible_contract_version', "Adapter {$manifest->adapterKey} targets an incompatible contract version.");
        }

        foreach ($manifest->capabilities as $capability) {
            if (!in_array($capability, TelephonyContract::CAPABILITIES, true)) {
                throw new AdapterRegistrationRejected('unknown_capability', "Adapter declares unknown capability {$capability}.");
            }
        }
        foreach (['events.ingest', 'health.probe'] as $requiredCapability) {
            if (!in_array($requiredCapability, $manifest->capabilities, true)) {
                throw new AdapterRegistrationRejected('missing_baseline_capability', "Adapter is missing {$requiredCapability}.");
            }
        }

        foreach (TelephonyContract::COMMAND_CAPABILITIES as $command => $capability) {
            $hasCapability = in_array($capability, $manifest->capabilities, true);
            $hasHandler = array_key_exists($command, $manifest->commandHandlerRefs);
            if ($hasCapability !== $hasHandler) {
                throw new AdapterRegistrationRejected('capability_handler_mismatch', "Command {$command} capability and handler declarations differ.");
            }
        }

        $this->adapters[$manifest->adapterKey] = $adapter;
    }

    public function get(string $adapterKey): TelephonyAdapter
    {
        if (!isset($this->adapters[$adapterKey])) {
            throw new AdapterRegistrationRejected('adapter_not_registered', "Adapter {$adapterKey} is not registered.");
        }

        return $this->adapters[$adapterKey];
    }

    public function has(string $adapterKey): bool
    {
        return isset($this->adapters[$adapterKey]);
    }

    /** @return list<string> */
    public function keys(): array
    {
        $keys = array_keys($this->adapters);
        sort($keys, SORT_STRING);

        return $keys;
    }
}
