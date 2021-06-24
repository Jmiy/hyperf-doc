<?php

namespace App\Models;

use App\Models\BaseModel as Model;

class UserRole extends Model {
    /**
     * 此模型的连接名称。
     *
     * @var string
     */
    protected $connection = 'db_permission';
}
