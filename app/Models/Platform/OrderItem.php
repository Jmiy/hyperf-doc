<?php

namespace App\Models\Platform;

use App\Models\BaseModel as Model;
use App\Database\Eloquent\SoftDeletes;
use App\Constants\Constant;

class OrderItem extends Model {

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
    protected $table = 'platform_order_items';

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
     * 不可被批量赋值的属性。
     * $guarded 属性包含的是不想被批量赋值的属性的数组。即所有不在数组里面的属性都是可以被批量赋值的。也就是说，$guarded 从功能上讲更像是一个「黑名单」。而在使用的时候，也要注意只能是 $fillable 或 $guarded 二选一
     * 如果想让所有的属性都可以被批量赋值，就把 $guarded 定义为空数组。
     *
     * @var array
     */
    protected $guarded = [];
    
    const TABLE_ALIAS = 'poi';

    /**
     * 订单items 一对一
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function product() {
        return $this->HasOne(Product::class, Constant::DB_TABLE_UNIQUE_ID, Constant::DB_TABLE_PRODUCT_UNIQUE_ID);
    }

    /**
     * 订单items 一对一
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function variant() {
        return $this->HasOne(ProductVariant::class, Constant::DB_TABLE_UNIQUE_ID, Constant::DB_TABLE_PRODUCT_VARIANT_UNIQUE_ID);
    }

    /**
     * 订单items物流数据 一对一
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function item_fulfillment() {
        return $this->HasOne(FulfillmentOrderItem::class, Constant::DB_TABLE_ORDER_ITEM_UNIQUE_ID, Constant::DB_TABLE_UNIQUE_ID);
    }

    /**
     * 订单items产品类目数据 一对一
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function product_category() {
        return $this->HasOne(ProductCategory::class, Constant::DB_TABLE_PRODUCT_UNIQUE_ID, Constant::DB_TABLE_PRODUCT_UNIQUE_ID);
    }

}
