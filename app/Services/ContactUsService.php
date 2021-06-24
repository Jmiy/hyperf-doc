<?php

/**
 * 联系我们服务
 * User: Jmiy
 * Date: 2020-02-13
 * Time: 20:53
 */

namespace App\Services;

use App\Services\Platform\OrderService;
use Hyperf\Utils\Arr;
use App\Constants\Constant;
use App\Utils\Response;
use App\Utils\FunctionHelper;
use Carbon\Carbon;

class ContactUsService extends BaseService {

    /**
     * 获取db query
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getQuery($storeId = 1, $country = Constant::PARAMETER_STRING_DEFAULT, $where = []) {
        return static::getModel($storeId, $country)->buildWhere($where);
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $where = [];

        $account = data_get($params, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT); //邮箱
        if ($account) {
            $where[] = [Constant::DB_TABLE_ACCOUNT, '=', $account];
        }

        $_where = [];
        if (data_get($params, Constant::DB_TABLE_PRIMARY, 0)) {
            $_where[Constant::DB_TABLE_PRIMARY] = $params[Constant::DB_TABLE_PRIMARY];
        }

        if ($where) {
            $_where[] = $where;
        }

        $order = $order ? $order : [Constant::DB_TABLE_PRIMARY, 'desc'];
        return Arr::collapse([parent::getPublicData($params, $order), [
                        'where' => $_where,
        ]]);
    }

    /**
     * 列表
     * @param $params
     * @param bool $toArray
     * @param bool $isPage
     * @return array
     */
    public static function getListData($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $_data = static::getPublicData($params);

        $where = $_data['where'];
        $order = data_get($params, 'orderBy', data_get($_data, 'order', []));
        $pagination = $_data[Constant::DB_EXECUTION_PLAN_PAGINATION];
        $limit = data_get($params, 'limit', data_get($pagination, 'page_size', 10));

        $customerCount = true;
        $storeId = data_get($params, 'store_id', 1);
        $country = data_get($params, 'country', Constant::PARAMETER_STRING_DEFAULT);
        $query = static::getQuery($storeId, $country, $where);

        if ($isPage || $isOnlyGetCount) {
            $customerCount = static::adminCount($params, $query);
            $pagination['total'] = $customerCount;
            $pagination['total_page'] = ceil($customerCount / $limit);

            if ($isOnlyGetCount) {
                return $pagination;
            }
        }

        if (empty($customerCount)) {
            $query = null;
            return [
                'data' => [],
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
            ];
        }

        if ($order) {
            $query = $query->orderBy($order[0], $order[1]);
        }

        $data = [
            'query' => $query,
            Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
        ];

        $select = $select ? $select : ['*'];
        $data = static::getList($data, $toArray, $isPage, $select, $isRaw, $isGetQuery);

        if ($isGetQuery) {
            return $data;
        }

        return $data;
    }

    /**
     * 获取邮件数据
     * @param int $storeId 商城id
     * @param int $id 联系我们id
     * @param array $parameters 扩展参数
     * @return array $emailData
     */
    public static function getEmailData($storeId, $id, $parameters = []) {

        $emailData = Arr::collapse([$parameters, [
                        Constant::RESPONSE_CODE_KEY => 1,
                        Constant::RESPONSE_MSG_KEY => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_STOREID => $storeId, //商城id
                        Constant::DB_TABLE_CONTENT => Constant::PARAMETER_STRING_DEFAULT, //邮件内容
                        Constant::SUBJECT => Constant::PARAMETER_STRING_DEFAULT, //邮件主题
                        Constant::DB_TABLE_COUNTRY => Constant::PARAMETER_STRING_DEFAULT, //邮件国家
                        Constant::DB_TABLE_EXTINFO => [], //邮件扩展数据
                        'isSendEmail' => false, //是否发送邮件 true：发送  false：不发送 默认：false
                    //'emailView' => 'emails.coupon.default',
        ]]);

        $isExists = data_get($parameters, 'isExists', false);
        if ($isExists) {
            return $emailData;
        }

        $where = [Constant::DB_TABLE_PRIMARY => $id];
        $data = static::getModel($storeId)->buildWhere($where)->first();
        if (empty($data)) {
            return $emailData;
        }
        $data = $data->toArray();

        //获取邮件模板
        $replacePairs = [];
        foreach ($data as $key => $value) {
            $replacePairs['{{$' . $key . '}}'] = $value;
        }

        $emailView = DictStoreService::getByTypeAndKey($storeId, 'email', 'view_contact_us', true, true, Constant::PARAMETER_STRING_DEFAULT); //获取coupon邮件模板
        $content = strtr($emailView, $replacePairs);

        data_set($emailData, Constant::DB_TABLE_CONTENT, $content);
        data_set($emailData, Constant::SUBJECT, data_get($data, Constant::SUBJECT, Constant::PARAMETER_STRING_DEFAULT)); // . $env
        data_set($emailData, 'country', Constant::PARAMETER_STRING_DEFAULT);
        data_set($emailData, Constant::DB_TABLE_EXTINFO, []);
        data_set($emailData, 'isSendEmail', true);

        return $emailData;
    }

    /**
     * 是否发送反馈邮件
     * @param int $storeId 商城id
     * @param array $requestData 请求数据
     * @return boolean true:发送  false:不发送
     */
    public static function isSendEmail($storeId, $requestData = []) {
        $extId = data_get($requestData, Constant::DB_TABLE_EXT_ID, Constant::PARAMETER_INT_DEFAULT);
        $extType = data_get($requestData, Constant::DB_TABLE_EXT_TYPE, Constant::PARAMETER_STRING_DEFAULT);

        if (empty($extId) || empty($extType)) {
            return true;
        }

        switch ($extType) {
            case OrderWarrantyService::getModelAlias():
            case OrderReviewService::getModelAlias():
                $type = 'order_support';
                $confKey = 'email_limit_time';

                break;

            default:
                $type = null;
                $confKey = null;
                break;
        }

        if (empty($type) || empty($confKey)) {
            return true;
        }

        $emailLimitTime = DictStoreService::getByTypeAndKey($storeId, $type, $confKey, true); //订单索评意见反馈频次时间(单位小时)
        if ($emailLimitTime === '') {//如果订单索评意见反馈频次时间没有限制，就直接返回
            return true;
        }

        $time = Carbon::now()->timestamp - $emailLimitTime * 3600; //$emailLimitTime个小时以前的时间戳
        $createdAt = Carbon::createFromTimestamp($time)->toDateTimeString(); //$emailLimitTime个小时以前的时间
        $where = [
            Constant::DB_TABLE_ACCOUNT => data_get($requestData, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_ORDER_NO => data_get($requestData, Constant::DB_TABLE_ORDER_NO, Constant::PARAMETER_STRING_DEFAULT),
            [[Constant::DB_TABLE_CREATED_AT, '>=', $createdAt]]
        ];
        $isExists = static::existsOrFirst($storeId, '', $where);
        if ($isExists) {//如果 $emailLimitTime 小时以内，发送过反馈邮件，就提示用户
            return false;
        }

        return true;
    }

    /**
     * 处理反馈邮件
     * @param int $storeId 商城id
     * @param array $requestData 请求数据
     * @return array
     */
    public static function add($storeId, $requestData = []) {

        $isSendEmail = static::isSendEmail($storeId, $requestData);
        if ($isSendEmail === false) {//如果不发送邮件，就提示用户
            return Response::getDefaultResponseData(110001);
        }

        $account = data_get($requestData, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT);
        $subject = data_get($requestData, Constant::SUBJECT, Constant::PARAMETER_STRING_DEFAULT);
        $extId = data_get($requestData, Constant::DB_TABLE_EXT_ID, Constant::PARAMETER_INT_DEFAULT);
        $extType = data_get($requestData, Constant::DB_TABLE_EXT_TYPE, Constant::PARAMETER_STRING_DEFAULT);
        $data = [
            Constant::DB_TABLE_ACCOUNT => $account,
            Constant::DB_TABLE_TOPIC => data_get($requestData, Constant::DB_TABLE_TOPIC, Constant::PARAMETER_STRING_DEFAULT),
            Constant::PRODUCT_TYPE => data_get($requestData, Constant::PRODUCT_TYPE, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_ORDER_NO => data_get($requestData, Constant::DB_TABLE_ORDER_NO, Constant::PARAMETER_STRING_DEFAULT),
            Constant::SUBJECT => $subject,
            Constant::EXCEPTION_MSG => data_get($requestData, Constant::EXCEPTION_MSG, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_EXT_ID => $extId, //关联id
            Constant::DB_TABLE_EXT_TYPE => $extType, //关联模型
        ];
        $id = static::getModel($storeId)->insertGetId($data);
        if (empty($id)) {
            return Response::getDefaultResponseData(110000);
        }

        $extData = Arr::collapse([FunctionHelper::getJobData(static::getNamespaceClass(), 'getEmailData', [$storeId, $id]), [
                        'replyTo' => ['address' => $account, 'name' => $subject],
        ]]); //扩展数据

        $service = EmailService::getNamespaceClass();
        $toEmail = DictStoreService::getByTypeAndKey($storeId, 'email', 'contact_us_to', true, true, Constant::PARAMETER_STRING_DEFAULT); //联系我们邮件接收者
        $_toEmail = data_get($requestData, 'contact_us_to', Constant::PARAMETER_STRING_DEFAULT);
        if ($_toEmail) {
            $toEmail = $_toEmail;
        }

        $method = 'handle'; //邮件处理
        $group = 'contact_us';
        $emailType = Constant::PARAMETER_STRING_DEFAULT;
        $remark = '联系我们';
        $parameters = [$storeId, $toEmail, $group, $emailType, $remark, $id, static::getModelAlias(), $extData];
        FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters));

        //记录 $extType 对应的事件已经发送联系我们的邮件
        if ($extId && $extType) {
            switch ($extType) {
                case OrderReviewService::getModelAlias():
                    $where = [
                        Constant::DB_TABLE_PRIMARY => $extId,
                    ];
                    OrderReviewService::update($storeId, $where, [Constant::DB_TABLE_CONTACT_US_ID => $id]);

                    break;

                default:
                    break;
            }
        }

        return Response::getDefaultResponseData(Constant::RESPONSE_SUCCESS_CODE);
    }
}
