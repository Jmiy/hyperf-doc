<?php

declare(strict_types=1);
/**
 * 自定义注解
 * @link     https://hyperf.wiki/2.0/#/zh-cn/annotation?id=%e8%87%aa%e5%ae%9a%e4%b9%89%e6%b3%a8%e8%a7%a3
 */
namespace App\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Annotation
 * @Target({"METHOD","PROPERTY"})
 */
class Bar extends AbstractAnnotation
{
    // some code
}
