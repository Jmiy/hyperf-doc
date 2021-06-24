<?php

namespace Torann\GeoIP\Facades;

class GeoIP
{

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public static function __callStatic($method, $args)
    {
        return geoip()->$method(...$args);
    }
}