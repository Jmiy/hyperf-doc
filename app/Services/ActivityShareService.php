<?php

/**
 * 活动分享服务
 * User: Jmiy
 * Date: 2019-10-30
 * Time: 14:18
 */

namespace App\Services;

use Hyperf\Utils\Arr;
use Carbon\Carbon;
use App\Utils\FunctionHelper;
use App\Constants\Constant;

class ActivityShareService extends BaseService {

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'ActivityShareLog';
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @param array $order 排序控制
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $where = [];

        $actId = data_get($params, Constant::DB_TABLE_ACT_ID, 0); //活动id
        $customerId = data_get($params, Constant::DB_TABLE_CUSTOMER_PRIMARY, 0); //会员id
        $account = data_get($params, Constant::DB_TABLE_ACCOUNT, ''); //会员账号
        $url = data_get($params, 'url', ''); //分享链接

        $country = data_get($params, Constant::DB_TABLE_COUNTRY, ''); //分享国家
        $social_media = data_get($params, Constant::SOCIAL_MEDIA, 0); //社媒平台 FB TW


        if ($actId) {//活动id
            $where[] = [Constant::DB_TABLE_ACT_ID, '=', $actId];
        }

        if ($account) {//会员账号
            $where[] = [Constant::DB_TABLE_ACCOUNT, 'like', "%$account%"];
        }

        if ($url) {//分享链接
            $where[] = ['url', '=', $url];
        }

        if ($customerId) {//会员id
            $where[] = [Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', $customerId];
        }

        if ($country) {//分享国家
            $where[] = [Constant::DB_TABLE_COUNTRY, '=', $country];
        }

        if ($social_media) {//社媒平台 FB TW
            $where[] = [Constant::SOCIAL_MEDIA, '=', $social_media];
        }

        $order = $order ? $order : [['id', 'DESC']];
        return Arr::collapse([parent::getPublicData($params, $order), [
                        Constant::DB_EXECUTION_PLAN_WHERE => [$where],
        ]]);
    }

    /**
     * 列表
     * @param array $params 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认:true
     * @param boolean $isPage  是否分页 true:是 false:否 默认:true
     * @param array $select  查询字段
     * @param boolean $isRaw  是否是原生sql true:是 false:否 默认:false
     * @return array 列表数据
     */
    public static function getListData($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $storeId = data_get($params, 'store_id', 0);
        $_data = static::getPublicData($params, data_get($params, 'orderby', []));

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, []);
        $order = data_get($_data, 'order', []);
        $pagination = data_get($_data, 'pagination', []);
        $limit = data_get($pagination, 'page_size', 10);
        $offset = data_get($pagination, 'offset', 0);

        $select = $select ? $select : ['*']; //
        $dbExecutionPlan = [
            'parent' => [
                'setConnection' => true,
                'storeId' => $storeId,
                'builder' => null,
                'make' => static::getModelAlias(),
                'from' => '',
                'joinData' => [
                ],
                'select' => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                'orders' => $order,
                'offset' => $offset,
                'limit' => $limit,
                'isPage' => $isPage,
                'isOnlyGetCount' => $isOnlyGetCount,
                'pagination' => $pagination,
                'handleData' => [],
            //'unset' => [Constant::DB_TABLE_CUSTOMER_PRIMARY],
            ],
            'with' => [
            ],
            'itemHandleData' => [
            ],
                //'sqlDebug' => true,
        ];

        $dataStructure = 'list';
        $flatten = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * 添加记录
     * @param int $storeId 商城id
     * @param array $country 国家
     * @param array $data  数据
     * @return int
     */
    public static function insert($storeId = 0, $country = '', $data = []) {
        $nowTime = Carbon::now()->toDateTimeString();

        $data['created_at'] = $nowTime;
        $data['updated_at'] = $nowTime;

        return static::getModel($storeId, $country)->insertGetId($data);
    }

    /**
     * 处理分享
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param string $account 会员账号
     * @param string $socialMedia 社媒平台 FB TW
     * @param string $fromUrl 分享的页面地址
     * @param array $requestData 请求数据
     * @return int
     */
    public static function handle($storeId = 0, $actId = 0, $customerId = 0, $account = '', $socialMedia = '', $fromUrl = '', $requestData = []) {

        $data = [
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ACCOUNT => $account,
            Constant::SOCIAL_MEDIA => $socialMedia,
            'url' => $fromUrl,
            Constant::DB_TABLE_COUNTRY => data_get($requestData, Constant::DB_TABLE_COUNTRY, ''),
            'ip' => data_get($requestData, 'ip', ''),
        ];
        static::insert($storeId, '', $data);

        //获取分享是否增加次数标识
        $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, 'share', 'is_add_nums');
        //默认为增加次数
        if (data_get($activityConfigData, 'share_is_add_nums.value', 1)) {
            //添加参与活动次数
            $actionData = [
                Constant::SERVICE_KEY => ActivityService::getNamespaceClass(),
                Constant::METHOD_KEY => 'increment',
                Constant::PARAMETERS_KEY => [],
                Constant::REQUEST_DATA_KEY => $requestData,
            ];
            $customerId = data_get($requestData, 'act_form', 'lottery') == 'lottery' ? $customerId : $account;
            ActivityService::handleLimit($storeId, $actId, $customerId, $actionData);
        }

        //添加分享排行榜积分
        $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, ['rank', 'share'], ['rank_type', 'rank_score']);
        $rankTypeData = data_get($activityConfigData, 'rank_rank_type.value', Constant::PARAMETER_INT_DEFAULT);
        if ($rankTypeData) {
            $rankTypeData = explode(',', $rankTypeData);
            $score = data_get($activityConfigData, 'share_rank_score.value', Constant::PARAMETER_INT_DEFAULT);
            RankService::handle($storeId, $customerId, $actId, $rankTypeData, 1, $score); //榜单类型 0:综合榜 1:分享 2:邀请 3:签到
        }

        return [];
    }

    /**
     * 分享得积分
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员ID
     * @param string $account 账号
     * @param string $socialMedia 分享平台
     * @param string $fromUrl
     * @param array $requestData
     * @return bool
     */
    public static function share($storeId, $actId, $customerId, $account, $socialMedia, $fromUrl, $requestData) {

        $data = [
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ACCOUNT => $account,
            Constant::SOCIAL_MEDIA => $socialMedia,
            Constant::FILE_URL => $fromUrl,
            Constant::DB_TABLE_COUNTRY => data_get($requestData, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_IP => data_get($requestData, Constant::DB_TABLE_IP, Constant::PARAMETER_STRING_DEFAULT),
        ];
        $shareId = static::insert($storeId, '', $data);
        if (empty($storeId)) {
            return false;
        }

        $currentDay = date('Y-m-d');
        $startAt = $currentDay . ' 00:00:00';
        $endAt = $currentDay . ' 23:59:59';
        $where = [
            [Constant::DB_TABLE_CUSTOMER_PRIMARY, $customerId],
            [Constant::DB_TABLE_ACT_ID, $actId],
            [Constant::DB_TABLE_CREATED_AT, '>=', $startAt],
            [Constant::DB_TABLE_CREATED_AT, '<=', $endAt],
            [Constant::SOCIAL_MEDIA, $socialMedia],
        ];
        $count = static::getModel($storeId)->where($where)->count();

        $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, 'share', ['get_credit', 'get_credit_nums']);
        $creditValue = data_get($activityConfigData, 'share_get_credit.value', 2);
        $getCreditNums = data_get($activityConfigData, 'share_get_credit_nums.value', 1);

        if ($getCreditNums >= $count) {
            $creditHistory = [
                Constant::DB_TABLE_STORE_ID => $storeId,
                Constant::DB_TABLE_VALUE => $creditValue,
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                Constant::DB_TABLE_ADD_TYPE => 1,
                Constant::DB_TABLE_ACTION => 'share',
                Constant::DB_TABLE_EXT_ID => $shareId,
                Constant::DB_TABLE_EXT_TYPE => static::getModelAlias(),
            ];

            CreditService::handle($creditHistory);
        }

        return true;
    }
}
