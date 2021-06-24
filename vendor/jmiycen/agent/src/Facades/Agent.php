<?php

namespace Jenssegers\Agent\Facades;

class Agent
{

    public static function __callStatic($method, $args)
    {
        return agent()->$method(...$args);
    }
}
