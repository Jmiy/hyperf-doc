<?php

namespace App\Services\Platform;

use App\Services\BaseService;
use App\Constants\Constant;
use App\Services\Store\PlatformServiceManager;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;
use Hyperf\DbConnection\Db as DB;

class OrderItemService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'PlatformOrderItem';
    }

    /**
     * 记录订单买家客户端数据
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param array $data 回调数据
     * @return bool
     */
    public static function handle($storeId, $platform, $data) {

        if (empty($data)) {
            return false;
        }

        //订单产品数据
        $items = PlatformServiceManager::handle($platform, 'Order', 'getItemData', [$storeId, $platform, $data]);
        if (empty($items)) {
            return false;
        }

        //删除无效item
        $uniqueIds = collect($items)->pluck(Constant::DB_TABLE_UNIQUE_ID);
        static::getModel($storeId)->where(Constant::DB_TABLE_ORDER_UNIQUE_ID, data_get($items, '0' . Constant::LINKER . Constant::DB_TABLE_ORDER_UNIQUE_ID, -1))->whereNotIn(Constant::DB_TABLE_UNIQUE_ID, $uniqueIds)->delete();

        foreach ($items as $item) {

            $uniqueId = data_get($item, Constant::DB_TABLE_UNIQUE_ID, 0); //唯一id
            if (empty($uniqueId)) {
                continue;
            }

            $where = [
                Constant::DB_TABLE_UNIQUE_ID => $uniqueId, //唯一id
            ];
            static::updateOrCreate($storeId, $where, $item, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($item)));
        }
        return true;
    }

    /**
     * 构建自定义where
     * @param int $storeId 品牌商店id
     * @param array $whereFields where
     * @param array $whereColumns 关联where
     * @param array $extData 扩展数据
     * @return array 自定义where
     */
    public static function buildCustomizeWhere($storeId, $whereFields, $whereColumns, $extData = []) {

        $joinData = [];
        if (data_get($extData, 'isLeftCategory')) {
            $dbConfig = OrderItemService::getDbConfig($storeId);
            $tableAlias = data_get($dbConfig, 'table_alias', '');
            $ppcDbConfig = ProductCategoryService::getDbConfig($storeId);
            $ppcTableAlias = data_get($ppcDbConfig, 'table_alias', '');
            $joinData[] = FunctionHelper::getExePlanJoinData(DB::raw(data_get($ppcDbConfig, 'raw_from', '')), function ($join) use($ppcTableAlias, $tableAlias) {
                        $join->on([[$ppcTableAlias . Constant::LINKER . Constant::DB_TABLE_PRODUCT_UNIQUE_ID, '=', $tableAlias . Constant::LINKER . Constant::DB_TABLE_PRODUCT_UNIQUE_ID]])
                                ->where($ppcTableAlias . Constant::LINKER . Constant::DB_TABLE_STATUS, '=', 1)
                        ;
                    });
        }

        $_extData = [
            Constant::DB_EXECUTION_PLAN_JOIN_DATA => $joinData,
        ];

        return static::buildWhereExists($storeId, $whereFields, $whereColumns, $_extData);
    }

}
