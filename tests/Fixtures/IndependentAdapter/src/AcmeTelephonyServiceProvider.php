<?php

declare(strict_types=1);

namespace Acme\LarenaTelephonyDemo;

use Illuminate\Support\ServiceProvider;
use Larena\Telephony\Registry\AdapterRegistry;

final class AcmeTelephonyServiceProvider extends ServiceProvider
{
    public function boot(AdapterRegistry $registry): void
    {
        if (!$registry->has('acme.demo')) {
            $registry->register(new AcmeTelephonyAdapter());
        }
    }
}
