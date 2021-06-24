<?php

/**
 * 订单服务
 * User: Jmiy
 * Date: 2020-04-16
 * Time: 15:57
 */

namespace App\Services;

use App\Services\Traits\GetDefaultConnectionModel;
use Carbon\Carbon;
use App\Constants\Constant;
use App\Models\Erp\Amazon\AmazonOrderItem;
use App\Utils\FunctionHelper;
use Hyperf\DbConnection\Db as DB;
use Exception;
use App\Exception\Handler\AppExceptionHandler as ExceptionHandler;
use App\Utils\Response;
use Hyperf\Utils\Arr;
use App\Models\Store;
use App\Services\Store\PlatformServiceManager;

class OrdersService extends BaseService {

    use GetDefaultConnectionModel;

    //us:3429514 uk:1164971 de:891601 ca:522432 fr:450533 it:380574 es:334277 jp:176886 mx:61404 au:5822 in:2123 ae:2225 sg:0
    public static $orderCountryData = ["us", "uk", "de", "ca", "fr", "it", "es", "jp", "mx", "au", "in", "ae", "sg", "nl"]; //

    /**
     * 获取模型别名
     * @return string
     */

    public static function getModelAlias() {
        return 'Order';
    }

    /**
     * 获取要清空的tags
     * @return array
     */
    public static function getClearTags() {
        return [static::getCacheTags()];
    }

    /**
     * 获取缓存tags
     * @return string
     */
    public static function getCacheTags() {
        return 'ordersLock';
    }

    /**
     * 拉取亚马逊订单
     * @param int $storeId 商城id
     * @param array $orderItemData 订单item数据
     * @param string $orderCountry 订单item数据
     * @param boolean $isRetry 是否重试 true:是  false:否
     * @return array $orderData 订单数据
     */
    public static function pullAmazonOrder($storeId, $orderItemData, $orderCountry = Constant::PARAMETER_STRING_DEFAULT, $isRetry = true) {

        if (empty($orderItemData)) {
            return Constant::PARAMETER_ARRAY_DEFAULT;
        }

        $_orderItemData = collect($orderItemData);
        $orderItemData = $_orderItemData->sortBy(Constant::DB_TABLE_MODFIY_AT_TIME)->values()->all();
        $orderItem = current($orderItemData);
        $shippedItem = $_orderItemData->firstWhere(Constant::DB_TABLE_ORDER_STATUS, '=','Shipped');
        if (!empty($shippedItem)) {
            $orderItem = $shippedItem;
        }

        $orderno = trim(data_get($orderItem, Constant::DB_TABLE_AMAZON_ORDER_ID, ''));
        $orderCountry = trim($orderCountry);

        $type = Constant::DB_TABLE_PLATFORM;
        $platform = Constant::PLATFORM_AMAZON;
        if (empty($orderno)) {
            return static::getOrderDetails($storeId, $orderno, $type, $platform);
        }

        $where = [
            Constant::DB_TABLE_ORDER_NO => $orderno,
            Constant::DB_TABLE_TYPE => $type,
            Constant::DB_TABLE_PLATFORM => $platform,
            Constant::DB_TABLE_COUNTRY => $orderCountry,
        ];

        $tag = static::getCacheTags();
        $cacheKey = strtolower($tag . ':' . implode(':', $where));

        $dictData = static::getPullOrderConfig(['pull_order', Constant::DB_TABLE_ORDER_STATUS]);
        data_set($dictData, Constant::DB_TABLE_ORDER_STATUS, collect(data_get($dictData, Constant::DB_TABLE_ORDER_STATUS, []))->flip());

        $isForceReleaseOrderLock = data_get($dictData, 'pull_order.is_force_release_order_lock', 0); //是否强制释放订单锁 1:是 0:否  默认:1
        $releaseTime = data_get($dictData, 'pull_order.release_time', 0); //释放订单锁时间 单位秒 默认:600(10分钟)
        if ($isForceReleaseOrderLock) {
            static::forceReleaseLock($cacheKey, 'forceRelease', $releaseTime); //如果获取分布式锁失败，10秒后强制释放锁
        }
        $service = static::getNamespaceClass();
        $method = __FUNCTION__;
        $parameters = func_get_args();

        $lockParameters = [
            function () use($storeId, $orderItemData, $orderCountry, $isRetry, $_orderItemData, $orderItem, $where, $cacheKey, $service, $method, $parameters, $dictData) {

                $orderno = data_get($orderItem, Constant::DB_TABLE_AMAZON_ORDER_ID, '');
                //LogService::addSystemLog('debug', 'amazon_order_pull_lock', $orderno, $cacheKey, $orderItemData); //添加系统日志

                $_orderData = Constant::PARAMETER_ARRAY_DEFAULT;

                $connection = static::getModel($storeId, '')->getConnection();
                $connection->beginTransaction();
                try {

                    $nowTime = Carbon::now()->toDateTimeString();

                    $sum = floatval($_orderItemData->sum('item_price_amount')) - floatval($_orderItemData->sum('promotion_discount_amount'));

                    //更新订单数据
                    $currencyCode = data_get($orderItem, Constant::DB_TABLE_CURRENCY_CODE, ''); //交易货币
                    $amount = number_format(floatval($sum), 2, '.', '') + 0; //订单金额
                    $orderStatus = data_get($orderItem, Constant::DB_TABLE_ORDER_STATUS, 'Pending'); //订单状态 Pending Shipped Canceled
                    $orderStatusData = data_get($dictData, Constant::DB_TABLE_ORDER_STATUS, []); //订单状态 -1:Matching 0:Pending 1:Shipped 2:Canceled 3:Failure 默认:-1

                    $platformUpdatedAt = data_get($orderItem, Constant::DB_TABLE_MODFIY_AT_TIME, ''); //平台订单更新时间

                    $orderUniqueId = PlatformServiceManager::handle(Constant::PLATFORM_SERVICE_AMAZON, 'Order', 'getOrderUniqueId', [$storeId, $orderCountry, Constant::PLATFORM_SERVICE_AMAZON, $orderno]);

                    $data = [
                        Constant::DB_TABLE_UNIQUE_ID => $orderUniqueId, //FunctionHelper::getUniqueId($orderCountry, Constant::PLATFORM_SERVICE_AMAZON, $orderno, static::getModelAlias()), //平台订单唯一id
                        Constant::DB_TABLE_COUNTRY => $orderCountry,
                        Constant::DB_TABLE_AMOUNT => $amount ? $amount : '0.00',
                        Constant::DB_TABLE_CURRENCY_CODE => $currencyCode,
                        Constant::DB_TABLE_ORDER_AT => Carbon::parse(data_get($orderItem, Constant::DB_TABLE_PURCHASE_DATE, $nowTime))->toDateTimeString(), //订单时间
                        Constant::DB_TABLE_ORDER_STATUS => data_get($orderStatusData, $orderStatus, Constant::ORDER_STATUS_DEFAULT), //订单状态 -1:Matching 0:Pending 1:Shipped 2:Canceled 3:Failure 默认:-1
                        Constant::DB_TABLE_PURCHASE_DATE_ORIGIN => data_get($orderItem, Constant::DB_TABLE_PURCHASE_DATE_ORIGIN, ''), //MWS接口下单时间 2017-05-01T00:01:05Z
                        Constant::DB_TABLE_RATE => data_get($orderItem, Constant::DB_TABLE_RATE, ''), //汇率
                        Constant::DB_TABLE_RATE_AMOUNT => data_get($orderItem, Constant::DB_TABLE_RATE_AMOUNT, ''), //折算汇率金额
                        Constant::DB_TABLE_IS_REPLACEMENT_ORDER => data_get($orderItem, Constant::DB_TABLE_IS_REPLACEMENT_ORDER, 0), //是否替换订单 0 false | 1 true
                        Constant::DB_TABLE_IS_PREMIUM_ORDER => data_get($orderItem, Constant::DB_TABLE_IS_PREMIUM_ORDER, 0), //是否重要订单 0 false | 1 true
                        Constant::DB_TABLE_SHIPMENT_SERVICE_LEVEL_CATEGORY => data_get($orderItem, Constant::DB_TABLE_SHIPMENT_SERVICE_LEVEL_CATEGORY, ''), //装运服务等级类别
                        Constant::DB_TABLE_LATEST_SHIP_DATE => data_get($orderItem, Constant::DB_TABLE_LATEST_SHIP_DATE, ''), //最新发货日期
                        Constant::DB_TABLE_EARLIEST_SHIP_DATE => data_get($orderItem, Constant::DB_TABLE_EARLIEST_SHIP_DATE, ''), //最早的发货日期
                        Constant::DB_TABLE_SALES_CHANNEL => data_get($orderItem, Constant::DB_TABLE_SALES_CHANNEL, ''), //销售渠道
                        Constant::DB_TABLE_IS_BUSINESS_ORDER => data_get($orderItem, Constant::DB_TABLE_IS_BUSINESS_ORDER, ''), //是否B2B订单 0:否;1是
                        Constant::DB_TABLE_FULFILLMENT_CHANNEL => data_get($orderItem, Constant::DB_TABLE_FULFILLMENT_CHANNEL, ''), //发货渠道
                        Constant::DB_TABLE_PAYMENT_METHOD => data_get($orderItem, Constant::DB_TABLE_PAYMENT_METHOD, ''), //支付方式
                        Constant::DB_TABLE_IS_HAND => data_get($orderItem, Constant::DB_TABLE_IS_HAND, ''), //是否手工单 0:否;1是
                        Constant::DB_TABLE_ORDER_TYPE => data_get($orderItem, Constant::DB_TABLE_ORDER_TYPE, ''), //订单类型
                        Constant::DB_TABLE_SHIP_SERVICE_LEVEL => data_get($orderItem, Constant::DB_TABLE_SHIP_SERVICE_LEVEL, ''), //发货优先级
                        Constant::DB_TABLE_PLATFORM_UPDATED_AT => $platformUpdatedAt, //平台订单更新时间
                        Constant::DB_TABLE_LAST_UPDATE_DATE => data_get($orderItem, Constant::DB_TABLE_LAST_UPDATE_DATE, ''), //发货优先级
                    ];

                    $_orderData = static::updateOrCreate($storeId, $where, $data, '', FunctionHelper::getDbBeforeHandle([], [], [], array_keys($data)));
                    $orderId = data_get($_orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
                    if ($orderId) {
                        $rs = OrderItemService::pullAmazonOrderItem($storeId, $orderId, $orderItemData);
                        if (data_get($rs, 'exeRs', false) === false) {
                            data_set($parameters, 'rs', $rs);
                            $code = 30008;
                            $msg = Response::getResponseMsg($storeId, $code);
                            throw new Exception($msg, $code);
                        }
                    }
                    $connection->commit();
                } catch (Exception $exc) {
                    $connection->rollBack();
                    data_set($parameters, 'exc', ExceptionHandler::getMessage($exc));
                    LogService::addSystemLog('error', $method, $orderno, $service, $parameters); //添加系统日志

                    if ($isRetry === true) {
                        $retryLaterTime = data_get($dictData, 'pull_order.retry_later_time', 3); //订单拉取重试延期时间 单位秒
                        $queueData = [
                            Constant::SERVICE_KEY => $service,
                            Constant::METHOD_KEY => $method,
                            Constant::PARAMETERS_KEY => [$storeId, $orderItemData, $orderCountry, false],
                        ];
                        FunctionHelper::laterQueue($retryLaterTime, $queueData, null, '{amazon-order-pull}'); //延时 $retryLaterTime 秒再弹出任务
                    }
                }

                return $_orderData;
            }
        ];
        $rs = static::handleLock([$cacheKey], $lockParameters);

        if ($isForceReleaseOrderLock && $rs === false) {//如果强制释放订单锁，并且获取分布式锁失败，就统计获取次数
            LogService::addSystemLog('debug', 'amazon_order_pull_lock_no', $orderno, $cacheKey, $orderItemData); //添加系统日志
            static::forceReleaseLock($cacheKey, 'statisticsLock', $releaseTime); //统计锁
        }

        $ids = [];
        $orderData = static::getOrderDetails($storeId, $orderno, $type, $platform);
        if (data_get($orderData, 'items')) {
            $ids[] = data_get($orderData, Constant::DB_TABLE_PRIMARY);
        }

        if ($ids) {
            static::update($storeId, [Constant::DB_TABLE_PRIMARY => $ids], ['is_repair' => 1]);
        }

        return $orderData;
    }

    /**
     * 释放订单拉取分布式锁
     * @return boolean
     */
    public static function forceReleaseOrdersLock($country, $startAt = null, $endAt = null, $orderno = '') {
        //释放分布式锁  如果你想在不尊重当前锁的所有者的情况下释放锁，你可以使用 forceRelease 方法 Cache::lock('foo')->forceRelease();
        $service = static::getNamespaceClass();
        $tag = static::getCacheTags();
        $cacheKey = array_filter([$tag, $country, $startAt, $endAt]);
        $cacheKey = implode(':', $cacheKey);
        $handleCacheData = [
            'service' => $service,
            'method' => 'lock',
            'parameters' => [
                $cacheKey,
            ],
            'serialHandle' => [
                [
                    'service' => $service,
                    'method' => 'forceRelease',
                    'parameters' => [],
                ]
            ]
        ];
        $rs = static::handleCache($tag, $handleCacheData);

        if ($orderno) {
            //释放分布式锁  如果你想在不尊重当前锁的所有者的情况下释放锁，你可以使用 forceRelease 方法 Cache::lock('foo')->forceRelease();
            $cacheKey = array_filter([$tag, $country, $orderno]);
            $cacheKey = implode(':', $cacheKey);
            $handleCacheData = [
                'service' => $service,
                'method' => 'lock',
                'parameters' => [
                    $cacheKey,
                ],
                'serialHandle' => [
                    [
                        'service' => $service,
                        'method' => 'forceRelease',
                        'parameters' => [],
                    ]
                ]
            ];
            $rs = static::handleCache($tag, $handleCacheData);
        }

        return $rs;
    }

    /**
     * 获取拉取订单配置
     * @return array
     */
    public static function getPullOrderConfig($type = 'pull_order', $extWhere = []) {
        $select = [
            Constant::DB_TABLE_TYPE, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE,
        ];
        return DictService::getListByType($type, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE, null, null, $extWhere, $select);
    }

    /**
     * 获取订单拉取时间段
     * @return array $dataTime
     */
    public static function getOrderPullTimePeriod() {

        $dictData = static::getPullOrderConfig();
        $_dataTime = data_get($dictData, 'data_time', ''); //2020-05-01 00:00:00|;2020-04-01 00:00:00|2020-05-01 00:00:00;2020-03-01 00:00:00|2020-04-01 00:00:00;2020-02-01 00:00:00|2020-03-01 00:00:00;2019-07-01 00:00:00|2020-01-01 00:00:00
        $dataTime = [];
        if ($_dataTime) {
            $_dataTime = explode(';', $_dataTime);
            foreach ($_dataTime as $value) {
                $valueData = explode('|', $value);
                $startAt = data_get($valueData, 0, null);
                $endAt = data_get($valueData, 1, null);
                $dataTime[] = [
                    'startAt' => $startAt ? $startAt : null,
                    'endAt' => $endAt ? $endAt : null,
                ];
            }
        }

        return $dataTime;
    }

    /**
     * 获取订单延后拉取时间
     * @return int $laterTime 单位秒
     */
    public static function getLaterTime() {
        $service = static::getNamespaceClass();
        $tag = static::getCacheTags();
        $cacheKey = $tag . ':pullAmazonOrder:';

        $dictData = static::getPullOrderConfig();
        $limit = data_get($dictData, 'limit', 50); //获取每次拉取订单数量
        $eachPullTime = data_get($dictData, 'each_pull_time', 1); //每个订单拉取需要的时间 单位秒
        $ttl = data_get($dictData, 'ttl', 600); //批次已经在消息队列里面的缓存时间 单位秒
        $eachBatchExeTime = $limit * $eachPullTime; //每个批次执行时间

        $laterTime = 0;
        $orderCountryData = static::$orderCountryData;
        $orderPullTimePeriod = static::getOrderPullTimePeriod(); //获取订单拉取时间段
        foreach ($orderPullTimePeriod as $item) {
            foreach ($orderCountryData as $key => $country) {
                $startAt = data_get($item, 'startAt', null);
                $endAt = data_get($item, 'endAt', null);
                $parameters = [$country, $startAt, $endAt];

                $_cacheKey = $cacheKey . implode(':', $parameters); //拉取时段cacheKey
                //获取 $_cacheKey 对应的批次是否存在
                $has = static::handleCache($tag, FunctionHelper::getJobData($service, 'get', [$_cacheKey]));
                if ($has === '1') {
                    $laterTime = $laterTime + $eachBatchExeTime;
                }
            }
        }
        return $laterTime ? $laterTime : $eachBatchExeTime;
    }

    /**
     * 处理定时拉取亚马逊订单数据
     * @param string $country 国家
     * @param string $startAt 开始时间  默认：null
     * @param string $endAt   结束时间  默认：null
     * @return boolean
     */
    public static function handleAmazonOrder($country, $startAt = null, $endAt = null) {

        $tag = static::getCacheTags();
        $service = static::getNamespaceClass();
        $method = __FUNCTION__;
        $parameters = func_get_args();
        $_cacheKey = $tag . ':pullAmazonOrder:' . implode(':', $parameters); //拉取时段cacheKey
        //设置 $_cacheKey 对应批次不需要拉取
        $has = static::handleCache($tag, FunctionHelper::getJobData($service, 'get', [$_cacheKey]));
        if ($has === '0') {//如果 $_cacheKey 对应批次不需要拉取，就直接返回
            return false;
        }

        ini_set('max_execution_time', 6000); // 设置PHP超时时间
        ini_set('memory_limit', '5120M'); // 设置PHP临时允许内存大小


        $cacheKey = array_filter([$tag, $country, $startAt, $endAt]);
        $cacheKey = implode(':', $cacheKey);

        $dictData = static::getPullOrderConfig(['handle_order', 'pull_order']);

        $isForceReleaseOrderLock = data_get($dictData, 'handle_order.is_force_release_order_lock', 0); //是否强制释放订单锁 1:是 0:否  默认:1
        $releaseTime = data_get($dictData, 'handle_order.release_time', 0); //释放订单锁时间 单位秒 默认:600(10分钟)
        if ($isForceReleaseOrderLock) {
            static::forceReleaseLock($cacheKey, 'forceRelease', $releaseTime); //如果获取分布式锁失败，10秒后强制释放锁
        }

        $eachPullTime = data_get($dictData, 'pull_order.each_pull_time', 1); //每个订单拉取需要的时间 单位秒
        $ttl = data_get($dictData, 'pull_order.ttl', 600); //批次已经在消息队列里面的缓存时间 单位秒

        $lockParameters = [
            function () use($country, $startAt, $endAt, $eachPullTime, $service, $method, $parameters, $tag, $_cacheKey, $ttl, $dictData) {

                $storeId = 2;
                FunctionHelper::setTimezone($storeId);
                $updateAt = '2020-01-01 00:00:00';
                $country = strtolower($country);
                $nowTime = Carbon::now()->toDateTimeString();

                $orderItemModel = OrderItemService::getModel($storeId);
                $model = static::getModel($storeId, $country, [], 'AmazonOrderItem')->setTable(AmazonOrderItem::$tablePrefix . '_' . $country);
                try {

                    //获取订单最新更新时间
                    $type = Constant::DB_TABLE_PLATFORM;
                    $platform = Constant::PLATFORM_AMAZON;
                    $where = [
                        Constant::DB_TABLE_TYPE => $type,
                        Constant::DB_TABLE_PLATFORM => $platform,
                        Constant::DB_TABLE_ORDER_COUNTRY => $country,
                        Constant::DB_TABLE_PULL_MODE => 2, //订单拉取方式 1:C端用户主动拉取 2:定时任务拉取
                    ];

                    $_where = [];
                    if ($startAt) {
                        $_where[] = [Constant::DB_TABLE_PLATFORM_UPDATED_AT, '>=', $startAt];
                    }

                    if ($endAt) {
                        $_where[] = [Constant::DB_TABLE_PLATFORM_UPDATED_AT, '<', $endAt];
                    }

                    if ($_where) {
                        $where[] = $_where;
                    }

                    if ($endAt && $endAt < $nowTime) {

                        $amazonOrderItemWhere = [];
                        $_amazonOrderItemWhere = [];
                        if ($startAt) {
                            $_amazonOrderItemWhere[] = [Constant::DB_TABLE_MODFIY_AT_TIME, '>=', $startAt];
                        }

                        if ($endAt) {
                            $_amazonOrderItemWhere[] = [Constant::DB_TABLE_MODFIY_AT_TIME, '<', $endAt];
                        }

                        if ($_amazonOrderItemWhere) {
                            $amazonOrderItemWhere[] = $_amazonOrderItemWhere;
                        }

                        $amazonOrderItemCount = $model->buildWhere($amazonOrderItemWhere)->count();
                        if (empty($amazonOrderItemCount)) {//如果 亚马逊订单  在 $startAt $endAt 没有订单，就直接返回
                            dump('====' . $country . '==startAt==' . $startAt . '==endAt====' . $endAt . '====amazonOrderItemCount==' . $amazonOrderItemCount);

                            //删除 $_cacheKey 对应批次的存在标识
//                                        $handleCacheData = FunctionHelper::getJobData($service, 'forget', [$_cacheKey];
                            //设置 $_cacheKey 对应批次不需要拉取
                            static::handleCache($tag, FunctionHelper::getJobData($service, 'put', [$_cacheKey, 0, $ttl]));

                            return $amazonOrderItemCount;
                        }

                        $localCount = $orderItemModel->buildWhere($where)->count();
                        if ($amazonOrderItemCount == $localCount) {
                            //设置 $_cacheKey 对应批次不需要拉取
                            static::handleCache($tag, FunctionHelper::getJobData($service, 'put', [$_cacheKey, 0, $ttl]));

                            dump('====' . $country . '==startAt==' . $startAt . '==endAt====' . $endAt . '====amazonOrderItemCount==' . $amazonOrderItemCount . '====localCount==' . $localCount);

                            return $amazonOrderItemCount;
                        }
                    }

                    $data = $orderItemModel->buildWhere($where)->max(Constant::DB_TABLE_PLATFORM_UPDATED_AT); //->orderBy(Constant::DB_TABLE_PLATFORM_UPDATED_AT, 'DESC')->value(Constant::DB_TABLE_PLATFORM_UPDATED_AT);
                    $updateAt = $data ? $data : ($startAt ? $startAt : $updateAt);

                    //获取 更新时间等于 $updateAt 的订单数据
                    $maxPlatformOrderItemIdWhere = [
                        Constant::DB_TABLE_TYPE => $type,
                        Constant::DB_TABLE_PLATFORM => $platform,
                        Constant::DB_TABLE_ORDER_COUNTRY => $country,
                        Constant::DB_TABLE_PULL_MODE => 2, //订单拉取方式 1:C端用户主动拉取 2:定时任务拉取
                    ];
                    data_set($maxPlatformOrderItemIdWhere, Constant::DB_TABLE_PLATFORM_UPDATED_AT, $updateAt);
                    $orderItemIds = $orderItemModel->buildWhere($maxPlatformOrderItemIdWhere)->max(Constant::DB_TABLE_PLATFORM_ORDER_ITEM_ID); //->orderBy(Constant::DB_TABLE_PLATFORM_ORDER_ITEM_ID, 'DESC')->value(Constant::DB_TABLE_PLATFORM_ORDER_ITEM_ID); //->pluck(Constant::DB_TABLE_PRIMARY, Constant::DB_TABLE_PLATFORM_ORDER_ITEM_ID); //平台订单item id ->pluck('title', 'name');//字段名为：name的值作为key 字段名为：title的值作为value
                    //$orderCount = $orderItemIds->count();
                    $orderCount = $orderItemIds;

                    //获取 销参订单更新时间等于 $updateAt 的订单总数
                    $amazonOrderItemWhere = [
                        Constant::DB_TABLE_MODFIY_AT_TIME => $updateAt,
                    ];

                    if ($orderItemIds) {
                        $amazonOrderItemWhere[] = [[Constant::DB_TABLE_PRIMARY, '>', $orderItemIds]];
                    }
                    //$count = $model->buildWhere($countWhere)->count(); //DB::raw('DISTINCT ' . Constant::DB_TABLE_AMAZON_ORDER_ID)

                    $isSleep = true;
                    $limit = data_get($dictData, 'pull_order.limit', 50); //获取每次拉取订单数量
                    $page = 1;
                    $select = [
                        Constant::DB_TABLE_AMAZON_ORDER_ID,
                        Constant::DB_TABLE_PRIMARY,
                    ];
                    $amazonOrderData = $model->select($select)
                            ->buildWhere($amazonOrderItemWhere)
                            ->orderBy(Constant::DB_TABLE_PRIMARY, 'ASC')
                            ->limit($limit); //DB::raw('DISTINCT ' . Constant::DB_TABLE_AMAZON_ORDER_ID)
//                                if ($orderCount > 0) {
//                                    $amazonOrderData = $amazonOrderData->whereNotIn(Constant::DB_TABLE_PRIMARY, $orderItemIds->toArray());
//                                }
                    $amazonOrderData = $amazonOrderData->pluck(Constant::DB_TABLE_AMAZON_ORDER_ID, Constant::DB_TABLE_PRIMARY); //->offset($offset)
                    //$amazonOrderData = $amazonOrderData->diffKeys($orderItemIds->toArray())->all();
                    //$count = count($amazonOrderData); //$amazonOrderData->count();
                    $count = $amazonOrderData->count();
                    if ($count) {

                        $updateCount = 0;
                        $msg = 'success';

                        foreach ($amazonOrderData as $platformOrderItemId => $orderId) {
                            //dump('=======platformOrderItemId========>' . $platformOrderItemId, '=======amazon_order_id========>' . $orderId);
                            if ($orderId) {

                                $isSleep = false;

                                $_parameters = [
                                    'orderno' => $orderId,
                                    'order_country' => $country,
                                ];
                                static::handlePull($storeId, Constant::PLATFORM_SERVICE_AMAZON, $_parameters);

                                $orderWhere = [
                                    Constant::DB_TABLE_PLATFORM_ORDER_ITEM_ID => $platformOrderItemId,
                                    Constant::DB_TABLE_TYPE => $type,
                                    Constant::DB_TABLE_PLATFORM => $platform,
                                    Constant::DB_TABLE_ORDER_COUNTRY => $country,
                                ];

                                $isExists = OrderItemService::existsOrFirst($storeId, '', $orderWhere);
                                if (empty($isExists)) {
                                    $msg = 'failure';
                                    break;
                                }

                                $updateData = [
                                    Constant::DB_TABLE_PULL_MODE => 2,
                                ];
                                OrderItemService::update($storeId, $orderWhere, $updateData);
                                $updateCount++;
                            }
                        }

                        dump('====' . $country . '==' . $updateAt . '===platform_order_item_id===' . $orderCount . '==startAt==' . $startAt . '==endAt====' . $endAt . '====limit==' . $limit . '===count===>' . $count . '===update_count===>' . $updateCount . '===msg===>' . $msg);
                    }

                    if ($count == 0) {
                        $where = [
                            [[Constant::DB_TABLE_MODFIY_AT_TIME, '>', $updateAt]]
                        ];

                        $_where = [];
                        if ($startAt) {
                            $_where[] = [Constant::DB_TABLE_MODFIY_AT_TIME, '>=', $startAt];
                        }

                        if ($endAt) {
                            $_where[] = [Constant::DB_TABLE_MODFIY_AT_TIME, '<', $endAt];
                        }

                        if ($_where) {
                            $where[] = $_where;
                        }

                        $select = [
                            Constant::DB_TABLE_AMAZON_ORDER_ID,
                            Constant::DB_TABLE_PRIMARY,
                        ];
                        $amazonOrderData = $model->select($select)->buildWhere($where)->orderBy(Constant::DB_TABLE_MODFIY_AT_TIME, 'ASC')->orderBy(Constant::DB_TABLE_PRIMARY, 'ASC')->limit($limit)->pluck(Constant::DB_TABLE_AMAZON_ORDER_ID, Constant::DB_TABLE_PRIMARY);

                        $updateCount = 0;
                        $msg = 'success';
                        foreach ($amazonOrderData as $platformOrderItemId => $orderId) {
                            //$amazonOrder = $model->select($select)->buildWhere($where)->orderBy(Constant::DB_TABLE_MODFIY_AT_TIME, 'ASC')->first();
                            //$orderId = data_get($amazonOrder, Constant::DB_TABLE_AMAZON_ORDER_ID, '');
                            if ($orderId) {

                                $isSleep = false;

                                $_parameters = [
                                    'orderno' => $orderId,
                                    'order_country' => $country,
                                ];
                                static::handlePull($storeId, Constant::PLATFORM_SERVICE_AMAZON, $_parameters);

                                $orderWhere = [
                                    Constant::DB_TABLE_PLATFORM_ORDER_ITEM_ID => $platformOrderItemId,
                                    Constant::DB_TABLE_TYPE => $type,
                                    Constant::DB_TABLE_PLATFORM => $platform,
                                    Constant::DB_TABLE_ORDER_COUNTRY => $country,
                                ];

                                $isExists = OrderItemService::existsOrFirst($storeId, '', $orderWhere);
                                if (empty($isExists)) {
                                    $msg = 'failure';
                                    break;
                                }

                                $updateData = [
                                    Constant::DB_TABLE_PULL_MODE => 2,
                                ];
                                OrderItemService::update($storeId, $orderWhere, $updateData);
                                $updateCount++;
                            }
                            //unset($amazonOrder);
                        }
                        $count = $amazonOrderData->count();

                        unset($amazonOrderData);

                        if ($endAt && $endAt < $nowTime && $count == 0) {
                            //设置 $_cacheKey 对应批次不需要拉取
                            static::handleCache($tag, FunctionHelper::getJobData($service, 'put', [$_cacheKey, 0, $ttl]));

                            dump('====' . $country . '====>' . $updateAt . '===platform_order_item_id===' . $orderCount . '==startAt==' . $startAt . '==endAt====' . $endAt . '====amazonOrderItemCount==' . $count);

                            return $count;
                        }

                        dump('====' . $country . '====>' . $updateAt . '===platform_order_item_id===' . $orderCount . '==startAt==' . $startAt . '==endAt====' . $endAt . '====limit==' . $limit . '===count===>' . $count . '===update_count===>' . $updateCount . '===msg===>' . $msg);
                    }

                    $noRealTimePullCountry = []; //"in", "ae", "sg", "nl"
                    if (!in_array($country, $noRealTimePullCountry)) {
                        $data = FunctionHelper::getJobData($service, $method, $parameters);
                        $realTimePullAmazonOrder = data_get($dictData, 'pull_order.real_time_pull_amazon_order', 0); //是否实时拉取亚马逊订单 1:是 0:否  默认:1
                        if ($realTimePullAmazonOrder) {

                            $doTime = data_get($dictData, 'pull_order.do_time', ''); //订单可拉取的时间段
                            $doTime = explode('|', $doTime);
                            $doStartTime = data_get($doTime, 0, '12:00:00');
                            $doEndTime = data_get($doTime, 1, '20:00:00');
                            $nowTime = Carbon::now()->rawFormat('H:i:s');
                            if ($doStartTime <= $nowTime && $nowTime <= $doEndTime) {
                                $laterTime = OrdersService::getLaterTime();
                                $_laterTime = $laterTime - ($limit * $eachPullTime);
                                FunctionHelper::laterQueue($_laterTime, $data, null, '{amazon-order-pull}'); //延时 1800 秒再弹出任务
                                //记录 $_cacheKey 对应的批次已经在消息队列里面,并且需要继续拉取
                                static::handleCache($tag, FunctionHelper::getJobData($service, 'put', [$_cacheKey, 1, $ttl]));

                                dump('====' . $country . '==startAt==' . $startAt . '==endAt====' . $endAt . '====laterQueue=={amazon-order-pull}=====' . $_laterTime); //, $amazonOrderData->pluck(Constant::DB_TABLE_AMAZON_ORDER_ID)
                            }
                        }
                    } else {

                        //删除 $_cacheKey 对应批次的存在标识
                        static::handleCache($tag, FunctionHelper::getJobData($service, 'forget', [$_cacheKey]));
                    }
                } catch (Exception $exc) {
                    data_set($parameters, 'exc', ExceptionHandler::getMessage($exc));
                    LogService::addSystemLog('error', 'handleAmazonOrder', $method, $service, $parameters); //添加系统日志
                }

                return true;
            }
        ];
        $rs = static::handleLock([$cacheKey], $lockParameters);

        if ($rs === false) {//获取分布式锁失败，就添加系统日志
            LogService::addSystemLog('debug', 'amazon_handle_order_lock_no', $country, $cacheKey, $parameters); //添加系统日志
        }

        if ($isForceReleaseOrderLock && $rs === false) {//如果强制释放订单锁，并且获取分布式锁失败，就统计获取次数
            static::forceReleaseLock($cacheKey, 'statisticsLock', $releaseTime); //统计锁
        }

        return $rs;
    }

    /**
     * 处理定时拉取亚马逊订单数据
     * @param string $orderno 订单号
     * @return boolean
     */

    /**
     * 获取订单详情
     * @param int $storeId 品牌商店id
     * @param string $orderno din
     * @param type $type
     * @param type $platform
     * @return type
     */
    public static function getOrderDetails($storeId, $orderno, $type = Constant::DB_TABLE_PLATFORM, $platform = Constant::PLATFORM_SERVICE_AMAZON) {

        if (empty($orderno)) {
            return Constant::PARAMETER_ARRAY_DEFAULT;
        }

        $select = [
            Constant::DB_TABLE_PRIMARY, //订单 主键id
            Constant::DB_TABLE_UNIQUE_ID, //订单 唯一id
            Constant::DB_TABLE_ORDER_NO . ' as ' . Constant::DB_TABLE_AMAZON_ORDER_ID, //订单no
            Constant::DB_TABLE_ORDER_STATUS, //订单状态 Pending Shipped Canceled
            Constant::DB_TABLE_SHIP_SERVICE_LEVEL, //发货优先级
            Constant::DB_TABLE_AMOUNT, //订单金额
            Constant::DB_TABLE_CURRENCY_CODE, //交易货币
            Constant::DB_TABLE_CURRENCY_CODE . ' as ' . Constant::DB_TABLE_CURRENCY, //交易货币
            Constant::DB_TABLE_COUNTRY . ' as ' . Constant::DB_TABLE_COUNTRY_CODE, //寄送地址 国家代码
            Constant::DB_TABLE_COUNTRY, //订单国家
            Constant::DB_TABLE_ORDER_AT . ' as ' . Constant::DB_TABLE_PURCHASE_DATE, //下单日期(当前国家对应的时间)
        ];

        $where = [
            Constant::DB_TABLE_ORDER_NO => $orderno,
            Constant::DB_TABLE_TYPE => $type,
            Constant::DB_TABLE_PLATFORM => strtolower($platform),
        ];

        $orderStatusData = static::getPullOrderConfig(Constant::DB_TABLE_ORDER_STATUS); //订单状态 -1:Matching 0:Pending 1:Shipped 2:Canceled 3:Failure 默认:-1
        $field = Constant::DB_TABLE_ORDER_STATUS;
        $data = $orderStatusData;
        $dataType = 'string';
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = data_get($orderStatusData, -1, Constant::PARAMETER_STRING_DEFAULT);
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;

        $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];
        $handleData = [
            //'items.*' . Constant::LINKER . Constant::DB_TABLE_ORDER_STATUS => FunctionHelper::getExePlanHandleData(...$parameters),
            'items.*' . Constant::LINKER . Constant::DB_TABLE_SHIP_SERVICE_LEVEL => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_SHIP_SERVICE_LEVEL, '', Constant::PARAMETER_ARRAY_DEFAULT, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            'items.*' . Constant::LINKER . Constant::DB_TABLE_PURCHASE_DATE => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_PURCHASE_DATE, '', Constant::PARAMETER_ARRAY_DEFAULT, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            Constant::DB_TABLE_ORDER_STATUS => FunctionHelper::getExePlanHandleData(...$parameters),
        ];


        $joinData = Constant::PARAMETER_ARRAY_DEFAULT;

        $itemHandleData = [
            Constant::DB_TABLE_ORDER_STATUS => FunctionHelper::getExePlanHandleData(...$parameters),
        ];
        $itemSelect = [
            Constant::DB_TABLE_PRIMARY, //订单item id
            Constant::DB_TABLE_ORDER_ID, //订单id
            Constant::DB_TABLE_SKU . ' as ' . Constant::DB_TABLE_SELLER_SKU, //产品店铺sku
            Constant::DB_TABLE_PRICE . ' as ' . Constant::DB_TABLE_TTEM_PRICE_AMOUNT, //订单中sku的金额
            Constant::DB_TABLE_PROMOTION_DISCOUNT_AMOUNT, //促销所产生的折扣金额
            Constant::DB_TABLE_ASIN, //asin
            Constant::DB_TABLE_QUANTITY_ORDERED, //订单中的sku件数
            Constant::DB_TABLE_ORDER_NO . ' as ' . Constant::DB_TABLE_AMAZON_ORDER_ID, //亚马逊订单
            Constant::DB_TABLE_AMOUNT, //订单产品金额
            Constant::DB_TABLE_CURRENCY_CODE, //交易货币
            Constant::DB_TABLE_ORDER_COUNTRY . ' as ' . Constant::DB_TABLE_COUNTRY_CODE, //寄送地址 国家代码
            Constant::FILE_TITLE,
            Constant::DB_TABLE_IMG,
            Constant::DB_TABLE_PRODUCT_COUNTRY,
            Constant::DB_TABLE_ORDER_COUNTRY,
            Constant::DB_TABLE_SKU,
            Constant::DB_TABLE_ORDER_STATUS,
        ];
        $itemOrders = [[Constant::DB_TABLE_AMOUNT, Constant::DB_EXECUTION_PLAN_ORDER_DESC]];
        $with = [
            'items' => FunctionHelper::getExePlan(
                    Constant::DB_EXECUTION_PLAN_DEFAULT_CONNECTION . $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $itemSelect, [], $itemOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], $itemHandleData, Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联订单item
        ];
        $unset = Constant::PARAMETER_ARRAY_DEFAULT;
        $exePlan = FunctionHelper::getExePlan(Constant::DB_EXECUTION_PLAN_DEFAULT_CONNECTION . $storeId, null, static::getModelAlias(), Constant::PARAMETER_STRING_DEFAULT, $select, $where, [], null, null, false, [], false, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $itemHandleDataCallback = [
            Constant::DB_TABLE_UNIQUE_ID => function($item) use($storeId) {//延保时间
                $orderCountry = data_get($item, Constant::DB_TABLE_COUNTRY, ''); //订单国家
                $orderno = data_get($item, Constant::DB_TABLE_AMAZON_ORDER_ID, ''); //亚马逊订单
                $orderUniqueId = data_get($item, Constant::DB_TABLE_UNIQUE_ID, 0); //订单 唯一id

                if (empty($orderUniqueId)) {
                    $orderUniqueId = PlatformServiceManager::handle(Constant::PLATFORM_SERVICE_AMAZON, 'Order', 'getOrderUniqueId', [$storeId, $orderCountry, Constant::PLATFORM_SERVICE_AMAZON, $orderno]);
                    static::update($storeId, [Constant::DB_TABLE_PRIMARY => data_get($item, Constant::DB_TABLE_PRIMARY, 0)], [Constant::DB_TABLE_UNIQUE_ID => $orderUniqueId]); //更新 订单 唯一id
                }

                return $orderUniqueId;
            }
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
     * 更新订单总表账号数据
     * @param string $orderno 订单号
     * @param int $storeId 商城id
     * @param int $customerId 账号id
     * @param string $account 账号
     * @return int 影响记录条数
     */
    public static function updateOrderCustomer($orderno, $storeId, $customerId, $account) {
        //更新订单总表的账号和商城id数据
        $where = [
            Constant::DB_TABLE_ORDER_NO => $orderno,
            'or' => [
                Constant::DB_TABLE_STORE_ID => Constant::PARAMETER_INT_DEFAULT,
                Constant::DB_TABLE_CUSTOMER_PRIMARY => Constant::PARAMETER_INT_DEFAULT,
                Constant::DB_TABLE_ACCOUNT => Constant::PARAMETER_STRING_DEFAULT,
            ],
        ];

        $data = [
            Constant::DB_TABLE_STORE_ID => DB::raw("IF(" . Constant::DB_TABLE_STORE_ID . "=" . Constant::PARAMETER_INT_DEFAULT . ", " . $storeId . ", " . Constant::DB_TABLE_STORE_ID . ")"),
            Constant::DB_TABLE_CUSTOMER_PRIMARY => DB::raw("IF(" . Constant::DB_TABLE_CUSTOMER_PRIMARY . "=" . Constant::PARAMETER_INT_DEFAULT . ", " . $customerId . ", " . Constant::DB_TABLE_CUSTOMER_PRIMARY . ")"),
            Constant::DB_TABLE_ACCOUNT => DB::raw("IF(" . Constant::DB_TABLE_ACCOUNT . "='" . Constant::PARAMETER_STRING_DEFAULT . "', '" . $account . "', " . Constant::DB_TABLE_ACCOUNT . ")"),
        ];

        return static::update($storeId, $where, $data);
    }

    /**
     * 获取订单信息
     * @param string $orderno 订单id
     * @param string $country 订单国家
     * @param string $platform 订单平台
     * @param int $storeId 商城id 默认：1
     * @param boolean $isUseLocal 是否从本地获取订单数据 true：是 false：否  默认：true
     * @return array 订单数据
     */
    public static function getOrderData($orderno, $country, $platform = Constant::PLATFORM_SERVICE_AMAZON, $storeId = Constant::ORDER_STATUS_SHIPPED_INT, $isUseLocal = true) {

        $_parameters = [
            'orderno' => $orderno,
            'order_country' => $country,
        ];

        if ($isUseLocal === false) {
            return static::handlePull($storeId, $platform, $_parameters); //获取亚马逊订单数据
        }

        //优先获取本系统的订单数据
        $orderData = static::getOrderDetails($storeId, $orderno, Constant::DB_TABLE_PLATFORM, $platform);
        if ($orderData && data_get($orderData, 'items', [])) {
            return Response::getDefaultResponseData(1, '', $orderData);
        }

        return static::handlePull($storeId, $platform, $_parameters); //获取亚马逊订单数据
    }

    /**
     * 修复拉取失败的订单
     * @return boolean
     */
    public static function repair() {

        $tag = static::getCacheTags();
        $service = static::getNamespaceClass();
        $method = __FUNCTION__;
        $parameters = func_get_args();
        $_cacheKey = implode(':', [$tag, $method, md5(json_encode($parameters))]); //拉取时段cacheKey

        $has = static::handleCache($tag, FunctionHelper::getJobData($service, 'get', [$_cacheKey]));
        if ($has === '0') {//如果 $_cacheKey 对应批次不需要拉取，就直接返回
            return false;
        }

        $dictData = static::getPullOrderConfig('pull_order');
        $ttl = data_get($dictData, 'ttl', 600); //批次已经在消息队列里面的缓存时间 单位秒

        $storeId = 2;
//        $where = [
//            'oi.id' => null,
//        ];
//        $data = static::getModel($storeId)
//                ->from('orders as o')
//                ->leftJoin('order_items as oi', function ($join) {
//                    $join->on('oi.orderno', '=', 'o.orderno')->where('oi.status', 1);
//                })
//                ->buildWhere($where)
//                ->select(['o.orderno', 'o.country'])
//                ->limit(100)
//                ->get()
//        ;
//
//        $repairType = 'item';
//        if ($data->isEmpty()) {
//            $where = [
//                'o.is_repair' => 0,
//            ];
//            $data = static::getModel($storeId)
//                    ->from('orders as o')
//                    ->buildWhere($where)
//                    ->select(['o.orderno', 'o.country'])
//                    ->limit(100)
//                    ->get()
//            ;
//
//            $repairType = 'item_is_repair';
//
//            if ($data->isEmpty()) {

        $where = [
            'o.unique_id' => 0,
        ];
        $data = static::getModel($storeId)
                ->from('orders as o')
                ->buildWhere($where)
                ->select(['o.orderno', 'o.country'])
                ->orderBy(Constant::DB_TABLE_PRIMARY, Constant::DB_EXECUTION_PLAN_ORDER_ASC)
                ->limit(100)
                ->get()
        ;
        $repairType = 'item_unique_id';
        if ($data->isEmpty()) {
            dump('====' . $repairType . '=={amazon-order-pull}======laterQueue=========10s');

            FunctionHelper::laterQueue(10, FunctionHelper::getJobData($service, $method, $parameters), null, '{amazon-order-pull}'); //把任务加入消息队列
            static::handleCache($tag, FunctionHelper::getJobData($service, 'put', [$_cacheKey, 1, $ttl])); //记录 $_cacheKey 对应的批次已经在消息队列里面,并且需要继续拉取
            //设置 $_cacheKey 对应批次不需要拉取
            //static::handleCache($tag, FunctionHelper::getJobData($service, 'put', [$_cacheKey, 0, $ttl]));
            return false;
        }
        //}
//        }

        $storeIds = Store::pluck('id');
        foreach ($data as $item) {
            $_parameters = [
                'orderno' => data_get($item, 'orderno'),
                'order_country' => data_get($item, 'country'),
            ];
            $orderRet = static::handlePull(2, Constant::PLATFORM_SERVICE_AMAZON, $_parameters);

            if (data_get($orderRet, Constant::RESPONSE_CODE_KEY, 0) == 1) {
                $uniqueId = data_get($orderRet, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_UNIQUE_ID, 0); //订单唯一id
                $amount = data_get($orderRet, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_AMOUNT, 0); //订单金额
                $currencyCode = data_get($orderRet, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_CURRENCY_CODE, ''); //交易货币
                $orderData = data_get($orderRet, Constant::RESPONSE_DATA_KEY, Constant::PARAMETER_ARRAY_DEFAULT);
                $orderId = data_get($orderRet, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_AMAZON_ORDER_ID, -1); //订单号

                foreach ($storeIds as $storeId) {
                    FunctionHelper::setTimezone($storeId);
                    $updateAt = Carbon::now()->toDateTimeString();
                    $updateData = [
                        Constant::DB_TABLE_ORDER_UNIQUE_ID => $uniqueId, //订单唯一id
                        Constant::DB_TABLE_AMOUNT => $amount,
                        Constant::DB_TABLE_CURRENCY_CODE => $currencyCode,
                        Constant::DB_TABLE_CONTENT => json_encode($orderData),
                        Constant::DB_TABLE_OLD_UPDATED_AT => DB::raw("IF(" . Constant::DB_TABLE_OLD_UPDATED_AT . "='', '$updateAt', " . Constant::DB_TABLE_OLD_UPDATED_AT . ")")
                    ];
                    OrderWarrantyService::update($storeId, [Constant::DB_TABLE_ORDER_NO => $orderId], $updateData);
                }
            }
        }

        FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters), null, '{amazon-order-pull}'); //把任务加入消息队列

        static::handleCache($tag, FunctionHelper::getJobData($service, 'put', [$_cacheKey, 1, $ttl])); //记录 $_cacheKey 对应的批次已经在消息队列里面,并且需要继续拉取

        dump('====' . $repairType . '=={amazon-order-pull}======count=========' . $data->count());

        return true;
    }

    /**
     * 拉取订单
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $parameters 拉取平台订单参数
     * @param array $orderItemData 平台订单item 数据
     * @return type
     */
    public static function handlePull($storeId, $platform, $parameters, $orderItemData = null) {

        $orderItemData = $orderItemData ? $orderItemData : PlatformServiceManager::handle($platform, 'Order', 'getOrderItem', [$storeId, $parameters]);
        if (empty($orderItemData)) {
            return Response::getDefaultResponseData(0, 'Order number does not exist.');
        }

        //更新订单数据
        $data = current($orderItemData);
        $orderCountry = strtoupper(trim(data_get($data, Constant::DB_TABLE_ORDER_COUNTRY, '')));
        $orderData = static::pullAmazonOrder($storeId, $orderItemData, $orderCountry);

        return Response::getDefaultResponseData(($orderData ? 1 : 0), ($orderData ? '' : 'Order number does not exist.'), $orderData);
    }

    /**
     * 判断订单是否存在
     * @param int $storeId 品牌id
     * @param string $orderNo 订单号
     * @return int
     */
    public static function isExists($storeId, $orderNo, $platform = Constant::PLATFORM_SERVICE_AMAZON) {

        $isExists = static::existsOrFirst($storeId, '', [Constant::DB_TABLE_ORDER_NO => $orderNo]);
        if ($isExists) {
            return $isExists;
        }

        $parameters = [
            'orderno' => $orderNo,
        ];
        $orderData = static::handlePull($storeId, $platform, $parameters);

        return data_get($orderData, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT);
    }

}
