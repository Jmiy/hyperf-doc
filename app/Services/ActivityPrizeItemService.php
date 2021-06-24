<?php

/**
 * 积分服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use Hyperf\DbConnection\Db as DB;
use Hyperf\Utils\Arr;
use App\Constants\Constant;
use App\Utils\FunctionHelper;

class ActivityPrizeItemService extends BaseService {

    /**
     * 添加记录
     * @param int $storeId 商城id
     * @param array $where where条件
     * @param array $data  数据
     * @return int
     */
    public static function insert($storeId, $where, $data) {
        return static::updateOrCreate($storeId, $where, $data); //updateOrCreate：不可以添加主键id的值  updateOrInsert：可以添加主键id的值
    }

    /**
     * 奖品item 编辑
     * @param int $storeId 商城id
     * @param int|string $prizeId
     * @param array $data
     * @return array
     */
    public static function input($storeId, $prizeId, $data, $requestData = []) {

        $rs = [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => '',
            Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
        ];

        if (strpos($prizeId, '-') !== false) {
            $idData = explode('-', $prizeId);
            $prizeId = data_get($idData, 1, Constant::PARAMETER_INT_DEFAULT);
        }

        if (empty($prizeId)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT);
            data_set($rs, Constant::RESPONSE_MSG_KEY, '非法参数');
        }

        $_data = Constant::PARAMETER_ARRAY_DEFAULT;
        $imgUrl = '';
        $name = '';
        $sort = Constant::PARAMETER_INT_DEFAULT;
        $type = Constant::PARAMETER_INT_DEFAULT;
        foreach ($data as $item) {
            $name = data_get($item, Constant::DB_TABLE_NAME, Constant::PARAMETER_STRING_DEFAULT);
            $imgUrl = data_get($item, Constant::DB_TABLE_IMG_URL, Constant::PARAMETER_STRING_DEFAULT);
            $sort = data_get($item, Constant::DB_TABLE_SORT, Constant::PARAMETER_INT_DEFAULT);
            $type = data_get($item, Constant::DB_TABLE_TYPE, Constant::PARAMETER_INT_DEFAULT);
            $typeValue = data_get($item, Constant::DB_TABLE_TYPE_VALUE, Constant::PARAMETER_STRING_DEFAULT); //类型数据
            $country = data_get($item, Constant::DB_TABLE_COUNTRY, 'all');

            $itemId = data_get($item, 'item_id', null);
            if ($itemId === null) {//如果是新增
                $prizeItemWhere = [
                    Constant::DB_TABLE_PRIZE_ID => $prizeId,
                    Constant::DB_TABLE_COUNTRY => $country, //国家
                ];
                if (in_array($type, [1, 2])) {//如果是 coupon/实物 就使用asin作为唯一性判断条件 奖品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:活动积分
                    data_set($prizeItemWhere, Constant::DB_TABLE_TYPE_VALUE, $typeValue);
                }
            } else {
                $prizeItemWhere = [
                    Constant::DB_TABLE_PRIMARY => $itemId
                ];
            }

            $probability = data_get($item, 'probability', Constant::PARAMETER_INT_DEFAULT); //中奖概率 %
            $winProbability = ActivityPrizeService::getWinProbability($probability);
            $itemData = Arr::collapse([$winProbability, [
                            Constant::DB_TABLE_PRIZE_ID => $prizeId,
                            Constant::DB_TABLE_COUNTRY => $country, //国家
                            Constant::DB_TABLE_TYPE => $type,
                            Constant::DB_TABLE_TYPE_VALUE => $typeValue,
                            Constant::DB_TABLE_QTY => data_get($item, Constant::DB_TABLE_QTY, Constant::PARAMETER_INT_DEFAULT), //库存
                            Constant::DB_TABLE_ASIN => data_get($item, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT),
                            Constant::DB_TABLE_SKU => data_get($item, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT), //sku
                            Constant::DB_TABLE_NAME => $name, //标题
            ]]);
            $_data[] = static::updateOrCreate($storeId, $prizeItemWhere, $itemData);
        }

        $data = static::getModel($storeId)->select([Constant::DB_TABLE_PRIZE_ID, DB::raw('sum(qty) as qty')])->buildWhere([Constant::DB_TABLE_PRIZE_ID => $prizeId])->pluck(Constant::DB_TABLE_QTY, Constant::DB_TABLE_PRIZE_ID); //
        foreach ($data as $prizeId => $qty) {
            $prizeData = Arr::collapse([ActivityPrizeService::getWinProbability(0), [
                            Constant::DB_TABLE_NAME => $name,
                            Constant::DB_TABLE_IMG_URL => $imgUrl,
                            Constant::DB_TABLE_SORT => $sort,
                            Constant::DB_TABLE_TYPE => $type,
                            Constant::DB_TABLE_QTY => $qty,
            ]]);
            ActivityPrizeService::update($storeId, [Constant::DB_TABLE_PRIMARY => $prizeId], $prizeData);
        }

        data_set($rs, Constant::RESPONSE_DATA_KEY, $_data);
        return $rs;
    }

}
