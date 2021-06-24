<?php

/**
 * 邀请码服务
 * User: Jmiy
 * Date: 2019-11-13
 * Time: 16:50
 */

namespace App\Services;

use App\Utils\FunctionHelper;
use App\Utils\Support\Facades\Cache;
use App\Services\Traits\GetDefaultConnectionModel;
use App\Constants\Constant;

class InviteCodeService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 检查是否存在
     * @param int $customerId 会员id
     * @param string $inviteCode 邀请码
     * @param boolean $getData 是否获取数据 true:是 false:否 默认:false
     * @param mixed $select
     * @param array $extData 扩展参数
     * @return boolean|array
     */
    public static function exists($customerId = 0, $inviteCode = '', $getData = false, $select = null, $extData = []) {
        $where = [];

        if ($customerId) {
            $where['customer_id'] = $customerId;
        }

        if ($inviteCode) {
            $where[Constant::DB_TABLE_INVITE_CODE] = $inviteCode;
        }

        $inviteCodeType = data_get($extData, Constant::DB_TABLE_INVITE_CODE_TYPE, Constant::PARAMETER_INT_DEFAULT);
        if ($inviteCodeType) {
            $where[Constant::DB_TABLE_INVITE_CODE_TYPE] = $inviteCodeType;
        }

        return static::existsOrFirst(0, '', $where, $getData, $select);
    }

    /**
     * 通过邀请码获取邀请者数据
     * @param int $inviteCode 邀请码
     * @return array|obj 邀请者数据
     */
    public static function getCustomerData($inviteCode) {

        if (empty($inviteCode)) {
            return [];
        }

        $tags = config('cache.tags.inviteCode', ['{inviteCode}']);
        $ttl = 86400; //缓存24小时 单位秒
        $key = $inviteCode;
        return Cache::tags($tags)->remember($key, $ttl, function () use($inviteCode) {
                    return static::getModel(0, '')->buildWhere([Constant::DB_TABLE_INVITE_CODE => $inviteCode])->with('customer')->first();
                });
    }

    /**
     * 获取邀会员邀请码数据
     * @param int $customerId 会员id
     * @param array $extData 扩展参数
     * @return 会员邀请码数据
     */
    public static function getInviteCodeData($customerId, $extData = []) {

        if (empty($customerId)) {
            return [];
        }

        $tags = config('cache.tags.inviteCode', ['{inviteCode}']);
        $ttl = 86400; //缓存24小时 单位秒
        $key = $customerId;

        $inviteCodeLength = 8;
        $inviteCodeType = data_get($extData, Constant::DB_TABLE_INVITE_CODE_TYPE, 1);
        $storeId = data_get($extData, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);
        if ($inviteCodeType && $inviteCodeType == 2 && in_array($storeId, [3])) { //holife邀请注册活动
            $inviteCodeLength = 10;
            $key = $customerId . Constant::LINKER . $inviteCodeType;
        } else {
            $inviteCodeType = 1;
        }

        return Cache::tags($tags)->remember($key, $ttl, function () use($customerId, $inviteCodeType, $inviteCodeLength) {

                    $isExists = static::exists($customerId, '', true, null, [Constant::DB_TABLE_INVITE_CODE_TYPE => $inviteCodeType]);
                    if ($isExists) {
                        return $isExists;
                    }

                    //分配邀请码
                    $inviteCodeData = [
                        Constant::DB_TABLE_INVITE_CODE => FunctionHelper::randomStr($inviteCodeLength),
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                        Constant::DB_TABLE_INVITE_CODE_TYPE => $inviteCodeType,
                    ];
                    $inviteCodeData[Constant::DB_TABLE_PRIMARY] = static::getModel(0, '')->insertGetId($inviteCodeData);

                    return $inviteCodeData;
                });
    }

}
