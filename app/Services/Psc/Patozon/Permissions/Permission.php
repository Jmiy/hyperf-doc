<?php

/**
 * Created by Patazon.
 * @desc   :
 * @author : Roy_qiu
 * @email  : Roy_qiu@patazon.net
 * @date   : 2020/11/12 10:40
 */

namespace App\Services\Psc\Patozon\Permissions;

use App\Services\Psc\Patozon\BaseService;
use App\Services\StoreService;
use App\Constants\Constant;
use Hyperf\Utils\Arr;

class Permission extends BaseService
{

    /**
     * token认证
     * @param $authToken
     * @return array|mixed
     */
    public static function getPermissionByRole($authToken)
    {

        $url = static::getApiUrl('/api/permission/getPermissionByRole');

        $requestData = json_encode(
            [
                "jsonrpc" => "2.0",
                "method" => "",
                "id" => 1,
                "params" => [
                    "systemCode" => "ptxBrand"
                ]
            ]
        );
        $requestMethod = 'POST';
        $responseData = static::request($url, $requestData, '', $authToken, $requestMethod);

        if (data_get($responseData, Constant::RESPONSE_TEXT . '.error.code', NULL)) {
            return [];
        }

        $data = data_get($responseData, Constant::RESPONSE_TEXT . '.result', NULL);

        $storePermissionsData = [
            'store' => [],
            'permissions' => [],
        ];
        if ($data) {
            $storeData = StoreService::getModel()->pluck('name', 'id')->sortByDesc(function ($value, $key) {
                return $value;
            });

            foreach ($storeData as $storeId => $name) {
                $name = strtolower($name);
                foreach ($data as $p_key => $item) {
                    $roleName = strtolower(data_get($item, 'roleName', ''));

                    if(empty($roleName)){
                        unset($data[$p_key]);
                        continue;
                    }

                    if (false !== strpos($roleName, $name)) {

                        if (!isset($storePermissionsData['store'][$storeId])) {
                            $storePermissionsData['store'][$storeId] = $name;
                        }

                        $permissions = data_get($item, 'permissions', []);//权限数据
                        foreach ($permissions as $key => $permissionItem) {//遍历权限，把同一品牌的权限汇总在一起

                            foreach ($permissionItem as $_key => $value) {//合并各个类型权限，并去重
                                if (!isset($storePermissionsData['permissions'][$storeId][$key][$_key])) {
                                    $storePermissionsData['permissions'][$storeId][$key][$_key] = [];
                                }

                                $storePermissionsData['permissions'][$storeId][$key][$_key] = Arr::collapse(
                                    [
                                        $storePermissionsData['permissions'][$storeId][$key][$_key],
                                        $value
                                    ]
                                );

                                $storePermissionsData['permissions'][$storeId][$key][$_key] = array_values(array_filter(array_unique($storePermissionsData['permissions'][$storeId][$key][$_key])));
                            }
                        }

                        unset($data[$p_key]);
                    }
                }
            }
        }

        $storePermissionsData['store'] = collect($storePermissionsData['store'])->sortBy(function ($value, $key) {
            return $key;
        })->all();

        $storePermissionsData['permissions'] = collect($storePermissionsData['permissions'])->sortBy(function ($value, $key) {
            return $key;
        })->all();

        return $storePermissionsData;
    }
}
