<?php

namespace App\Services\Payment;

use Hyperf\Utils\Arr;
use Illuminate\Support\Manager;
use Hyperf\Utils\Str;
use InvalidArgumentException;
use App\Services\Payment\Providers\PaypalProvider;

class PaymentManager extends Manager implements Contracts\Factory {

    /**
     * Get a driver instance.
     *
     * @param  string  $driver
     * @return mixed
     */
    public function with($driver) {
        return $this->driver($driver);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \App\Services\Payment\Providers\AbstractProvider
     */
    protected function createPaypalDriver() {
        $config = $this->app->make('config')['services.payment.paypal'];

        return $this->buildProvider(
                        PaypalProvider::class, $config
        );
    }

    /**
     * Build an provider instance.
     *
     * @param  string  $provider
     * @param  array  $config
     * @return \App\Services\Payment\Providers\AbstractProvider
     */
    public function buildProvider($provider, $config) {
        return new $provider(
                $this->app,
                $this->app->make('request'), 
                $config['client_id'], 
                $config['client_secret'], 
                $config['currency'], 
                $this->formatUrl($config, 'callback_uri'),
                Arr::get($config, 'guzzle', [])
        );
    }

    /**
     * Format the server configuration.
     *
     * @param  array  $config
     * @return array
     */
    public function formatConfig(array $config) {
        return array_merge([
            'identifier' => $config['client_id'],
            'secret' => $config['client_secret'],
            'callback_uri' => $this->formatUrl($config,'callback_uri'),
                ], $config);
    }

    /**
     * Format the callback URL, resolving a relative URI if needed.
     *
     * @param  array  $config
     * @return string
     */
    public function formatUrl(array $config, $key = null) {
        $redirect = value(data_get($config, $key, ''));

        return Str::startsWith($redirect, '/') ? $this->app->make('url')->to($redirect) : $redirect;
    }

    /**
     * Get the default driver name.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function getDefaultDriver() {
        throw new InvalidArgumentException('No Payment driver was specified.');
    }

}
