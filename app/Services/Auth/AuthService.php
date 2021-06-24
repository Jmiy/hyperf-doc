<?php

/**
 * 认证服务
 * User: Jmiy
 * Date: 2020-09-07
 * Time: 17:50
 */

namespace App\Services\Auth;

use App\Services\BaseService;
use App\Services\Traits\GetDefaultConnectionModel;
use App\Constants\Constant;
use App\Utils\Response;
use App\Services\ActionLogService;

class AuthService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'Customer';
    }

    /**
     * 修改密码
     * @param Request $request
     * @return boolean
     */
    public static function updatePassword($requestData) {
        $password = data_get($requestData, Constant::DB_TABLE_PASSWORD, null);
        $storeId = data_get($requestData, Constant::DB_TABLE_STORE_ID);
        $account = data_get($requestData, Constant::DB_TABLE_ACCOUNT);
        if (null !== $password && null !== $account && null !== $storeId) {
            $updateWhere = [
                Constant::DB_TABLE_STORE_ID => $storeId,
                Constant::DB_TABLE_ACCOUNT => $account,
            ];
            static::update($storeId, $updateWhere, [Constant::DB_TABLE_PASSWORD => encrypt($password)]);
        }

        return true;
    }

    /**
     * 登录
     * @param array $requestData 请求参数
     * @return array
     */
    public static function login($requestData) {

        //更新密码
        static::updatePassword($requestData);

        $storeId = data_get($requestData, Constant::DB_TABLE_STORE_ID);
        $account = data_get($requestData, Constant::DB_TABLE_ACCOUNT);

        if (null === $account || null === $storeId) {
            return Response::getDefaultResponseData(9999999999);
        }

        $where = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_ACCOUNT => $account,
        ];
        $select = [Constant::DB_TABLE_CUSTOMER_PRIMARY];
        $customerData = static::existsOrFirst($storeId, '', $where, true, $select);
        $customerId = data_get($customerData, Constant::DB_TABLE_CUSTOMER_PRIMARY, 0); //会员id
        unset($customerData);

        $actId = data_get($requestData, Constant::DB_TABLE_ACT_ID, 0);

        return ActionLogService::login($storeId, $customerId, $actId, 6, $requestData);
    }

}
