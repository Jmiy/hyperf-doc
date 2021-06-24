<?php

declare(strict_types=1);
/**
 * AOP 面向切面编程
 *
 * @link     https://hyperf.wiki/2.0/#/zh-cn/aop
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace App\Aspect;

use App\Services\UserService;
use App\Annotation\Bar;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;

/**
 * @Aspect
 */
class FooAspect extends AbstractAspect
{
    // 要切入的类，可以多个，亦可通过 :: 标识到具体的某个方法，通过 * 可以模糊匹配
    public $classes = [
        UserService::class,
        //UserService::class.'::getInfoById',
        //UserServiceInterface::class.'::*ById',
//        'App\Services\UserService::getInfoById',
//        'App\Services\UserService::*ById',
    ];

    // 要切入的注解，具体切入的还是使用了这些注解的类，仅可切入类注解和类方法注解
    public $annotations = [
        Bar::class,
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        //var_dump(__METHOD__);

        // 切面切入后，执行对应的方法会由此来负责
        // $proceedingJoinPoint 为连接点，通过该类的 process() 方法调用原方法并获得结果
        // 在调用前进行某些处理
        $result = $proceedingJoinPoint->process();
        // 在调用后进行某些处理

        //var_dump($result);

        return $result;
    }
}
