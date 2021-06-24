<?php

namespace App\Models;

use App\Models\BaseModel as Model;
use App\Database\Eloquent\SoftDeletes;

class ActivityCustomer extends Model {

    use SoftDeletes;

    /**
     * 此模型的连接名称。
     *
     * @var string
     */
    //protected $connection = 'db_mpow';

    const STATUS_AT = 'status';
    const DELETED_AT = 'deleted_at';

}
