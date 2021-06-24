<?php

namespace App\Models;

use App\Models\BaseModel as Model;
use App\Database\Eloquent\SoftDeletes;
use App\Utils\FunctionHelper;
use App\Constants\Constant;

class Activity extends Model {

    use SoftDeletes;

    /**
     * 此模型的连接名称。
     *
     * @var string
     */
    //protected $connection = 'db_victsing';

    /**
     * 获取需要的字段
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
            $table . Constant::DB_TABLE_PRIMARY,
            $table . Constant::DB_TABLE_ACT_UNIQUE,
            $table . Constant::DB_TABLE_NAME,
            $table . Constant::DB_TABLE_START_AT,
            $table . Constant::DB_TABLE_END_AT,
            $table . Constant::DB_TABLE_CREATED_AT,
            $table . Constant::DB_TABLE_TYPE,
            $table . Constant::DB_TABLE_ACT_TYPE,
            $table . Constant::FILE_URL,
        ];

        $columns = FunctionHelper::getColumns($columns, $addColumns);

        return $columns;
    }

    /**
     * 活动配置 一对多
     * @return \Hyperf\Database\Model\Relations\HasMany
     */
    public function configs() {
        return $this->hasMany(ActivityConfig::class, 'activity_id', 'id');
    }

}
