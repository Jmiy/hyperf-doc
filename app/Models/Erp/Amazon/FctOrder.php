<?php

namespace App\Models\Erp\Amazon;

use App\Models\BaseModel as Model;

class FctOrder extends Model {

    /**
     * 此模型的连接名称。
     *
     * @var string
     */
    protected $connection = 'db_xc_ptx_db';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'auuid';

    /**
     * 与模型关联的数据表
     *
     * @var string
     */
    protected $table = 'c_fct_order';

}
