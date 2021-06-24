<?php

/**
 * 积分服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use App\Utils\FunctionHelper;
use App\Constants\Constant;
use App\Utils\Response;

class RewardCategoryService extends BaseService {

    /**
     * 记录类目相关数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 类目树型结构数据
     * @return bool
     */
    public static function handle($storeId, $rewardId, $categoryData = []) {

        if (empty($categoryData)) {
            return false;
        }

        if (is_string($categoryData)) {
            $categoryData = json_decode($categoryData, true);
        }

        //删除无效的 订单item
        $categoryCodes = collect($categoryData)->pluck('category_code');
        static::getModel($storeId)->where('reward_id', $rewardId)->whereNotIn('category_code', $categoryCodes)->delete();

        foreach ($categoryData as $item) {
            $where = [
                'reward_id' => $rewardId,
                'category_code' => data_get($item, 'category_code', ''),
            ];
            static::updateOrCreate($storeId, $where, $item, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($item)));
        }

        return true;
    }

}
