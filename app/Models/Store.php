<?php

namespace App\Models;

use App\Models\BaseModel as Model;
use App\Database\Eloquent\SoftDeletes;

class Store extends Model {
    
    use SoftDeletes;

}
