<?php

namespace App\Services\Platform;

use App\Services\BaseService;
use App\Constants\Constant;
use App\Services\Store\PlatformServiceManager;
use App\Utils\Response;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;

class ProductService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'PlatformProduct';
    }

    /**
     * 记录产品相关数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条产品数据
     * @return bool
     */
    public static function handle($storeId, $platform, $data) {

        if (empty($data)) {
            return false;
        }

        $productData = PlatformServiceManager::handle($platform, 'Product', 'getPlatformProductData', [$storeId, $platform, $data]);

        $uniqueId = data_get($productData, Constant::DB_TABLE_UNIQUE_ID, 0); //产品唯一id
        if (empty($uniqueId)) {
            return false;
        }

        $where = [
            Constant::DB_TABLE_UNIQUE_ID => $uniqueId, //产品唯一id
        ];
        static::updateOrCreate($storeId, $where, $productData, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($productData)));

        ProductImageService::handle($storeId, $platform, $data);

        ProductVariantService::handle($storeId, $platform, $data);

        ProductCategoryService::handle($storeId, $platform, $data);

        return true;
    }

    /**
     * 拉取产品数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $parameters 拉取平台产品参数
     * @param array $productData 平台产品数据
     * @return type
     */
    public static function handlePull($storeId, $platform, $parameters, $productData = []) {

        $productData = $productData ? $productData : PlatformServiceManager::handle($platform, 'Product', 'getProduct', [$storeId, $parameters]); //Constant::PLATFORM_SERVICE_SHOPIFY

        if ($productData === null) {
            return false;
        }

        if (empty($productData)) {
            unset($productData);
            return Response::getDefaultResponseData(0, 'data is empty', []);
        }

        foreach ($productData as $data) {
            static::handle($storeId, $platform, $data);
        }

        return Response::getDefaultResponseData(1, '', $productData);
    }

}
