<?php

namespace App\Services\Platform\OnlineStore;

use App\Services\BaseService;
use App\Services\Traits\GetDefaultConnectionModel;
use App\Services\Store\PlatformServiceManager;
use App\Constants\Constant;
use App\Utils\FunctionHelper;
use App\Utils\Response;

class AssetService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 记录数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条数据
     * @return bool
     */
    public static function handle($storeId, $platform, $data) {

        $pageId = data_get($data, Constant::DB_TABLE_PRIMARY) ?? 0; //平台订单ID
        $_data = PlatformServiceManager::handle($platform, 'Asset', 'getAssetData', [$storeId, $platform, $data]);

        //数据
        $where = [
            Constant::DB_TABLE_UNIQUE_ID => data_get($_data, Constant::DB_TABLE_UNIQUE_ID, 0), //平台订单唯一id
        ];
//        $updateHandle = [
//            function ($instance) use($_data) {
//                return data_get($instance, 'platform_updated_at', '') < data_get($_data, 'platform_updated_at', '');
//            }
//        ];

        return static::updateOrCreate($storeId, $where, $_data, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($_data)));
    }

    /**
     * 拉取数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $parameters 平台参数
     * @return type
     */
    public static function handlePull($storeId, $platform, $parameters) {
        $_data = PlatformServiceManager::handle($platform, 'Asset', 'getList', $parameters);

        if (empty($_data)) {
            unset($_data);
            return Response::getDefaultResponseData(0, 'data is empty', []);
        }

        foreach ($_data as $data) {

            $themeId = data_get($data, 'theme_id', 0);
            $assetKey = data_get($data, 'key', '');
            if (empty($themeId)) {
                continue;
            }

            $assetData = PlatformServiceManager::handle($platform, 'Asset', 'getAsset', [$storeId, $themeId, $assetKey]);
            static::handle($storeId, $platform, $assetData);
        }

        return Response::getDefaultResponseData(1, null, $_data);
    }

}
