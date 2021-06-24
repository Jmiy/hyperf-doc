<?php

namespace App\Services\Platform\OnlineStore;

use App\Services\BaseService;
use App\Services\Traits\GetDefaultConnectionModel;
use App\Services\Store\PlatformServiceManager;
use App\Constants\Constant;
use App\Utils\Response;
//use Hyperf\Utils\Arr;
//use Illuminate\Support\Facades\Storage;
use App\Utils\FunctionHelper;
use App\Services\Monitor\MonitorServiceManager;

class PagePublishService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 记录数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $parameters 页面参数
     * @return bool
     */
    public static function handle($storeId, $platform, $parameters) {

        $templateSuffix = data_get($parameters, 'template_suffix', '');

        $env = config('app.env', 'production');
        $templates = 'page.' . $templateSuffix . '.liquid';

        //更新主题资源数据
        $assetKey = data_get($parameters, 'asset_key', '');
        if ($assetKey) {

            $themeHandlePull = ThemeService::handlePull($storeId, $platform, [$storeId]);

            if (data_get($themeHandlePull, Constant::RESPONSE_CODE_KEY, 0) != 1) {
                return $themeHandlePull;
            }

            $themeData = data_get($themeHandlePull, Constant::RESPONSE_DATA_KEY, []);

            $themeName = data_get($parameters, 'theme_name', '');
            $assetValue = data_get($parameters, 'asset_value', '');
            $themeData = collect($themeData);
            $theme = $themeData->firstWhere('name', $themeName);

            $themeId = data_get($theme, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
            if (empty($themeId)) {
                return Response::getDefaultResponseData(200009);
            }

            if (data_get($theme, 'role') !== 'main') {//如果不是线上分支，就在线上分支创建同样的 $assetKey 对应的资源文件
                $mainTheme = $themeData->firstWhere('role', 'main');
                $mainThemeId = data_get($mainTheme, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
                $mainAssetData = PlatformServiceManager::handle($platform, 'Asset', 'getAsset', [$storeId, $mainThemeId, $assetKey]);

                $response = static::getResponseData(200012, 200013, $mainAssetData);
                if ($response !== true) {
                    return $response;
                }

                if (empty($mainAssetData)) {
                    $assetParameters = [
                        'theme_id' => $mainThemeId,
                        'key' => $assetKey,
                        'value' => $assetValue,
                    ];
                    $assetData = PlatformServiceManager::handle($platform, 'Asset', 'update', [$storeId, $assetParameters]);
                    $response = static::getResponseData(200014, 200015, $assetData);
                    if ($response !== true) {
                        return $response;
                    }
                }
            }

            //$value = Storage::disk('front')->get('index.html');
            $assetParameters = [
                'theme_id' => $themeId,
                'key' => $assetKey,
                'value' => $assetValue,
            ];
            $assetData = PlatformServiceManager::handle($platform, 'Asset', 'update', [$storeId, $assetParameters]);

            $response = static::getResponseData(200010, 200011, $assetData);
            if ($response !== true) {
                return $response;
            }
        }

        //更新页面数据
        $handle = data_get($parameters, 'handle', '');

        if (empty($handle)) {
            return Response::getDefaultResponseData(1);
        }

        $searchParameters = [
            'handle' => $handle,
        ];
        $pageData = PlatformServiceManager::handle($platform, 'Page', 'getList', [$storeId, $searchParameters]);
        $response = static::getResponseData(200003, 200004, $pageData);
        if ($response !== true) {
            return $response;
        }

        $id = data_get($pageData, '0' . Constant::LINKER . Constant::DB_TABLE_PRIMARY, 0);
        if (empty($id)) {
            $_data = PlatformServiceManager::handle($platform, 'Page', 'create', [$storeId, $parameters]);

            $response = static::getResponseData(200016, 200005, $_data);
            if ($response !== true) {
                return $response;
            }

            PageService::handle($storeId, $platform, $_data);

            return Response::getDefaultResponseData(1, null, $_data);
        }

//        data_set($parameters, 'metafields.key', 'page_key1');
//        data_set($parameters, 'metafields.value', 'page_value');
//        data_set($parameters, 'metafields.value_type', 'string');
//        data_set($parameters, 'metafields.namespace', 'global');
        data_set($parameters, Constant::DB_TABLE_PRIMARY, $id);
        $_data = PlatformServiceManager::handle($platform, 'Page', 'update', [$storeId, $parameters]);
        $response = static::getResponseData(200017, 200006, $_data);
        if ($response !== true) {
            return $response;
        }

        PageService::handle($storeId, $platform, $_data);

        return Response::getDefaultResponseData(1, null, $_data);
    }

    /**
     * 执行发布任务
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $parameters 平台参数
     * @return type
     */
    public static function handlePublish($storeId, $platform = Constant::PLATFORM_SERVICE_SHOPIFY, $parameters = []) {

        $id = data_get($parameters, Constant::DB_TABLE_PRIMARY, 0);

        $where = [
            'platform' => FunctionHelper::getUniqueId($platform), //平台
            'task_no' => '0',
        ];

        if ($storeId) {
            $where[Constant::DB_TABLE_STORE_ID] = $storeId;
        }

        if ($id) {
            $where = [
                Constant::DB_TABLE_PRIMARY => $id,
            ];
        }

        $taskNo = FunctionHelper::randomStr(10); //任务编号
        $dataWhere = ['task_no' => $taskNo];
        $isUpdated = static::update($storeId, $where, $dataWhere);
        if (empty($isUpdated)) {
            return Response::getDefaultResponseData(1, 'no data publish');
        }

        $select = [
            Constant::DB_TABLE_STORE_ID,
            Constant::DB_TABLE_PRIMARY . ' as publish_id',
            "title",
            "body_html",
            'template_suffix',
            'handle',
            'author',
            'body_url',
            'theme_name',
            'asset_key',
            'published',
            'asset_value_url',
            'asset_value',
            'env',
        ];

        $rs = Response::getDefaultResponseData(1);
        static::getModel($storeId)->buildWhere($dataWhere)->select($select)->orderBy(Constant::DB_TABLE_PRIMARY, 'ASC')
                ->chunk(10, function ($data) use($platform, &$rs) {
                    foreach ($data as $key => $item) {

                        if ($key > 0) {
                            sleep(1);
                        }

                        $request = app('request');
                        $env = data_get($item, 'env');
                        $request->offsetSet('app_env', ($env ? $env : null));

                        $storeId = data_get($item, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);
                        $assetUrl = data_get($item, 'asset_value_url', Constant::PARAMETER_STRING_DEFAULT); //'https://www.baidu.com'
                        if (empty(data_get($item, 'asset_value', '')) && $assetUrl) {
                            $requestData = [];
                            $requestMethod = 'GET';
                            $headers = [
                            ];
                            $html = PlatformServiceManager::handle($platform, 'Base', 'request', [$assetUrl, [], '', '', $requestMethod, $headers]);

                            if (data_get($html, 'curlInfo.http_code') != 200) {
                                data_set($rs, Constant::RESPONSE_CODE_KEY, 0);

                                $exceptionName = '获取ssr失败（asset_value）url：' . $assetUrl;
                                $messageData = [json_encode(data_get($html, 'curlInfo', []))];
                                $message = implode(',', $messageData);
                                $parameters = [$exceptionName, $message, ''];
                                MonitorServiceManager::handle('Ali', 'Ding', 'report', $parameters);

                                return false;
                            }

                            data_set($item, 'asset_value', data_get($html, Constant::RESPONSE_TEXT));
                        }

                        $url = data_get($item, 'body_url', Constant::PARAMETER_STRING_DEFAULT);
                        if (empty(data_get($item, 'body_html')) && $url) {
                            $requestData = [];
                            $requestMethod = 'GET';
                            $headers = [
                            ];
                            $bodyHtml = PlatformServiceManager::handle($platform, 'Base', 'request', [$url, [], '', '', $requestMethod, $headers]);

                            if (data_get($bodyHtml, 'curlInfo.http_code') != 200) {
                                data_set($rs, Constant::RESPONSE_CODE_KEY, 0);

                                $exceptionName = '获取ssr失败（body_url）url：' . $url;
                                $messageData = [json_encode(data_get($bodyHtml, 'curlInfo', []))];
                                $message = implode(',', $messageData);
                                $parameters = [$exceptionName, $message, ''];
                                MonitorServiceManager::handle('Ali', 'Ding', 'report', $parameters);

                                return false;
                            }

                            data_set($item, 'body_html', data_get($bodyHtml, Constant::RESPONSE_TEXT));
                        }

                        $rs = static::handle($storeId, $platform, $item->toArray());
                        $where = [
                            Constant::DB_TABLE_PRIMARY => data_get($item, 'publish_id', -1),
                        ];
                        if (data_get($rs, Constant::RESPONSE_CODE_KEY) == 1) {
                            static::update($storeId, $where, ['is_published' => 1]);
                        } else {
                            static::update($storeId, $where, ['is_published' => 0, 'task_no' => 0]);
                            return false;
                        }
                    }
                });

        if (data_get($rs, Constant::RESPONSE_CODE_KEY) != 1) {
            $isUpdated = static::update($storeId, $dataWhere, ['is_published' => 0, 'task_no' => 0]);
        }

        return $rs;
    }

}
