<?php

/**
 * Created by Patazon.
 * @desc   :
 * @author : Roy_qiu
 * @email  : Roy_qiu@patazon.net
 * @date   : 2020/7/28 11:53
 */

namespace App\Services;

use App\Services\Traits\GetDefaultConnectionModel;
use App\Constants\Constant;
use App\Utils\FunctionHelper;

class ReportLogService extends BaseService {

    use GetDefaultConnectionModel;

    public static function handle($requestData) {
        $service = static::getNamespaceClass();
        $method = 'report';
        $parameters = [$requestData];

        return FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters));
    }

    /**
     * 记录上报数据
     * @param array $requestData
     * @return boolean true:成功 false：失败
     */
    public static function report($requestData) {

        $storeId = data_get($requestData, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);
        $actId = data_get($requestData, Constant::DB_TABLE_ACT_ID, Constant::PARAMETER_INT_DEFAULT);
        $customerId = data_get($requestData, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
        $actionType = data_get($requestData, Constant::ACTION_TYPE, Constant::PARAMETER_INT_DEFAULT);
        $subType = data_get($requestData, Constant::SUB_TYPE, Constant::PARAMETER_INT_DEFAULT);
        $uri = data_get($requestData, Constant::CLIENT_ACCESS_API_URI, Constant::PARAMETER_STRING_DEFAULT);
        $ip = data_get($requestData, Constant::DB_TABLE_IP, Constant::PARAMETER_STRING_DEFAULT);
        $country = data_get($requestData, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT);

        $account = data_get($requestData, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT);
        if (empty($customerId) && !empty($account)) {
            $customer = CustomerService::existsOrFirst($storeId, '', [Constant::DB_TABLE_STORE_ID => $storeId, Constant::DB_TABLE_ACCOUNT => $account], true, [Constant::DB_TABLE_CUSTOMER_PRIMARY]);
            $customerId = data_get($customer, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
        }

        $reportLog = \Hyperf\Utils\Arr::collapse([[
                Constant::DB_TABLE_STORE_ID => $storeId,
                Constant::DB_TABLE_ACT_ID => $actId,
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                Constant::ACTION_TYPE => $actionType,
                Constant::SUB_TYPE => $subType,
                Constant::DB_TABLE_EXT_ID => data_get($requestData, Constant::DB_TABLE_EXT_ID, Constant::PARAMETER_INT_DEFAULT),
                Constant::DB_TABLE_EXT_TYPE => data_get($requestData, Constant::DB_TABLE_EXT_TYPE, Constant::PARAMETER_STRING_DEFAULT),
                Constant::DB_TABLE_IP => $ip,
                Constant::DB_TABLE_COUNTRY => $country,
                'api_url' => $uri,
                'from_url' => data_get($requestData, Constant::CLIENT_ACCESS_URL, Constant::PARAMETER_STRING_DEFAULT),
                    //'request_data' => json_encode($requestData),
                    ], data_get($requestData, Constant::CLIENT_DATA, Constant::PARAMETER_ARRAY_DEFAULT)]);

        return static::getModel($storeId)->insert($reportLog);
    }

}
