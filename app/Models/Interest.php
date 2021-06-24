<?php

namespace App\Models;

use App\Models\BaseModel as Model;
use App\Database\Eloquent\SoftDeletes;
//use App\Utils\Support\Facades\Cache;

use Carbon\Carbon;

class Interest extends Model {
    
    use SoftDeletes;
    
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
    
    /**
     * 需要被转换成日期的属性。
     *
     * @var array
     */
    protected $dates = [];
    
    const STATUS_AT = 'status';
    const DELETED_AT = 'deleted_at';

    public static function edit($customerId, $data) {

        if (!isset($data['interests']) || empty($customerId)) {
            return true;
        }

        static::where('customer_id', $customerId)->delete(); //删除兴趣
        $interestData = [];
        $now_at = Carbon::now()->toDateTimeString();
        foreach ($data['interests'] as $interest) {
            $interestData[] = [
                'customer_id' => $customerId,
                'interest' => $interest,
                'created_at' => $now_at,
                'updated_at' => $now_at,
            ];
        }
        if ($interestData) {
            static::insert($interestData);
        }
        return true;
    }

}
