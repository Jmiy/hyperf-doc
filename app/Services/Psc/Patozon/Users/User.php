<?php

/**
 * Created by Patozon.
 * @desc   :
 * @author : Jmiy
 * @email  : Jmiy_cen@patazon.net
 * @date   : 2020/12/10 13:41
 */

namespace App\Services\Psc\Patozon\Users;

use App\Constants\Constant;
use App\Services\Psc\Patozon\BaseService;

class User extends BaseService
{

    /**
     * token认证
     * @param $authToken
     * @return array|mixed
     */
    public static function tokenAuthentication($authToken)
    {
        $url = static::getApiUrl('/api/user/tokenAuthentication');

        $requestData = [];
        $requestMethod = 'POST';
        $responseData = static::request($url, $requestData, '', $authToken, $requestMethod);

        $user = data_get($responseData, Constant::RESPONSE_TEXT . '.result');
        if ($user) {
            data_set($user, Constant::DB_TABLE_PRIMARY, data_get($user, 'pscUserId', 0));
            data_set($user, Constant::USERNAME, data_get($user, 'pscUsername', ''));
            data_set($user, 'api_token', $authToken);


            $user = collect($user);
        }

        return $user;
    }

    /**
     * 退出权限系统
     * @param string $authToken 认证token
     * @return array|mixed
     */
    public static function singleSignOff($authToken = '')
    {
        $url = static::getApiUrl('/api/user/singleSignOff');

        $requestData = json_encode(
            [
                "jsonrpc" => "2.0",
                "method" => "",
                "id" => 1,
                "params" => [],
            ]
        );
        $requestMethod = 'POST';
        $responseData = static::request($url, $requestData, '', $authToken, $requestMethod);

        return data_get($responseData, Constant::RESPONSE_TEXT . '.result');
    }


}
