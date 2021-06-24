<?php

/**
 * Created by Patazon.
 * @desc   :
 * @author : Roy_qiu
 * @email  : Roy_qiu@patazon.net
 * @date   : 2020/11/28 11:47
 */

namespace App\Services;

use App\Services\Traits\GetDefaultConnectionModel;
use App\Constants\Constant;
use App\Utils\Response;
use Illuminate\Support\Facades\Log;

class BusGiftCardApplyService extends BaseService {

    use GetDefaultConnectionModel;

    public static function getBusGiftCardApply($storeId, $orderNo) {
        try {
            $where = [
                'order_number' => $orderNo,
                'status' => [400, 600], //状态，0-未审核，200-审核失败，400-审核通过，600-已使用
            ];
            return static::getModel($storeId)->withTrashed()->buildWhere($where)->get()->toArray();

        } catch (\Exception $e) {
            //Log::debug($e->getTraceAsString());
            return [];
        }
    }

    public static function rewardWarrantyHandle($storeId, $orderNo) {
        $rs = Response::getDefaultResponseData(1);

        $giftCard = static::getBusGiftCardApply($storeId, $orderNo);
        $exists = GiftCardApplyService::getGiftCardApply($storeId, $orderNo);
        //都不存在
        if (empty($giftCard) && !$exists) {
            return $rs;
        }

        //销参礼品返现表存在返现，记录当前匹配的订单礼品数据
        if (!empty($giftCard) && !$exists) {
            GiftCardApplyService::add($storeId, $giftCard);
        }

        $isCanWarranty = DictStoreService::getByTypeAndKey($storeId, 'reward', 'is_can_warranty', true);
        if ($isCanWarranty) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 2);
        } else {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 3);
        }
        return $rs;
    }
}
