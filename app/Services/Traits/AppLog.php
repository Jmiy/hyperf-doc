<?php

/**
 * Db trait
 * User: Jmiy
 * Date: 2020-12-09
 * Time: 11:25
 */

namespace App\Services\Traits;

use App\Constants\Constant;
use App\Utils\FunctionHelper;
use Carbon\Carbon;
use Hyperf\Utils\Arr;
use App\Services\ActivityService;

trait AppLog
{

    /**
     * 获取日志流水对应的值
     * @param $storeId 品牌id
     * @param $value 配置值
     * @param $limitValue 限制值
     * @param $where 日志where
     * @param string $sumField 求和field
     * @return int|mixed
     */
    public static function getHandleLogValue($storeId, $value, $limitValue, $where, $sumField = Constant::DB_TABLE_VALUE)
    {

        if (null === $limitValue) {//$value &&
            return $value;
        }

        $sum = static::getModel($storeId)->buildWhere($where)->sum($sumField);
        if ($sum >= $limitValue) {
            $value = 0;
        } else {
            $_value = $limitValue - $sum;
            $value = $_value > $value ? $value : $_value;
            unset($_value);
        }

        return $value;
    }

    /**
     * 获取处理日志流水的参数
     * @param $storeId 品牌id
     * @param int $actId
     * @param null|mixed $actValue 活动配置值
     * @param int $customerId 账号id
     * @param string $type 配置类型
     * @param string $action 流水行为
     * @param string $key 流水key
     * @param null|mixed $configData 配置数据
     * @return array
     */
    public static function getHandleLogParameters($storeId, $actId = 0, $actValue = null, $customerId = 0, $type = Constant::SIGNUP_KEY, $action = Constant::ACTION_INVITE, $key = 'credit', $configData = null)
    {

        $valueKey = $action . '_' . $key;
        $limitMonthKey = $valueKey . '_limit_month';
        $limitYearKey = $valueKey . '_limit_year';
        $limitKey = $valueKey . '_limit';

        $distKey = [$valueKey, $limitMonthKey, $limitYearKey, $limitKey];

        if ($actId) {
            $actData = ActivityService::getActData($storeId, $actId);
            if (data_get($actData, 'isValid') === true) {//如果活动有效，就根据活动配置发放积分和经验
                $configData = ActivityService::getActivityConfigData($storeId, $actId, $type, $distKey);

                $valueKey = $type . '_' . $valueKey . '.value';
                $limitMonthKey = $type . '_' . $limitMonthKey . '.value';
                $limitYearKey = $type . '_' . $limitYearKey . '.value';
                $limitKey = $type . '_' . $limitKey . '.value';

            }
        } else {
            $extWhere = [
                Constant::DICT => [
                    Constant::DB_TABLE_DICT_KEY => $distKey,
                ],
                Constant::DICT_STORE => [
                    Constant::DB_TABLE_STORE_DICT_KEY => $distKey,
                ],
            ];
            $configData = $configData !== null ? $configData : static::getMergeConfig($storeId, $type, $extWhere);
        }

        $limitMonth = data_get($configData, $limitMonthKey);//邀请功能积分最高100/月
        $limitYear = data_get($configData, $limitYearKey);//邀请功能积分最高1200/年
        $limit = data_get($configData, $limitKey);//邀请功能累计积分最高限制

        $value = null !== $actValue ? $actValue : data_get($configData, $valueKey);//邀请功能积分

        $where = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ADD_TYPE => 1,
            Constant::DB_TABLE_ACTION => $action,
        ];
        if ($actId) {
            data_set($where, Constant::DB_TABLE_ACT_ID, $actId);
        }

        //获取按月限制后的日志流水对应的值
        $monthStart = Carbon::now()->rawFormat('Y-m-01 00:00:00');
        $end = Carbon::now()->rawFormat('Y-m-d H:i:s');
        $monthWhere = Arr::collapse([$where, [
            Constant::DB_EXECUTION_PLAN_CUSTOMIZE_WHERE => [FunctionHelper::getJobData('', 'whereBetween', [Constant::DB_TABLE_OLD_CREATED_AT, [$monthStart, $end]])],
        ]]);
        $value = static::getHandleLogValue($storeId, $value, $limitMonth, $monthWhere);

        //获取按年限制后的日志流水对应的值
        $yearStart = Carbon::now()->rawFormat('Y-01-01 00:00:00');
        $yearWhere = Arr::collapse([$where, [
            Constant::DB_EXECUTION_PLAN_CUSTOMIZE_WHERE => [FunctionHelper::getJobData('', 'whereBetween', [Constant::DB_TABLE_OLD_CREATED_AT, [$yearStart, $end]])],
        ]]);
        $value = static::getHandleLogValue($storeId, $value, $limitYear, $yearWhere);

        //获取按总限制后的日志流水对应的值
        $value = static::getHandleLogValue($storeId, $value, $limit, $where);

        return [
            Constant::DB_TABLE_TYPE => (null != $limitMonth || null != $limitYear || null != $limit) ? null : $type,
            Constant::DB_TABLE_VALUE => $value,
        ];

    }

}
