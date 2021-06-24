<?php

namespace App\Services\Store\Localhost\Orders;

use App\Services\Store\Localhost\BaseService;
use App\Services\Store\Traits\Orders\Order as OrderTrait;

class Order extends BaseService {
    use OrderTrait;
}
