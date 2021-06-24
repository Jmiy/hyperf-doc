<?php

use Hyperf\Utils\ApplicationContext;

if (!function_exists('agent')) {
    /**
     * Get the location of the provided IP.
     *
     * @param string $ip
     *
     * @return \Torann\GeoIP\GeoIP|\Torann\GeoIP\Location
     */
    function agent()
    {
        $container = ApplicationContext::getContainer();
        if (!$container->has('agent')) {
            return null;
        }

        return $container->get('agent');
    }
}