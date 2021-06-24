<?php

namespace App\Services;

use App\Constants\Constant;

class KeyWordLogService extends BaseService {

    /**
     * 提交口令加一次机会
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
        ];
        if ($key != '#play again#' || $word != '#good luck#') {
            $result[Constant::RESPONSE_CODE_KEY] = 200000;
            return $result;
        }

        //判断是否已经提交过
        $where = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_KEY => $key,
            'word' => $word,
        ];
        if (static::existsOrFirst($storeId, '', $where)) {
            $result[Constant::RESPONSE_CODE_KEY] = 200001;
            return $result;
        }

        //数据写入
        static::updateOrCreate($storeId, $where, []);

        //获取口令增加次数
        $getKeyWordNums = GameService::getPlayNums($storeId, $actId, 'add_nums', 'get_key_word');

        //次数增加
        $actionData = [
            Constant::SERVICE_KEY => ActivityService::getNamespaceClass(),
            Constant::METHOD_KEY => 'increment',
            Constant::PARAMETERS_KEY => [$getKeyWordNums],
            Constant::REQUEST_DATA_KEY => [
                'act_form' => Constant::ACT_FORM_SLOT_MACHINE
            ],
        ];
        ActivityService::handleLimit($storeId, $actId, $customerId, $actionData);

        return $result;
    }
}
