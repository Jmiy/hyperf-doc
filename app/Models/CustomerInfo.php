<?php

namespace App\Models;

use App\Models\BaseModel as Model;
use App\Database\Eloquent\SoftDeletes;
use App\Utils\FunctionHelper;
use App\Constants\Constant;

class CustomerInfo extends Model {

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
    protected $table = 'customer_info';

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

    const CREATED_AT = null;
    const UPDATED_AT = 'mtime';
    const TABLE_ALIAS = 'ci';

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
     * 获取site需要的字段
     * @param string $table 表名或者别名
     * @param array $addColumns 额外要查询的字段 array('s.softid','s.file1024_md5 f1024md5')
     * @return array site需要的字段
     */
    public static function getColumns($table = '', $addColumns = array()) {
        //在 laravel 中获取表前缀的方法
        //有3种方法：
        //\Illuminate\Support\Facades\DB::getConfig('prefix');
        //\Illuminate\Support\Facades\DB::connection()->getTablePrefix();
        //Config::get('databases.default.prefix');
        $table = $table ? ($table . '.') : '';
        $columns = [
            $table . 'id',
            $table . Constant::DB_TABLE_CUSTOMER_PRIMARY,
            $table . 'first_name',
            $table . 'last_name',
            $table . 'brithday',
            $table . 'country',
            $table . 'gender',
            $table . 'isorder',
            $table . 'isactivate',
            $table . 'credit',
            $table . 'total_credit',
            $table . 'exp',
            $table . 'honor',
            $table . 'vip',
            $table . 'ip',
            $table . 'phone',
            $table . 'code',
            $table . 'lastlogin',
            $table . 'mtime',
            $table . 'is_complete_edit',
            $table . 'profile_url',
            $table . 'avatar',
        ];

        $columns = FunctionHelper::getColumns($columns, $addColumns);

        return $columns;
    }

    /**
     * 收件地址 一对多
     * @return \Hyperf\Database\Model\Relations\HasMany
     */
    public function address() {
        return $this->hasMany(CustomerAddress::class, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::DB_TABLE_CUSTOMER_PRIMARY);
    }

//    public function test() {
//        return static::withTrashed()->whereIn('department_id', [5, 6]);
//    }

    public function address_home() {
        return $this->hasOne(CustomerAddress::class, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::DB_TABLE_CUSTOMER_PRIMARY)->where('type', 'home');
    }

    public function interests() {
        return $this->hasMany(Interest::class, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::DB_TABLE_CUSTOMER_PRIMARY);
    }

    public function order_data() {
        return $this->hasOne(CustomerOrder::class, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::DB_TABLE_CUSTOMER_PRIMARY);
    }

}
