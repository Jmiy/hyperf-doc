<?php

namespace App\Services;

use App\Constants\Constant;

class ActivityTaskService extends BaseService {

    /**
     * 分享
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param string $account 账号
     * @param string $socialMedia 社媒标识
     * @param string $url 分享的url
     * @param array $requestData 请求参数
     * @return array
     */
    public static function share($storeId, $actId, $customerId, $account, $socialMedia, $url, $requestData) {
        $isApplied = static::isApplied($storeId, $actId, $customerId);
        if (!$isApplied) {
            //不符合条件，不能分享
            return [
                Constant::RESPONSE_CODE_KEY => 0,
                Constant::RESPONSE_MSG_KEY => "Not eligible, can not be shared",
                Constant::RESPONSE_DATA_KEY => [],
            ];
        }

        //获取任务数据
        $addShareLog = false;
        $where = [
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId
        ];
        $taskInfo = static::getModel($storeId)->buildWhere($where)->first();
        if (empty($taskInfo)) {
            $addShareLog = true;
        } else {
            $clickShare = data_get($taskInfo, Constant::CLICK_SHARE, 0);
            $clickShare === 0 && $addShareLog = true;
        }

        //更新为点击过
        $data = [
            Constant::CLICK_SHARE => 1,
            Constant::SOCIAL_MEDIA => $socialMedia
        ];
        static::updateOrCreate($storeId, $where, $data);
        //新增分享Log
        if ($addShareLog) {
            $shareLog = [
                'url' => $url,
                Constant::DB_TABLE_ACT_ID => $actId,
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                Constant::DB_TABLE_ACCOUNT => $account,
                Constant::DB_TABLE_COUNTRY => data_get($requestData, Constant::DB_TABLE_COUNTRY, ''),
                Constant::DB_TABLE_IP => data_get($requestData, Constant::DB_TABLE_IP, ''),
                Constant::SOCIAL_MEDIA => $socialMedia
            ];
            ActivityShareService::getModel($storeId)->insert($shareLog);
        }

        return [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => '',
            Constant::RESPONSE_DATA_KEY => []
        ];
    }

    /**
     * profileUrl填写
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param string $account 账号
     * @param string $socialMedia 社媒标识
     * @param string $url 填写的url
     * @param array $requestData 请求参数
     * @return array
     */
    public static function profileUrl($storeId, $actId, $customerId, $account, $socialMedia, $url, $requestData) {
        if (!static::isVaildFaceBookUrl($url)) {
            //无效的url
            return [
                Constant::RESPONSE_CODE_KEY => 0,
                Constant::RESPONSE_MSG_KEY => "Invalid URL.",
                Constant::RESPONSE_DATA_KEY => [],
            ];
        }

        $isApplied = static::isApplied($storeId, $actId, $customerId);
        if (!$isApplied) {
            //不符合条件，不能填写
            return [
                Constant::RESPONSE_CODE_KEY => 0,
                Constant::RESPONSE_MSG_KEY => "The conditions are not met, and the URL cannot be filled in.",
                Constant::RESPONSE_DATA_KEY => [],
            ];
        }

        //更新profileUrl
        $where = [
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId
        ];
        $data = [
            Constant::SOCIAL_MEDIA => $socialMedia,
            Constant::SOCIAL_MEDIA_URL => $url
        ];
        static::updateOrCreate($storeId, $where, $data);

        $where = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId
        ];
        $data = [
            'profile_url' => $url
        ];
        CustomerInfoService::getModel()->buildWhere($where)->update($data);

        return [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => '',
            Constant::RESPONSE_DATA_KEY => []
        ];
    }

    /**
     * vip club click
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param string $account 账号
     * @param string $socialMedia 社媒标识
     * @param string $url url
     * @param array $requestData 请求参数
     * @return array
     */
    public static function vipClub($storeId, $actId, $customerId, $account, $socialMedia, $url, $requestData) {
        $isApplied = static::isApplied($storeId, $actId, $customerId);
        if (!$isApplied) {
            //不符合条件，不能点击加入vip club
            return [
                Constant::RESPONSE_CODE_KEY => 0,
                Constant::RESPONSE_MSG_KEY => "Do not meet the conditions. You cannot click to join VIP Club.",
                Constant::RESPONSE_DATA_KEY => [],
            ];
        }

        //更新click_vip_club
        $where = [
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId
        ];
        $data = [
            Constant::SOCIAL_MEDIA => $socialMedia,
            Constant::CLICK_VIP_CLUB => 1
        ];
        static::updateOrCreate($storeId, $where, $data);

        return [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => '',
            Constant::RESPONSE_DATA_KEY => []
        ];
    }

    /**
     * 用户是否符合条件分享
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param string $extType 关联类型
     * @param int $productType 产品id
     * @return bool
     */
    public static function isApplied($storeId, $actId, $customerId, $extType = 'ActivityProduct', $productType = 3) {

        if (empty($storeId) || empty($actId) || empty($customerId)) {
            return false;
        }

        $where = [
            Constant::DB_TABLE_EXT_TYPE => $extType,
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::PRODUCT_TYPE => $productType,
        ];

        return ActivityApplyService::existsOrFirst($storeId, '', $where) ? true : false;
    }

    /**
     * 获取用户注册IP或当前请求IP
     * @param int $storeId 官网id
     * @param int $customerId 会员id
     * @param array $requestData 请求参数
     * @return mixed
     */
    public static function getRegIp($storeId, $customerId, $requestData) {
        //获取用户注册IP
        $customerInfo = CustomerInfoService::exists($storeId, $customerId, '', true);
        $ip = data_get($customerInfo, Constant::DB_TABLE_IP, '');

        //如果ip为空，就获取当前请求的ip
        if (empty($ip)) {
            $ip = data_get($requestData, Constant::DB_TABLE_IP, '');
        }

        //不是线上环境
        $env = config('app.env', 'production');
        if ($env !== 'production') {
            $devIp = data_get($requestData, 'dev_ip', '');
            if (!empty($devIp)) {
                $ip = $devIp;
            }
        }

        return $ip;
    }

    /**
     * 是否是fburl
     * @param string $url url
     * @return bool
     */
    public static function isVaildFaceBookUrl($url) {
        $parse = parse_url($url);
        $scheme = data_get($parse, 'scheme', '');
        $host = data_get($parse, 'host', '');
        $path = data_get($parse, 'path', '');
        $query = data_get($parse, 'query', '');

        if (empty($path)) {
            return false;
        }

        $fbProfileUrl = empty($scheme) ? $host . $path : $scheme . '://' . $host . $path;
        if (stripos($fbProfileUrl, 'https://www.facebook.com') !== false ||
                stripos($fbProfileUrl, 'http://www.facebook.com') !== false ||
                stripos($fbProfileUrl, 'www.facebook.com') !== false ||
                stripos($fbProfileUrl, 'facebook.com') !== false) {
            //if (preg_match('/.{0,}(id=)[0-9]+.{0,}/', $query)) {
            //    return true;
            //}
            return true;
        }

        return false;
    }

    /**
     * 获取任务数据
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @return array
     */
    public static function taskInfo($storeId, $actId, $customerId) {
        $where = [
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId
        ];
        $taskInfo = static::getModel($storeId)->buildWhere($where)->first();
        if (empty($taskInfo)) {
            return [];
        }
        return $taskInfo;
    }

    /**
     * 判断任务是否已经完成
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @return bool
     */
    public static function taskIsFinish($storeId, $actId, $customerId) {
        if (static::isApplied($storeId, $actId, $customerId)) {

//            $taskInfo = static::taskInfo($storeId, $actId, $customerId);
//            $clickShare = data_get($taskInfo, Constant::CLICK_SHARE, 0);
//            $profileUrl = data_get($taskInfo, Constant::SOCIAL_MEDIA_URL, '');
//            $clickVipClub = data_get($taskInfo, Constant::CLICK_VIP_CLUB, 0);
//
//            if ($clickShare == 1 || !empty($profileUrl) || $clickVipClub == 1) {
//                return true;
//            }
            return true;
        }
        return false;
    }

    /**
     * 获取用户完成的任务状态及任务数据
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param string $extType 关联类型
     * @param array $requestData 请求参数
     * @return array
     */
    public static function taskStatusAndInfos($storeId, $actId, $customerId, $extType, $requestData) {
        $result = [];

        //申请的配件
        $applyResult = static::applyStatus($storeId, $actId, $customerId, $requestData);
        data_set($result, 'apply', $applyResult);

        $applyInfo = ActivityApplyInfoService::getModel($storeId)->buildWhere([
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId
        ])->orderBy(Constant::DB_TABLE_PRIMARY, Constant::DB_EXECUTION_PLAN_ORDER_DESC)->limit(1)->get();
        data_set($result, 'apply.order_no', data_get($applyInfo, '0.orderno', ''));
        data_set($result, 'apply.description', data_get($applyInfo, '0.remarks', ''));

        //地址
        $address = ActivityAddressService::getModel($storeId)->buildWhere([
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId
        ])->orderBy(Constant::DB_TABLE_PRIMARY, Constant::DB_EXECUTION_PLAN_ORDER_DESC)->limit(1)->get();
        data_set($result, 'address', $address);

        return $result;
    }

    /**
     * 申请状态及申请资料
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param array $requestData 请求参数
     * @param string $extType 关联类型
     * @param int $type 类型
     * @return array
     */
    public static function applyStatus($storeId, $actId, $customerId, $requestData, $extType = 'ActivityProduct', $type = 3) {
        $result = [
            Constant::DB_TABLE_STATUS => 0,
            Constant::DB_TABLE_APPLY_ID => 0,
            'apply_product' => []
        ];

        //用户申请的配件
        $where = [
            Constant::DB_TABLE_EXT_TYPE => $extType,
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::PRODUCT_TYPE => $type,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
        ];
        $applyData = ActivityApplyService::getModel($storeId)->buildWhere($where)->orderBy(Constant::DB_TABLE_PRIMARY, Constant::DB_EXECUTION_PLAN_ORDER_DESC)->get();
        $applyData = $applyData->toArray();
        if (!empty($applyData)) {
            $productInfo = static::productInfo($storeId, [$applyData[0]]);
            $applyDataTmp = array_column($applyData, NULL, 'ext_id');

            data_set($result, Constant::DB_TABLE_APPLY_ID, data_get($applyDataTmp, $productInfo['product_id'] . '.id', 0));
            data_set($result, 'apply_product', $productInfo);

            $where = [
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                Constant::DB_TABLE_ACT_ID => $actId,
                Constant::AUDIT_STATUS => 1
            ];
            $applyProducts = ActivityApplyService::getModel($storeId)->buildWhere($where)->select([Constant::DB_TABLE_EXT_ID])->get();
            data_set($result, 'productIds', array_values(array_column($applyProducts->toArray(), Constant::DB_TABLE_EXT_ID)));
        }

        //判断当前用户能否申请
        $_applyInfoData = ActivityApplyInfoService::getApplyInfo($storeId, $actId, $customerId);
        data_set($result, Constant::DB_TABLE_STATUS, $_applyInfoData->count());

        if ($_applyInfoData->count() < 2) {
            //判断IP下是否存在其他用户申请的配件
            $ip = static::getRegIp($storeId, $customerId, $requestData);
            if (!empty($ip)) {
                $where = [
                    Constant::DB_TABLE_EXT_TYPE => ActivityProductService::getModelAlias(),
                    Constant::DB_TABLE_ACT_ID => $actId,
                    Constant::PRODUCT_TYPE => 3,
                    'ip' => $ip
                ];
                $_applyData = ActivityApplyService::getModel($storeId)->select([Constant::DB_TABLE_CUSTOMER_PRIMARY])->buildWhere($where)->get();
                if (!$_applyData->isEmpty()) {
                    $customerIds = array_column($_applyData->toArray(), Constant::DB_TABLE_CUSTOMER_PRIMARY);
                    $_applyInfoData = ActivityApplyInfoService::getApplyInfo($storeId, $actId, $customerIds);
                    if ($_applyInfoData->count() >= 2) {
                        data_set($result, Constant::DB_TABLE_STATUS, $_applyInfoData->count());
                    }
                }
            }
       }

        return $result;
    }

    /**
     * 产品数据
     * @param int $storeId 官网id
     * @param int $applyData 申请数据
     * @return array
     */
    public static function productInfo($storeId, $applyData) {
        $where = [
            Constant::DB_TABLE_PRIMARY => array_column($applyData, 'ext_id')
        ];
        $select = ['name', 'in_stock', Constant::CATEGORY_ID, Constant::DB_TABLE_PRIMARY];
        $products = ActivityProductService::getModel($storeId)->buildWhere($where)->select($select)->get();
        if (empty($products)) {
            return [];
        }

        $products = $products->toArray();
        $categoryId = data_get($products, '0.category_id', 0);
        $productName = data_get($products, '0.name', '');
        $productId = data_get($products, '0.id', 0);
        if (count($products) > 1) {
            foreach ($products as $product) {
                if (data_get($product, 'in_stock', 0) == 1) {
                    $categoryId = data_get($product, Constant::CATEGORY_ID, 0);
                    $productName = data_get($product, 'name', '');
                    $productId = data_get($product, Constant::DB_TABLE_PRIMARY, 0);
                    break;
                }
            }
        }

        $where = [
            'id' => $categoryId
        ];
        $categoryItem = ActivityCategoryService::getModel($storeId)->buildWhere($where)->first();
        $imgUrl = data_get($categoryItem, 'img_url', '');
        $mobImgUrl = data_get($categoryItem, 'mb_img_url', '');
        $categoryName = data_get($categoryItem, 'name', '');

        return [
            'product_name' => $productName,
            'category_name' => $categoryName,
            'product_id' => $productId,
            Constant::CATEGORY_ID => $categoryId,
            'category_img_url' => $imgUrl,
            'category_mb_img_url' => $mobImgUrl
        ];
    }

    /**
     * 口令
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param string $key copy_key
     * @param string $word 回复的口令
     * @return array
     */
    public static function input($storeId, $actId, $customerId, $key, $word) {
        $result = [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => Constant::PARAMETER_STRING_DEFAULT,
            Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
        ];

        //口令不对
        if ($key != '#freeparts#' || $word != '#Getfree#') {
            return [
                Constant::RESPONSE_CODE_KEY => 200000,
                Constant::RESPONSE_MSG_KEY => Constant::PARAMETER_STRING_DEFAULT,
                Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
            ];
        }

        $isApplied = static::isApplied($storeId, $actId, $customerId);
        if (!$isApplied) {
            //不符合条件，不能填写
            return [
                Constant::RESPONSE_CODE_KEY => 0,
                Constant::RESPONSE_MSG_KEY => "The conditions are not met, and the URL cannot be filled in.",
                Constant::RESPONSE_DATA_KEY => [],
            ];
        }

        $where = [
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId
        ];
        $data = [
            Constant::SOCIAL_MEDIA => 'facebook',
            Constant::SOCIAL_MEDIA_URL => $key
        ];
        static::updateOrCreate($storeId, $where, $data);

        $where = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_KEY => $key,
            'word' => $word,
        ];
        KeyWordLogService::updateOrCreate($storeId, $where, []);

        return $result;
    }
}
