<?php

namespace App\Models;

use App\Models\BaseModel as Model;
use App\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Hyperf\Utils\Arr;
use App\Constants\Constant;

class CustomerGuide extends Model {

    use SoftDeletes;

    //可插入表单字段
//    protected $fillable = [
//        'user_id', 'status', 'department_id', 'domain', 'logo', 'title',
//        'description', 'keywords', 'themes', 'lang', 'deleted_at', Constant::DB_TABLE_CREATED_AT, Constant::DB_TABLE_UPDATED_AT
//    ];

    /**
     * 不可被批量赋值的属性。
     * $guarded 属性包含的是不想被批量赋值的属性的数组。即所有不在数组里面的属性都是可以被批量赋值的。也就是说，$guarded 从功能上讲更像是一个「黑名单」。而在使用的时候，也要注意只能是 $fillable 或 $guarded 二选一
     * 如果想让所有的属性都可以被批量赋值，就把 $guarded 定义为空数组。
     *
     * @var array
     */

    /**
     * 与模型关联的数据表
     *
     * @var string
     */
    //protected $primaryKey = 'id';
    protected $table = 'customer_guide';
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
        $where = [
            Constant::DB_TABLE_STORE_ID => $data[Constant::DB_TABLE_STORE_ID],
            'frequency' => 1,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $data[Constant::DB_TABLE_CUSTOMER_PRIMARY],
        ];
        $now_at = Carbon::now()->toDateTimeString();
        $_data = Arr::collapse([[
                Constant::DB_TABLE_CREATED_AT => $now_at,
                Constant::DB_TABLE_UPDATED_AT => $now_at,
                    ], $where]);
        static::insert($_data);

//        //\Illuminate\Support\Facades\DB::enableQueryLog();
//        $select = array_merge(['id'], array_keys($_data));
//        static::withTrashed()->select($select)->updateOrCreate($where, $_data); //updateOrCreate：不可以添加主键id的值  updateOrInsert：可以添加主键id的值
//        //var_dump(\Illuminate\Support\Facades\DB::getQueryLog());
//        //exit;

        return true;
    }

    public static function upd($data) {
        $customer_id = $data[Constant::DB_TABLE_CUSTOMER_PRIMARY] ?? 0;
        if (empty($customer_id)) {
            return false;
        }
        $where = [
            Constant::DB_TABLE_STORE_ID => $data[Constant::DB_TABLE_STORE_ID],
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $data[Constant::DB_TABLE_CUSTOMER_PRIMARY],
        ];
        $now_at = Carbon::now()->toDateTimeString();
        $_data = Arr::collapse([[
                Constant::DB_TABLE_CREATED_AT => $now_at,
                Constant::DB_TABLE_UPDATED_AT => $now_at,
                'frequency' => 2,
                    ], $where]);
        //\Illuminate\Support\Facades\DB::enableQueryLog()
        static::updateOrCreate($where, $_data); //updateOrCreate：不可以添加主键id的值  updateOrInsert：可以添加主键id的值
        //var_dump(\Illuminate\Support\Facades\DB::getQueryLog());
        //exit;

        return true;
    }

}
