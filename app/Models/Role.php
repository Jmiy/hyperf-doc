<?php

namespace App\Models;

use App\Models\BaseModel as Model;
use App\Database\Eloquent\SoftDeletes;

class Role extends Model {

    use SoftDeletes;

    /**
     * 此模型的连接名称。
     *
     * @var string
     */
    protected $connection = 'db_permission';

    /**
     * 获取角色权限数据  多对多
     * @return type
     */
    public function permissions() {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id')->as('role_permissions')->withPivot(['id', 'select', 'update'])->withTrashed();
    }

}
