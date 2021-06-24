<?php

namespace App\Services\Platform;

use App\Services\BaseService;
use App\Constants\Constant;
use App\Services\Store\PlatformServiceManager;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;

class ProductVariantService extends BaseService {
    
    use GetDefaultConnectionModel;

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'PlatformProductVariant';
    }

    /**
     * 记录产品变种 item 相关数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条产品数据
     * @return bool
     */
    public static function handle($storeId, $platform, $data) {

        if (empty($data)) {
            return false;
        }

        $productVariants = PlatformServiceManager::handle($platform, 'Product', 'getProductVariants', [$storeId, $platform, $data]);
        if (empty($productVariants)) {
            return false;
        }

        //删除无效item
        $uniqueIds = collect($productVariants)->pluck(Constant::DB_TABLE_UNIQUE_ID);
        static::getModel($storeId)->where(Constant::DB_TABLE_PRODUCT_UNIQUE_ID, data_get($productVariants, '0' . Constant::LINKER . Constant::DB_TABLE_PRODUCT_UNIQUE_ID, -1))->whereNotIn(Constant::DB_TABLE_UNIQUE_ID, $uniqueIds)->delete();

        //图片数据
        foreach ($productVariants as $item) {

            $uniqueId = data_get($item, Constant::DB_TABLE_UNIQUE_ID, 0); //产品 variant 唯一id
            if (empty($uniqueId)) {
                continue;
            }

            $where = [
                Constant::DB_TABLE_UNIQUE_ID => $uniqueId, //产品 variant 唯一id
            ];
            static::updateOrCreate($storeId, $where, $item, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($item)));
        }

        return true;
    }

    public static function getProducts($storeId, $ids, $toArray = true, $select = ['*'], $key = null, $value = null) {

        $_where = [];
        if ($ids) {
            $_where[Constant::DB_TABLE_PRIMARY] = $ids;
        }

        $data = static::getModel($storeId)->select($select)->buildWhere($_where)->get()->pluck($value, $key);

        if (empty($data)) {
            return $toArray ? [] : $data;
        }

        return $toArray ? $data->toArray() : $data;
    }

}
