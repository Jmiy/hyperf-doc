<?php

/**
 * 活动产品item服务
 * User: Jmiy
 * Date: 2019-12-12
 * Time: 13:59
 */

namespace App\Services;

use Hyperf\DbConnection\Db as DB;
use App\Constants\Constant;

class ActivityProductItemService extends BaseService {

    /**
     * 奖品item 编辑
     * @param int $storeId 商城id
     * @param int|string $productId
     * @param array $data
     * @return array
     */
    public static function input($storeId, $productId, $data, $requestData = []) {
        $rs = [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => '',
            Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
        ];

        if (strpos($productId, '-') !== false) {
            $idData = explode('-', $productId);
            $productId = data_get($idData, 1, Constant::PARAMETER_INT_DEFAULT);
        }

        if (empty($productId)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT);
            data_set($rs, Constant::RESPONSE_MSG_KEY, '非法参数');
            return $rs;
        }

        $_data = Constant::PARAMETER_ARRAY_DEFAULT;
        $helpSum = 0;
        $imgUrl = '';
        $name = '';
        $sort = Constant::PARAMETER_INT_DEFAULT;
        $type = data_get($data, '0.' . Constant::DB_TABLE_TYPE, Constant::PARAMETER_INT_DEFAULT);
        $actId = data_get($data, '0.' . Constant::DB_TABLE_ACT_ID, Constant::PARAMETER_INT_DEFAULT);
        $countLimit = [
            //3 => 9,    20201013 邀请活动 去掉个数限制 holife
        ];
        $_countLimit = data_get($countLimit, $type, null);
        if ($_countLimit !== null) {
            $countWhere = [
                Constant::DB_TABLE_ACT_ID => $actId,
                Constant::DB_TABLE_TYPE => $type,
                [[Constant::DB_TABLE_PRIMARY, '!=', $productId]],
            ];
            $count = ActivityProductService::getModel($storeId)->buildWhere($countWhere)->count();
            if ($count >= $_countLimit) {
                data_set($rs, Constant::RESPONSE_CODE_KEY, 0);
                data_set($rs, Constant::RESPONSE_MSG_KEY, '邀请类活动产品上传超过限制(实物9个，折扣/积分不限)，请先将部分产品状态修改，再提交保存');
                return false;
            }
        }

        foreach ($data as $item) {

            $name = data_get($item, Constant::DB_TABLE_NAME, Constant::PARAMETER_STRING_DEFAULT);
            $imgUrl = data_get($item, Constant::DB_TABLE_IMG_URL, Constant::PARAMETER_STRING_DEFAULT);
            $sort = data_get($item, Constant::DB_TABLE_SORT, Constant::PARAMETER_INT_DEFAULT);
            $helpSum = data_get($item, Constant::DB_TABLE_HELP_SUM, Constant::PARAMETER_INT_DEFAULT);

            $type = data_get($item, Constant::DB_TABLE_TYPE, Constant::PARAMETER_INT_DEFAULT); //产品类型数据 0:其他 1:礼品卡 2:coupon 3:实物 5:活动积分
            $typeValue = data_get($item, Constant::DB_TABLE_TYPE_VALUE, Constant::PARAMETER_STRING_DEFAULT); //类型值
            $country = data_get($item, Constant::DB_TABLE_COUNTRY, 'all');

            $itemId = data_get($item, 'item_id', null);
            if ($itemId === null) {//如果是新增
                $productItemWhere = [
                    Constant::DB_TABLE_PRODUCT_ID => $productId,
                    Constant::DB_TABLE_COUNTRY => $country, //国家
                ];
                if (in_array($type, [1, 2])) {//如果是 礼品卡/coupon 就使用 类型数据 作为唯一性判断条件
                    data_set($productItemWhere, Constant::DB_TABLE_TYPE_VALUE, $typeValue);
                }
            } else {
                $productItemWhere = [
                    Constant::DB_TABLE_PRIMARY => $itemId
                ];
            }

            $itemData = [
                Constant::DB_TABLE_PRODUCT_ID => $productId,
                Constant::DB_TABLE_COUNTRY => $country, //国家
                Constant::DB_TABLE_TYPE => $type,
                Constant::DB_TABLE_TYPE_VALUE => $typeValue,
                Constant::DB_TABLE_QTY => data_get($item, Constant::DB_TABLE_QTY, Constant::PARAMETER_INT_DEFAULT), //库存
                Constant::DB_TABLE_ASIN => data_get($item, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT),
                Constant::DB_TABLE_SKU => data_get($item, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT), //sku
                Constant::DB_TABLE_NAME => $name, //标题
            ];
            $_data[] = static::updateOrCreate($storeId, $productItemWhere, $itemData);
        }

        $_data = static::getModel($storeId)->select([Constant::DB_TABLE_PRODUCT_ID, DB::raw('sum(qty) as qty')])->buildWhere([Constant::DB_TABLE_PRODUCT_ID => $productId])->pluck(Constant::DB_TABLE_QTY, Constant::DB_TABLE_PRODUCT_ID); //
        foreach ($_data as $productId => $qty) {
            $updateData = [
                Constant::DB_TABLE_NAME => $name,
                Constant::DB_TABLE_IMG_URL => $imgUrl,
                Constant::DB_TABLE_MB_IMG_URL => $imgUrl,
                Constant::DB_TABLE_SORT => $sort,
                Constant::DB_TABLE_HELP_SUM => $helpSum,
                Constant::DB_TABLE_TYPE => $type,
                Constant::DB_TABLE_QTY => $qty,
                Constant::DB_TABLE_ASIN => data_get($data, '0.' . Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT),
                Constant::DB_TABLE_SKU => data_get($data, '0.' . Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT),
            ];
            ActivityProductService::update($storeId, [Constant::DB_TABLE_PRIMARY => $productId], $updateData);
        }

        data_set($rs, Constant::RESPONSE_DATA_KEY, $_data);
        return $rs;
    }

}
