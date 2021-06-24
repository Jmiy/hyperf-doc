<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\JsonRpc\Contracts;

interface CalculatorServiceInterface
{

    public function add(int $a, int $b);
    //public function add(int $a, int $b): int;

    //public function sum(MathValue $v1, MathValue $v2): MathValue;
}
