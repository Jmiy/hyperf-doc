<?php

use Hyperf\Utils\ApplicationContext;

if (!function_exists('geoip')) {
    /**
     * Get the location of the provided IP.
     *
     * @param string $ip
     *
     * @return \Torann\GeoIP\GeoIP|\Torann\GeoIP\Location
     */
    function geoip($ip = null)
    {
        $container = ApplicationContext::getContainer();
        if (!$container->has('geoip')) {
            return null;
        }

        $geoip = $container->get('geoip');

        if (is_null($ip)) {
            return $geoip;
        }

        return $geoip->getLocation($ip);
    }
}