<?php

namespace App\Services\Store\Shopify\OnlineStore;

use App\Services\Store\Shopify\BaseService;
use App\Constants\Constant;
use App\Utils\FunctionHelper;
use App\Services\Platform\OnlineStore\AssetService;

class Asset extends BaseService {

    /**
     * 获取数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据
     * @return array|max 订单数据
     */
    public static function getAssetData($storeId, $platform, $data) {
        
        $storeId = static::castToString($storeId);
        
        $id = data_get($data, Constant::DB_TABLE_KEY) ?? ''; //id
        $createdAt = FunctionHelper::handleTime(data_get($data, Constant::DB_TABLE_CREATED_AT)); //创建时间
        $themeId = data_get($data, 'theme_id') ?? 0; //theme_id
        return [
            Constant::DB_TABLE_UNIQUE_ID => FunctionHelper::getUniqueId($storeId, $platform, $themeId, $id, static::getCustomClassName()), //平台唯一id
            Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_PLATFORM_CREATED_AT => $createdAt, //订单创建时间
            Constant::DB_TABLE_PLATFORM_UPDATED_AT => FunctionHelper::handleTime(data_get($data, Constant::DB_TABLE_UPDATED_AT)), //订单更新时间
            Constant::DB_TABLE_KEY => $id, //资源key
            Constant::DB_TABLE_VALUE => data_get($data, Constant::DB_TABLE_VALUE) ?? '', //value
            'public_url' => data_get($data, 'public_url') ?? Constant::PARAMETER_STRING_DEFAULT, //public_url
            'content_type' => data_get($data, 'content_type') ?? Constant::PARAMETER_STRING_DEFAULT, //content_type
            'size' => data_get($data, 'size') ?? 0, //size
            'theme_id' => $themeId, //theme_id
            'attachment' => data_get($data, 'attachment') ?? '', //attachment
            Constant::DB_TABLE_ADMIN_GRAPHQL_API_ID => data_get($data, Constant::DB_TABLE_ADMIN_GRAPHQL_API_ID) ?? '', //admin_graphql_api_id
        ];
    }

    /**
     * Retrieves a list of assets for a theme https://shopify.dev/docs/admin-api/rest/reference/online-store/asset?api[version]=2020-04#index-2020-04
     * @param int $storeId 品牌商店id
     * @param int $themeId 主题id
     * @return array 主题资源列表
     */
    public static function getList($storeId = 1, $themeId = '') {
        
        $storeId = static::castToString($storeId);

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'themes/' . $themeId . '/assets.json';

        $requestData = [];
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'GET';
        $headers = [];
        $dataKey = 'assets';
        $curlExtData = [
            'dataKey' => $dataKey,
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        return data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey, data_get($res, Constant::RESPONSE_TEXT));
    }

    /**
     * Retrieves a single asset for a theme. https://shopify.dev/docs/admin-api/rest/reference/online-store/asset?api[version]=2020-04#show-2020-04
     * @param int $storeId 品牌商店id
     * @param int $themeId 主题id
     * @param string $key 主题资源key
     * @return array 主题资源数据
     */
    public static function getAsset($storeId = 1, $themeId = '', $key = 'templates/index.liquid') {
        
        $storeId = static::castToString($storeId);

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'themes/' . $themeId . '/assets.json';

        $requestData = [
            'asset' => [
                Constant::DB_TABLE_KEY => $key,
            ],
        ];
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'GET';
        $headers = [];
        $dataKey = 'asset';
        $curlExtData = [
            'dataKey' => null,
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        return data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey, data_get($res, Constant::RESPONSE_TEXT));
    }

    /**
     * Creates or updates an asset for a theme.In the PUT request, you can include the src or source_key property to create the asset from an existing file.
     * https://shopify.dev/docs/admin-api/rest/reference/online-store/asset?api[version]=2020-04#update-2020-04
     * @param int $storeId 品牌商店id
     * @param int $themeId 主题id
     * @param string $key  主题资源key
     * @param string $value  主题资源值
     * @return array
     */
    public static function update($storeId = 1, $parameters) {
        
        $storeId = static::castToString($storeId);

        $themeId = data_get($parameters, 'theme_id', 0);
        if (empty($themeId)) {
            return false;
        }

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'themes/' . $themeId . '/assets.json';

        $key = data_get($parameters, Constant::DB_TABLE_KEY, '');
        $value = data_get($parameters, Constant::DB_TABLE_VALUE, null);

        $attachment = data_get($parameters, 'attachment', null);

        $src = data_get($parameters, 'src', null);

        $source_key = data_get($parameters, 'source_key', null);

        $assetData = [
            "key" => $key,
        ];

        //Change an existing Liquid template's value
        if ($value !== null) {
            data_set($assetData, 'value', $value);
        }

        //Create an image asset by providing a base64-encoded attachment
        if ($attachment !== null) {
            data_set($assetData, 'attachment', $attachment);
        }

        //Create an image asset by providing a source URL from which to upload the image
        if ($src !== null) {
            data_set($assetData, 'src', $src);
        }

        //Duplicate an existing asset by providing a source key
        if ($source_key !== null) {
            data_set($assetData, 'source_key', $source_key);
        }

        $requestData = json_encode([
            "asset" => $assetData
        ]);
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'PUT';
        $headers = [];
        $dataKey = 'asset';
        $curlExtData = [
            'dataKey' => null,
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        $data = data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey, data_get($res, Constant::RESPONSE_TEXT));

        if ($data !== false && $data !== null && empty(data_get($data, 'errors'))) {
            $data = \Hyperf\Utils\Arr::collapse([$assetData, $data]);
            AssetService::handle($storeId, Constant::PLATFORM_SERVICE_SHOPIFY, $data);
        }

        return $data;
    }

    /**
     * Deletes an asset from a theme. https://shopify.dev/docs/admin-api/rest/reference/online-store/asset?api[version]=2020-04#destroy-2020-04
     * @param int $storeId 品牌商店id
     * @param int $themeId 主题id
     * @param string $key  主题资源key
     * @return array {"message": "assets/bg-body.gif was successfully deleted"} {"message": "layout/theme.liquid could not be deleted"}
     */
    public static function delete($storeId = 1, $themeId = '', $key = '') {

        $storeId = static::castToString($storeId);
        
        if (empty($themeId) || empty($key)) {
            return [];
        }

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'themes/' . $themeId . '/assets.json';

        $requestData = json_encode(array_filter([
            "asset" => [
                "key" => $key,
            ]
        ]));
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'DELETE';
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod);

        return data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . 'message', data_get($res, Constant::RESPONSE_TEXT));
    }

}
