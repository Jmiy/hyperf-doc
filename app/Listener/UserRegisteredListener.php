<?php

declare(strict_types=1);
/**
 * https://hyperf.wiki/2.0/#/zh-cn/event
 * 在通过注解注册监听器时，我们可以通过设置 priority 属性定义当前监听器的顺序，如 @Listener(priority=1) ，底层使用 SplPriorityQueue 结构储存，priority 数字越大优先级越高。
 * 使用 @Listener 注解时需 use Hyperf\Event\Annotation\Listener; 命名空间
 */

namespace App\Listener;

use App\Event\UserRegistered;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;

use Hyperf\Di\Annotation\Inject;
use Psr\Container\ContainerInterface;
use Hyperf\Contract\ConfigInterface;

/**
 * @Listener(priority=1)
 */
class UserRegisteredListener implements ListenerInterface
{
    /**
     * @Inject
     * @var ContainerInterface
     */
    protected $container;

    public function listen(): array
    {
        // 返回一个该监听器要监听的事件数组，可以同时监听多个事件
        return [
            UserRegistered::class,
        ];
    }

    /**
     * @param UserRegistered $event
     */
    public function process(object $event)
    {
        // 事件触发后该监听器要执行的代码写在这里，比如该示例下的发送用户注册成功短信等
        // 直接访问 $event 的 user 属性获得事件触发时传递的参数值
        // $event->user;

        var_dump($event->user);

        //通过应用容器 获取配置类对象
        $config = $this->container->get(ConfigInterface::class);

        //获取配置数据
        var_dump($config->get('server'));

    }
}
