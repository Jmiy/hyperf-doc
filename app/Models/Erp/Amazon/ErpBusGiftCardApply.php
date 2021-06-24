<?php

/**
 * Created by Patazon.
 * @desc   :
 * @author : Roy_qiu
 * @email  : Roy_qiu@patazon.net
 * @date   : 2020/12/7 10:39
 */

namespace App\Models\Erp\Amazon;

use App\Models\BaseModel as Model;

class ErpBusGiftCardApply extends Model {

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
    protected $table = 'bus_gift_card_apply';
}
