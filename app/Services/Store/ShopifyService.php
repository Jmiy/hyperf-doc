<?php

namespace App\Services\Store;

use App\Services\Store\Shopify\BaseService;
use App\Services\Store\Shopify\Orders\Transaction;
use App\Services\Store\Shopify\Orders\Order;

class ShopifyService extends BaseService {

    /**
     * 付款回调
     * @param int $storeId 商城id
     * @param string $orderId 订单id
     * @param int $total 订单金额
     * @param string $note 备注
     * @return boolean
     */
    public static function paidOrder($storeId = 2, $orderId = '', $total = 0, $note = '') {

        //更新订单金额
        Transaction::create($storeId, $orderId, $total);

        //更新订单备注
        Order::update($storeId, $orderId, $note);

        return true;
    }

}
