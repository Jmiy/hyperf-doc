<?php

/**
 * Created by PhpStorm.
 * User: Jmiy
 * Date: 2020/01/21
 * Time: 14:24
 */

namespace App\Models\Erp\Amazon;

use App\Models\BaseModel as Model;

class BaseAmazon extends Model {

    /**
     * 此模型的连接名称。
     *
     * @var string
     */
    protected $connection = 'db_xc';
    
    const STATUS_AT = null;
    const DELETED_AT = null;
    const CREATED_MARK = null;
    const UPDATED_MARK = null;

}
