<?php

namespace App\Services\Payment\Facades;

use Illuminate\Support\Facades\Facade;
use App\Services\Payment\Contracts\Factory;

/**
 * @method static \Laravel\Socialite\Contracts\Provider driver(string $driver = null)
 * @see \App\Services\Payment\PaymentManager
 */
class Payment extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return Factory::class;
    }
}
