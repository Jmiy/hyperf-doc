<?php

namespace App\Models;

use App\Models\BaseModel as Model;
use App\Database\Eloquent\SoftDeletes;
use App\Constants\Constant;

class ActivityApplyInfo extends Model {

    use SoftDeletes;

    /**
     * 此模型的连接名称。
     *
     * @var string
     */
    //protected $connection = 'db_mpow';

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

    const STATUS_AT = 'status';
    const DELETED_AT = 'deleted_at';

    /**
     * 会员详情 一对一
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function customerInfo() {
        return make('CustomerInfo')->hasOne(CustomerInfo::class, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::DB_TABLE_CUSTOMER_PRIMARY);
    }

    /**
     * 收件地址 一对多
     * @return \Hyperf\Database\Model\Relations\HasMany
     */
    public function address() {
        return $this->hasMany(CustomerAddress::class, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::DB_TABLE_CUSTOMER_PRIMARY);
    }

    /**
     * 活动申请资料 一对一
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function order() {
        return $this->hasOne(CustomerOrder::class, 'orderno', 'orderno');
    }

}
