<?php

/**
 * 
 */

namespace App\Models\Erp\Amazon;

use App\Models\BaseModel as Model;
use App\Constants\Constant;
use App\Models\Erp\Amazon\Xc\Product\DimCountryAsin;

class ShopInfo extends Model {

    /**
     * 此模型的连接名称。
     *
     * @var string
     */
    protected $connection = 'db_xc_single_product';

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
    protected $table = 'shop_info';

    /**
     * 需要被转换成日期的属性。
     *
     * @var array
     */
    protected $dates = [];

    /**
     * Indicates if the model should be timestamped.
     * 时间戳
     * 默认情况下，Eloquent 预期你的数据表中存在 created_at 和 updated_at 。如果你不想让 Eloquent 自动管理这两个列， 请将模型中的 $timestamps 属性设置为 false：
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * 订单items 一对多
     * @return \Hyperf\Database\Model\Relations\HasMany
     */
    public function variants() {
        return $this->hasMany(ShopAsin::class, Constant::DB_TABLE_ASIN, Constant::DB_TABLE_ASIN);
    }
    
    /**
     * 订单items 一对一
     * @return \Hyperf\Database\Model\Relations\HasMany
     */
    public function category() {
        /**
         * Define a one-to-one relationship.
         *
         * @param  string  $related
         * @param  string  $foreignKey
         * @param  string  $localKey
         * @return \Hyperf\Database\Model\Relations\HasOne
         */
        return $this->hasOne(DimCountryAsin::class, Constant::DB_TABLE_ASIN, Constant::DB_TABLE_ASIN);
    }

}
