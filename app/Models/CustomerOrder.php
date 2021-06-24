<?php

namespace App\Models;

use App\Models\BaseModel as Model;
use App\Database\Eloquent\SoftDeletes;
use App\Services\OrderReviewService;
use App\Services\Platform\OrderItemService;
use App\Constants\Constant;
use App\Models\Platform\OrderItem;
use App\Services\CustomerInfoService;
use App\Services\Platform\OrderService;
use App\Models\Platform\Order;

class CustomerOrder extends Model {

    use SoftDeletes;

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
    protected $table = 'customer_order';

    /**
     * Indicates if the model should be timestamped.
     * 时间戳
     * 默认情况下，Eloquent 预期你的数据表中存在 created_at 和 updated_at 。如果你不想让 Eloquent 自动管理这两个列， 请将模型中的 $timestamps 属性设置为 false：
     *
     * @var bool
     */
    public $timestamps = false;

    const CREATED_AT = 'ctime';
    const UPDATED_AT = 'mtime';
    const TABLE_ALIAS = 'co';

    //可插入表单字段
//    protected $fillable = [
//        'user_id', 'status', 'department_id', 'domain', 'logo', 'title',
//        'description', 'keywords', 'themes', 'lang', 'deleted_at', 'created_at', 'updated_at'
//    ];

    /**
     * 不可被批量赋值的属性。
     * $guarded 属性包含的是不想被批量赋值的属性的数组。即所有不在数组里面的属性都是可以被批量赋值的。也就是说，$guarded 从功能上讲更像是一个「黑名单」。而在使用的时候，也要注意只能是 $fillable 或 $guarded 二选一
     * 如果想让所有的属性都可以被批量赋值，就把 $guarded 定义为空数组。
     *
     * @var array
     */
    protected $guarded = [];

//    public function test() {
//        return static::withTrashed()->whereIn('department_id', [5, 6]);
//    }

    public function credit() {
        return $this->hasOne(CreditLog::class, 'ext_id', 'id')->where('ext_type', 'customer_order')->withTrashed();
    }

    public function customer_info() {
        return CustomerInfoService::getModel($this->getStoreId())->hasOne(CustomerInfo::class, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::DB_TABLE_CUSTOMER_PRIMARY);
    }

    public function getMorphToConnection() {
        return [
            'Order' => 'default_connection',
            'PlatformOrder' => 'parent',
        ];
    }

    /**
     * 订单items 一对多
     * @return \Hyperf\Database\Model\Relations\HasMany
     */
    public function items() {
        return OrderItemService::getModel($this->getStoreId())->hasMany(OrderItem::class, Constant::DB_TABLE_ORDER_UNIQUE_ID, Constant::DB_TABLE_ORDER_UNIQUE_ID);
    }

    /**
     * 订单 一对一
     * @return \Hyperf\Database\Model\Relations\HasMany
     */
    public function order() {
        return OrderService::getModel($this->getStoreId())->hasOne(Order::class, Constant::DB_TABLE_UNIQUE_ID, Constant::DB_TABLE_ORDER_UNIQUE_ID);
    }

    /**
     * 订单reviews 一对多
     * @return \Hyperf\Database\Model\Relations\HasMany
     */
    public function reviews() {
        return $this->hasMany(OrderReview::class, Constant::DB_TABLE_ORDER_NO, Constant::DB_TABLE_ORDER_NO);
    }
}
