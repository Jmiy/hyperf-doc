<?php

namespace App\Models;

use App\Models\BaseModel as Model;
use App\Database\Eloquent\SoftDeletes;
use App\Constants\Constant;

class ActivityProduct extends Model {

    use SoftDeletes;

    /**
     * 此模型的连接名称。
     *
     * @var string
     */
    //protected $connection = 'db_1';

    /**
     * 需要被转换成日期的属性。
     *
     * @var array
     */
    protected $dates = [];

    /**
     * Indicates if the model should be timestamped.
     * 时间戳
     * 默认情况下，Eloquent 会认为在你的数据库表有 created_at 和 updated_at 字段。如果你不希望让 Eloquent 来自动维护这两个字段，可在模型内将 $timestamps 属性设置为 false
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Define a one-to-one relationship.
     *
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function activity_applie() {
        /**
         * Define a one-to-one relationship.
         *
         * @param  string  $related
         * @param  string  $foreignKey
         * @param  string  $localKey
         * @return \Hyperf\Database\Model\Relations\HasOne
         */
        return $this->hasOne(ActivityApply::class, 'ext_id', 'id')->where('ext_type', 'ActivityProduct');
    }

    public function activity_coupon() {
        /**
         * Define a one-to-one relationship.
         *
         * @param  string  $related
         * @param  string  $foreignKey
         * @param  string  $localKey
         * @return \Hyperf\Database\Model\Relations\HasOne
         */
        return $this->hasOne(Coupon::class, 'asin', 'asin');
    }

    public function activity() {
        /**
         * Define a one-to-one relationship.
         *
         * @param  string  $related
         * @param  string  $foreignKey
         * @param  string  $localKey
         * @return \Hyperf\Database\Model\Relations\HasOne
         */
        return $this->hasOne(Activity::class, 'id', 'act_id');
    }

    /**
     * 产品items 一对多
     * @return \Hyperf\Database\Model\Relations\HasMany
     */
    public function items() {
        return $this->hasMany(ActivityProductItem::class, Constant::DB_TABLE_PRODUCT_ID, Constant::DB_TABLE_PRIMARY);
    }

    /**
     * @return \Hyperf\Database\Model\Relations\HasMany
     */
    public function metafields() {
        return \App\Services\MetafieldService::getModel($this->getStoreId())->hasMany(Metafield::class, 'owner_id', 'id');
    }
}
