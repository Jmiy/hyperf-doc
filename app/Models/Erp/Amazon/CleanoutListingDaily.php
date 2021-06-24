<?php

/**
 * 
 */

namespace App\Models\Erp\Amazon;

use App\Models\BaseModel as Model;

class CleanoutListingDaily extends Model {

    /**
     * 此模型的连接名称。
     *
     * @var string
     */
    protected $connection = 'db_xc_single_product';

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
    protected $table = 'cleanout_listing_daily_us';
    
    /**
     * 表前缀
     *
     * @var string
     */
    public static $tablePrefix = 'cleanout_listing_daily';

}
