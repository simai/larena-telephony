<?php

declare(strict_types=1);

namespace Larena\Telephony\Providers;

use Illuminate\Support\ServiceProvider;
use Larena\Telephony\Contracts\CommandReceiptStore;
use Larena\Telephony\Registry\AdapterRegistry;
use Larena\Telephony\Runtime\InMemoryCommandReceiptStore;

final class TelephonyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdapterRegistry::class, static fn (): AdapterRegistry => new AdapterRegistry());
        $this->app->singleton(CommandReceiptStore::class, static fn (): CommandReceiptStore => new InMemoryCommandReceiptStore());
    }
}
