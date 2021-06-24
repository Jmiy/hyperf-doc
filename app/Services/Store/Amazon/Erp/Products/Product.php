<?php

namespace App\Services\Store\Amazon\Erp\Products;

use App\Services\Store\Amazon\Erp\BaseService;
use App\Constants\Constant;
use App\Utils\FunctionHelper;

class Product extends BaseService {

    /**
     * erp产品获取
     * @param int $storeId 商城id
     * @param array $parameters 请求参数
     * @return array
     */
    public static function getProduct($storeId = 1, $parameters = []) {

        $storeId = static::castToString($storeId);

        static::setConf($storeId);

        $requestData = [
            'jsonrpc' => 2.0,
            'method' => '',
            'id' => 1,
            'params' => [
                'month' => date('Y-m'),
                'orderBy' => 'currencyCode',
                'orderDirection' => 'desc',
                'pageSize' => 100
            ],
        ];

        $url = static::$storeUrl . '/rpc/commodity/list'; //https://erp.patozon.net/rpc/enum/getCountries  /rpc/commodity/list
        $username = static::$apiKey;
        $password = static::$password;
        $requestMethod = 'POST';
        $headers = [];
        $dataKey = 'result.tree';
        $curlExtData = [
            'dataKey' => $dataKey,
            'keyInfo' => implode('_', array_filter([static::$storeId, data_get($parameters, 'operator', '')])),
        ];
        $res = static::request($url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);
        dd($res);

        return data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey);
    }

    /**
     * 获取统一平台产品数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条产品数据
     * @return array
     */
    public static function getProductData($storeId, $platform, $data) {

        $storeId = static::castToString($storeId);

        if (empty($data)) {
            return [];
        }

        $categoryId = data_get($data, 'categoryId') ?? 0; //产品id

        if (empty($categoryId)) {
            return [];
        }

        //$createdAt = FunctionHelper::handleTime(data_get($data, 'FCreatedTime')); //创建时间

        $_data = [
            [
                Constant::DB_TABLE_UNIQUE_ID => FunctionHelper::getUniqueId($storeId, $platform, $categoryId, static::getCustomClassName()), //平台产品唯一id
                Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
                Constant::DB_TABLE_STORE_ID => $storeId,
                Constant::DB_TABLE_PLATFORM_CREATED_AT => null, //$createdAt, //创建时间
                Constant::DB_TABLE_PLATFORM_UPDATED_AT => null, //FunctionHelper::handleTime(data_get($data, 'FLastUpdateTime')), //更新时间
                Constant::CATEGORY_ID => $categoryId, //类目id
                Constant::RESPONSE_CODE_KEY => data_get($data, Constant::RESPONSE_CODE_KEY) ?? '', //类目编号
                Constant::DB_TABLE_NAME => data_get($data, Constant::DB_TABLE_NAME) ?? '', //类目名称
                'level' => data_get($data, 'level') ?? '', //类目层级  1为一级品类，2为二级品类，3为三级品类
                'parent_id' => data_get($data, 'parentId') ?? '', //上级品类，以对应品类的品类ID表示
                'department_id' => data_get($data, 'departmentId') ?? 0, //所属部门ID
                'creator' => data_get($data, 'creator') ?? '', //创建者，使用登录名表示
            ]
        ];

        $children = data_get($data, 'children'); //子类目
        if ($children) {
            foreach ($children as $childrenData) {
                $_childrenData = static::getCategoryData($storeId, $platform, $childrenData);
                $_data = \Hyperf\Utils\Arr::collapse([$_data, $_childrenData]);
            }
            return $_data;
        }

        return $_data;
    }

}
