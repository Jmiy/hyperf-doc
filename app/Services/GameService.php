<?php

namespace App\Services;

use App\Constants\Constant;
use App\Utils\FunctionHelper;
use Hyperf\Utils\Arr;
use Hyperf\DbConnection\Db as DB;
use App\Utils\Support\Facades\Redis;

class GameService extends BaseService {

    /**
     * 获取赠送游戏的次数
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param string $type 配置类型
     * @param string $key 配置key
     * @return mixed
     */
    public static function getPlayNums($storeId, $actId, $type = 'add_nums', $key = 'init') {
        $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, $type, [$key]);

        return data_get($activityConfigData, "{$type}_{$key}.value", Constant::PARAMETER_INT_DEFAULT);
    }

    /**
     * 点击开始游戏按钮，预生成后两列图片结果，写入缓存
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param array $requestData 请求参数
     * @return array
     */
    public static function preGeneration($storeId, $actId, $customerId, $requestData) {
        $result = [];

        //判断用户次数是否足够
        if (!static::playNumIsEnough($storeId, $actId, $customerId, $requestData)) {
            return $result;
        }

        //获取活动数据
        $activityData = ActivityService::getActivityData($storeId, $actId);

        //获取图片组配置
        $images = static::getImages($storeId, $actId);

        //生成图片结果
        $result = static::generateImageResult($storeId, $actId, $images);

        //计算过期时间,缓存至活动结束时间点
        $currentTime = time();
        $endAt = !empty($activityData[Constant::DB_TABLE_END_AT]) ? strtotime($activityData[Constant::DB_TABLE_END_AT]) : $currentTime;
        $expireTime = $endAt - $currentTime;

        //图片结果key
        $imageKey = "preImage_{$storeId}_{$actId}_{$customerId}";
        //点击stop次数key
        $playNumKey = "stopNum_{$storeId}_{$actId}_{$customerId}";

        //写入缓存
        Redis::setex($imageKey, $expireTime, json_encode($result));
        Redis::setex($playNumKey, $expireTime, 0);

        return $result;
    }

    /**
     * 生成图片结果
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param array $images 图片组配置
     * @return array
     */
    public static function generateImageResult($storeId, $actId, $images) {
        $result = [];

        //获取图片生成列配置
        $columns = static::imageColumns($storeId, $actId);
        if (empty($columns)) {
            return $result;
        }

        //生成图片结果
        foreach ($columns as $index) {
            $columnImages = $images["images{$index}"];

            $count = count($columnImages);

            $randIdx = mt_rand(0, $count - 1);

            $result["col{$index}"] = [
                $columnImages[($randIdx - 1) < 0 ? $count - 1 : $randIdx - 1],
                $columnImages[$randIdx],
                $columnImages[($randIdx + 1) % $count ? ($randIdx + 1) % $count : 0],
            ];
        }

        return $result;
    }

    /**
     * 老虎机抽奖逻辑
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param int $columnIdx 点击列编号
     * @param array $playResult 前端传过来的结果
     * @return array
     */
    public static function playGame($storeId, $actId, $customerId, $columnIdx, $playResult = [], $requestData = []) {
        //获取活动数据
        $activityData = ActivityService::getActivityData($storeId, $actId);

        //获取用户信息
        $customerInfo = CustomerInfoService::existsOrFirst($storeId, '', [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId], true);
        if (empty($customerInfo)) {
            return [];
        }

        //计算过期时间,缓存至活动结束时间点
        $currentTime = time();
        $endAt = !empty($activityData[Constant::DB_TABLE_END_AT]) ? strtotime($activityData[Constant::DB_TABLE_END_AT]) : $currentTime;
        $expireTime = $endAt - $currentTime;

        //点击stop次数key
        $playStopNumKey = "stopNum_{$storeId}_{$actId}_{$customerId}";
        //点击stop次数加1
        $playStopNums = Redis::incrby($playStopNumKey, 1);

        //总列数
        $totalColumns = [1, 2, 3];

        $preImageColumns = static::imageColumns($storeId, $actId);
        if (!in_array($columnIdx, $preImageColumns)) {
            $columnImageKey = "column_{$columnIdx}_{$storeId}_{$actId}_{$customerId}";
            Redis::setex($columnImageKey, $expireTime, json_encode($playResult));
        }

        //点击满三列
        if ($playStopNums == 3) {
            //获取后端预生成的结果
            if (!empty($preImageColumns)) {
                $preImageKey = "preImage_{$storeId}_{$actId}_{$customerId}";
                $preImageResult = Redis::get($preImageKey);
                $preImageResult = json_decode($preImageResult, true);
                foreach ($preImageColumns as $preImageColumn) {
                    $preImageColumn == 1 && $colOneArray = array_values(array_column($preImageResult['col1'], Constant::DB_TABLE_PRIMARY));
                    $preImageColumn == 2 && $colTwoArray = array_values(array_column($preImageResult['col2'], Constant::DB_TABLE_PRIMARY));
                    $preImageColumn == 3 && $colThreeArray = array_values(array_column($preImageResult['col3'], Constant::DB_TABLE_PRIMARY));
                }
            }

            //获取前端传过来的结果
            foreach ($totalColumns as $column) {
                if (!in_array($column, $preImageColumns)) {
                    $colImageKey = "column_{$column}_{$storeId}_{$actId}_{$customerId}";
                    $playResult = Redis::get($colImageKey);
                    $playResult = json_decode($playResult, true);
                    $column == 1 && $colOneArray = array_values($playResult);
                    $column == 2 && $colTwoArray = array_values($playResult);
                    $column == 3 && $colThreeArray = array_values($playResult);
                }
            }

            //游戏结果处理
            $gameRet = static::winningResultProcessing($colOneArray, $colTwoArray, $colThreeArray);

            //更新用户积分
            static::rankUpdate($storeId, $actId, 1, 0, $customerId, $gameRet[Constant::TOTAL_SCORE], $customerInfo);

            //中奖结果写入winning_log表
            static::insertWinningLogs($storeId, $actId, $customerId, $gameRet[Constant::WINNING_LOGS], $gameRet[Constant::TOTAL_SCORE]);

            //游戏可用次数扣减
            static::updatePlayNums($storeId, $actId, $customerId, 'deduct_nums', 'play', $requestData);

            //删除缓存数据
            Redis::del($playStopNumKey);
            Redis::del($preImageKey);
            isset($colOneImageKey) && Redis::del($colOneImageKey);

            //中奖结果
            return $gameRet;
        }

        return [];
    }

    /**
     * 处理游戏结果
     * @param array $colOneArray 第一列结果
     * @param array $colTwoArray 第二列结果
     * @param array $colThreeArray 第三列结果
     * @return array
     */
    public static function winningResultProcessing($colOneArray, $colTwoArray, $colThreeArray) {
        $result = [
            Constant::WINNING_LOGS => []
        ];
        $totalScore = 0;
        //判断横向结果
        foreach ($colOneArray as $key => $id) {
            if ($id == $colTwoArray[$key] && $id == $colThreeArray[$key]) {
                $key == 0 && $score = 100;
                $key == 1 && $score = 200;
                $key == 2 && $score = 100;

                $result[Constant::WINNING_LOGS][] = [
                    Constant::DB_TABLE_TYPE => $key,
                    Constant::SCORE => $score,
                ];

                $totalScore += $score;
            }
        }

        //斜对角(右斜向下)
        if ($colOneArray[0] == $colTwoArray[1] && $colOneArray[0] == $colThreeArray[2]) {
            $result[Constant::WINNING_LOGS][] = [
                Constant::DB_TABLE_TYPE => 3,
                Constant::SCORE => 300,
            ];

            $totalScore += 300;
        }

        //斜对角(右斜向上)
        if ($colOneArray[2] == $colTwoArray[1] && $colOneArray[2] == $colThreeArray[0]) {
            $result[Constant::WINNING_LOGS][] = [
                Constant::DB_TABLE_TYPE => 4,
                Constant::SCORE => 300,
            ];

            $totalScore += 300;
        }

        $result[Constant::TOTAL_SCORE] = empty($totalScore) ? 30 : $totalScore;

        return $result;
    }

    /**
     * 用户积分更新
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $rankType 排行榜类型
     * @param int $type 类型
     * @param int $customerId 会员id
     * @param int $totalScore 分数
     * @param array $customerInfo 用户信息
     * @return bool
     */
    public static function rankUpdate($storeId, $actId, $rankType, $type, $customerId, $totalScore, $customerInfo) {
        $where = [
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_TYPE => $rankType,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
        ];
        $rankData = [
            Constant::SCORE => DB::raw("score + {$totalScore}"),
        ];

        //更新积分
        RankService::insert($storeId, $where, $rankData);

        //更新缓存
        $zsetKey = RankService::getRankKey($storeId, $actId, $rankType, $type);
        if (Redis::exists($zsetKey)) {
            $string = json_encode([
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                Constant::DB_TABLE_FIRST_NAME => $customerInfo[Constant::DB_TABLE_ACCOUNT],
                Constant::DB_TABLE_LAST_NAME => '',
            ]);
            Redis::zincrby($zsetKey, $totalScore, $string);
        }

        return true;
    }

    /**
     * 中奖流水写入
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param array $winningLogs 中奖记录
     * @param int $totalScore 中奖总分
     * @return array
     */
    public static function insertWinningLogs($storeId, $actId, $customerId, $winningLogs, $totalScore) {
        //参与流水
        $gameLog = [
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::SCORE => $totalScore
        ];
        GameLogService::getModel($storeId)->insert($gameLog);

        if (empty($winningLogs)) {
            return [];
        }

        //获取用户信息
        $customerInfo = CustomerInfoService::existsOrFirst($storeId, '', [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId], true);
        if (empty($customerInfo)) {
            return [];
        }

        //获取积分奖励数据
        $prizes = static::getPrizes($storeId, $actId);
        $prizes = array_column($prizes, NULL, Constant::DB_TABLE_TYPE_VALUE);

        //中奖流水
        $insertData = [];
        foreach ($winningLogs as $winningLog) {

            $insertData = [
                Constant::DB_TABLE_PRIZE_ID => $prizes[$winningLog[Constant::SCORE]][Constant::DB_TABLE_PRIMARY],
                Constant::DB_TABLE_PRIZE_ITEM_ID => $prizes[$winningLog[Constant::SCORE]][Constant::DB_TABLE_ITEM_ID],
                Constant::DB_TABLE_ACCOUNT => $customerInfo[Constant::DB_TABLE_ACCOUNT],
                Constant::DB_TABLE_COUNTRY => $customerInfo[Constant::DB_TABLE_COUNTRY],
                Constant::DB_TABLE_IP => $customerInfo[Constant::DB_TABLE_IP],
                Constant::DB_TABLE_QUANTITY => '',
                'prize_type' => '',
                Constant::DB_TABLE_FIRST_NAME => $customerInfo[Constant::DB_TABLE_FIRST_NAME],
                Constant::DB_TABLE_LAST_NAME => $customerInfo[Constant::DB_TABLE_LAST_NAME],
                Constant::DB_TABLE_ACT_ID => $actId,
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            ];
        }
        !empty($insertData) && ActivityWinningService::getModel($storeId)->insert($insertData);

        return [];
    }

    /**
     * 获取用户剩余次数
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param array $requestData 请求参数
     * @return int
     */
    public static function playNumIsEnough($storeId, $actId, $customerId, $requestData) {
        $actionData = [
            Constant::SERVICE_KEY => ActivityService::getNamespaceClass(),
            Constant::METHOD_KEY => 'get',
            Constant::PARAMETERS_KEY => [],
            Constant::REQUEST_DATA_KEY => $requestData,
        ];
        $playNums = ActivityService::handleLimit($storeId, $actId, $customerId, $actionData);

        return data_get($playNums, Constant::LOTTERY_NUM, Constant::PARAMETER_INT_DEFAULT);
    }

    /**
     * 获取活动奖品数据
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @return array
     */
    public static function getPrizes($storeId, $actId) {
        $dbExecutionPlan = ActivityPrizeService::getPrizeDbExecutionPlan($storeId, $actId);

        $publicWhere = data_get($dbExecutionPlan, (Constant::DB_EXECUTION_PLAN_PARENT . Constant::LINKER . Constant::DB_EXECUTION_PLAN_WHERE), []);
        data_set($dbExecutionPlan, (Constant::DB_EXECUTION_PLAN_PARENT . Constant::LINKER . Constant::DB_EXECUTION_PLAN_WHERE), Arr::collapse([$publicWhere, []]));
        data_set($dbExecutionPlan, 'parent.limit', null);

        return FunctionHelper::getResponseData(null, $dbExecutionPlan, false, false, 'list');
    }

    /**
     * 游戏次数更新
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param string $type 配置类型
     * @param string $key 配置key
     * @param array $extData 扩展参数
     * @return bool
     */
    public static function updatePlayNums($storeId, $actId, $customerId, $type = 'deduct_nums', $key = 'play', $extData = []) {

        //获取活动数据
        $activityData = ActivityService::getActivityData($storeId, $actId);

        //计算过期时间,缓存至活动结束时间点
        $currentTime = time();
        $endAt = !empty($activityData[Constant::DB_TABLE_END_AT]) ? strtotime($activityData[Constant::DB_TABLE_END_AT]) : $currentTime;
        $expireTime = $endAt - $currentTime;

        $actFormData = ActivityService::getActivityConfigData($storeId, $actId, 'act_form', 'act_form');
        $actForm = data_get($actFormData,'act_form.value', Constant::PARAMETER_STRING_DEFAULT);
        !empty($actForm) && data_set($extData, 'act_form', $actForm);

        //活动期间内，激活只加一次
        if ($key == 'activate') {
            //是否激活过标识
            $activateKey = "activate_{$storeId}_{$actId}_{$customerId}";
            if (Redis::exists($activateKey)) {
                //重复设置更新缓存时间，为了防止活动结束时间更新
                Redis::setex($activateKey, $expireTime, 1);
                return false;
            }

            Redis::setex($activateKey, $expireTime, 1);
        }

        //邀请逻辑，邀请的用户，相同的注册IP只送一次邀请次数
        if ($key == 'invite') {
            //获取用户注册IP
            $ip = data_get($extData, Constant::DB_TABLE_IP, Constant::PARAMETER_INT_DEFAULT);

            $inviteKey = "invite_{$storeId}_{$actId}_{$ip}";
            if (Redis::exists($inviteKey)) {
                //重复设置更新缓存时间，为了防止活动结束时间更新
                Redis::setex($inviteKey, $expireTime, 1);
                return false;
            }

            Redis::setex($inviteKey, $expireTime, 1);
        }

        //后续参与游戏每天送一次机会
        if ($key == 'every') {
            //key
            $dateKey = "every_{$storeId}_{$actId}_{$customerId}_" . date("Ymd");

            //过期时间至当天末尾
            //存在则不赠送
            if (Redis::exists($dateKey)) {
                //过期时间至当天末尾
                return false;
            }

            //过期时间至当天末尾
            $dateKeyExpireTime = strtotime(date("Y-m-d 23:59:59")) - time();

            Redis::setex($dateKey, $dateKeyExpireTime, 1);
        }

        //获取次数
        $playNums = static::getPlayNums($storeId, $actId, $type, $key);
        if (empty($playNums)) {
            return true;
        }

        //次数更新
        $actionData = [
            Constant::SERVICE_KEY => ActivityService::getNamespaceClass(),
            Constant::METHOD_KEY => 'increment',
            Constant::PARAMETERS_KEY => [$playNums],
            Constant::REQUEST_DATA_KEY => [
                'act_form' => data_get($extData, 'act_form', 'lottery'),
            ],
        ];
        if ($key == 'every') {
            data_set($actionData, 'requestData.day_add_nums', $playNums);
        }

        ActivityService::handleLimit($storeId, $actId, $customerId, $actionData);

        //记录次数变更日志
        $chanceLog = [
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_TYPE => $type,
            Constant::DB_TABLE_KEY => $key,
            'num' => $playNums,
        ];
        ChanceLogService::getModel($storeId)->insert($chanceLog);

        return true;
    }

    /**
     * 获取图片配置
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @return array
     */
    public static function getImages($storeId, $actId) {
        $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, ['images1', 'images2', 'images3'], '', [['sort', 'ASC']]);
        $images = [];
        foreach ($activityConfigData as $k => $v) {
            $retKey = explode('_', $k); //$retKey[1]
            $images[$retKey[0]][] = [
                Constant::DB_TABLE_PRIMARY => $retKey[2],
                Constant::DB_TABLE_IMG => $v[Constant::DB_TABLE_VALUE],
            ];
        }

        return $images;
    }

    /**
     * 状态
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @return array
     */
    public static function taskFlag($storeId, $actId, $customerId) {
        $result = [
            'is_submit' => false,
            'is_verify' => false,
        ];

        if (KeyWordLogService::existsOrFirst($storeId, '', [Constant::DB_TABLE_ACT_ID => $actId, Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId])) {
            $result['is_submit'] = true;
        }

        //获取会员激活状态
        $customer = CustomerService::getCustomerActivateData($storeId, $customerId);
        $isActivate = data_get($customer, 'info.isactivate', Constant::PARAMETER_INT_DEFAULT);
        if ($isActivate) {
            $result['is_verify'] = true;
        }

        return $result;
    }

    /**
     * 图片生成列配置
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @return array
     */
    public static function imageColumns($storeId, $actId) {
        //获取图片生成列配置
        $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, 'generate', 'image_col_idx');
        $imageColIdx = data_get($activityConfigData, 'generate_image_col_idx.value', '');
        if (empty($imageColIdx)) {
            return [];
        }

        //配置如：2_3，表示生成第2和第3列的图片(单列配置只配一个值，多列配置_分割)
        $columns = explode('_', $imageColIdx);
        if (empty($columns)) {
            return [];
        }

        return $columns;
    }

}
