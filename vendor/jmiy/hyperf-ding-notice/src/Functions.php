<?php

use DingNotice\DingTalk;

use Hyperf\Utils\ApplicationContext;
use DingNotice\Contracts\FactoryInterface;

if (!function_exists('ding')){

    /**
     * @return bool|DingTalk
     */
    function ding(){

        $container = ApplicationContext::getContainer();
        if (!$container->has(FactoryInterface::class)) {
            return null;
        }

        $arguments = func_get_args();

        $dingTalk = $container->get(FactoryInterface::class);

        if (empty($arguments)) {
            return $dingTalk;
        }

        if (is_string($arguments[0])) {
            $robot = $arguments[1] ?? 'default';
            return $dingTalk->with($robot)->text($arguments[0]);
        }

    }
}