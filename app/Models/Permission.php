<?php

namespace App\Models;

use App\Models\BaseModel as Model;
use App\Database\Eloquent\SoftDeletes;

class Permission extends Model {

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
     * 此模型的连接名称。
     *
     * @var string
     */
    protected $connection = 'db_permission';

    use SoftDeletes;

    /**
     * 获取订单状态 多对一
     * @return type
     */
    public function parents() {
        return $this->hasOne(Permission::class, 'id', 'parent_id');
    }

}
