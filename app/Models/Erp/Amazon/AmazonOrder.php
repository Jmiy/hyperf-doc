<?php

/**
 * Created by PhpStorm.
 * User: harry
 * Date: 2018/4/4
 * Time: 18:52
 */

namespace App\Models\Erp\Amazon;

class AmazonOrder extends BaseAmazon {

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * 与模型关联的数据表
     *
     * @var string
     */
    protected $table = 'amazon_order';

    /**
     * 表前缀
     *
     * @var string
     */
    public static $tablePrefix = 'amazon_order';

}
