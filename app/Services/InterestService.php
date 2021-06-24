<?php

/**
 * 会员兴趣服务
 * User: Jmiy
 * Date: 2019-11-13
 * Time: 16:50
 */

namespace App\Services;

use App\Services\Traits\GetDefaultConnectionModel;
use Carbon\Carbon;

class InterestService extends BaseService {

    use GetDefaultConnectionModel;

    public static function edit($storeId, $customerId, $data) {

        if (!isset($data['interests']) || empty($customerId)) {
            return true;
        }

        $model = static::getModel($storeId);
        $model->where('customer_id', $customerId)->delete(); //删除兴趣
        $interestData = [];
        $now_at = Carbon::now()->toDateTimeString();
        foreach ($data['interests'] as $interest) {
            $interestData[] = [
                'customer_id' => $customerId,
                'interest' => $interest,
                'created_at' => $now_at,
                'updated_at' => $now_at,
            ];
        }

        if ($interestData) {
            $model->insert($interestData);
        }

        return true;
    }

}
