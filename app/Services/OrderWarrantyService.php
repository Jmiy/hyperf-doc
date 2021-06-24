<?php

/**
 * 订单延保服务
 * User: Jmiy
 * Date: 2019-05-17
 * Time: 19:50
 */

namespace App\Services;

use Hyperf\DbConnection\Db as DB;
use Carbon\Carbon;
use Hyperf\Utils\Arr;
use App\Utils\Support\Facades\Cache;
use App\Models\Customer;
use App\Models\CustomerInfo;
use App\Services\LogService;
use App\Utils\FunctionHelper;
use App\Constants\Constant;
use App\Utils\Response;
use App\Services\Traits\Order;
use App\Services\Platform\OrderService;
use App\Services\Platform\OrderItemService;
use App\Services\CustomerInfoService;

class OrderWarrantyService extends BaseService {

    use Order;

    public static $statusData = [
        Constant::PARAMETER_INT_DEFAULT => '未绑定',
        Constant::ORDER_STATUS_SHIPPED_INT => '已绑定',
        Constant::ORDER_STATUS_CANCELED_INT => '其他',
    ];
    public static $initOrderStatus = Constant::ORDER_STATUS_DEFAULT;

    /**
     * 获取模型别名
     * @return string
     */
    public static function getModelAlias() {
        return 'CustomerOrder';
    }

    /**
     * 检测是否存在
     * @param int $storeId 商城id
     * @param int $customerId 会员id
     * @param string $type 订单类型
     * @param string $orderno 订单号
     * @return bool
     */
    public static function checkExists($storeId = 0, $customerId = 0, $type = '', $orderno = '', $getData = false, $select = null) {
        $where = Constant::PARAMETER_ARRAY_DEFAULT;
        if ($storeId) {
            $where[Constant::DB_TABLE_STORE_ID] = $storeId;
        }
        if ($customerId) {
            $where[Constant::DB_TABLE_CUSTOMER_PRIMARY] = $customerId;
        }
        if ($type) {
            $where[Constant::DB_TABLE_TYPE] = $type;
        }
        if ($orderno) {
            $where[Constant::DB_TABLE_ORDER_NO] = $orderno;
        }

        return static::existsOrFirst($storeId, '', $where, $getData, $select);
    }

    /**
     * 获取订单信息 此方法不在使用 202010092027 （Jmiy 注释）
     * @param string $orderno 订单id
     * @param string $country 订单国家
     * @param string $platform 订单平台
     * @param int $storeId 商城id
     * @param boolean $isUseLocal 是否从本地获取订单数据 true：是 false：否  默认：true
     * @return type
     */
//    public static function getOrder($orderno, $country, $platform = Constant::PLATFORM_SERVICE_AMAZON, $storeId = Constant::ORDER_STATUS_SHIPPED_INT, $isUseLocal = true) {
//        return OrderService::getOrderData($orderno, $country, $platform, $storeId, $isUseLocal);
//    }

    /**
     * 添加客户订单
     * @param $params
     * @return bool
     */
    public static function addCustomerOrder($data) {
        $now_at = Carbon::now()->toDateTimeString();
        $_data = [
            Constant::DB_TABLE_STORE_ID => data_get($data, Constant::DB_TABLE_STORE_ID, 0),
            Constant::DB_TABLE_TYPE => data_get($data, Constant::DB_TABLE_TYPE, Constant::DB_TABLE_PLATFORM),
            Constant::DB_TABLE_CUSTOMER_PRIMARY => data_get($data, Constant::DB_TABLE_CUSTOMER_PRIMARY, 0),
            Constant::DB_TABLE_ORDER_NO => data_get($data, Constant::DB_TABLE_ORDER_NO, ''),
            Constant::DB_TABLE_PLATFORM => data_get($data, Constant::DB_TABLE_PLATFORM, ''),
            Constant::DB_TABLE_COUNTRY => data_get($data, Constant::DB_TABLE_COUNTRY, ''),
            Constant::DB_TABLE_AMOUNT => data_get($data, Constant::DB_TABLE_AMOUNT, '0.00'),
            Constant::DB_TABLE_CURRENCY_CODE => data_get($data, Constant::DB_TABLE_CURRENCY_CODE, ''),
            Constant::DB_TABLE_CONTENT => data_get($data, Constant::DB_TABLE_CONTENT, ''),
            Constant::DB_TABLE_ORDER_TIME => isset($data[Constant::DB_TABLE_ORDER_TIME]) && $data[Constant::DB_TABLE_ORDER_TIME] ? $data[Constant::DB_TABLE_ORDER_TIME] : $now_at,
            Constant::DB_TABLE_ORDER_STATUS => data_get($data, Constant::DB_TABLE_ORDER_STATUS, static::$initOrderStatus), //订单状态 -1:Matching 0:Pending 1:Shipped 2:Canceled 默认:1
            Constant::DB_TABLE_STATUS => data_get($data, Constant::DB_TABLE_STATUS, 1), //状态 0:无效 1:有效
            Constant::DB_TABLE_OLD_CREATED_AT => data_get($data, Constant::DB_TABLE_CREATED_AT, $now_at),
            Constant::DB_TABLE_OLD_UPDATED_AT => data_get($data, Constant::DB_TABLE_UPDATED_AT, $now_at),
            Constant::DB_TABLE_ACCOUNT => data_get($data, Constant::DB_TABLE_ACCOUNT, ''), //会员账号
            Constant::DB_TABLE_BRAND => data_get($data, Constant::DB_TABLE_BRAND, ''), //品牌
            Constant::DB_TABLE_ACT_ID => data_get($data, Constant::DB_TABLE_ACT_ID, 0), //活动id
        ];

        $_data[Constant::DB_TABLE_ORDER_TIME] = $_data[Constant::DB_TABLE_ORDER_TIME] ? Carbon::parse($_data[Constant::DB_TABLE_ORDER_TIME])->toDateTimeString() : $data[Constant::DB_TABLE_ORDER_TIME];

        return static::getModel($_data[Constant::DB_TABLE_STORE_ID], '')->insertGetId($_data);
    }

    /**
     * 订单绑定
     * @param $params
     * @return bool
     */
    public static function bind($storeId, $account, $orderno, $country, $type = Constant::DB_TABLE_PLATFORM, $extData = Constant::PARAMETER_ARRAY_DEFAULT) {

        $tags = config('cache.tags.order', ['{order}']);

        $defaultRs = Response::getDefaultResponseData(30001);

        $cacheKey = 'bind:' . $storeId . ':' . $orderno;

        $isForceReleaseOrderLock = DictService::getByTypeAndKey(Constant::ORDER_BIND, 'is_force_release_order_lock', true); //是否强制释放订单绑定锁 1:是 0:否  默认:1
        $releaseTime = DictService::getByTypeAndKey(Constant::ORDER_BIND, 'release_time', true); //释放订单锁时间 单位秒 默认:600(10分钟)
        if ($isForceReleaseOrderLock) {
            static::forceReleaseLock($cacheKey, 'forceRelease', $releaseTime); //如果获取分布式锁失败，10秒后强制释放锁
        }

        $rs = Cache::tags($tags)->lock($cacheKey)->get(function () use($storeId, $account, $orderno, $country, $type, $extData) {
            $retult = Response::getDefaultResponseData();
            $storeId = trim($storeId);
            if (empty($storeId)) {
                $retult[Constant::RESPONSE_MSG_KEY] = 'store_id is required.';
                return $retult;
            }

            $account = trim($account);
            if (empty($account)) {
                $retult[Constant::RESPONSE_MSG_KEY] = 'account is required.';
                return $retult;
            }

            $orderno = trim($orderno);
            if (empty($orderno)) {
                $retult[Constant::RESPONSE_MSG_KEY] = 'orderno is required.';
                return $retult;
            }

            $country = trim($country);
            if (empty($country)) {
                $retult[Constant::RESPONSE_MSG_KEY] = 'country is required.';
                return $retult;
            }

            $customer = Customer::select(Constant::DB_TABLE_CUSTOMER_PRIMARY)->where([Constant::DB_TABLE_STORE_ID => $storeId, Constant::DB_TABLE_ACCOUNT => $account])->first();
            if (empty($customer)) {
                $retult[Constant::RESPONSE_MSG_KEY] = 'The account:' . $account . ' not exists';
                return $retult;
            }
            $customerId = $customer->customer_id;

            //检查该订单是否绑定过
            $isExists = static::checkExists($storeId, 0, Constant::DB_TABLE_PLATFORM, $orderno);
            if ($isExists) {
                return Response::getDefaultResponseData(30000);
            }

            $now_at = Carbon::now()->toDateTimeString();
            $data = [
                Constant::DB_TABLE_STORE_ID => $storeId,
                Constant::DB_TABLE_TYPE => $type,
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                Constant::DB_TABLE_ORDER_NO => $orderno,
                Constant::DB_TABLE_COUNTRY => $country,
                Constant::DB_TABLE_AMOUNT => 0,
                Constant::DB_TABLE_CURRENCY_CODE => '',
                Constant::DB_TABLE_CONTENT => '',
                Constant::DB_TABLE_ORDER_TIME => $now_at,
                Constant::DB_TABLE_ORDER_STATUS => static::$initOrderStatus, //订单状态 -1:未匹配 0:未支付 1:已经支付 2:取消 默认:1
                Constant::DB_TABLE_STATUS => 1, //状态 0:无效 1:有效
                Constant::DB_TABLE_ACCOUNT => $account, //会员账号
                Constant::DB_TABLE_BRAND => data_get($extData, Constant::DB_TABLE_BRAND, ''), //品牌
                Constant::DB_TABLE_PLATFORM => data_get($extData, Constant::DB_TABLE_PLATFORM, ''), //平台
                Constant::DB_TABLE_ACT_ID => data_get($extData, Constant::DB_TABLE_ACT_ID, 0), //活动id
            ];

            if (isset($extData[Constant::DB_TABLE_CREATED_AT])) {
                data_set($data, Constant::DB_TABLE_ORDER_TIME, $extData[Constant::DB_TABLE_CREATED_AT]);
                data_set($data, Constant::DB_TABLE_CREATED_AT, $extData[Constant::DB_TABLE_CREATED_AT]);
            }

            if (isset($extData[Constant::DB_TABLE_UPDATED_AT])) {
                data_set($data, Constant::DB_TABLE_UPDATED_AT, $extData[Constant::DB_TABLE_UPDATED_AT]);
            }

            $customerOrderId = static::addCustomerOrder($data);
            if (!$customerOrderId) {
                $retult[Constant::RESPONSE_MSG_KEY] = 'binding false';
                return $retult;
            }

            $data = [$customerOrderId, $storeId, app('request')->input(Constant::REQUEST_MARK, ''), true, $extData];
            $result = static::handleBind(...$data); //订单实时拉取

            $responseData = [
                Constant::RESPONSE_CODE_KEY => 1,
                Constant::RESPONSE_MSG_KEY => 'Your order has submitted successfully, and it is in review. The points will be added to your account in 48 hours.',
                Constant::RESPONSE_DATA_KEY => [
                    $data,
                    'order_country' => data_get($result, 'data.order_country', ''),
                    'credit' => data_get($result, 'data.credit', 0)
                ],
            ];
            return $responseData;
        });

        if ($isForceReleaseOrderLock && $rs === false) {//如果强制释放订单锁，并且获取分布式锁失败，就统计获取次数
            LogService::addSystemLog('debug', 'order_bind_lock_no', $orderno, $cacheKey, func_get_args()); //添加系统日志
            static::forceReleaseLock($cacheKey, 'statisticsLock', $releaseTime); //统计锁
        }

        return $rs === false ? $defaultRs : $rs;
    }

    /**
     * 处理订单绑定业务
     * @param int $orderId 订单延保id
     * @param int $storeId 商城id
     * @param string $createdMark 创建标识
     * @param bool $isUseLocal 是否从本地获取订单数据 true：是 false：否  默认：true
     * @param array $extData 扩展参数
     * @return array|string|int
     */
    public static function handleBind($orderId, $storeId = 0, $createdMark = '', $isUseLocal = true, $extData = []) {

        $retult = Response::getDefaultResponseData();
        if (empty($orderId)) {
            $retult[Constant::RESPONSE_MSG_KEY] = 'binding false';
            return $retult;
        }

        //设置时区
        app('request')->offsetSet(Constant::REQUEST_MARK, $createdMark);
        FunctionHelper::setTimezone($storeId);
        $nowTime = Carbon::now()->toDateTimeString();

        $orderModel = static::getModel($storeId, '');
        $orderData = $orderModel->where([Constant::DB_TABLE_PRIMARY => $orderId])->first(); //->withTrashed()
        if (empty($orderData)) {
            $retult[Constant::RESPONSE_MSG_KEY] = 'binding false';
            return $retult;
        }

        if (data_get($orderData, Constant::DB_TABLE_ORDER_STATUS, Constant::ORDER_STATUS_DEFAULT) > 0) {//如果订单已经匹配成功,就不再匹配
            $retult[Constant::RESPONSE_MSG_KEY] = 'The order submitted successfully, please do not repeat';
            return $retult;
        }

        if (data_get($orderData, Constant::DB_TABLE_PULL_NUM, Constant::PARAMETER_INT_DEFAULT) > 0) {
            $retult[Constant::RESPONSE_MSG_KEY] = 'stop pull order from system-' . $orderData->pull_num;
            return $retult;
        }

        //更新拉取订单次数
        $customerOrderData = [
            Constant::DB_TABLE_PULL_NUM => DB::raw(Constant::DB_TABLE_PULL_NUM . '+1'),
            Constant::DB_TABLE_OLD_UPDATED_AT => $nowTime,
            'task_no' => '',
        ];
        $orderModel->withTrashed()->where([Constant::DB_TABLE_PRIMARY => $orderId])->update($customerOrderData);

        //查询订单系统
        $orderRet = OrderService::getOrderData(data_get($orderData, Constant::DB_TABLE_ORDER_NO, Constant::PARAMETER_STRING_DEFAULT), data_get($orderData, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT), Constant::PLATFORM_SERVICE_AMAZON, $storeId, $isUseLocal, $orderId);
        $orderStatus = data_get($orderRet, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_ORDER_STATUS, 'Pending'); //订单状态 Pending Shipped Canceled
        if ($isUseLocal === true && !in_array($orderStatus, ['Shipped', 'Canceled'])) {//如果订单是从本地获取，并且订单状态不是 Shipped Canceled，就去销参拉取最新的订单数据
            $orderModel->withTrashed()->where([Constant::DB_TABLE_PRIMARY => $orderId])->update([Constant::DB_TABLE_PULL_NUM => 0, 'task_no' => '']);

            $service = static::getNamespaceClass();
            $method = __FUNCTION__;
            $parameters = [$orderId, $storeId, $createdMark, false];

            FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters), null, '{amazon-order-bind}');
        }

        $actId = data_get($orderData, Constant::DB_TABLE_ACT_ID, 0);
        if ($orderRet[Constant::RESPONSE_CODE_KEY] != 1) {//如果拉取订单失败，就返回
            static::pushEmailQueue($storeId, $orderId, $actId); //推送订单邮件到消息队列
            return $orderRet;
        }

        if (empty($orderRet[Constant::RESPONSE_DATA_KEY])) {//如果拉取订单成功并且订单数据不存在，就返回
            $retult[Constant::RESPONSE_MSG_KEY] = $orderRet[Constant::RESPONSE_MSG_KEY];
            static::pushEmailQueue($storeId, $orderId, $actId); //推送订单邮件到消息队列
            return $retult;
        }

        $orderno = data_get($orderData, Constant::DB_TABLE_ORDER_NO, Constant::PARAMETER_STRING_DEFAULT);
        $customerId = data_get($orderData, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
        $account = data_get($orderData, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT);

        //更新订单总表的账号和商城id数据
        //OrdersService::updateOrderCustomer($orderno, $storeId, $customerId, $account);
        //更新订单数据
        $currencyCode = data_get($orderRet, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_CURRENCY_CODE, ''); //交易货币
        $amount = data_get($orderRet, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_AMOUNT, 0); //订单金额
        $orderStatusData = DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, Constant::DB_TABLE_DICT_VALUE, Constant::DB_TABLE_DICT_KEY); //订单状态 -1:Matching 0:Pending 1:Shipped 2:Canceled 3:Failure 默认:-1
        $data = [
            Constant::DB_TABLE_ORDER_UNIQUE_ID => data_get($orderRet, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_UNIQUE_ID, 0), //订单唯一id
            Constant::DB_TABLE_AMOUNT => $amount,
            Constant::DB_TABLE_CURRENCY_CODE => $currencyCode,
            Constant::DB_TABLE_CONTENT => json_encode(data_get($orderRet, Constant::RESPONSE_DATA_KEY, Constant::PARAMETER_ARRAY_DEFAULT)),
            Constant::DB_TABLE_ORDER_TIME => Carbon::parse(data_get($orderRet, Constant::RESPONSE_DATA_KEY . Constant::LINKER . 'purchase_date', $nowTime))->toDateTimeString(),
            Constant::DB_TABLE_ORDER_STATUS => data_get($orderStatusData, $orderStatus, static::$initOrderStatus), //订单状态 -1:Matching 0:Pending 1:Shipped 2:Canceled 3:Failure 默认:-1
            Constant::DB_TABLE_OLD_UPDATED_AT => $nowTime, //更新时间
        ];
        $orderModel->where([Constant::DB_TABLE_PRIMARY => $orderId])->update($data);

        switch ($data[Constant::DB_TABLE_ORDER_STATUS]) {
            case static::$initOrderStatus://订单是初始状态，提示用户
            case Constant::ORDER_STATUS_PENDING_INT://订单未支付，提示用户
                $retult[Constant::RESPONSE_MSG_KEY] = 'Your order has submitted successfully, and it is in review. The points will be added to your account in 48 hours.';
                break;

            case Constant::ORDER_STATUS_CANCELED_INT://订单取消，就提示用户
                $retult[Constant::RESPONSE_CODE_KEY] = 1;
                break;

            case Constant::ORDER_STATUS_SHIPPED_INT://订单支付成
                $retult[Constant::RESPONSE_CODE_KEY] = 1;
                $retult[Constant::RESPONSE_DATA_KEY] = $orderRet[Constant::RESPONSE_DATA_KEY];

                $actId = data_get($extData, Constant::DB_TABLE_ACT_ID, Constant::PARAMETER_INT_DEFAULT); //活动id
                //处理订单延保 积分和经验
                $creditValueRs = static::handleCreditAndExp($storeId, $orderId, $actId, $extData);
                data_set($retult, Constant::RESPONSE_DATA_KEY . '.credit', intval($creditValueRs));


                static::handleWarrantyAt($storeId, $orderId, $customerId);

                break;

            default:
                $retult[Constant::RESPONSE_MSG_KEY] = 'Order status is abnormal==>' . $orderStatus;
                break;
        }

        static::pushEmailQueue($storeId, $orderId, $actId); //推送订单邮件到消息队列

        data_set($retult, Constant::RESPONSE_DATA_KEY . '.order_country', data_get($orderRet, 'data.items.0.order_country'));

        return $retult;
    }

    /**
     * 获取订单邮件配置
     * @param int $storeId 商城id
     * @return array 订单邮件配置
     */
    public static function getOrderEmailConfig($storeId = 0, $country = '') {
        return static::getOrderConfig($storeId, $country);
    }

    public static function handleEmailLimit($orderId = 0, $orderStatus = -1, $actionData = Constant::PARAMETER_ARRAY_DEFAULT) {
        //限制重发，一分钟内限制点击5次防止被刷，超过次数在按钮下方给出提示文案：Messages are limited,please wait for about 10 minutes before you try again。
        $key = 'order:' . $orderId . ':' . $orderStatus;
        $tags = config('cache.tags.email');

        $method = data_get($actionData, Constant::METHOD_KEY, '');
        $parameters = data_get($actionData, Constant::PARAMETERS_KEY, Constant::PARAMETER_ARRAY_DEFAULT);
        array_unshift($parameters, $key);

        return Cache::tags($tags)->{$method}(...$parameters);
    }

    /**
     * 推送订单邮件到消息队列
     * @param int $storeId 商城id
     * @param int $orderId 订单id
     * @param int $actId 活动id
     * @return mixed
     */
    public static function pushEmailQueue($storeId = 0, $orderId = 0, $actId = 0) {
        //处理订单邮件业务

        $queueConnection = config('queue.mail_queue_connection');
        $extData = [
            'queueConnectionName' => $queueConnection,//Queue Connection
        ];
        return FunctionHelper::pushQueue(FunctionHelper::getJobData(static::getNamespaceClass(), 'handleEmail', func_get_args(), null, $extData), '', config('queue.connections.' . $queueConnection . '.queue'));//邮件处理

        //return FunctionHelper::pushQueue(FunctionHelper::getJobData(static::getNamespaceClass(), 'handleEmail', func_get_args()), null, '{amazon-order-bind}');
    }

    /**
     * 处理订单邮件业务
     * @param int $storeId 商城id
     * @param int $orderId 订单id
     * @param int $actId 活动id
     * @return array $retult
     */
    public static function handleEmail($storeId = 0, $orderId = 0, $actId = 0) {

        $retult = Response::getDefaultResponseData(1);

        $orderEmailConfig = static::getOrderConfig($storeId, 'all');
        if (empty(data_get($orderEmailConfig, 'email', 0))) {//如果订单延保,不发送邮件就直接返回
            $emails = DictStoreService::getByTypeAndKey($storeId,'order', 'send_email', true);
            $orderData = static::getModel($storeId, '')->where([Constant::DB_TABLE_PRIMARY => $orderId])->first();
            $account = data_get($orderData, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT);
            if (stripos($emails, $account) === false) {
                return $retult;
            }
        }

        if (empty($orderId)) {
            $retult[Constant::RESPONSE_CODE_KEY] = 39000;
            $retult[Constant::RESPONSE_MSG_KEY] = 'orderId is required.';
            return $retult;
        }

        $orderModel = static::getModel($storeId, '');
        $orderData = $orderModel->where([Constant::DB_TABLE_PRIMARY => $orderId])->first(); //->withTrashed()
        if (empty($orderData)) {
            $retult[Constant::RESPONSE_CODE_KEY] = 39001;
            $retult[Constant::RESPONSE_MSG_KEY] = 'Order does not exist.';
            return $retult;
        }

        /*         * ***************锁定任务 防止重复发送订单邮件***************************** */
        //判断任务是否在执行
        $limit = static::handleEmailLimit($orderId, $orderData->order_status, FunctionHelper::getJobData(static::getNamespaceClass(), 'get', Constant::PARAMETER_ARRAY_DEFAULT));
        if ($limit) {
            $retult[Constant::RESPONSE_CODE_KEY] = 39005;
            $retult[Constant::RESPONSE_MSG_KEY] = 'Order Email Processing.';
            return $retult;
        }

        //锁定任务
        $actionData = [
            Constant::SERVICE_KEY => '',
            Constant::METHOD_KEY => 'put',
            Constant::PARAMETERS_KEY => [1, 600],
        ];
        static::handleEmailLimit($orderId, $orderData->order_status, $actionData);


        //设置时区
        FunctionHelper::setTimezone($storeId);

        $orderTriggerTime = data_get($orderEmailConfig, 'email_trigger_time_' . $orderData->order_status, 0);
        if ($orderTriggerTime) {

            $nowTime = Carbon::now()->toDateTimeString();
            $orderBindTime = Carbon::parse($orderData->ctime)->toDateTimeString();
            $orderTriggerTime = strtotime($orderTriggerTime, strtotime($orderBindTime)); //触发发送邮件时间
            $orderTriggerTime = Carbon::createFromTimestamp($orderTriggerTime)->toDateTimeString();

            if ($nowTime < $orderTriggerTime) {
                $retult[Constant::RESPONSE_CODE_KEY] = 39002;
                $retult[Constant::RESPONSE_MSG_KEY] = 'Order does not Trigger Email.';
                return $retult;
            }
        }

        $orderStatusData = DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //订单状态 -1:匹配中 0:未支付 1:已经支付 2:取消 默认:-1
        $orderStatus = data_get($orderStatusData, $orderData->order_status, '');

        $toEmail = $orderData->account;  //收件人
        $group = Constant::ORDER; //分组
        $type = $orderStatus; //类型
        $remark = '订单 ' . $orderStatus . ' 邮件'; //备注
        $extId = $orderId; //订单id
        $extType = static::getModelAlias();

        //判断订单邮件是否已经发送
        $where = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            'group' => $group,
            Constant::DB_TABLE_TYPE => $type,
            Constant::DB_TABLE_COUNTRY => $orderData->country,
            'to_email' => $toEmail,
            Constant::DB_TABLE_EXT_ID => $extId,
            Constant::DB_TABLE_EXT_TYPE => $extType,
        ];

        //mpow shopify 订单 同一个用户同一个订单当且仅当只收到一封延保通知邮件，订单状态后续变更不触发通知邮件
        //vt amazon 订单 同一个用户同一个订单当且仅当只收到一封延保通知邮件，订单状态后续变更不触发通知邮件
        $platform = data_get($orderData, Constant::DB_TABLE_PLATFORM, Constant::PLATFORM_AMAZON);
        switch ($platform) {
            case Constant::PLATFORM_SHOPIFY:
                if ($storeId == 1) {
                    $where = [
                        Constant::DB_TABLE_STORE_ID => $storeId,
                        'group' => $group,
                        Constant::DB_TABLE_EXT_ID => $extId,
                        Constant::DB_TABLE_EXT_TYPE => $extType,
                    ];
                }

                $orderWarrantEmail = explode(',', data_get($orderEmailConfig, 'shopify_email_data', $toEmail));
                if ('production' != config('app.env', 'production') && !in_array($toEmail, $orderWarrantEmail)) {//如果是非正式环境，延保邮件只发給配置指定的邮箱
                    $retult[Constant::RESPONSE_CODE_KEY] = 39002;
                    $retult[Constant::RESPONSE_MSG_KEY] = 'No Send Email.';

                    //解除任务
                    $actionData = [
                        Constant::SERVICE_KEY => '',
                        Constant::METHOD_KEY => 'forget',
                        Constant::PARAMETERS_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
                    ];
                    static::handleEmailLimit($orderId, $orderData->order_status, $actionData);

                    return $retult;
                }
                break;

            case Constant::PLATFORM_AMAZON:
                if (in_array($storeId, [2, 3])) {
                    $where = [
                        Constant::DB_TABLE_STORE_ID => $storeId,
                        'group' => $group,
                        Constant::TO_EMAIL => $toEmail,
                        Constant::DB_TABLE_EXT_ID => $extId,
                        Constant::DB_TABLE_EXT_TYPE => $extType,
                    ];
                }
                break;

            default:
                break;
        }

        $isExists = EmailService::exists($storeId, '', $where);
        if ($isExists) {//如果订单邮件已经发送，就提示
            $retult[Constant::RESPONSE_CODE_KEY] = 39003;
            $retult[Constant::RESPONSE_MSG_KEY] = 'Order Email exist.';
            return $retult;
        }

        $extService = static::getNamespaceClass();
        $extMethod = 'getEmailData'; //获取订单邮件数据
        $_extData = [
            Constant::ACT_ID => $actId,
            Constant::STORE_DICT_TYPE => 'order_email', //订单邮件配置 crm_dict_store.type
            Constant::ACTIVITY_CONFIG_TYPE => 'order_email', //订单邮件配置 crm_activity_configs.type
        ];
        $extParameters = [$storeId, $orderId, $actId, $_extData];

        //解除任务
        $actionData = [
            Constant::SERVICE_KEY => '',
            Constant::METHOD_KEY => 'forget',
            Constant::PARAMETERS_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
        ];
        $extData = Arr::collapse([
                    [
                        Constant::SERVICE_KEY => $extService,
                        Constant::METHOD_KEY => $extMethod,
                        Constant::PARAMETERS_KEY => $extParameters,
                        'callBack' => [
                            [
                                Constant::SERVICE_KEY => $extService,
                                Constant::METHOD_KEY => 'handleEmailLimit',
                                Constant::PARAMETERS_KEY => [$orderId, $orderData->order_status, $actionData],
                            ]
                        ],
                    ], $_extData
        ]); //扩展数据

//        $service = EmailService::getNamespaceClass();
//        $method = 'handle'; //邮件处理
//        $parameters = [$storeId, $toEmail, $group, $type, $remark, $extId, $extType, $extData];
//
//        $queueConnection = config('queue.mail_queue_connection');
//        $extData = [
//            'queueConnectionName' => $queueConnection,//Queue Connection
//        ];
//        FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters, null, $extData), '', config('queue.connections.' . $queueConnection . '.queue'));
//        return $retult;

        return EmailService::handle($storeId, $toEmail, $group, $type, $remark, $extId, $extType, $extData);

    }

    /**
     * 获取订单邮件数据
     * @param int $storeId 商城id
     * @param int $orderId 订单id
     * @param int $actId 活动id
     * @return string
     */
    public static function getOrderEmailData($storeId = 0, $orderId = 0, $actId = 0) {

        $select = [
            Constant::DB_TABLE_ORDER_NO,
            Constant::DB_TABLE_BRAND,
            Constant::DB_TABLE_CONTENT,
            Constant::DB_TABLE_COUNTRY,
            Constant::DB_TABLE_ACCOUNT,
            Constant::DB_TABLE_ORDER_STATUS,
            Constant::DB_TABLE_ORDER_TIME,
            Constant::DB_TABLE_CUSTOMER_PRIMARY,
            Constant::WARRANTY_DES,
            Constant::DB_TABLE_PLATFORM,
            Constant::DB_TABLE_ORDER_TIME . ' as ' . Constant::DB_TABLE_ORDER_AT,
            Constant::WARRANTY_AT,
        ];

        $where = [
            Constant::DB_TABLE_PRIMARY => $orderId
        ];

        $field = 'customer_info.first_name{or}customer_info.last_name{or}account';
        $storeId == 2 && $field = 'account'; // vt延保邮件模板，邮件称呼用邮箱
        $data = Constant::PARAMETER_ARRAY_DEFAULT;
        $dataType = Constant::PARAMETER_STRING_DEFAULT;
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = Constant::PARAMETER_STRING_DEFAULT;
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];


        $orderStatusData = DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //订单状态 -1:匹配中 0:未支付 1:已经支付 2:取消 默认:-1
        $_orderStatusData = clone $orderStatusData;
        $orderStatusData[1] = '';

        $handleData = [
            'account_name' => FunctionHelper::getExePlanHandleData(...$parameters), //会员名称
            Constant::DB_TABLE_CONTENT => FunctionHelper::getExePlanHandleData('json|' . Constant::DB_TABLE_CONTENT, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only), //订单产品数据
            'orderStatus' => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_ORDER_STATUS, $default, $orderStatusData, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only), //订单状态
            Constant::DB_TABLE_ORDER_TIME => FunctionHelper::getExePlanHandleData('orderStatus{or}' . Constant::DB_TABLE_ORDER_TIME, $default, $data, Constant::DB_EXECUTION_PLAN_DATATYPE_DATETIME, 'Y-m-d H:i', $time, $glue, $isAllowEmpty, $callback, $only), //订单时间
        ];

        $joinData = Constant::PARAMETER_ARRAY_DEFAULT;
        $customerHandleData = Constant::PARAMETER_ARRAY_DEFAULT;
        $customerSelect = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY,
            Constant::DB_TABLE_FIRST_NAME,
            Constant::DB_TABLE_LAST_NAME,
        ];
        $with = [
            'customer_info' => FunctionHelper::getExePlan(
                    0, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $customerSelect, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, null, null, false, Constant::PARAMETER_ARRAY_DEFAULT, false, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, $customerHandleData, Constant::PARAMETER_ARRAY_DEFAULT, Constant::HAS_ONE, true, [
                Constant::DB_TABLE_CUSTOMER_PRIMARY => Constant::DB_TABLE_CUSTOMER_PRIMARY,
            ]), //关联订单item
        ];
        $unset = ['customer_info', Constant::DB_TABLE_CONTENT];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), Constant::PARAMETER_STRING_DEFAULT, $select, $where, Constant::PARAMETER_ARRAY_DEFAULT, null, null, false, Constant::PARAMETER_ARRAY_DEFAULT, false, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $orderConfig = static::getOrderEmailConfig($storeId, '');
        $itemHandleDataCallback = [
            Constant::RESPONSE_WARRANTY => function($item) use($storeId, $orderConfig, $_orderStatusData) {
                $clientWarrantData = static::getClientWarrantyData($storeId, $item, [
                            Constant::DB_TABLE_ORDER_AT => Constant::DB_TABLE_ORDER_TIME,
                            'orderConfig' => $orderConfig,
                            'orderStatusData' => $_orderStatusData,
                ]);
                return data_get($clientWarrantData, Constant::RESPONSE_WARRANTY, '');
            },
            Constant::DB_TABLE_CONTENT . '.items.*.warranty' => function($item) {
                $handle = FunctionHelper::getExePlanHandleData(Constant::RESPONSE_WARRANTY);
                return FunctionHelper::handleData($item, $handle);
            },
            'items' => function($item) {
                //订单产品数据
                $handle = FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_CONTENT . '.items', Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, Constant::PARAMETER_ARRAY_DEFAULT, [
                            "quantity_ordered",
                            "amount",
                            "currency_code",
                            "img",
                            Constant::RESPONSE_WARRANTY,
                            "asin",
                            "sku",
                ]);
                return FunctionHelper::handleData($item, $handle);
            },
        ];

        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
        ];

        $dataStructure = 'one';
        $flatten = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);
    }

    /**
     * 获取邮件数据
     * @param int $storeId 商城id
     * @param int $orderId 订单id
     * @param int $actId 活动id
     * @return string
     */
    public static function getEmailData($storeId = 0, $orderId = 0, $actId = 0, $extData = []) {
        $emailData = [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => '',
            Constant::DB_EXECUTION_PLAN_STOREID => $storeId, //商城id
            Constant::ACT_ID => $actId, //活动id
            Constant::DB_TABLE_CONTENT => '', //邮件内容
            'subject' => '', //邮件主题
            Constant::DB_TABLE_COUNTRY => '', //邮件国家
                //'emailView' => 'emails.coupon.default',
        ];

        if (empty($orderId)) {
            $emailData[Constant::RESPONSE_CODE_KEY] = 39000;
            $emailData[Constant::RESPONSE_MSG_KEY] = 'orderId is required.';
            return $emailData;
        }

        $orderData = static::getOrderEmailData($storeId, $orderId, $actId);
        if (empty($orderData)) {
            $emailData[Constant::RESPONSE_CODE_KEY] = 39001;
            $emailData[Constant::RESPONSE_MSG_KEY] = 'Order does not exist.';
            return $emailData;
        }

        $orderno = data_get($orderData, Constant::DB_TABLE_ORDER_NO, '');
        $brand = data_get($orderData, Constant::DB_TABLE_BRAND, '');
        $country = strtoupper(data_get($orderData, Constant::DB_TABLE_COUNTRY, ''));
        $orderStatus = data_get($orderData, Constant::DB_TABLE_ORDER_STATUS, '');
        $accountName = data_get($orderData, 'account_name', '');
        $sku = data_get($orderData, 'items.0.sku', '');

        $countryMap = data_get(CouponService::$countryMap, $storeId, Constant::PARAMETER_ARRAY_DEFAULT);
        $emailViewCountry = data_get($countryMap, $country, data_get($countryMap, 'OTHER', 'US'));

        if ($storeId != 5) {
            $emailViewCountry = 'all';
        }

        $emailPlatform = '';
        $platform = data_get($orderData, Constant::DB_TABLE_PLATFORM, Constant::PLATFORM_AMAZON);
        switch ($platform) {
            case Constant::PLATFORM_SHOPIFY:
                $emailPlatform = $platform . '_';

                //订单状态是：Paid，Pending，Authorized，Partially_paid，Partially_refunded  触发延保成功邮件通知
                //订单状态是：Voided，Refunded 触发延保失败通知邮件
                //订单状态 -1:Matching 0:Pending 1:Shipped 2:Canceled 3:Failure 4:partially_refunded 5:partially_paid 6:authorized
                $showStatus = [
                    -1 => 1,
                    0 => 1,
                    1 => 1,
                    2 => 2,
                    3 => 2,
                    4 => 1,
                    5 => 1,
                    6 => 1,
                ];
                $orderStatus = data_get($showStatus, $orderStatus, $orderStatus);

                if ($storeId == 1 && data_get($orderData, Constant::DB_TABLE_ORDER_AT, '') <= '2020-07-23 19:14:09') {
                    data_set($emailData, 'isSendEmail', false);
                }

                break;

            default:
                $emailPlatform = '';

                $langCountries = DictStoreService::getByTypeAndKey($storeId, 'lang', 'country', true);
                if (!empty($langCountries)) {
                    $langCountries = explode(',', $langCountries);
                    if (!empty($langCountries)) {
                        $_orderData = OrderService::getOrderDataNew($orderno, '', Constant::PLATFORM_SERVICE_AMAZON, $storeId);
                        $orderCountry = strtoupper(data_get($_orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT));
                        if (in_array(strtoupper($orderCountry), $langCountries)) {
                            $emailViewCountry = $orderCountry;
                        }
                    }
                }

                break;
        }

        $orderEmailConfig = static::getOrderConfig($storeId, strtolower($emailViewCountry)); //
        $orderEmailView = data_get($orderEmailConfig, $emailPlatform . 'email_view_' . $orderStatus, '');
        if (empty($orderEmailView)) {
            $emailData[Constant::RESPONSE_CODE_KEY] = 39004;
            $emailData[Constant::RESPONSE_MSG_KEY] = 'Order Email View does not exist.';
            return $emailData;
        }

        $env = config('app.env', 'production');
        $env = $env != 'production' ? ('-' . $env) : '';
        $subject = data_get($orderEmailConfig, $emailPlatform . 'email_subject_' . $orderStatus, '');
        $replacePairs = [
            '#' => '#' . $orderno . $env,
        ];
        if (in_array($storeId, [1,3])) {
            $replacePairs = [
                '#' => '#' . $orderno . "[$sku]" . $env,
            ];
        }
        $subject = strtr($subject, $replacePairs);
        data_set($emailData, 'subject', $subject);

        switch ($orderStatus) {
            case -1:
            case 0:
            case 2:
                $amazonHostData = DictService::getListByType('amazon_host', Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);
                $replacePairs = [
                    '{{$orderno}}' => $orderno,
                    '{{$site}}' => data_get($amazonHostData, $country, data_get($amazonHostData, 'US', '')),
                    '{{$brand}}' => $brand,
                    '{{$account_name}}' => $accountName,
                    '{{$subject}}' => $subject,
                ];
                $content = strtr($orderEmailView, $replacePairs);
                break;

            case 1:

                if ($storeId == 5) {
                    $content = view($orderEmailView, $orderData)->render();
                } else {

                    $customerId = data_get($orderData, Constant::DB_TABLE_CUSTOMER_PRIMARY, -1);
                    $creditWhere = [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                        Constant::DB_TABLE_ADD_TYPE => 1,
                        Constant::DB_TABLE_ACTION => Constant::ORDER_BIND,
                        Constant::DB_TABLE_EXT_ID => $orderId,
                        Constant::DB_TABLE_EXT_TYPE => Constant::CUSTOMER_ORDER,
                    ];
                    $credit = CreditService::exists($storeId, $creditWhere, true, [Constant::DB_TABLE_VALUE]);

                    $replacePairs = [
                        '{{$account_name}}' => $accountName,
                        '{{$orderno}}' => $orderno,
                        '{{$order_time}}' => data_get($orderData, Constant::DB_TABLE_ORDER_TIME, ''),
                        '{{$warranty}}' => data_get($orderData, Constant::RESPONSE_WARRANTY, ''),
                        '{{$warranty_des}}' => data_get($orderData, Constant::WARRANTY_DES, ''),
                        '{{$points}}' => data_get($credit, Constant::DB_TABLE_VALUE, 0),
                        '{{$subject}}' => $subject,
                    ];
                    $content = strtr($orderEmailView, $replacePairs);
                }

                break;

            default:
                $emailData[Constant::RESPONSE_CODE_KEY] = 39005;
                $emailData[Constant::RESPONSE_MSG_KEY] = 'Order Status does not exist --> ' . $orderStatus;
                return $emailData;
                break;
        }

        data_set($emailData, Constant::DB_TABLE_CONTENT, $content);
        data_set($emailData, Constant::DB_TABLE_COUNTRY, $country);

        if ($actId) {

            $type = data_get($extData, Constant::ACTIVITY_CONFIG_TYPE, Constant::DB_TABLE_EMAIL);

            $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, $type, ['replyto_address', 'replyto_name']);

            $address = data_get($activityConfigData, $type . '_replyto_address.value', null);
            $name = data_get($activityConfigData, $type . '_replyto_name.value', null);
            if ($address) {
                data_set($emailData, 'replyTo.address', $address);
            }

            if ($name) {
                data_set($emailData, 'replyTo.name', $name);
            }
        }

        return $emailData;
    }

    /**
     * 获取db query
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getQuery($where = Constant::PARAMETER_ARRAY_DEFAULT, $storeId = 0, $country = '') {
        return static::getModel($storeId, $country)
                        ->from(Constant::CUSTOMER_ORDER . ' as co')
                        ->buildWhere($where);
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @return array
     */
    public static function getPublicData($params, $order = Constant::PARAMETER_ARRAY_DEFAULT) {

        $_where = Constant::PARAMETER_ARRAY_DEFAULT;
        $where = Constant::PARAMETER_ARRAY_DEFAULT;
        $customizeWhere = Constant::PARAMETER_ARRAY_DEFAULT;

        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0); //商城id

        $params[Constant::DB_TABLE_STORE_ID] = $params[Constant::DB_TABLE_STORE_ID] ?? '';
        $params[Constant::DB_TABLE_ACCOUNT] = $params[Constant::DB_TABLE_ACCOUNT] ?? '';
        $params[Constant::START_TIME] = $params[Constant::START_TIME] ?? '';
        $params[Constant::DB_TABLE_END_TIME] = $params[Constant::DB_TABLE_END_TIME] ?? '';

        $params[Constant::DB_TABLE_ORDER_NO] = $params[Constant::DB_TABLE_ORDER_NO] ?? '';
        $orderCountry = data_get($params, 'order_country', ''); //订单国家

        $params[Constant::DB_TABLE_TYPE] = $params[Constant::DB_TABLE_TYPE] ?? '';
        $params[Constant::DB_TABLE_CUSTOMER_PRIMARY] = $params[Constant::DB_TABLE_CUSTOMER_PRIMARY] ?? '';

        $params[Constant::DB_TABLE_ORDER_STATUS] = $params[Constant::DB_TABLE_ORDER_STATUS] ?? ''; //订单评论列表需要订单状态匹配查询
        $params[Constant::REVIEW_STATUS] = $params[Constant::REVIEW_STATUS] ?? ''; //订单评论列表需要审核状态匹配查询
        $params['review_' . Constant::START_TIME] = $params['review_' . Constant::START_TIME] ?? ''; //订单评论填写时间筛选开始时间
        $params['review_' . Constant::DB_TABLE_END_TIME] = $params['review_' . Constant::DB_TABLE_END_TIME] ?? ''; //订单评论填写时间筛选结束时间

        $params[Constant::DB_TABLE_PLATFORM] = $params[Constant::DB_TABLE_PLATFORM] ?? ''; //平台来演

        $actId = data_get($params, Constant::DB_TABLE_ACT_ID, 0); //活动id

        $orderno = data_get($params, Constant::DB_TABLE_ORDER_NO); //订单号
        if ($orderno) {
            $_where['co' . Constant::LINKER . Constant::DB_TABLE_ORDER_NO] = $orderno;
        }

        if ($params[Constant::DB_TABLE_PLATFORM]) {//平台来源
            $where[] = ['co.platform', '=', $params[Constant::DB_TABLE_PLATFORM]];
        }

//        if ($params[Constant::DB_TABLE_ACCOUNT]) {//账号
//            $where[] = ['co.account', '=', $params[Constant::DB_TABLE_ACCOUNT]];
//        }

        if ($params[Constant::DB_TABLE_STORE_ID]) {//商城ID
            $where[] = ['co' . Constant::LINKER . Constant::DB_TABLE_STORE_ID, '=', $params[Constant::DB_TABLE_STORE_ID]];
        }

        if ($params[Constant::DB_TABLE_TYPE]) {//订单类型
            $where[] = ['co.type', '=', $params[Constant::DB_TABLE_TYPE]];
        }

        if ($params[Constant::DB_TABLE_CUSTOMER_PRIMARY]) {//会员id
            $where[] = ['co' . Constant::LINKER . Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', $params[Constant::DB_TABLE_CUSTOMER_PRIMARY]];
        }

        if ($params[Constant::START_TIME]) {//开始时间
            $where[] = ['co' . Constant::LINKER . Constant::DB_TABLE_OLD_CREATED_AT, '>=', $params[Constant::START_TIME]];
        }

        if ($params[Constant::DB_TABLE_END_TIME]) {//结束时间
            $where[] = ['co' . Constant::LINKER . Constant::DB_TABLE_OLD_CREATED_AT, '<=', $params[Constant::DB_TABLE_END_TIME]];
        }

        if (strlen($params[Constant::DB_TABLE_ORDER_STATUS])) {//订单状态
            $where[] = ['co' . Constant::LINKER . Constant::DB_TABLE_ORDER_STATUS, '=', $params[Constant::DB_TABLE_ORDER_STATUS]];
        }

        if (strlen($params[Constant::REVIEW_STATUS])) {//订单评论审核状态
            $where[] = ['co.review_status', '=', $params[Constant::REVIEW_STATUS]];
        }

        if ($params['review_' . Constant::START_TIME]) {//评论填写筛选开始时间
            $where[] = ['co.review_time', '>=', $params['review_' . Constant::START_TIME]];
        }

        if ($params['review_' . Constant::DB_TABLE_END_TIME]) {//评论填写筛选结束时间
            $where[] = ['co.review_time', '<=', $params['review_' . Constant::DB_TABLE_END_TIME]];
        }

        if ($actId) {
            $where[] = ['co.act_id', '=', $actId];
        }

        $addWhere = data_get($params, 'addWhere', Constant::PARAMETER_ARRAY_DEFAULT);
        $where = Arr::collapse([$where, $addWhere]);

        if (data_get($params, Constant::DB_TABLE_PRIMARY, 0)) {
            $_where['co' . Constant::LINKER . Constant::DB_TABLE_PRIMARY] = $params[Constant::DB_TABLE_PRIMARY];
        }

        if ($where) {
            $_where[] = $where;
        }

        if ($params[Constant::DB_TABLE_ACCOUNT]) {//账号
            $_where['co.account'] = $params[Constant::DB_TABLE_ACCOUNT];
        }

        if ($orderCountry) {//国家简写
            $orderWhereFields = [
                [
                    'field' => 'po' . Constant::LINKER . Constant::DB_TABLE_COUNTRY,
                    Constant::DB_TABLE_VALUE => $orderCountry,
                ]
            ];
            $whereColumns = [
                [
                    'foreignKey' => Constant::DB_TABLE_UNIQUE_ID,
                    'localKey' => 'co.' . Constant::DB_TABLE_ORDER_UNIQUE_ID,
                ]
            ];
            $customizeWhere = Arr::collapse([$customizeWhere, OrderService::buildWhereExists($storeId, $orderWhereFields, $whereColumns)]);
        }

        $name = data_get($params, Constant::DB_TABLE_NAME); //用户名
        if ($name) {
            $customerInfoWhereFields = [
                [
                    Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                    Constant::PARAMETERS_KEY => [function ($query) use($name) {
                            if (is_array($name)) {
                                foreach ($name as $value) {
                                    $query->orWhere('ci.' . Constant::DB_TABLE_FIRST_NAME, 'like', '%' . $value . '%')
                                        ->orWhere('ci.' . Constant::DB_TABLE_LAST_NAME, 'like', '%' . $value . '%');
                                }
                            } else {
                                $query->where('ci.' . Constant::DB_TABLE_FIRST_NAME, 'like', '%' . $name . '%')
                                    ->orWhere('ci.' . Constant::DB_TABLE_LAST_NAME, 'like', '%' . $name . '%');
                            }
                        }],
                ]
            ];

            $whereColumns = [
                [
                    'foreignKey' => Constant::DB_TABLE_CUSTOMER_PRIMARY,
                    'localKey' => 'co.' . Constant::DB_TABLE_CUSTOMER_PRIMARY,
                ]
            ];
            $customizeWhere = Arr::collapse([$customizeWhere, CustomerInfoService::buildWhereExists($storeId, $customerInfoWhereFields, $whereColumns)]);
        }

        $sku = $params['sku'] ?? '';
        if ($sku) {
            $whereFields[] = [
                'field' => 'sku',
                Constant::DB_TABLE_VALUE => $sku,
            ];
        }

        $asin = $params['asin'] ?? '';
        if ($asin) {
            $whereFields[] = [
                'field' => 'asin',
                Constant::DB_TABLE_VALUE => $asin,
            ];
        }

        if (!empty($whereFields)) {
            $whereColumns = [
                [
                    'foreignKey' => Constant::DB_TABLE_ORDER_UNIQUE_ID,
                    'localKey' => 'co.' . Constant::DB_TABLE_ORDER_UNIQUE_ID,
                ]
            ];
            $customizeWhere = Arr::collapse([$customizeWhere, OrderItemService::buildCustomizeWhere($storeId, $whereFields, $whereColumns)]);
        }

        $isLeftCategory = false;
        $one_category_code = $params['one_category_code'] ?? '';
        if ($one_category_code) {
            $whereCategoryFields[] = [
                'field' => 'ppc.one_category_code',
                Constant::DB_TABLE_VALUE => $one_category_code,
            ];
            $isLeftCategory = true;
        }

        $two_category_code = $params['two_category_code'] ?? '';
        if ($two_category_code) {
            $whereCategoryFields[] = [
                'field' => 'ppc.two_category_code',
                Constant::DB_TABLE_VALUE => $two_category_code,
            ];
            $isLeftCategory = true;
        }

        $three_category_code = $params['three_category_code'] ?? '';
        if ($three_category_code) {
            $whereCategoryFields[] = [
                'field' => 'ppc.three_category_code',
                Constant::DB_TABLE_VALUE => $three_category_code,
            ];
            $isLeftCategory = true;
        }

        if (!empty($whereCategoryFields)) {
            $whereColumns = [
                [
                    'foreignKey' => Constant::DB_TABLE_ORDER_UNIQUE_ID,
                    'localKey' => 'co.' . Constant::DB_TABLE_ORDER_UNIQUE_ID,
                ]
            ];
            $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0);
            $customizeWhere = Arr::collapse([$customizeWhere, OrderItemService::buildCustomizeWhere($storeId, $whereCategoryFields, $whereColumns, ['isLeftCategory' => $isLeftCategory])]);
        }

        if ($customizeWhere) {
            $_where['{customizeWhere}'] = $customizeWhere;
        }

        $order = $order ? $order : ['co' . Constant::LINKER . Constant::DB_TABLE_OLD_CREATED_AT, 'desc'];
        return Arr::collapse([parent::getPublicData($params, $order), [
                        Constant::DB_EXECUTION_PLAN_WHERE => $_where,
        ]]);
    }

    /**
     * 获取订单数据
     * @param array $params 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认:true
     * @param boolean $isPage  是否分页 true:是 false:否 默认:true
     * @param array $select  查询字段
     * @param boolean $isRaw 是否原始 select true:是 false:否 默认:false
     * @param boolean $isGetQuery 是否获取 query
     * @param boolean $isOnlyGetCount 是否仅仅获取总记录数
     * @return array|\Hyperf\Database\Model\Builder 列表数据|Builder
     */
    public static function getData($data, $toArray = true, $isPage = true, $select = Constant::PARAMETER_ARRAY_DEFAULT, $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {
        $_data = static::getPublicData($data);

        $where = data_get($data, Constant::DB_EXECUTION_PLAN_WHERE, data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null));
        $order = data_get($_data, Constant::DB_EXECUTION_PLAN_ORDER_BY, data_get($_data, Constant::ORDER, Constant::PARAMETER_ARRAY_DEFAULT));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, Constant::PARAMETER_ARRAY_DEFAULT);
        $limit = data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10);
        $offset = data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0);

        $storeId = data_get($data, Constant::DB_TABLE_STORE_ID, 0); //商城id
        $select = $select ? $select : ['co.*'];

        $orderStatusData = DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //订单状态 -1:匹配中 0:未支付 1:已经支付 2:取消 默认:-1
        $orderStatusData[1] = '';
        $orderConfig = static::getOrderEmailConfig($storeId, '');

        $field = Constant::DB_TABLE_ORDER_STATUS;
        $data = $orderStatusData;
        $dataType = Constant::PARAMETER_STRING_DEFAULT;
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = Constant::PARAMETER_STRING_DEFAULT;
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];

        $handleData = [
            'orderStatus' => FunctionHelper::getExePlanHandleData(...$parameters), //订单状态
            Constant::DB_TABLE_ORDER_TIME => FunctionHelper::getExePlanHandleData('orderStatus{or}order_time', $default, Constant::PARAMETER_ARRAY_DEFAULT, Constant::DB_EXECUTION_PLAN_DATATYPE_DATETIME, 'Y-m-d H:i', $time, $glue, $isAllowEmpty, $callback, $only), //订单时间
            Constant::DB_TABLE_CONTENT => FunctionHelper::getExePlanHandleData('json|content', Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only), //订单产品数据
        ];

        $joinData = [];
        $with = Constant::PARAMETER_ARRAY_DEFAULT;
        $unset = [Constant::DB_TABLE_CONTENT];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), Constant::CUSTOMER_ORDER . ' as co', $select, $where, [$order], $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $itemHandleDataCallback = [
            Constant::RESPONSE_WARRANTY => function($item) use($storeId) {//$orderConfig
                $clientWarrantData = static::getClientWarrantyData($storeId, $item, [Constant::DB_TABLE_ORDER_AT => Constant::DB_TABLE_ORDER_TIME]);
                return data_get($clientWarrantData, Constant::RESPONSE_WARRANTY, '');
            },
            "content.items.*.warranty" => function($item) {
                //延保时间
                $handle = FunctionHelper::getExePlanHandleData(Constant::RESPONSE_WARRANTY, Constant::PARAMETER_ARRAY_DEFAULT);
                return FunctionHelper::handleData($item, $handle);
            },
            "items" => function($item) {
                //订单产品数据
                $handle = FunctionHelper::getExePlanHandleData('content.items', Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, Constant::PARAMETER_ARRAY_DEFAULT, [
                            "quantity_ordered",
                            "amount",
                            "currency_code",
                            "img",
                            Constant::RESPONSE_WARRANTY,
                ]);
                return FunctionHelper::handleData($item, $handle);
            },
            Constant::DB_TABLE_ORDER_STATUS => function($item) use($storeId) {
                $clientWarrantData = static::getClientWarrantyData($storeId, $item, [Constant::DB_TABLE_ORDER_AT => Constant::DB_TABLE_ORDER_TIME]);
                return data_get($clientWarrantData, Constant::DB_TABLE_ORDER_STATUS, -1);
            }
        ];
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
        ];

        $dataStructure = 'list';
        $flatten = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * 后台订单列表
     * @param array $data 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认：true
     * @param boolean $isPage  是否分页 true:是 false:否 默认：true
     * @param array $select 查询字段
     * @param boolean $isRaw 是否原始查询 true:是 false:否 默认：false
     * @param boolean $isGetQuery 是否获取 query
     * @return array|objects
     */
    public static function getListData($data, $toArray = true, $isPage = true, $select = Constant::PARAMETER_ARRAY_DEFAULT, $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $params = $data;
        $storeId = data_get($data, Constant::DB_TABLE_STORE_ID, 0); //商城id

        if (isset($data[Constant::DB_TABLE_STORE_ID])) {
            unset($data[Constant::DB_TABLE_STORE_ID]);
        }

        $_data = static::getPublicData($data);
        $isExportData = data_get($data, 'srcParameters.0.is_export_data', false); //是否是数据导出

        $where = data_get($data, Constant::DB_EXECUTION_PLAN_WHERE, data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null));
        $order = data_get($params, Constant::DB_EXECUTION_PLAN_ORDER_BY, data_get($_data, Constant::ORDER, Constant::PARAMETER_ARRAY_DEFAULT));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, Constant::PARAMETER_ARRAY_DEFAULT);
        $limit = data_get($params, Constant::DB_EXECUTION_PLAN_LIMIT, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0));

        $select = $select ? $select : ['co.*'];

        $joinData = [];

        $orderSelect = [
            Constant::DB_TABLE_UNIQUE_ID,
            Constant::DB_TABLE_COUNTRY, //订单国家
        ];
        $orderWhere = [
            Constant::DB_TABLE_STATUS => 1,
        ];
        $orderOrders = [];

        $itemSelect = [
            Constant::DB_TABLE_ORDER_UNIQUE_ID,
            Constant::DB_TABLE_SKU, //产品店铺sku
            Constant::DB_TABLE_ASIN, //asin
            Constant::FILE_TITLE
        ];
        $itemOrders = [[Constant::DB_TABLE_AMOUNT, Constant::DB_EXECUTION_PLAN_ORDER_DESC]];

        $customerInfoSelect = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY,
            Constant::DB_TABLE_FIRST_NAME,
            Constant::DB_TABLE_LAST_NAME,
        ];
        $customerInfoWhere = [
            Constant::DB_TABLE_STATUS => 1,
        ];
        $customerInfoOrders = [];
        $with = [
            'order' => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $orderSelect, $orderWhere, $orderOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, 'hasOne', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联订单item
            'items' => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $itemSelect, [], $itemOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联订单item
            'customer_info' => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $customerInfoSelect, $customerInfoWhere, $customerInfoOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, 'hasOne', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联用户基本信息
        ];
        $unset = ['order', 'items', 'customer_info'];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), Constant::CUSTOMER_ORDER . ' as co', $select, $where, [$order], $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, [], $unset);

        if (data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            $dbExecutionPlan = [
                Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            ];
        } else {
            $auditStatusData = data_get($data, 'srcParameters.0.auditStatusData', DictService::getListByType('audit_status', Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE)); //审核状态 -1:未提交审核 0:未审核 1:已通过 2:未通过 3:其他
            $orderStatusData = data_get($data, 'srcParameters.0.orderStatusData', DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE)); //订单状态 -1:匹配中 0:未支付 1:已经支付 2:取消 默认:-1
            $orderConfig = data_get($data, 'srcParameters.0.orderConfig', DictStoreService::getByTypeAndKey($storeId, Constant::ORDER, [Constant::CONFIG_KEY_WARRANTY_DATE_FORMAT, Constant::WARRANTY_DATE]));

            $field = 'json|content';
            $data = Constant::PARAMETER_ARRAY_DEFAULT;
            $dataType = Constant::PARAMETER_STRING_DEFAULT;
            $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
            $time = Constant::PARAMETER_STRING_DEFAULT;
            $glue = Constant::PARAMETER_STRING_DEFAULT;
            $isAllowEmpty = true;
            $default = Constant::PARAMETER_ARRAY_DEFAULT;
            $callback = Constant::PARAMETER_ARRAY_DEFAULT;
            $only = Constant::PARAMETER_ARRAY_DEFAULT;

            $handleData = [
                'product_title' => FunctionHelper::getExePlanHandleData('items.0.' . Constant::FILE_TITLE, Constant::PARAMETER_STRING_DEFAULT, $data, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
                Constant::DB_TABLE_SKU => FunctionHelper::getExePlanHandleData('items.0.' . Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT, $data, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
                Constant::DB_TABLE_ASIN => FunctionHelper::getExePlanHandleData('items.0.' . Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT, $data, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
                Constant::REVIEW_STATUS => FunctionHelper::getExePlanHandleData(Constant::REVIEW_STATUS, data_get($auditStatusData, '-1', ''), $auditStatusData, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only), //审核状态
                Constant::DB_TABLE_COUNTRY => FunctionHelper::getExePlanHandleData('order' . Constant::LINKER . Constant::DB_TABLE_COUNTRY), //审核状态
                Constant::DB_TABLE_FIRST_NAME => FunctionHelper::getExePlanHandleData('customer_info' . Constant::LINKER . Constant::DB_TABLE_FIRST_NAME), //审核状态
                Constant::DB_TABLE_LAST_NAME => FunctionHelper::getExePlanHandleData('customer_info' . Constant::LINKER . Constant::DB_TABLE_LAST_NAME), //审核状态
            ];

            $exePlan[Constant::DB_EXECUTION_PLAN_HANDLE_DATA] = $handleData;

            $itemHandleDataCallback = [];
            if ($isExportData) {
                $warrantyStatusData = clone $orderStatusData;
                $itemHandleDataCallback = [
                    Constant::RESPONSE_WARRANTY => function($item) use($storeId, $orderConfig, $warrantyStatusData) {//延保时间
                        $extData = [
                            Constant::DB_TABLE_ORDER_AT => Constant::DB_TABLE_ORDER_TIME,
                            'orderConfig' => $orderConfig,
                            'orderStatusData' => $warrantyStatusData,
                        ];

                        $clientWarrantData = static::getClientWarrantyData($storeId, $item, $extData);
                        return data_get($clientWarrantData, Constant::RESPONSE_WARRANTY, '');
                    }
                ];
            }

            $itemHandleDataCallback[Constant::DB_TABLE_ORDER_STATUS] = function($item) use($orderStatusData) {//订单状态
                $handle = FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_ORDER_STATUS, data_get($orderStatusData, '-1', ''), $orderStatusData);
                return FunctionHelper::handleData($item, $handle);
            };

            $dbExecutionPlan = [
                Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
                Constant::DB_EXECUTION_PLAN_WITH => $with,
                Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
            ];
        }

        $dataStructure = 'list';
        $flatten = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * 订单详情
     * @param array $data 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认：true
     * @param boolean $isPage  是否分页 true:是 false:否 默认：true
     * @param array $select 查询字段
     * @param boolean $isRaw 是否原始查询 true:是 false:否 默认：false
     * @param boolean $isGetQuery 是否获取 query
     * @return array|objects
     */
    public static function getDetails($storeId = 0, $orderno = '') {

        $where = [
            'co' . Constant::LINKER . Constant::DB_TABLE_STORE_ID => $storeId,
            'co' . Constant::LINKER . Constant::DB_TABLE_ORDER_NO => $orderno,
        ];
        $select = [
            'co' . Constant::LINKER . Constant::DB_TABLE_ORDER_NO,
            'co.amount',
            'co' . Constant::LINKER . Constant::DB_TABLE_COUNTRY,
            'co.account',
            'co' . Constant::LINKER . Constant::DB_TABLE_ORDER_STATUS,
            'co.content',
            'co' . Constant::LINKER . Constant::DB_TABLE_OLD_CREATED_AT,
            'co' . Constant::LINKER . Constant::DB_TABLE_ORDER_TIME,
        ];


        $orderStatusData = DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //订单状态 -1:匹配中 0:未支付 1:已经支付 2:取消 默认:-1

        $field = 'json|content.items.0.title';
        $data = Constant::PARAMETER_ARRAY_DEFAULT;
        $dataType = Constant::DB_EXECUTION_PLAN_DATATYPE_STRING;
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = Constant::PARAMETER_STRING_DEFAULT;
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];

        $handleData = [
            'product_title' => FunctionHelper::getExePlanHandleData(...$parameters), //产品名称
            'sku' => FunctionHelper::getExePlanHandleData('json|content.items.*.seller_sku', $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only), //sku
            Constant::DB_TABLE_ORDER_STATUS => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_ORDER_STATUS, data_get($orderStatusData, '-1', ''), $orderStatusData, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only), //订单状态
        ];

        $joinData = [];
        $with = Constant::PARAMETER_ARRAY_DEFAULT;
        $unset = [Constant::DB_TABLE_CONTENT];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), Constant::CUSTOMER_ORDER . ' as co', $select, $where, [], null, null, false, [], false, $joinData, $with, $handleData, $unset);

        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
        ];
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, false, false, 'one');
    }

    /**
     * 前端列表
     * @param array $data 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认：true
     * @param boolean $isPage  是否分页 true:是 false:否 默认：true
     * @param array $select 查询字段
     * @param boolean $isRaw 是否原始查询 true:是 false:否 默认：false
     * @return array|objects
     */
    public static function getShowList($data, $toArray = true, $isPage = true, $select = Constant::PARAMETER_ARRAY_DEFAULT, $isRaw = false) {

        $storeId = data_get($data, Constant::DB_TABLE_STORE_ID, 0);

        $where = [
            ['co' . Constant::LINKER . Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', data_get($data, Constant::DB_TABLE_CUSTOMER_PRIMARY, 0)],
            ['co' . Constant::LINKER . Constant::DB_TABLE_STORE_ID, '=', $storeId],
        ];

        switch ($storeId) {
            case 1://mpow只要与销参匹配成功的订单数据
            case 5://ikich只要 Shipped 状态的订单数据
                $where[] = ['co' . Constant::LINKER . Constant::DB_TABLE_ORDER_STATUS, '=', 1]; //2020-01-13 12:00:00  改成只要  Shipped 状态的订单数据

                break;

            default:
                break;
        }

        $data[Constant::DB_EXECUTION_PLAN_WHERE] = [$where];
        $select = $select ? $select : [
            'co' . Constant::LINKER . Constant::DB_TABLE_ORDER_NO,
            'co.amount',
            'co.currency_code',
            'co.content',
            'co' . Constant::LINKER . Constant::DB_TABLE_ORDER_TIME,
            'co' . Constant::LINKER . Constant::DB_TABLE_ORDER_STATUS,
            'co' . Constant::LINKER . Constant::DB_TABLE_COUNTRY,
            'co.warranty_date',
            'co.warranty_des',
            'co' . Constant::LINKER . Constant::WARRANTY_AT,
        ];
        $data = static::getData($data, $toArray, $isPage, $select, $isRaw);

        return $data;
    }

    /**
     * 校验兑换产品
     * @param $products 产品数据
     * @param $items    兑换的产品数据
     * @param $customerId  会员id
     * @return array
     */
    public static function checkExchangeProduct($products, $items, $customerId) {
        $isValid = false;
        $data = ProductService::validExchangeProduct($products, $items, $customerId);
        if ($data['valid']) {
            $isValid = true; //检验成功
        }
        return [$data, $isValid];
    }

    /**
     * 积分兑换
     * 规则：
     * 1：添加订单
     * 2：更新会员积分，添加积分流水
     * 3：更新商品库存
     * @param $params
     * @return array
     */
    public static function exchange($params) {

        $retult = [Constant::RESPONSE_CODE_KEY => 0, Constant::RESPONSE_MSG_KEY => '', Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT];

        $params[Constant::DB_TABLE_TYPE] = $params[Constant::DB_TABLE_TYPE] ?? 'credit';
        $params[Constant::DB_TABLE_ACCOUNT] = $params[Constant::DB_TABLE_ACCOUNT] ?? '';
        $params[Constant::DB_TABLE_EXT_ID] = $params[Constant::DB_TABLE_PRIMARY] ?? ''; //产品id
        $params[Constant::DB_TABLE_EXT_TYPE] = 'Product';

        //1：添加订单
        $ret = static::addCustomerOrder($params); //添加订单
        if (empty($ret)) {
            $retult[Constant::RESPONSE_MSG_KEY] = 'Exchange failure';
            return $retult;
        }

        //2：更新会员积分，添加积分流水
        //$params[Constant::DB_TABLE_ORDER_NO] = $params[Constant::DB_TABLE_ORDER_NO] ?? '';
        $storeId = $params[Constant::DB_TABLE_STORE_ID];
        $params[Constant::DB_TABLE_ADD_TYPE] = 2;
        $params[Constant::DB_TABLE_ACTION] = 'creditExchange';
        $params['qty'] = $params['qty'] ?? 1;
        $params['sub_id'] = $params['qty'];
        $params[Constant::DB_TABLE_CONTENT] = json_encode($params);

        $product = ProductService::info($storeId, $params['store_product_id']);
        $params[Constant::DB_TABLE_VALUE] = $params['qty'] * $product['credit'];

        $creditRet = CreditService::handle($params);
        if ($creditRet[Constant::RESPONSE_CODE_KEY] != 1) {
            return $creditRet;
        }

        //3：更新商品库存
        ProductService::handle($storeId, [Constant::DB_TABLE_PRIMARY => $params[Constant::DB_TABLE_PRIMARY]], ['qty' => $params['qty']]);

        $retult[Constant::RESPONSE_CODE_KEY] = 1;
        return $retult;
    }

    /**
     * 积分兑换产品
     * @param array $products 产品数据
     * @param array $requestData
     * @param array $productIdQty 购买产品数据[[store_product_id=>qty],]: [[1521037410327=>1],]
     * @return boolean
     */
    public static function batchExchange($products, $requestData, $productIdQty) {

        $result = true;

        //DB::beginTransaction();
        try {

            foreach ($products as $k => $product) {
                $requestData[Constant::DB_TABLE_PRIMARY] = $product[Constant::DB_TABLE_PRIMARY];
                $requestData['store_product_id'] = $product['store_product_id'];
                $requestData['qty'] = $productIdQty[$product['store_product_id']];
                $ret = static::exchange($requestData);
            }
            //提交
            //DB::commit();
        } catch (\Exception $e) {
            // 出错回滚
            //DB::rollBack();
            $content = [
                'requestData' => $requestData,
                'err' => $e->getTraceAsString(),
            ];
            LogService::addSystemLog('error', Constant::ORDER, 'exchange', '', $content); //添加系统日志
            $result = false;
        }

        return $result;
    }

    /**
     * 会员积分显示
     * @param int $customerId 会员id
     * @return array|objects
     */
    public static function getCredit($customerId) {
        $customerCredit = CustomerInfo::select(['credit', 'total_credit'])->where(Constant::DB_TABLE_CUSTOMER_PRIMARY, $customerId)->first(); //查询info表获取会员积分和累计积分
        return [
            'credit' => data_get($customerCredit, 'credit', 0),
            'total_credit' => data_get($customerCredit, 'total_credit', 0),
        ];
    }

    /**
     * 前台订单评论链接列表展示信息
     * @param array $data 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认：true
     * @param boolean $isPage  是否分页 true:是 false:否 默认：true
     * @param array $select 查询字段
     * @param boolean $isRaw 是否原始查询 true:是 false:否 默认：false
     * @param boolean $isGetQuery 是否获取 query
     * @return array|objects
     */
    public static function getReviewlist($data, $toArray = true, $isPage = true, $select = Constant::PARAMETER_ARRAY_DEFAULT, $isRaw = false, $isGetQuery = false) {

        $storeId = data_get($data, Constant::DB_TABLE_STORE_ID, 0); //商城id
        $customerId = data_get($data, Constant::DB_TABLE_CUSTOMER_PRIMARY, 0);

        $_data = static::getPublicData($data);
        $order = $_data[Constant::ORDER];
        $pagination = $_data[Constant::DB_EXECUTION_PLAN_PAGINATION];
        $limit = $pagination[Constant::REQUEST_PAGE_SIZE];
        $offset = $pagination['offset'];

        $where = [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId];

        $select = $select ? $select : [
            Constant::DB_TABLE_PRIMARY,
            Constant::DB_TABLE_ORDER_NO,
            Constant::DB_TABLE_ORDER_STATUS,
            Constant::DB_TABLE_ORDER_TIME,
            Constant::REVIEW_STATUS,
            Constant::WARRANTY_AT,
            'review_credit',
        ];

        $orderStatusData = collect([
            -1 => "Waiting",
            0 => "Waiting",
            1 => "Pass",
            2 => "Failure",
            3 => "Failure",
        ]);

        $_orderStatusData = clone $orderStatusData;
        data_set($_orderStatusData, 1, '');

        $field = Constant::DB_TABLE_ORDER_STATUS;
        $data = $_orderStatusData;
        $dataType = Constant::PARAMETER_STRING_DEFAULT;
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = '';
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $parameters = [$field, data_get($data, '-1', ''), $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];

        $handleData = [
            'order_status_credit' => FunctionHelper::getExePlanHandleData(...$parameters),
        ];

        $joinData = [];
        $unset = ['order_status_credit'];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), '', $select, $where, [[Constant::DB_TABLE_PRIMARY, 'desc']], $limit, $offset, $isPage, $pagination, false, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $creditHandleData = [
            'credit.add_type' => FunctionHelper::getExePlanHandleData('credit.add_type', $default, [
                1 => '+',
                2 => '-'
            ]),
            'credit.credit_dest' => FunctionHelper::getExePlanHandleData('credit.add_type{connection}credit.value', data_get($orderStatusData, '-1', ''), Constant::PARAMETER_ARRAY_DEFAULT, 'string', '', '', '', false),
            'credit' => FunctionHelper::getExePlanHandleData('order_status_credit{or}credit.credit_dest', 0),
        ];
        $with = [
            'credit' => FunctionHelper::getExePlan($storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, [Constant::DB_TABLE_EXT_ID, Constant::DB_TABLE_VALUE, Constant::DB_TABLE_ADD_TYPE], [Constant::DB_TABLE_ACTION => Constant::ORDER_BIND], [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], $creditHandleData, Constant::PARAMETER_ARRAY_DEFAULT, Constant::HAS_ONE, true, [Constant::DB_TABLE_EXT_ID => Constant::DB_TABLE_PRIMARY]),
        ];

        $itemHandleDataCallback = [
            Constant::RESPONSE_WARRANTY => function($item) use($storeId) {//延保时间
                $clientWarrantData = static::getClientWarrantyData($storeId, $item, [Constant::DB_TABLE_ORDER_AT => Constant::DB_TABLE_ORDER_TIME]);
                return data_get($clientWarrantData, Constant::RESPONSE_WARRANTY, '');
            },
            Constant::DB_TABLE_ORDER_STATUS => function($item) use($orderStatusData) {//订单状态
                $handle = FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_ORDER_STATUS, data_get($orderStatusData, '-1', ''), $orderStatusData);
                return FunctionHelper::handleData($item, $handle);
            }
        ];
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
        ];

        $dataStructure = 'list';
        $flatten = false;
        $_data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);

        data_set($_data, 'credit', static::getCredit($customerId)); //获取会员积分与累计积分

        return $_data;
    }

    /**
     * 添加评论链接
     * @param string $store_id
     * @param string $order_id 订单id
     * @param string $review_link 评论链接
     * @return array|objects
     */
    public static function addReviewLink($store_id, $order_id, $customer_id, $review_link = '', $extData = Constant::PARAMETER_ARRAY_DEFAULT) {
        $where = Constant::PARAMETER_ARRAY_DEFAULT;
        if ($store_id) {
            $where[Constant::DB_TABLE_STORE_ID] = $store_id;
        }
        if ($order_id) {
            $where[Constant::DB_TABLE_PRIMARY] = $order_id;
        }
        if ($customer_id) {
            $where[Constant::DB_TABLE_CUSTOMER_PRIMARY] = $customer_id;
        }
        $review_link = trim($review_link); //格式化链接的空格
        $review_time = Carbon::now()->toDateTimeString();
        $review = [
            'review_link' => $review_link,
            'review_time' => data_get($extData, Constant::DB_TABLE_CREATED_AT, $review_time),
            Constant::REVIEW_STATUS => DB::raw("IF(review_status=-1, 0, review_status)"),
        ]; //提交成功，-1为初始 0为审核中 1为审核通过 2为审核失败 3为其他
        $data = static::getModel($store_id, '')->where($where)->update($review); //更新添加

        return $data;
    }

    /**
     * 审核评论链接
     * @param string $store_id
     * @param string $order_id 订单id
     * @param string $review_link 评论链接
     * @return array|objects
     */
    public static function addReviewcheck($store_id = 0, $order_id = 0, $review_status = 0, $review_credit = 0, $addType = 0, $action = '', $review_remark = '') {
        $rs = [Constant::RESPONSE_CODE_KEY => 1, Constant::RESPONSE_MSG_KEY => '', Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT];
        if (empty($order_id) || !is_array($order_id)) {//判断订单ID是否为空
            return $rs;
        }

        $orderModel = static::getModel($store_id, '');
        $reviewData = $orderModel->select([Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::DB_TABLE_PRIMARY])->whereIn(Constant::DB_TABLE_PRIMARY, $order_id)->get(); //根据订单ID查询对应的会员ID

        if (empty($reviewData)) {
            $rs[Constant::RESPONSE_CODE_KEY] = 0;
            $rs[Constant::RESPONSE_MSG_KEY] = '数据不存';
            return $rs;
        }

        $review = [
            Constant::REVIEW_STATUS => $review_status,
            'review_credit' => $review_credit,
            'review_remark' => $review_remark
        ]; //提交成功，-1为初始 0为审核中 1为审核通过 2为审核失败 3为其他

        $reviewUpdate = $orderModel->whereIn(Constant::DB_TABLE_PRIMARY, $order_id)->update($review); //更新添加
        if ($reviewUpdate) {
            foreach ($reviewData as $item) {
                $customerId[Constant::DB_TABLE_CUSTOMER_PRIMARY] = $item->customer_id;
                static::sendCheck($store_id, $customerId, $review_status, $review_credit); //发送邮件

                if ($review_status == 1) {//判断审核状态为通过时，发送成功邮件，同时发放积分流水
                    $expansionData = [
                        Constant::DB_TABLE_STORE_ID => $store_id,
                        'remark' => '订单评论审核通过加积分',
                    ];
                    $data = FunctionHelper::getHistoryData([
                                Constant::DB_TABLE_CUSTOMER_PRIMARY => $item->customer_id,
                                Constant::DB_TABLE_VALUE => $review_credit,
                                Constant::DB_TABLE_ADD_TYPE => $addType,
                                Constant::DB_TABLE_ACTION => $action,
                                Constant::DB_TABLE_EXT_ID => $item->id,
                                Constant::DB_TABLE_EXT_TYPE => Constant::CUSTOMER_ORDER,
                                    ], $expansionData);
                    CreditService::handle($data); //记录积分流水
                }
            }
        }
        return $rs;
    }

    /**
     * 发送审核邮件
     * @param $params
     * @return bool
     */
    public static function sendCheck($storeId = 0, $customer_id = Constant::PARAMETER_ARRAY_DEFAULT, $review_status = 0, $review_credit = 0, $group = 'review') {
        if ($review_status == 1) {//流水备注
            $remark = '评论审核成功';
        } elseif ($review_status == 2) {
            $remark = '评论审核失败';
        }
        if ($customer_id) {
            $query = CustomerInfo::from('customer_info as b')
                    ->leftJoin('customer as a', 'a.customer_id', '=', 'b.customer_id')
                    ->whereIn('b.customer_id', $customer_id)
                    ->select(['b.first_name', 'b.last_name', 'b.ip', 'b.country', 'a.account'])
                    ->get();

            foreach ($query as $item) {

                //发送审核邮件
                $requestData = [
                    Constant::DB_TABLE_STORE_ID => $storeId,
                    Constant::DB_TABLE_CUSTOMER_PRIMARY => $customer_id,
                    Constant::DB_TABLE_ACCOUNT => $item->account,
                    Constant::DB_TABLE_COUNTRY => $item->country,
                    'group' => $group,
                    Constant::DB_TABLE_FIRST_NAME => $item->first_name,
                    Constant::DB_TABLE_LAST_NAME => $item->last_name,
                    'ip' => $item->ip,
                    'remark' => $remark,
                    Constant::DB_TABLE_OLD_CREATED_AT => Carbon::now()->toDateTimeString(),
                ];
                $rs = EmailService::sendReviewEmail($storeId, $requestData, $review_status, $review_credit);
            }
            return $rs;
        }
    }

    /**
     * 前台订单评论链接列表展示信息（新的）
     * @param array $data 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认：true
     * @param boolean $isPage  是否分页 true:是 false:否 默认：true
     * @param array $select 查询字段
     * @param boolean $isRaw 是否原始查询 true:是 false:否 默认：false
     * @param boolean $isGetQuery 是否获取 query
     * @return array|objects
     */
    public static function newReviewlist($data, $toArray = true, $isPage = true, $select = Constant::PARAMETER_ARRAY_DEFAULT, $isRaw = false, $isGetQuery = false) {

        $storeId = data_get($data, Constant::DB_TABLE_STORE_ID, 0); //商城id
        $customerId = data_get($data, Constant::DB_TABLE_CUSTOMER_PRIMARY, 0);

        $credit = static::getCredit($customerId); //获取会员积分与累计积分

        $_data = static::getPublicData($data);
        $order = $_data[Constant::ORDER];
        $pagination = $_data[Constant::DB_EXECUTION_PLAN_PAGINATION];
        $limit = $pagination[Constant::REQUEST_PAGE_SIZE];
        $offset = $pagination['offset'];

        $where = [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId];
        $customerCount = true;
        if ($isPage) {
            $customerCount = static::getModel($storeId, '')->buildWhere($where)->count();
            $pagination[Constant::TOTAL] = $customerCount;
            $pagination[Constant::TOTAL_PAGE] = ceil($customerCount / $limit);
        }

        if (empty($customerCount)) {
            $query = null;
            return [
                Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
                'credit' => $credit, //获取会员积分与累计积分
            ];
        }

        $orderStatusData = [
            -1 => "Waiting",
            0 => "Waiting",
            1 => "Pass",
            2 => "Failure",
        ];

        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => [
                'setConnection' => true,
                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                'builder' => '',
                'make' => static::getModelAlias(),
                'from' => Constant::CUSTOMER_ORDER . ' as co',
                'select' => ['co' . Constant::LINKER . Constant::DB_TABLE_PRIMARY, 'co' . Constant::LINKER . Constant::DB_TABLE_ORDER_NO, 'co' . Constant::LINKER . Constant::DB_TABLE_ORDER_STATUS, 'co' . Constant::LINKER . Constant::DB_TABLE_ORDER_TIME, 'b.audit_status', 'b.review_credit'],
                'joinData' => [
                    [
                        'table' => 'activity_applies as b',
                        'first' => 'b.ext_id',
                        'operator' => '=',
                        'second' => 'co' . Constant::LINKER . Constant::DB_TABLE_PRIMARY,
                        Constant::DB_TABLE_TYPE => 'left',
                    ],
                ],
                Constant::DB_EXECUTION_PLAN_WHERE => ['co' . Constant::LINKER . Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId],
                'orders' => [['co' . Constant::LINKER . Constant::DB_TABLE_PRIMARY, 'desc']],
                'limit' => $limit,
                'offset' => $offset,
                'handleData' => [
                    'warranty_from' => [//延保时间
                        'field' => '88',
                        Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
                        'dataType' => '',
                        'dateFormat' => '',
                        'glue' => '',
                        'default' => 'From',
                        'time' => '',
                    ],
                    'warranty_from_start' => [//延保时间
                        'field' => Constant::DB_TABLE_ORDER_TIME,
                        Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
                        'dataType' => Constant::DB_EXECUTION_PLAN_DATATYPE_DATETIME,
                        'dateFormat' => 'Y-m-d',
                        'glue' => '',
                        'default' => '',
                        'time' => '',
                    ],
                    'warranty_start' => [//延保时间
                        'field' => 'warranty_from{connection}warranty_from_start',
                        Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
                        'dataType' => 'string',
                        'dateFormat' => '',
                        'glue' => ' ',
                        'default' => '',
                        'time' => '',
                    ],
                    'warranty_end' => [//延保时间
                        'field' => Constant::DB_TABLE_ORDER_TIME,
                        Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
                        'dataType' => Constant::DB_EXECUTION_PLAN_DATATYPE_DATETIME,
                        'dateFormat' => 'Y-m-d',
                        'glue' => '',
                        'default' => '',
                        'time' => '+3 years',
                    ],
                    'warranty2' => [//延保时间
                        'field' => 'warranty_start{connection}warranty_end',
                        Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
                        'dataType' => 'string',
                        'dateFormat' => '',
                        'glue' => ' TO ',
                        'default' => '',
                    ],
                    'warranty1' => [//延保时间
                        'field' => Constant::DB_TABLE_ORDER_STATUS,
                        Constant::RESPONSE_DATA_KEY => [
                            -1 => 'Waiting',
                            0 => 'Waiting',
                            1 => '',
                            2 => 'Waiting',
                        ],
                        'dataType' => '',
                        'dateFormat' => '',
                        'glue' => '',
                        'default' => '',
                    ],
                    Constant::RESPONSE_WARRANTY => [//延保时间
                        'field' => 'warranty1{or}warranty2',
                        Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
                        'dataType' => '',
                        'dateFormat' => '',
                        'glue' => '',
                        'default' => '',
                    ],
                    'order_status_credit' => [//订单积分状态
                        'field' => Constant::DB_TABLE_ORDER_STATUS,
                        Constant::RESPONSE_DATA_KEY => [
                            -1 => "Waiting",
                            0 => "Waiting",
                            1 => "",
                            2 => "Failure",
                        ],
                        'dataType' => '',
                        'dateFormat' => '',
                        'glue' => '',
                        'default' => data_get($orderStatusData, '-1', ''),
                    ],
                    Constant::DB_TABLE_ORDER_STATUS => [//订单状态
                        'field' => Constant::DB_TABLE_ORDER_STATUS,
                        Constant::RESPONSE_DATA_KEY => $orderStatusData,
                        'dataType' => '',
                        'dateFormat' => '',
                        'glue' => '',
                        'default' => data_get($orderStatusData, '-1', ''),
                    ],
                ],
                'unset' => ['warranty_from', 'warranty_from_start', 'warranty_start', 'warranty_end', 'warranty2', 'warranty1', 'order_status_credit'],
            ],
            'with' => [
                'credit' => [
                    'setConnection' => true,
                    Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                    'relation' => Constant::HAS_ONE,
                    'select' => [Constant::DB_TABLE_EXT_ID, Constant::DB_TABLE_VALUE, Constant::DB_TABLE_ADD_TYPE],
                    'default' => [
                        Constant::DB_TABLE_EXT_ID => Constant::DB_TABLE_PRIMARY,
                    ],
                    Constant::DB_EXECUTION_PLAN_WHERE => [Constant::DB_TABLE_ACTION => Constant::ORDER_BIND],
                    'handleData' => [
                        'credit.add_type' => [
                            'field' => 'credit.add_type',
                            Constant::RESPONSE_DATA_KEY => [
                                1 => '+',
                                2 => '-'
                            ],
                            'dataType' => '',
                            'dateFormat' => '',
                            'glue' => '',
                            'default' => '',
                        ],
                        'credit.credit_dest' => [//订单状态
                            'field' => 'credit.add_type{connection}credit.value',
                            Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
                            'dataType' => 'string',
                            'dateFormat' => '',
                            'glue' => '',
                            'is_allow_empty' => false,
                            'default' => data_get($orderStatusData, '-1', ''),
                        ],
                        'credit' => [
                            'field' => 'order_status_credit{or}credit.credit_dest',
                            Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
                            'dataType' => '',
                            'dateFormat' => '',
                            'glue' => '',
                            'default' => 0,
                        ]
                    ],
                //'unset' => ['customer_info'],
                ],
            ]
        ];

        $dataStructure = 'list';
        $flatten = false;
        $_data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);

        return [
            Constant::RESPONSE_DATA_KEY => $_data,
            'credit' => $credit, //获取会员积分与累计积分
            Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
        ];
    }

    /**
     * 订单解除绑定
     * @param int $storeId 官网id
     * @param int $id 订单主键id
     * @return array
     */
    public static function unBind($storeId, $id) {
        //获取订单数据
        $order = static::getModel($storeId)->buildWhere([Constant::DB_TABLE_PRIMARY => $id])->first();
        if (empty($order)) {
            return [
                Constant::RESPONSE_CODE_KEY => -1,
                Constant::RESPONSE_MSG_KEY => '订单已删除',
                Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT
            ];
        }

        $customerId = data_get($order, Constant::DB_TABLE_CUSTOMER_PRIMARY);

        $select = [Constant::DB_TABLE_PRIMARY, Constant::DB_TABLE_ADD_TYPE, Constant::DB_TABLE_VALUE];
        $creditLog = \App\Services\BaseService::createModel($storeId, 'CreditLog');
        $gWhere = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_EXT_ID => $id,
            Constant::DB_TABLE_EXT_TYPE => Constant::CUSTOMER_ORDER,
            Constant::DB_TABLE_ACTION => Constant::ORDER_BIND,
        ];

        //获取积分流水
        $creditLogData = $creditLog->buildWhere($gWhere)->select($select)->get();
        foreach ($creditLogData as $key => $item) {
            //更新用户积分
            $update = [
                'credit' => DB::raw('credit-' . data_get($item, Constant::DB_TABLE_VALUE, Constant::PARAMETER_INT_DEFAULT)),
                'total_credit' => DB::raw('total_credit-' . data_get($item, Constant::DB_TABLE_VALUE, Constant::PARAMETER_INT_DEFAULT)),
            ];
            CustomerInfoService::getModel($storeId)->buildWhere([Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId])->update($update);

            //删除积分流水
            $creditLog->buildWhere([Constant::DB_TABLE_PRIMARY => data_get($item, Constant::DB_TABLE_PRIMARY)])->delete();
        }

        $expLog = \App\Services\BaseService::createModel($storeId, 'ExpLog');
        //获取经验流水
        $expLogData = $expLog->buildWhere($gWhere)->select($select)->get();
        foreach ($expLogData as $key => $item) {
            //更新用户经验
            $update = [
                'exp' => DB::raw('exp-' . data_get($item, Constant::DB_TABLE_VALUE, Constant::PARAMETER_INT_DEFAULT)),
            ];
            CustomerInfoService::getModel($storeId)->buildWhere([Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId])->update($update);

            //删除经验流水
            $expLog->buildWhere([Constant::DB_TABLE_PRIMARY => data_get($item, Constant::DB_TABLE_PRIMARY)])->delete();
        }

        //删除订单
        static::getModel($storeId)->buildWhere([Constant::DB_TABLE_PRIMARY => $id])->delete();

        return [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => '删除成功',
            Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT
        ];
    }

    /**
     * 处理订单延保 积分和经验
     * @param int $storeId 品牌商店id
     * @param int $orderWarrantyId 延保订单id
     * @param int $actId 活动id
     * @param array $extData 扩展参数胡
     * @return boolean
     */
    public static function handleCreditAndExp($storeId, $orderWarrantyId, $actId = 0, $extData = []) {

        $orderWarrantyData = $orderWarrantyId;
        if (is_numeric($orderWarrantyId)) {
            $orderWarrantyWhere = [
                Constant::DB_TABLE_PRIMARY => $orderWarrantyId,
            ];
            $orderWarrantySelect = [
                Constant::DB_TABLE_PRIMARY,
                Constant::DB_TABLE_CUSTOMER_PRIMARY,
                Constant::DB_TABLE_ORDER_STATUS,
                Constant::DB_TABLE_AMOUNT,
                Constant::DB_TABLE_CURRENCY_CODE,
                Constant::DB_TABLE_ORDER_TIME,
                Constant::DB_TABLE_ORDER_NO,
            ];
            $orderWarrantyData = static::existsOrFirst($storeId, '', $orderWarrantyWhere, true, $orderWarrantySelect);
        }

        $orderWarrantyId = data_get($orderWarrantyData, Constant::DB_TABLE_PRIMARY) ?? 0; //延保订单id
        $customerId = data_get($orderWarrantyData, Constant::DB_TABLE_CUSTOMER_PRIMARY) ?? Constant::DEFAULT_CUSTOMER_PRIMARY_VALUE; //账号id
        $orderStatus = data_get($orderWarrantyData, Constant::DB_TABLE_ORDER_STATUS) ?? Constant::ORDER_STATUS_DEFAULT; //订单状态 -1:Matching 0:Pending 1:Shipped 2:Canceled 3:Failure 默认:-1
        $amount = data_get($orderWarrantyData, Constant::DB_TABLE_AMOUNT) ?? 0; //订单金额
        $currencyCode = data_get($orderWarrantyData, Constant::DB_TABLE_CURRENCY_CODE) ?? ''; //交易货币
        $orderTime = data_get($orderWarrantyData, Constant::DB_TABLE_ORDER_TIME, Constant::PARAMETER_STRING_DEFAULT); //订单时间
        $orderNo = data_get($orderWarrantyData, Constant::DB_TABLE_ORDER_NO) ?? ''; //订单号

        if (in_array($storeId, [1, 2])) {
            $amount = static::orderItemTotalAmount($storeId, $orderNo);
        }

        $creditWhere = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ADD_TYPE => 1,
            Constant::DB_TABLE_ACTION => Constant::ORDER_BIND,
            Constant::DB_TABLE_EXT_ID => $orderWarrantyId,
            Constant::DB_TABLE_EXT_TYPE => 'customer_order',
        ];

        switch ($orderStatus) {
            case Constant::ORDER_STATUS_SHIPPED_INT://Shipped
                break;

            case Constant::ORDER_STATUS_CANCELED_INT://Canceled
                $isExists = CreditService::existsOrFirst($storeId, '', $creditWhere);
                if (empty($isExists)) {//如果当前订单没有添加过积分，就直接返回
                    return false;
                }
                data_set($creditWhere, Constant::DB_TABLE_ADD_TYPE, 2);

                break;

            default:
                data_set($creditWhere, Constant::DB_TABLE_ADD_TYPE, null);
                break;
        }

        if (data_get($creditWhere, Constant::DB_TABLE_ADD_TYPE, null) === null) {
            return false;
        }

        //币种转换
        $exchangeData = DictService::getListByType('exchange', Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);

        $exchange = data_get($exchangeData, $currencyCode, 0);
        $value = $exchange * $amount;
        $creditValue = CreditService::actCredit($storeId, $actId, $customerId, $value, $orderTime); //活动期间延保积分
        if ($value != 0) {

            $creditHistory = Arr::collapse([[
                    Constant::DB_TABLE_STORE_ID => $storeId,
                    Constant::DB_TABLE_VALUE => $value,
                        ], $creditWhere]);

            $remark = data_get($extData, Constant::DB_TABLE_REMARK, Constant::PARAMETER_STRING_DEFAULT);
            !empty($remark) && $creditHistory[Constant::DB_TABLE_REMARK] = $remark;

            $creditHistory[Constant::DB_TABLE_VALUE] = $creditValue;

            $isExists = CreditService::existsOrFirst($storeId, '', $creditWhere);
            if (empty($isExists)) {//加积分
                CreditService::handle($creditHistory);
            }

            $creditHistory[Constant::DB_TABLE_VALUE] = $value;

            //加经验值
            $supportExp = DictStoreService::getByTypeAndKey($storeId, Constant::ORDER_BIND, 'support_exp', true, true);
            if ($supportExp) {
                $isExists = ExpService::existsOrFirst($storeId, '', $creditWhere);
                if (empty($isExists)) {//加经验
                    ExpService::handle($creditHistory);
                }
            }
        }

        return abs(ceil($value));
    }

    public static function orderItemTotalAmount($storeId, $orderNo, $country = '', $platform = Constant::PLATFORM_SERVICE_AMAZON) {
        $amount = 0;
        $orderData = OrderService::getOrderData($orderNo, $country, $platform, $storeId);
        $orderItemData = data_get($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . 'items', []);
        if (empty($orderItemData)) {
            return $amount;
        }
        $amount = collect($orderItemData)->where(Constant::DB_TABLE_ORDER_STATUS, '=','Shipped')->sum('amount');
        return floatval($amount);
    }
}
