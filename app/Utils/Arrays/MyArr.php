<?php

namespace App\Utils\Arrays;

use Hyperf\Utils\Arr;

class MyArr {

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
        return Arr::$method(...$args);
    }

    /**
     * 判断数组是否为索引数组
     */
    public static function isIndexedArray($arr) {
        if (is_array($arr)) {
            return count(array_filter(array_keys($arr), 'is_string')) === 0;
        }
        return false;
    }

    /**
     * 判断数组是否为连续的索引数组
     * 以下这种索引数组为非连续索引数组
     * [
     *   0 => 'a',
     *   2 => 'b',
     *   3 => 'c',
     *   5 => 'd',
     * ]
     */
    public static function isContinuousIndexedArray($arr) {
        if (is_array($arr)) {
            $keys = array_keys($arr);
            return $keys == array_keys($keys);
        }
        return false;
    }

    /**
     * 判断数组是否为关联数组
     */
    public static function isAssocArray($arr) {
        if (is_array($arr)) {
            // return !is_indexed_array($arr);
            return count(array_filter(array_keys($arr), 'is_string')) === count($arr);
        }
        return false;
    }

    /**
     * 判断数组是否为混合数组
     */
    public static function isMixedArray($arr) {
        if (is_array($arr)) {
            $count = count(array_filter(array_keys($arr), 'is_string'));
            return $count !== 0 && $count !== count($arr);
        }
        return false;
    }

    public static function flatten($data) {
        foreach ($data as $key => $value) {
            if (Arr::accessible($value) && Arr::isAssoc($value)) {
                //$value = Arr::wrap($value);
                $data = Arr::collapse([$data, $value]);
                foreach ($data as $_value) {
                    if (Arr::accessible($_value) && Arr::isAssoc($_value)) {
                        $data = Arr::collapse([$data, static::flatten($_value)]);
                    }
                }
            }
        }
        return $data;
    }

    /**
     * 数组 去重 去空处理
     */
    public static function handle($data) {
        if (!is_array($data)) {
            return $data;
        }
        return array_unique(array_filter($data));
    }

}
