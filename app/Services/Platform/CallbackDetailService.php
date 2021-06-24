<?php

namespace App\Services\Platform;

use App\Services\BaseService;
use App\Constants\Constant;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;

class CallbackDetailService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'PlatformCallbackDetail';
    }

    /**
     * 第三方回调数据写入
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param string $businessId 业务id
     * @param string $businessType 业务主类型
     * @param string $businessSubtype 业务子类型
     * @param string $businessExtId 业务关联主类型
     * @param string $businessExtType 业务关联id
     * @param array $callbackData 回调数据
     * @return mixed
     */
    public static function handle($storeId, $platform, $businessId, $businessType, $businessSubtype, $businessExtId, $businessExtType, $callbackData) {

        $where = [
            Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
            'business_type' => $businessType, //业务类型(order|refund|fulfillment|等)
            'business_subtype' => $businessSubtype, //业务子类型(create|update|delete|cancel|paid|等)
            'business_id' => $businessId, //业务id(order_id|refund_id|fulfillment_id|等)
            Constant::DB_TABLE_STORE_ID => $storeId,
        ];

        $data = [
            'business_ext_type' => $businessExtType, //业务关联类型(如:refund关联order等)
            'business_ext_id' => $businessExtId, //业务关联id(如:refund_id关联order_id等)
            'details' => json_encode($callbackData), //内容|json
        ];

        return static::updateOrCreate($storeId, $where, $data, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($data)));
    }

}
