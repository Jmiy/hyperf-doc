<?php

namespace App\Services\Platform;

use App\Services\BaseService;
use App\Constants\Constant;
use App\Services\Store\PlatformServiceManager;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;

class ProductImageService extends BaseService {
    
    use GetDefaultConnectionModel;

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'PlatformProductImage';
    }

    /**
     * 记录产品图片相关数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条产品数据
     * @return bool
     */
    public static function handle($storeId, $platform, $data) {

        if (empty($data)) {
            return false;
        }

        $productImages = PlatformServiceManager::handle($platform, 'Product', 'getProductImages', [$storeId, $platform, $data]);
        if (empty($productImages)) {
            return false;
        }

        //删除无效item
        $uniqueIds = collect($productImages)->pluck(Constant::DB_TABLE_UNIQUE_ID);
        static::getModel($storeId)->where(Constant::DB_TABLE_PRODUCT_UNIQUE_ID, data_get($productImages, '0' . Constant::LINKER . Constant::DB_TABLE_PRODUCT_UNIQUE_ID, -1))->whereNotIn(Constant::DB_TABLE_UNIQUE_ID, $uniqueIds)->delete();

        //图片数据
        foreach ($productImages as $item) {

            $uniqueId = data_get($item, Constant::DB_TABLE_UNIQUE_ID, 0); //图片 item 唯一id
            if (empty($uniqueId)) {
                continue;
            }

            $where = [
                Constant::DB_TABLE_UNIQUE_ID => $uniqueId, //图片 item 唯一id, //图片 item 唯一id
            ];
            static::updateOrCreate($storeId, $where, $item, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($item)));
        }

        return true;
    }

}
