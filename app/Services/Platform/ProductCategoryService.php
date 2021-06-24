<?php

namespace App\Services\Platform;

use App\Services\BaseService;
use App\Constants\Constant;
use App\Services\Store\PlatformServiceManager;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;
use Hyperf\Utils\Arr;

class ProductCategoryService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'PlatformProductCategory';
    }

    /**
     * 记录交易相关数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 类目树型结构数据
     * @return bool
     */
    public static function handle($storeId, $platform, $data) {

        if (empty($data)) {
            return false;
        }

        $categoryData = PlatformServiceManager::handle($platform, 'Product', 'getProductCategory', [$storeId, $platform, $data]);
        if (empty($categoryData)) {
            return false;
        }


        foreach ($categoryData as $item) {
            $where = [
                Constant::DB_TABLE_PRODUCT_UNIQUE_ID => data_get($item, Constant::DB_TABLE_PRODUCT_UNIQUE_ID, ''),
            ];
            if (isset($item[Constant::DB_TABLE_PRIMARY])) {
                unset($item[Constant::DB_TABLE_PRIMARY]);
            }
            static::updateOrCreate($storeId, $where, $item, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($item)));
        }

        return true;
    }

    /**
     * 拉取交易数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $parameters  请求参数
     */
    public static function handlePull($storeId, $platform, $parameters = [], $categoryData = []) {

        $categoryData = $categoryData ? $categoryData : PlatformServiceManager::handle($platform, 'Product', 'getProductCategoryData', [$storeId, $parameters]);

        if ($categoryData === null) {
            return false;
        }

        $categoryIds = [];
        foreach ($categoryData as $data) {
            static::handle($storeId, $platform, $data);
        }

        return $categoryData;
    }

}
