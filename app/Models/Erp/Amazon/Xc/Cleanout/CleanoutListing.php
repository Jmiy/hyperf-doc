<?php

/**
 * 
 */

namespace App\Models\Erp\Amazon\Xc\Cleanout;

use App\Models\Erp\Amazon\BaseAmazon as Model;

class CleanoutListing extends Model {

    /**
     * 此模型的连接名称。
     *
     * @var string
     */
    protected $connection = 'db_xc_cleanout';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'ASIN_ID';

    /**
     * 与模型关联的数据表
     *
     * @var string
     */
    protected $table = 'cleanout_listing_us';
    
    /**
     * 表前缀
     *
     * @var string
     */
    public static $tablePrefix = 'cleanout_listing';
    
    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'add_date_time';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = null;

}
