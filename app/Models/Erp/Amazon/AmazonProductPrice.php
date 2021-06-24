<?php

/**
 * 
 */

namespace App\Models\Erp\Amazon;



class AmazonProductPrice extends BaseAmazon {

    /**
     * 此模型的连接名称。
     *
     * @var string
     */
    //protected $connection = 'db_xc';

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
    protected $table = 'sku_price_us';

    /**
     * 表前缀
     *
     * @var string
     */
    public static $tablePrefix = 'sku_price';

}
