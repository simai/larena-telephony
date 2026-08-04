<?php

declare(strict_types=1);

namespace Larena\Telephony\Providers;

use Illuminate\Support\ServiceProvider;
use Larena\Telephony\Contracts\CallSessionStore;
use Larena\Telephony\Contracts\CommandReceiptStore;
use Larena\Telephony\Contracts\EndpointRegistry;
use Larena\Telephony\Registry\AdapterRegistry;
use Larena\Telephony\Registry\InMemoryEndpointRegistry;
use Larena\Telephony\Runtime\CallSessionProjector;
use Larena\Telephony\Runtime\CallTransitionPolicy;
use Larena\Telephony\Runtime\EventIngressProcessor;
use Larena\Telephony\Runtime\InMemoryCommandReceiptStore;
use Larena\Telephony\Runtime\InMemoryCallSessionStore;
use Larena\Telephony\Runtime\InMemoryEventInbox;

final class TelephonyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdapterRegistry::class, static fn (): AdapterRegistry => new AdapterRegistry());
        $this->app->singleton(CommandReceiptStore::class, static fn (): CommandReceiptStore => new InMemoryCommandReceiptStore());
        $this->app->singleton(EndpointRegistry::class, static fn (): EndpointRegistry => new InMemoryEndpointRegistry());
        $this->app->singleton(CallSessionStore::class, static fn (): CallSessionStore => new InMemoryCallSessionStore());
        $this->app->singleton(InMemoryEventInbox::class, static fn (): InMemoryEventInbox => new InMemoryEventInbox());
        $this->app->singleton(CallTransitionPolicy::class, static fn (): CallTransitionPolicy => new CallTransitionPolicy());
        $this->app->singleton(CallSessionProjector::class, static fn ($app): CallSessionProjector => new CallSessionProjector(
            $app->make(CallSessionStore::class),
            $app->make(CallTransitionPolicy::class),
        ));
        $this->app->singleton(EventIngressProcessor::class, static fn ($app): EventIngressProcessor => new EventIngressProcessor(
            $app->make(AdapterRegistry::class),
            $app->make(EndpointRegistry::class),
            $app->make(InMemoryEventInbox::class),
            $app->make(CallSessionProjector::class),
        ));
    }
}
