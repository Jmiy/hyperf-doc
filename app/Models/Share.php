<?php

namespace App\Models;

use App\Models\BaseModel as Model;
use App\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Hyperf\Utils\Arr;
use App\Constants\Constant;

class Share extends Model {

    use SoftDeletes;

    /**
     * 不可被批量赋值的属性。
     * $guarded 属性包含的是不想被批量赋值的属性的数组。即所有不在数组里面的属性都是可以被批量赋值的。也就是说，$guarded 从功能上讲更像是一个「黑名单」。而在使用的时候，也要注意只能是 $fillable 或 $guarded 二选一
     * 如果想让所有的属性都可以被批量赋值，就把 $guarded 定义为空数组。
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = Constant::DB_TABLE_CREATED_AT;

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = Constant::DB_TABLE_UPDATED_AT;
    const STATUS_AT = 'status';
    const DELETED_AT = 'deleted_at';

    public static function add($data) {

        $customer_id = $data[Constant::DB_TABLE_CUSTOMER_PRIMARY] ?? 0;
        if (empty($customer_id)) {
            return false;
        }

        $content = $data['url'] ?? '';
        $where = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $data[Constant::DB_TABLE_CUSTOMER_PRIMARY],
            'content_md5' => md5($content),
        ];

        $now_at = Carbon::now()->toDateTimeString();
        if (static::where($where)->exists()) {
            if (isset($data['bk'])) {//如果是数据恢复，就更新添加时间
                $updateData = [
                    Constant::DB_TABLE_CREATED_AT => data_get($data, Constant::DB_TABLE_CREATED_AT, $now_at),
                    Constant::DB_TABLE_UPDATED_AT => data_get($data, Constant::DB_TABLE_UPDATED_AT, $now_at),
                ];
                static::where($where)->update($updateData);
            }
            return 20019;
        }

        $_data = Arr::collapse([[
                'content' => $content,
                Constant::DB_TABLE_CREATED_AT => data_get($data, Constant::DB_TABLE_CREATED_AT, $now_at),
                Constant::DB_TABLE_UPDATED_AT => data_get($data, Constant::DB_TABLE_UPDATED_AT, $now_at),
                    ], $where]);
        static::insert($_data);

        return true;
    }

}
