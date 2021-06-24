<?php

declare(strict_types=1);

namespace App\Utils;

use Hyperf\Utils\Context as HyperfContext;

class Context extends HyperfContext
{
    //protected static $nonCoContext = [];

    public static function storeData($key,callable $callback)
    {
        if (! static::has($key)) {
            return static::set($key, call($callback));
        }

        return static::get($key);
    }
}
