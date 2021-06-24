<?php

namespace App\Models\Publics;

use App\Models\BaseModel as Model;

class BaseLogModel extends Model {

    /**
     * 获得关联数据。
     */
    public function ext() {
//        var_dump(__NAMESPACE__);
//        var_dump(get_called_class());
//        var_dump(func_get_args());
        return $this->morphTo();
    }

}
