<?php

namespace App\Services\Store\Shopify\OnlineStore;

use App\Services\Store\Shopify\BaseService;
use Carbon\Carbon;
use App\Utils\FunctionHelper;
use DateTime;
use App\Constants\Constant;
use App\Services\Store\Shopify\Metafield\Metafield;
use Hyperf\Utils\Arr;

class Page extends BaseService {

    /**
     * 获取页面数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条订单数据
     * @return array|max 订单数据
     */
    public static function getPageData($storeId, $platform, $data) {
        
        $storeId = static::castToString($storeId);
        
        $id = data_get($data, Constant::DB_TABLE_PRIMARY) ?? 0; //id
        $createdAt = FunctionHelper::handleTime(data_get($data, Constant::DB_TABLE_CREATED_AT)); //创建时间
        return [
            Constant::DB_TABLE_UNIQUE_ID => FunctionHelper::getUniqueId($storeId, $platform, $id, static::getCustomClassName()), //平台唯一id
            Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_PLATFORM_CREATED_AT => $createdAt, //订单创建时间
            Constant::DB_TABLE_PLATFORM_UPDATED_AT => FunctionHelper::handleTime(data_get($data, Constant::DB_TABLE_UPDATED_AT)), //订单更新时间
            'published_at' => FunctionHelper::handleTime(data_get($data, 'published_at')), //处理时间
            'page_id' => $id, //页面id
            'title' => data_get($data, 'title') ?? '', //title
            'shop_id' => data_get($data, 'shop_id') ?? 0, //shop_id
            'handle' => data_get($data, 'handle') ?? '', //handle
            'body_html' => data_get($data, 'body_html') ?? '', //body_html
            'author' => data_get($data, 'author') ?? '', //author
            'published_at' => FunctionHelper::handleTime(data_get($data, 'published_at')), //published_at
            'template_suffix' => data_get($data, 'template_suffix') ?? '', //template_suffix
            Constant::DB_TABLE_ADMIN_GRAPHQL_API_ID => data_get($data, Constant::DB_TABLE_ADMIN_GRAPHQL_API_ID) ?? '', //admin_graphql_api_id
        ];
    }

    /**
     * Retrieve a list of all pages https://help.shopify.com/en/api/reference/online-store/page#index-2019-07
     * @param int $storeId 商城id
     * @return array 主题列表
     */
    public static function getList($storeId = 1, $parameters = []) {
        
        $storeId = static::castToString($storeId);

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'pages.json';

        $limit = data_get($parameters, 'limit', 250);
        $limit = $limit > 250 ? 250 : $limit;
        $sinceId = data_get($parameters, 'sinceId', ''); //Show orders after the specified ID.
        $title = data_get($parameters, 'title', '');
        $handle = data_get($parameters, 'handle', '');
        $createdAtMin = data_get($parameters, 'created_at_min', ''); //Show pages created after date (format: 2008-12-31).
        $createdAtMax = data_get($parameters, 'created_at_max', ''); //Show pages created before date (format: 2008-12-31).
        $updatedAtMin = data_get($parameters, 'updated_at_min', ''); //Show pages last updated after date (format: 2008-12-31).
        $updatedAtMax = data_get($parameters, 'updated_at_max', ''); //Show pages last updated before date (format: 2008-12-31).
        $publishedAtMin = data_get($parameters, 'published_at_min', ''); //Show pages published after date (format: 2014-04-25T16:15:47-04:00).
        $publishedAtMax = data_get($parameters, 'published_at_max', ''); //Show pages published before date (format: 2014-04-25T16:15:47-04:00).
        $fields = data_get($parameters, 'fields', []); //Retrieve only certain fields, specified by a comma-separated list of fields names.
        $fields = $fields ? implode(',', $fields) : '';

        /**
         * Restrict results to pages with a given published status:
          (default: any)
          published: Show only published pages.
          unpublished: Show only unpublished pages.
          any: Show published and unpublished pages.
         */
        $publishedStatus = data_get($parameters, 'published_status', '');

        $dateFormat = 'Y-m-d';
        $requestData = array_filter([
            'limit' => $limit,
            'since_id' => $sinceId ? $sinceId : '', //925376970775
            'title ' => $title, //207119551,1073339460
            'handle' => $handle,
            'created_at_min' => FunctionHelper::handleTime($createdAtMin, '', $dateFormat),
            'created_at_max' => FunctionHelper::handleTime($createdAtMax, '', $dateFormat),
            'updated_at_min ' => FunctionHelper::handleTime($updatedAtMin, '', $dateFormat),
            'updated_at_max' => FunctionHelper::handleTime($updatedAtMax, '', $dateFormat),
            'published_at_min' => FunctionHelper::handleTime($publishedAtMin, '', DateTime::ATOM), //2019-02-25T16:15:47-04:00
            'published_at_max' => FunctionHelper::handleTime($publishedAtMax, '', DateTime::ATOM),
            'fields' => $fields,
            'published_status' => $publishedStatus,
        ]);
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'GET';
        $headers = [];
        $dataKey = 'pages';
        $curlExtData = [
            'dataKey' => $dataKey,
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        $data = data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey, data_get($res, Constant::RESPONSE_TEXT, []));
        if ($data !== false) {
            $count = count($data);
            if ($count >= 250) {
                $_updatedAtMax = data_get($data, (($count - 1) . '.updated_at'), '');

                if ($updatedAtMax) {
                    data_set($parameters, 'updated_at_max', $_updatedAtMax);
                }

                sleep(1);
                $_data = static::getList($storeId, $parameters);

                return $data = Arr::collapse([$data, $_data]);
            }
        }

        return $data;
    }

    /**
     * Retrieves a single page by its ID. https://help.shopify.com/en/api/reference/online-store/page#show-2019-07
     * @param int $storeId 商城id
     * @param int $pageId  page id
     * @return array Page数据
     */
    public static function getPage($storeId = 1, $pageId = '') {

        $storeId = static::castToString($storeId);
        
        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'pages/' . $pageId . '.json';

        $requestData = [];
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'GET';
        $headers = [];
        $dataKey = 'page';
        $curlExtData = [
            'dataKey' => $dataKey,
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        return data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey, data_get($res, Constant::RESPONSE_TEXT));
    }

    /**
     * Metafields  https://shopify.dev/docs/admin-api/rest/reference/metafield?api[version]=2020-04
     * @param int $storeId 商城id
     * @param int $id  id
     * @return array Page数据
     */
    public static function getMetafield($storeId = 1, $id = '') {
        $storeId = static::castToString($storeId);
        return Metafield::getMetafield($storeId, $id, 'page');
    }

    /**
     * Retrieves a page count. https://help.shopify.com/en/api/reference/online-store/page#count-2019-07
     * @param int $storeId 商城id
     * @return array Page数据
     */
    public static function count($storeId = 1, $title = '', $createdAtMin = '', $createdAtMax = '', $updatedAtMin = '', $updatedAtMax = '', $publishedAtMin = '', $publishedAtMax = '', $publishedStatus = '') {

        $storeId = static::castToString($storeId);
        
        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'pages/count.json';

        $requestData = array_filter([
            'title ' => $title, //207119551,1073339460
            'created_at_min' => $createdAtMin ? Carbon::parse($createdAtMin)->toIso8601String() : '', //2019-02-25T16:15:47-04:00
            'created_at_max' => $createdAtMax ? Carbon::parse($createdAtMax)->toIso8601String() : '',
            'updated_at_min ' => $updatedAtMin ? Carbon::parse($updatedAtMin)->toIso8601String() : '', //2019-02-25T16:15:47-04:00
            'updated_at_max' => $updatedAtMax ? Carbon::parse($updatedAtMax)->toIso8601String() : '',
            'published_at_min' => $publishedAtMin ? Carbon::parse($publishedAtMin)->toIso8601String() : '', //2019-02-25T16:15:47-04:00
            'published_at_max' => $publishedAtMax ? Carbon::parse($publishedAtMax)->toIso8601String() : '',
            'published_status' => $publishedStatus,
        ]);
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'GET';
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod);

        $dataKey = 'count';

        return data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey, data_get($res, Constant::RESPONSE_TEXT));
    }

    /**
     * 获取平台数据
     * @param int $storeId 品牌商店id
     * @param array $parameters 请求参数
     * @return string
     */
    public static function getPlatformData($storeId = 1, $parameters = []) {
        
        $storeId = static::castToString($storeId);

        $id = $title = data_get($parameters, 'id', 0);
        $title = data_get($parameters, 'title', '');
        $bodyHtml = data_get($parameters, 'body_html', '');
        $templateSuffix = data_get($parameters, 'template_suffix', '');
        $handle = data_get($parameters, 'handle', '');
        $author = data_get($parameters, 'author', '');
        $published = data_get($parameters, 'published', true);
        $key = data_get($parameters, 'metafields.key', ''); //new
        $value = data_get($parameters, 'metafields.value', ''); //new value
        $valueType = data_get($parameters, 'metafields.value_type', ''); //string
        $namespace = data_get($parameters, 'metafields.namespace', ''); //global

        $metafield = array_filter([
            "key" => $key,
            "value" => $value,
            "value_type" => $valueType,
            "namespace" => $namespace,
        ]);

        $metafields = [];
        if ($metafield) {
            $metafields[] = $metafield;
        }

        $page = [
            "title" => $title,
            "body_html" => $bodyHtml,
            'template_suffix' => $templateSuffix,
            'handle' => $handle,
            'author' => $author,
            'published' => $published ? true : false,
        ];

        if ($id) {
            data_set($page, 'id', $id);
        }

        if ($metafields) {
            data_set($page, 'metafields', $metafields);
        }

        return json_encode(["page" => $page]);
    }

    /**
     * Create a page with HTML markup https://shopify.dev/docs/admin-api/rest/reference/online-store/page?api[version]=2020-04#create-2020-04
     * @param int $storeId 品牌商店id
     * @param array $parameters 请求参数
     * @return array 页面数据
     */
    public static function create($storeId = 1, $parameters = []) {
        
        $storeId = static::castToString($storeId);

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'pages.json';
        $requestData = static::getPlatformData($storeId, $parameters);
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'POST';
        $headers = [];
        $dataKey = 'page';
        $curlExtData = [
            'dataKey' => Constant::RESPONSE_TEXT,
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        return data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey, data_get($res, Constant::RESPONSE_TEXT));
    }

    /**
     * Updates a page https://shopify.dev/docs/admin-api/rest/reference/online-store/page?api[version]=2020-04#update-2020-04
     * @param int $storeId 品牌商店id
     * @param array $parameters 请求参数
     * @return array 页面数据
     */
    public static function update($storeId = 1, $parameters) {
        
        $storeId = static::castToString($storeId);

        //static::setConf($storeId);

        $pageId = data_get($parameters, 'id', 0);
        if (empty($pageId)) {
            return false;
        }

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'pages/' . $pageId . '.json';

        $requestData = static::getPlatformData($storeId, $parameters);
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'PUT';
        $headers = [];
        $dataKey = 'page';
        $curlExtData = [
            'dataKey' => Constant::RESPONSE_TEXT,
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        return data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey, data_get($res, Constant::RESPONSE_TEXT));
    }

    /**
     * Deletes a theme. https://shopify.dev/docs/admin-api/rest/reference/online-store/page?api[version]=2020-04#destroy-2020-04
     * @param int $storeId 品牌商店id
     * @param array $parameters 请求参数
     * @return array
     */
    public static function delete($storeId = 1, $parameters) {
        
        $storeId = static::castToString($storeId);

        //static::setConf($storeId);

        $pageId = data_get($parameters, 'id', 0);
        if (empty($pageId)) {
            return false;
        }

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'pages/' . $pageId . '.json';

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
