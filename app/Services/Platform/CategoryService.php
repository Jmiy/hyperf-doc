<?php

namespace App\Services\Platform;

use App\Services\BaseService;
use App\Constants\Constant;
use App\Services\Store\PlatformServiceManager;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;
use Hyperf\Utils\Arr;

class CategoryService extends BaseService {

    use GetDefaultConnectionModel;

    public static function getCacheTags() {
        return 'category';
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

        $categoryData = PlatformServiceManager::handle($platform, ['Erp', 'Products', 'Category'], 'getCategoryData', [$storeId, $platform, $data]);
        if (empty($categoryData)) {
            return false;
        }

        $categoryIds = [];
        foreach ($categoryData as $item) {
            $where = [
                Constant::DB_TABLE_UNIQUE_ID => data_get($item, Constant::DB_TABLE_UNIQUE_ID, ''),
            ];
            static::updateOrCreate($storeId, $where, $item, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($item)));

            $categoryIds[] = data_get($item, Constant::CATEGORY_ID, '');
        }

        return $categoryIds;
    }

    /**
     * 拉取交易数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $parameters  请求参数
     */
    public static function handlePull($storeId, $platform, $parameters = []) {

        $categoryData = PlatformServiceManager::handle($platform, ['Erp', 'Products', 'Category'], 'getCategory', [$storeId, $parameters]);

        if ($categoryData === null) {
            return false;
        }

        $categoryIds = [];
        foreach ($categoryData as $data) {
            $_categoryIds = static::handle($storeId, $platform, $data);
            $categoryIds = Arr::collapse([$categoryIds, $_categoryIds]);
        }
        $where = [
            'platform' => FunctionHelper::getUniqueId($platform),
            'store_id' => $storeId,
        ];
        static::getModel($storeId)->where($where)->whereNotIn('category_id', $categoryIds)->delete();

        return true;
    }

    /**
     * 拉取交易数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $parameters  请求参数
     */
    public static function getCategory($storeId, $platform, $parameters = []) {

        $key = md5(implode(':', [$storeId, $platform, json_encode($parameters)]));
        $ttl = static::getCacheTtl();

        $_parameters = [$key, $ttl, function () use ($storeId, $platform, $parameters) {
            return PlatformServiceManager::handle($platform, ['Erp', 'Products', 'Category'], 'getCategory', [$storeId, $parameters]);
        }];

        return static::handleCache(static::getCacheTags(), FunctionHelper::getJobData(static::getNamespaceClass(), 'remember', $_parameters));


    }

}
