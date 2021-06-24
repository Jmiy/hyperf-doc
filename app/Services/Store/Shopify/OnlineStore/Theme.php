<?php

namespace App\Services\Store\Shopify\OnlineStore;

use App\Services\Store\Shopify\BaseService;
use App\Constants\Constant;
use App\Utils\FunctionHelper;

class Theme extends BaseService {

    /**
     * 获取数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据
     * @return array|max 订单数据
     */
    public static function getThemeData($storeId, $platform, $data) {
        
        $storeId = static::castToString($storeId);
        
        $id = data_get($data, Constant::DB_TABLE_PRIMARY) ?? 0; //id
        $createdAt = FunctionHelper::handleTime(data_get($data, Constant::DB_TABLE_CREATED_AT)); //创建时间
        return [
            Constant::DB_TABLE_UNIQUE_ID => FunctionHelper::getUniqueId($storeId, $platform, $id, static::getCustomClassName()), //平台唯一id
            Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_PLATFORM_CREATED_AT => $createdAt, //订单创建时间
            Constant::DB_TABLE_PLATFORM_UPDATED_AT => FunctionHelper::handleTime(data_get($data, Constant::DB_TABLE_UPDATED_AT)), //订单更新时间
            'theme_id' => $id, //theme_id
            Constant::DB_TABLE_NAME => data_get($data, Constant::DB_TABLE_NAME) ?? Constant::PARAMETER_STRING_DEFAULT, //name
            'role' => data_get($data, 'role') ?? Constant::PARAMETER_STRING_DEFAULT, //role
            'theme_store_id' => data_get($data, 'theme_store_id') ?? 0, //theme_store_id
            'previewable' => data_get($data, 'previewable') ? 1 : 0, //previewable
            'processing' => data_get($data, 'processing') ? 1 : 0, //processing
            Constant::DB_TABLE_ADMIN_GRAPHQL_API_ID => data_get($data, Constant::DB_TABLE_ADMIN_GRAPHQL_API_ID) ?? '', //admin_graphql_api_id
        ];
    }

    /**
     * Retrieves a list of themes. https://shopify.dev/docs/admin-api/rest/reference/online-store/theme?api[version]=2020-04#index-2020-04
     * @param int $storeId 品牌商店id
     * @return array 主题列表
     */
    public static function getList($storeId = 1) {
        
        $storeId = static::castToString($storeId);

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . "themes.json";

        $requestData = [];
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'GET';
        $headers = [];
        $dataKey = 'themes';
        $curlExtData = [
            'dataKey' => $dataKey,
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        return data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey, data_get($res, Constant::RESPONSE_TEXT));
    }

    /**
     * Retrieves a single theme. https://shopify.dev/docs/admin-api/rest/reference/online-store/theme?api[version]=2020-04#show-2020-04
     * @param int $storeId 品牌商店id
     * @param int $themeId 主题id
     * @return array 主题数据
     */
    public static function getTheme($storeId = 1, $themeId = '') {
        
        $storeId = static::castToString($storeId);

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'themes/' . $themeId . '.json';

        $requestData = [];
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'GET';
        $headers = [];
        $dataKey = 'theme';
        $curlExtData = [
            'dataKey' => $dataKey,
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        return data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey, data_get($res, Constant::RESPONSE_TEXT));
    }

    /**
     * Create a theme that has a custom name and is published. https://shopify.dev/docs/admin-api/rest/reference/online-store/theme?api[version]=2020-04#create-2020-04
     * @param int $storeId 商城id
     * @param string $name 主题名称
     * @param string $src 主题来源
     * @param int $role 主题角色
     * @return array 主题数据
     */
    public static function create($storeId = 1, $name = '', $src = 'http://themes.shopify.com/theme.zip', $role = 'unpublished') {

        $storeId = static::castToString($storeId);
        
        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'themes.json';

        $requestData = [
            "theme" => [
                "name" => $name,
                "src" => $src,
                "role" => $role, //"main"
            ]
        ];
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'POST';
        $headers = [];
        $dataKey = 'theme';
        $curlExtData = [
            'dataKey' => $dataKey,
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        return data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey, data_get($res, Constant::RESPONSE_TEXT));
    }

    /**
     * Updates an existing theme. https://shopify.dev/docs/admin-api/rest/reference/online-store/theme?api[version]=2020-04#update-2020-04
     * @param int $storeId 商城id
     * @param int $themeId 主题id
     * @param int $role 主题角色
     * @return array
     */
    public static function update($storeId = 1, $themeId = '', $role = 'unpublished') {

        $storeId = static::castToString($storeId);
        
        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'themes/' . $themeId . '.json';

        $requestData = [
            "theme" => [
                "id" => $themeId,
                "role" => $role, //"main"
            ]
        ];
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'PUT';
        $headers = [];
        $dataKey = 'theme';
        $curlExtData = [
            'dataKey' => $dataKey,
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        return data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey, data_get($res, Constant::RESPONSE_TEXT));
    }

    /**
     * Deletes a theme. https://shopify.dev/docs/admin-api/rest/reference/online-store/theme?api[version]=2020-04#destroy-2020-04
     * @param int $storeId 商城id
     * @param int $themeId 主题id
     * @return array
     */
    public static function delete($storeId = 1, $themeId = '') {
        
        $storeId = static::castToString($storeId);

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'themes/' . $themeId . '.json';

        $requestData = [];
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'DELETE';
        $headers = [];
        $dataKey = Constant::RESPONSE_TEXT;
        $curlExtData = [
            'dataKey' => $dataKey,
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        return data_get($res, $dataKey);
    }

}
