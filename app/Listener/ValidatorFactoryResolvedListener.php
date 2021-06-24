<?php

declare(strict_types=1);
/**
 * https://hyperf.wiki/2.0/#/zh-cn/event
 * 在通过注解注册监听器时，我们可以通过设置 priority 属性定义当前监听器的顺序，如 @Listener(priority=1) ，底层使用 SplPriorityQueue 结构储存，priority 数字越大优先级越高。
 * 使用 @Listener 注解时需 use Hyperf\Event\Annotation\Listener; 命名空间
 */

namespace App\Listener;

use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Event\ValidatorFactoryResolved;

use Hyperf\Event\Annotation\Listener;

/**
 * @Listener
 */
class ValidatorFactoryResolvedListener implements ListenerInterface
{

    public function listen(): array
    {
        return [
            ValidatorFactoryResolved::class,
        ];
    }

    public function process(object $event)
    {
        /**  @var ValidatorFactoryInterface $validatorFactory */
        $validatorFactory = $event->validatorFactory;



        /*         * ***********************自定义验证规则 https://hyperf.wiki/2.0/#/zh-cn/validation?id=%e6%b3%a8%e5%86%8c%e8%87%aa%e5%ae%9a%e4%b9%89%e9%aa%8c%e8%af%81%e8%a7%84%e5%88%99 ******************************************************** */
        // 注册了 foo 验证器
//        $validatorFactory->extend('foo', function ($attribute, $value, $parameters, $validator) {
//            return $value == 'foo';
//        });
//
//        // 当创建一个自定义验证规则时，你可能有时候需要为错误信息定义自定义占位符这里扩展了 :foo 占位符
//        $validatorFactory->replacer('foo', function ($message, $attribute, $rule, $parameters) {
//            return str_replace(':foo', $attribute, $message);
//        });
        /**
         * 注册自定义的验证规则的另一种方法是使用 $validatorFactory 中的 extend 方法。让我们在 服务容器 中使用这个方法来注册自定义验证规则：
         * $validatorFactory->extend('api_code_msg', function ($attribute, $value, $parameters, $validator) {
        return false;
        });
         */
        /**
         * 除了使用闭包，你也可以传入类和方法到 extend 方法中：
         * $validatorFactory->extend('foo', 'FooValidator@validate');
         */
        /**
         * 当创建一个自定义验证规则时，你可能有时候需要为错误信息定义自定义占位符。可以通过创建自定义验证器然后调用 Validator 门面上的 replacer 方法。你可以在 服务容器 的 boot 方法中执行如下操作：
         * $validatorFactory->replacer('foo', function ($message, $attribute, $rule, $parameters) {
        //return str_replace(...);
        });
         */
        /**
         * 隐式扩展
         * 如果即使属性为空也要验证规则，则一定要暗示属性是必须的。要创建这样一个「隐式」扩展，可以使用 $validatorFactory->extendImplicit() 方法：
         */
        $validatorFactory->extendImplicit('api_code_msg', function ($attribute, $value, $parameters, $validator) {
            return false;
        });
    }
}
