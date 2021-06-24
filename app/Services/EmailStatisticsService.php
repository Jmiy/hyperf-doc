<?php

namespace App\Services;

use App\Services\Traits\GetDefaultConnectionModel;
use Hyperf\DbConnection\Db as DB;
use App\Constants\Constant;
use Hyperf\Utils\Arr;

class EmailStatisticsService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 获取发送邮箱发送统计数据
     * @param $emails
     * @param $storeId
     * @param int $actId
     * @param string $type
     * @param string $country
     * @param array $extData
     * @return mixed
     */
    public static function getStatistics($emails, $storeId, $actId = 0, $type = '', $country = '', $extData = []) {
        $where = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_ACT_ID => $actId,
            'type' => $type
        ];

        return static::getModel($storeId, $country)->where($where)->whereIn(Constant::DB_TABLE_EMAIL, $emails)->get()->toArray();
    }

    public static function batchAdd($datas, $storeId, $country = '') {
        return static::getModel($storeId, $country)->insert($datas);
    }

    /**
     * 更新发送次数
     * @param int $storeId
     * @param array $emailConfigs
     * @param array $extData
     * @return bool
     */
    public static function updateSendNums($storeId, $emailConfigs, $extData = []) {
        if (empty($emailConfigs)) {
            return false;
        }

        $email = data_get($emailConfigs, Constant::DB_EXECUTION_PLAN_FROM);
        $country = data_get($extData, Constant::DB_TABLE_COUNTRY, '');
        $actId = data_get($extData, Constant::ACT_ID, 0);
        $type = data_get($extData, 'type', '');

        $where = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_ACT_ID => $actId,
            'type' => $type,
            Constant::DB_TABLE_EMAIL => $email
        ];

        $data = [
            Constant::SEND_NUMS => DB::raw(Constant::SEND_NUMS . '+1'),
        ];

        return static::getModel($storeId, $country)->buildWhere($where)->update($data);
    }

    /**
     * 邮件配置选取，目前只做了发送邮箱的选取，选取发送次数最少的发送邮箱
     * @param int $storeId
     * @param array $emailConfigs
     * @param array $extData
     * @return array
     */
    public static function handleEmailConfigs($storeId, $emailConfigs, $extData = []) {
        if (empty($emailConfigs)) {
            return $emailConfigs;
        }

        $configType = data_get($extData, Constant::ACTIVITY_CONFIG_TYPE, Constant::DB_TABLE_EMAIL);
        $fromEmails = data_get($emailConfigs, ($configType . '_' . Constant::DB_EXECUTION_PLAN_FROM . Constant::LINKER . Constant::DB_TABLE_VALUE));

        if (empty($fromEmails)) {
            return $emailConfigs;
        }

        $emails = array_unique(array_filter(explode(',', $fromEmails)));
        $country = data_get($extData, 'country', '');
        $actId = data_get($extData, 'actId', 0);
        $type = data_get($extData, 'type', '');

        $statData = static::getStatistics($emails, $storeId, $actId, $type, $country, $extData);

        if (empty($statData)) {
            $batchData = [];
            foreach ($emails as $email) {

                if (empty($email)) {
                    continue;
                }

                $batchData[] = [
                    Constant::DB_TABLE_STORE_ID => $storeId,
                    Constant::DB_TABLE_ACT_ID => $actId,
                    'type' => $type,
                    Constant::DB_TABLE_EMAIL => $email,
                    Constant::SEND_NUMS => 0
                ];
            }
            static::batchAdd($batchData, $storeId, $country);

            data_set($emailConfigs, ($configType . '_' . Constant::DB_EXECUTION_PLAN_FROM . Constant::LINKER . Constant::DB_TABLE_VALUE), Arr::first($emails));

            return $emailConfigs;
        }

        $minSendNum = PHP_INT_MAX;
        $minSendNumEmail = '';
        foreach ($statData as $datum) {
            if ($datum[Constant::SEND_NUMS] < $minSendNum) {
                $minSendNum = $datum[Constant::SEND_NUMS];
                $minSendNumEmail = $datum[Constant::DB_TABLE_EMAIL];
            }
        }
        data_set($emailConfigs, ($configType . '_' . Constant::DB_EXECUTION_PLAN_FROM . Constant::LINKER . Constant::DB_TABLE_VALUE), $minSendNumEmail);

        return $emailConfigs;
    }

}
