<?php

namespace App\Utils\Support\Facades;

use function Illuminate\Cache\cache;

class Cache {

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param  string  $method
     * @param  array   $args
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public static function __callStatic($method, $args) {
        return cache()->$method(...$args);
    }
}
