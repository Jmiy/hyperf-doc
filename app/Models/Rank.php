<?php

namespace App\Models;

use App\Models\BaseModel as Model;
use App\Database\Eloquent\SoftDeletes;

use App\Database\ModelCache\Cacheable;
use App\Database\ModelCache\CacheableInterface;

use App\Database\Scout\Searchable;

use App\Services\RankDayService;
use App\Constants\Constant;

class Rank extends Model implements CacheableInterface {

    use SoftDeletes;
    use Cacheable;
    //use Searchable;

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
    //public $timestamps = false;

    public static $typeData = [
        1 => 'Sharing',
        2 => 'Invitation',
    ];

//    /**
//     * Get the index name for the model.
//     *
//     * @return string
//     */
//    public function searchableAs()
//    {
//        return $this->getConnectionName() . '_' . config('scout.prefix') . $this->getTable();
//    }

    public function rank_day() {
        return RankDayService::getModel($this->getStoreId())->hasMany(RankDay::class, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::DB_TABLE_CUSTOMER_PRIMARY);
    }

}
