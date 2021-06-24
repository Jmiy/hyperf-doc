<?php

namespace Torann\GeoIP;

use Exception;
use Monolog\Logger;
use Hyperf\Utils\Arr;
use Illuminate\Cache\CacheManager;
use Monolog\Handler\StreamHandler;

use Hyperf\Utils\Context;
use Psr\Http\Message\ServerRequestInterface;

class GeoIP
{
    /**
     * Illuminate config repository instance.
     *
     * @var array
     */
    protected $config;

    /**
     * Remote Machine IP address.
     *
     * @var float
     */
    protected $remote_ip = null;

    /**
     * Current location instance.
     *
     * @var Location
     */
    protected $location = null;

    /**
     * Currency data.
     *
     * @var array
     */
    protected $currencies = null;

    /**
     * GeoIP service instance.
     *
     * @var Contracts\ServiceInterface
     */
    protected $service;

    /**
     * Cache manager instance.
     *
     * @var \Illuminate\Cache\CacheManager
     */
    protected $cache;

    /**
     * Default Location data.
     *
     * @var array
     */
    protected $default_location = [
        'ip' => '127.0.0.0',
        'iso_code' => 'US',
        'country' => 'United States',
        'city' => 'New Haven',
        'state' => 'CT',
        'state_name' => 'Connecticut',
        'postal_code' => '06510',
        'lat' => 41.31,
        'lon' => -72.92,
        'timezone' => 'America/New_York',
        'continent' => 'NA',
        'currency' => 'USD',
        'default' => true,
        'cached' => false,
    ];

    /**
     * Create a new GeoIP instance.
     *
     * @param array        $config
     * @param CacheManager $cache
     */
    public function __construct(array $config, CacheManager $cache)
    {
        $this->config = $config;

        // Create caching instance
        $this->cache = new Cache(
            $cache,
            $this->config('cache_tags'),
            $this->config('cache_expires', 30)
        );

        // Set custom default location
        $this->default_location = array_merge(
            $this->default_location,
            $this->config('default_location', [])
        );

        // Set IP
        $this->remote_ip = $this->default_location['ip'] = $this->getClientIP();
    }

    /**
     * Get the location from the provided IP.
     *
     * @param string $ip
     *
     * @return \Torann\GeoIP\Location
     */
    public function getLocation($ip = null)
    {
        // Get location data
        $this->location = $this->find($ip);

        // Should cache location
        if ($this->shouldCache($ip, $this->location)) {
            $this->getCache()->set($ip, $this->location);
        }

        return $this->location;
    }

    /**
     * Find location from IP.
     *
     * @param string $ip
     *
     * @return \Torann\GeoIP\Location
     * @throws \Exception
     */
    private function find($ip = null)
    {
        // If IP not set, user remote IP
        $ip = $ip ?: $this->remote_ip;

        // Check cache for location
        if ($this->config('cache', 'none') !== 'none' && $location = $this->getCache()->get($ip)) {
            $location->cached = true;

            return $location;
        }

        // Check if the ip is not local or empty
        if ($this->isValid($ip)) {
            try {
                // Find location
                $location = $this->getService()->locate($ip);

                // Set currency if not already set by the service
                if (!$location->currency) {
                    $location->currency = $this->getCurrency($location->iso_code);
                }

                // Set default
                $location->default = false;

                return $location;
            }
            catch (\Exception $e) {
                if ($this->config('log_failures', true) === true) {
                    $log = new Logger('geoip');
                    $log->pushHandler(new StreamHandler(BASE_PATH . '/storage/logs/geoip.log', Logger::ERROR));
                    $log->error($e);
                }
            }
        }

        return $this->getService()->hydrate($this->default_location);
    }

    /**
     * Get the currency code from ISO.
     *
     * @param string $iso
     *
     * @return string
     */
    public function getCurrency($iso)
    {
        if ($this->currencies === null && $this->config('include_currency', false)) {
            $this->currencies = include(__DIR__ . '/Support/Currencies.php');
        }

        return Arr::get($this->currencies, $iso);
    }

    /**
     * Get service instance.
     *
     * @return \Torann\GeoIP\Contracts\ServiceInterface
     * @throws Exception
     */
    public function getService() {

        $service = $this->config('service');
        if (data_get($this->service, $service, null) === null) {
            // Get service configuration
            $config = $this->config('services.' . $service, []);

            // Get service class
            $class = Arr::pull($config, 'class');

            // Sanity check
            if ($class === null) {
                throw new Exception('The GeoIP service is not valid.');
            }

            // Create service instance
            data_set($this->service, $service, new $class($config));
        }

        return data_get($this->service, $service, null);
    }

    /**
     * Get cache instance.
     *
     * @return \Torann\GeoIP\Cache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * Get the client IP address.
     *
     * @return string
     */
    public function getClientIP($ip = null)
    {
        if (!empty($ip)) {
            return $ip;
        }

        $remotes_keys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'x_forwarded_for',
            'client_ip',
            'x_forwarded',
            'forwarded_for',
            'forwarded',
            'addr',
            'x_cluster_client_ip',
            'x-forwarded-for',
            'client-ip',
            'x-forwarded',
            'forwarded-for',
            'remote-addr',
            'x-cluster-client-ip',
        ];

        $clientIP = '127.0.0.0';
        $requestHeaders = Context::get(ServerRequestInterface::class)->getHeaders();
        foreach ($remotes_keys as $key) {
            $address = data_get($requestHeaders, strtolower($key));
            if (empty($address)) {
                continue;
            }

            $address = is_array($address) ? $address : [$address];

            foreach ($address as $_address) {
                $ipData = explode(',', $_address);
                foreach ($ipData as $clientIP) {
                    if ($this->isValid($clientIP)) {
                        return $clientIP;
                    }
                }
            }
        }

        return $clientIP;
    }

    /**
     * Checks if the ip is valid.
     *
     * @param string $ip
     *
     * @return bool
     */
    private function isValid($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the location should be cached.
     *
     * @param string   $ip
     * @param Location $location
     *
     * @return bool
     */
    private function shouldCache($ip = null, Location $location)
    {
        if ($location->default === true || $location->cached === true) {
            return false;
        }

        switch ($this->config('cache', 'none')) {
            case 'all':
                return true;
            case 'some' && $ip === null:
                return true;
        }

        return false;
    }

    /**
     * Get configuration value.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function config($key, $default = null)
    {
        return Arr::get($this->config, $key, $default);
    }

    /**
     * Set configuration value.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    public function setConfig($key, $value = null) {
        data_set($this->config, $key, $value);
        return $this;
    }

    public function setService($service) {
        $this->service = $service;

        return $this;
    }
}
