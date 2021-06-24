<?php

namespace App\Services\Store\Shopify\Metafield;

use App\Services\Store\Shopify\BaseService;
use Carbon\Carbon;
use App\Utils\FunctionHelper;
use DateTime;
use App\Constants\Constant;
use Hyperf\Utils\Arr;

class Metafield extends BaseService {

    /**
     * Retrieves a list of metafields that belong to a resource. https://shopify.dev/docs/admin-api/rest/reference/metafield?api[version]=2020-04#index-2020-04
     * @param int $storeId 品牌商店id
     * @param array $parameters  请求参数
     * @return array metafields列表
     */
    public static function getList($storeId = 1, $parameters = []) {
        
        $storeId = static::castToString($storeId);

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'metafields.json';

        $limit = data_get($parameters, 'limit', 250);
        $limit = $limit > 250 ? 250 : $limit;
        $sinceId = data_get($parameters, 'sinceId', ''); //Show orders after the specified ID.

        $createdAtMin = data_get($parameters, 'created_at_min', ''); //Show pages created after date (format: 2014-04-25T16:15:47-04:00).
        $createdAtMax = data_get($parameters, 'created_at_max', ''); //Show pages created before date (format: 2014-04-25T16:15:47-04:00)
        $updatedAtMin = data_get($parameters, 'updated_at_min', ''); //Show pages last updated after date (format: 2014-04-25T16:15:47-04:00)
        $updatedAtMax = data_get($parameters, 'updated_at_max', ''); //Show pages last updated before date (format: 2014-04-25T16:15:47-04:00)

        $namespace = data_get($parameters, 'namespace', '');
        $key = data_get($parameters, 'key', '');
        $valueType = data_get($parameters, 'value_type', '');

        $fields = data_get($parameters, 'fields', []); //Retrieve only certain fields, specified by a comma-separated list of fields names.
        $fields = $fields ? implode(',', $fields) : '';

        $dateFormat = 'Y-m-d';
        $requestData = array_filter([
            'limit' => $limit,
            'since_id' => $sinceId ? $sinceId : '', //925376970775
            'created_at_min' => FunctionHelper::handleTime($createdAtMin, '', DateTime::ATOM), //2019-02-25T16:15:47-04:00
            'created_at_max' => FunctionHelper::handleTime($createdAtMax, '', DateTime::ATOM),
            'updated_at_min ' => FunctionHelper::handleTime($updatedAtMin, '', DateTime::ATOM),
            'updated_at_max' => FunctionHelper::handleTime($updatedAtMax, '', DateTime::ATOM),
            'namespace' => $namespace,
            'key' => $key,
            'value_type' => $valueType,
            'fields' => $fields,
        ]);
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'GET';
        $headers = [];
        $dataKey = 'metafields';
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
     * Retrieves a list of metafields that belong to a $ownerResource. https://shopify.dev/docs/admin-api/rest/reference/metafield?api[version]=2020-04#index-2020-04
     * @param int $storeId 品牌商店id
     * @param int $ownerId 平台资源id
     * @param string $ownerResource 资源
     * @return array metafields 
     * array:1 [▼
      0 => array:11 [▼
      "id" => 12117918974004
      "namespace" => "global"
      "key" => "page_key"
      "value" => "page_value"
      "value_type" => "string"
      "description" => null
      "owner_id" => 50703007796
      "created_at" => "2020-07-23T15:31:36+08:00"
      "updated_at" => "2020-07-23T15:31:36+08:00"
      "owner_resource" => "page"
      "admin_graphql_api_id" => "gid://shopify/Metafield/12117918974004"
      ]
      ]
     */
    public static function getMetafield($storeId = 1, $ownerId = '', $ownerResource = '') {
        
        $storeId = static::castToString($storeId);

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'metafields.json';
        $requestData = [
            'metafield' => [
                'owner_id' => $ownerId,
                'owner_resource' => $ownerResource,
            ],
        ];

        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'GET';
        $headers = [];
        $dataKey = 'metafields';
        $curlExtData = [
            'dataKey' => $dataKey,
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        return data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey, data_get($res, Constant::RESPONSE_TEXT));
    }

    /**
     * 创建属性
     * @param int $storeId 品牌商店id
     * @param int $ownerId id
     * @param string $ownerResource 资源
     * @param string $key  属性key
     * @param string|int $value 属性值
     * @param string $valueType 属性类型 The metafield's information type. Valid values: string, integer, json_string.
     * @param string $description 属性描述
     * @param string $namespace A container for a set of metafields. You need to define a custom namespace for your metafields to distinguish them from the metafields used by other apps. Maximum length: 20 characters.
     * @return array metafield 
     * array:11 [▼
      "id" => 12162841280564
      "namespace" => "patazon"
      "key" => "test_key"
      "value" => 98
      "value_type" => "integer"
      "description" => null
      "owner_id" => 3047836876852
      "created_at" => "2020-08-31T02:12:23-07:00"
      "updated_at" => "2020-08-31T02:12:23-07:00"
      "owner_resource" => "customer"
      "admin_graphql_api_id" => "gid://shopify/Metafield/12162841280564"
      ]
     */
    public static function createMetafield($storeId = 1, $ownerId = '', $ownerResource = '', $key = '', $value = '', $valueType = 'integer', $description = '', $namespace = 'patazon') {

        $storeId = static::castToString($storeId);
        
        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'metafields.json';

        $requestData = json_encode([
            'metafield' => [
                'owner_id' => $ownerId,
                'owner_resource' => $ownerResource,
                "namespace" => $namespace,
                "key" => $key,
                "value" => $value,
                "value_type" => $valueType,
                'description' => $description,
            ],
        ]);
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'POST';
        $headers = [];
        $dataKey = 'metafield';
        $curlExtData = [
            'dataKey' => $dataKey,
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        return data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey, data_get($res, Constant::RESPONSE_TEXT));
    }

    /**
     * 创建属性
     * @param int $storeId 品牌商店id
     * @param int $ownerId id
     * @param string $ownerResource 资源
     * @param string $key  属性key
     * @param string|int $value 属性值
     * @param string $valueType 属性类型 The metafield's information type. Valid values: string, integer, json_string.
     * @param string $description 属性描述
     * @param string $namespace A container for a set of metafields. You need to define a custom namespace for your metafields to distinguish them from the metafields used by other apps. Maximum length: 20 characters.
     * @return array metafield 
     * array:11 [▼
      "id" => 12162841280564
      "namespace" => "patazon"
      "key" => "test_key"
      "value" => 98
      "value_type" => "integer"
      "description" => null
      "owner_id" => 3047836876852
      "created_at" => "2020-08-31T02:12:23-07:00"
      "updated_at" => "2020-08-31T02:12:23-07:00"
      "owner_resource" => "customer"
      "admin_graphql_api_id" => "gid://shopify/Metafield/12162841280564"
      ]
     */
    public static function updateMetafield($storeId = 1, $metafieldId = '', $ownerId = '', $ownerResource = '', $key = '', $value = '', $valueType = 'integer', $description = '', $namespace = 'patazon') {

        $storeId = static::castToString($storeId);
        
        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'metafields/' . $metafieldId . '.json';

        $update = [];
        if ($ownerId !== null) {
            data_set($update, 'owner_id', $ownerId);
        }

        if ($ownerResource !== null) {
            data_set($update, 'owner_resource', $ownerResource);
        }

        if ($namespace !== null) {
            data_set($update, 'namespace', $namespace);
        }

        if ($key !== null) {
            data_set($update, 'key', $key);
        }

        if ($value !== null) {
            data_set($update, 'value', $value);
        }

        if ($ownerId !== null) {
            data_set($update, 'owner_id', $ownerId);
        }

        if ($ownerId !== null) {
            data_set($update, 'owner_id', $ownerId);
        }

        if ($ownerId !== null) {
            data_set($update, 'owner_id', $ownerId);
        }

        $requestData = json_encode([
            'metafield' => [
                'owner_id' => $ownerId,
                'owner_resource' => $ownerResource,
                "namespace" => $namespace,
                "key" => $key,
                "value" => $value,
                "value_type" => $valueType,
                'description' => $description,
            ],
        ]);
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'POST';
        $headers = [];
        $dataKey = 'metafield';
        $curlExtData = [
            'dataKey' => $dataKey,
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        return data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey, data_get($res, Constant::RESPONSE_TEXT));
    }

}
