<?php

/**
 * 
 */

namespace App\Models\Erp\Amazon;

use App\Models\BaseModel as Model;

class AmazonOrderItem extends Model {

    /**
     * 此模型的连接名称。
     *
     * @var string
     */
    protected $connection = 'db_xc_order';

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
    protected $table = 'amazon_order_item_us';

    /**
     * 表前缀
     *
     * @var string
     */
    public static $tablePrefix = 'amazon_order_item';

    public function shop_asin() {
        return $this->hasOne(ShopAsin::class, 'shop_sku', 'sku');
    }

}
