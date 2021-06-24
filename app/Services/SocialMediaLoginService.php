<?php

namespace App\Services;

use App\Http\Controllers\Api\CustomerController;
use App\Services\Traits\GetDefaultConnectionModel;
use Hyperf\HttpServer\Contract\RequestInterface as Request;
use App\Services\Store\PlatformServiceManager;

class SocialMediaLoginService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'SocialMediaUser';
    }

    /**
     * 社媒注册
     * @param Request $request
     * @param array $params
     * @return array
     */
    public static function smLogin(Request $request, array $params) {
        //AuthServiceProvider逻辑中会生成不存在的用户
        //此处需要获取用户数据，判断密码值是否为空
        $customer = self::getUserInfo($params);

        //密码不存在时,生成默认密码,否则用customer的密码
        $defautPwRet = [];
        if (!$customer['is_exists'] || empty($customer['password'])) {
            $userPassword = $request->input('password', '');
            if (!empty($userPassword)) {
                $params['password'] = $userPassword;
            } else {
                $defautPwRet = self::defaultPassword($params['store_id'], $params['account'], $params['third_source']);
                $params['password'] = $defautPwRet['password'];
            }
        } else {
            $params['password'] = decrypt($customer['password']);
        }

        //判断用户是否存在shopify
        $shopifyUser = static::userIsExistsShopify($params);
        //用户存在shopify，但是会员系统密码为空
        $isModifyPassword = false;
        if ($shopifyUser['is_exists'] && empty($customer['password'])) {
            $isModifyPassword = true;
        }

        $request->offsetSet('password', $params['password']);

        $where = [
            'store_id' => $params['store_id'],
            'third_source' => $params['third_source'],
            'account' => $params['account']
        ];
        $userInfo = static::getModel($params['store_id'], $params['country'], [], 'SocialMediaUser')->buildWhere($where)->get()->toArray();

        //直接复用CustomerController
        //修改判断逻辑，以第三方数据表的数据为准，数据生成，账号在shopify那边已生成
        if (empty($userInfo)) {
            $customerController = new CustomerController($request);
            $result = $customerController->createCustomer($request);
            $resultCode = data_get($result, 'original.code');
            if ($resultCode != 1 && $resultCode != 10029) {
                return [
                    'code' => $resultCode,
                    'msg' => data_get($result, 'original.msg'),
                    'data' => []
                ];
            }
        }

        //更新密码及新增数据到第三方用户数据表
        $params['customer_id'] = $request->input('customer_id');
        //密码为空的时候，才能修改
        if (!$customer['is_exists'] || empty($customer['password'])) {
            self::modifyPassword($params['customer_id'], $params['password']);
        }
        $addSocialMediaUser = self::addSocialMediaUser($params);

        //用户表密码值不为空时，对密码做数据混淆
        if (!empty($customer['password'])) {
            $prefixStr = self::randStr(time(), 12);
            $suffixStr = self::randStr(time(), 12);
            $password = $prefixStr . $params['password'] . $suffixStr . 'md5sat';
            $md5Str = strtolower(md5($password));
            $password = $password . $md5Str;
        }

        return [
            'code' => 1,
            'msg' => '',
            'data' => [
                'account' => $params['account'],
                'password' => (empty($customer['password'])) ? base64_encode(strtolower(md5($params['password']))) : base64_encode($password),
                'rand_key' => (empty($customer['password'])) ? $defautPwRet['rand_str'] : '',
                'is_default' => (empty($customer['password'])) ? true : false,
                'is_modify_password' => $isModifyPassword,
                'customer_id' => $request->input('customer_id')
            ]
        ];
    }

    /**
     * 密码修改
     * @param int $customerId
     * @param string $password
     * @return array
     */
    public static function modifyPassword($customerId, $password) {
        $where = [
            'customer_id' => $customerId
        ];
        $updateData = [
            'password' => encrypt($password)
        ];

        $ret = CustomerService::update(0, $where, $updateData);
        if (!$ret) {
            return [
                'code' => 0,
                'msg' => 'password modify fail',
                'data' => []
            ];
        }
        return [
            'code' => 1,
            'msg' => '',
            'data' => [
                'is_success' => true
            ]
        ];
    }

    /**
     * 获取用户数据
     * @param array $params
     * @return array
     */
    public static function getUserInfo(array $params) {
        //判断用户是否在customer表
        $customerInfo = CustomerService::customerExists($params['store_id'], 0, $params['account'], 0, true);
        if (empty($customerInfo)) {
            return [
                'is_exists' => false
            ];
        }

        return [
            'is_exists' => true,
            'customer_id' => $customerInfo['customer_id'],
            'password' => $customerInfo['password']
        ];
    }

    /**
     * 判断用户是否存在shopify
     * @param array $params
     * @return array
     */
    public static function userIsExistsShopify(array $params) {
        $result = PlatformServiceManager::handle($params['platform'], 'Customer', 'customerQuery', [$params['store_id'], '', $params['account']]);
        if (empty($result)) {
            return [
                'is_exists' => false
            ];
        }

        return [
            'is_exists' => true,
            'info' => $result
        ];
    }

    /**
     * 新增or更新第三方用户数据
     * @param array $params
     * @return mixed
     */
    public static function addSocialMediaUser(array $params) {
        $where = [
            'store_id' => $params['store_id'],
            'account' => $params['account'],
            'third_source' => $params['third_source']
        ];

        $data = [
            'third_user_id' => $params['third_user_id'] ?? '',
            'first_name' => $params['first_name'] ?? '',
            'last_name' => $params['last_name'] ?? '',
            'ip' => $params['ip'] ?? '',
            'phone' => $params['phone'] ?? '',
            'gender' => intval($params['gender']),
            'birthday' => $params['birthday'] ?? '',
            'country' => $params['country'] ?? '',
            'user_info' => $params['user_info'] ?? '',
            'created_at' => $params['created_at'],
            'updated_at' => $params['updated_at'],
            'act_id' => intval($params['act_id'] ? $params['act_id'] : 0),
            'customer_id' => $params['customer_id'] ?? 0,
            'true_email' => $params['true_email'] ?? 0,
        ];

        $result = static::getModel($params['store_id'], $params['country'], [], 'SocialMediaUser')->updateOrCreate($where, $data);
        return $result;
    }

    /**
     * @param array $params
     * @return mixed
     */
    public static function getSocialMediaUserInfo(array $params) {
        $where = [
            'store_id' => $params['store_id'],
            'third_source' => $params['third_source'],
            'third_user_id' => $params['third_user_id']
        ];

        $country = data_get($params, 'country', '');

        $userInfo = static::getModel($params['store_id'], $country, [], 'SocialMediaUser')->buildWhere($where)->get()->toArray();

        return $userInfo;
    }

    /**
     * 默认密码生成
     * @param int $storeId
     * @param string $account
     * @param string $thirdSource
     * @return array
     */
    public static function defaultPassword($storeId, $account, $thirdSource) {
        //固定字符串
        $randStr = 'datsha132K#@';
        $str = md5($storeId . $account . $thirdSource . 'md5sat' . $randStr);
        $password = strtolower(substr($str, 0, 6) . substr($account, 0, 3));

        return [
            'password' => $password,
            'rand_str' => $randStr
        ];
    }

    /**
     * 生成指定位数的随机字符串
     * @param int $seed
     * @param int $length
     * @return string
     */
    public static function randStr($seed, $length = 12) {
        $randString = "";
        $codeAlphabet = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

        mt_srand($seed);

        for ($i = 0; $i < $length; $i++) {
            $randString .= $codeAlphabet[mt_rand(0, strlen($codeAlphabet) - 1)];
        }

        return $randString;
    }

    /**
     * 未获取到邮箱时，根据配置生成假邮箱
     * @param int $storeId
     * @param array $params
     * @param array $requestData
     * @return string
     */
    public static function generateAccount($storeId, array $params, array $requestData) {
        if (empty($storeId)) {
            return data_get($requestData, 'account', '');
        }

        $configs = DictStoreService::getListByType($storeId, 'social_media_account', 'sorts asc', 'conf_key', 'conf_value');
        if ($configs->isEmpty()) {
            return data_get($requestData, 'account', '');
        }

        $replacePairs = [];
        foreach ($configs as $key => $config) {
            if ($key === 'rule') {
                $accountStr = $config;
            } elseif ($key === 'id') {
                data_set($replacePairs, $config, $params['third_user_id']);
            } elseif (stripos($key, 'source_') !== false) {
                $arr = explode("=>", $config);
                $sourceKey = 'source_' . $params['third_source'];
                if ($sourceKey == $key) {
                    $replacePairs[trim(data_get($arr, '0', ''))] = trim(data_get($arr, '1', ''));
                }
            } else {
                $arr = explode("=>", $config);
                $replacePairs[trim(data_get($arr, '0', ''))] = trim(data_get($arr, '1', ''));
            }
        }

        if (empty($accountStr) || empty($replacePairs)) {
            return data_get($requestData, 'account', '');
        }

        $gAccount = strtr($accountStr, $replacePairs);
        if (empty($gAccount)) {
            return data_get($requestData, 'account', '');
        }

        return $gAccount;
    }

    /**
     * 逻辑删除
     * @param int $storeId
     * @param array $where
     * @return bool
     */
    public static function delete($storeId, $where) {
        if (empty($where)) {
            return false;
        }

        return static::getModel($storeId, '', [], 'SocialMediaUser')->buildWhere($where)->delete(); //逻辑删除
    }

}
