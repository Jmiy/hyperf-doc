<?php

namespace App\Services;

use App\Services\Store\Amazon\Customers\Customer;
use App\Constants\Constant;
use Carbon\Carbon;
use Hyperf\Utils\Arr;
use Hyperf\DbConnection\Db as DB;
use App\Utils\FunctionHelper;
use App\Utils\Response;
use App\Services\Platform\OrderService;
use App\Services\Platform\OrderItemService;
use App\Utils\Support\Facades\Redis;

class OrderReviewService extends BaseService {

    public static $orderReviewName = 'or';
    public static $customerOrderName = 'co';
    public static $platformOrderName = 'po';

    /**
     * 审核状态
     * @var array
     */
    public static $auditMap = [
        -1 => '未提交',
        0 => '未审核',
        1 => '审核通过',
        2 => '审核不通过',
        3 => '其他',
        4 => '索要rv链接',
        5 => '已返现'
    ];

    /**
     * 礼品名
     * @var array
     */
    public static $rewardType = [
        -1 => '',
        0 => '其他',
        1 => '礼品卡',
        2 => '折扣码',
        3 => '实物',
        5 => '积分',
        6 => '现金'
    ];

    /**
     * 列表
     * @param array $params 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认:true
     * @param boolean $isPage  是否分页 true:是 false:否 默认:true
     * @param array $select  查询字段
     * @param boolean $isRaw 是否原始 select true:是 false:否 默认:false
     * @param boolean $isGetQuery 是否获取 query
     * @param boolean $isOnlyGetCount 是否仅仅获取总记录数
     * @return array|\Hyperf\Database\Model\Builder 列表数据|Builder
     */
    public static function getListData($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {
        $_data = static::getPublicData($params, []);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);
        $order = data_get($params, Constant::ORDER_BY, data_get($_data, Constant::ORDER, []));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::ACT_LIMIT_KEY, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, Constant::PARAMETER_INT_DEFAULT));
        $storeId = Arr::get($params, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);

        $select = $select ? $select : static::getSelect();

        $isExport = data_get($params, 'is_export', data_get($params, 'srcParameters.0.is_export', Constant::PARAMETER_INT_DEFAULT));

        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $handleData = [];

        $joinData = [
            FunctionHelper::getExePlanJoinData(DB::raw('`ptxcrm`.`crm_platform_orders` as crm_po'), function ($join) use ($storeId) {
                        $join->on([[static::$platformOrderName . Constant::LINKER . Constant::DB_TABLE_UNIQUE_ID, '=', static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_ORDER_UNIQUE_ID]])
                                ->where(static::$platformOrderName . Constant::LINKER . Constant::DB_TABLE_STATUS, '=', 1)
                        ;
                    }),
        ];
        $itemSelect = [
            Constant::DB_TABLE_ORDER_UNIQUE_ID,
            Constant::DB_TABLE_PRODUCT_UNIQUE_ID, //产品唯一id
            Constant::DB_TABLE_SKU, //产品店铺sku
            Constant::DB_TABLE_ASIN, //asin
            Constant::FILE_TITLE,
            Constant::DB_TABLE_QUANTITY,
        ];
        $itemOrders = [[Constant::DB_TABLE_AMOUNT, Constant::DB_EXECUTION_PLAN_ORDER_DESC]];

        $itemProductCategorySelect = [
            Constant::DB_TABLE_PRODUCT_UNIQUE_ID, //产品唯一id
            'one_category_name',
            'two_category_name',
            'three_category_name',
        ];

        $itemProductCategoryWith = [
            'product_category' => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $itemProductCategorySelect, [], [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], Constant::PARAMETER_ARRAY_DEFAULT, 'hasOne', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联物流数据
        ];

        $with = [
            'items' => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $itemSelect, [], $itemOrders, null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, $itemProductCategoryWith, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联订单item
        ];
        $unset = [];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getNamespaceClass(), 'order_reviews as ' . static::$orderReviewName, $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        if (data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            $dbExecutionPlan = [
                Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            ];
        } else {
            $auditStatusData = static::$auditMap; //审核状态 -1:未提交审核 0:未审核 1:已通过 2:未通过 3:其他

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
                Constant::AUDIT_STATUS => FunctionHelper::getExePlanHandleData(Constant::AUDIT_STATUS, data_get($auditStatusData, '-1', ''), $auditStatusData, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only), //审核状态
                Constant::DB_TABLE_TYPE => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_TYPE, data_get(static::$rewardType, '-1', ''), static::$rewardType, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only), //审核状态
            ];

            $exePlan[Constant::DB_EXECUTION_PLAN_HANDLE_DATA] = $handleData;

            $itemHandleDataCallback = [
                Constant::AUDIT_STATUS => function($item) {//审核状态
                    return (data_get($item, Constant::DB_TABLE_CONTACT_US_ID) > 0 && data_get($item, Constant::AUDIT_STATUS) == -1)  ? '已发售后邮件' : data_get($item, Constant::AUDIT_STATUS);
                },
                Constant::DB_TABLE_REVIEW_TIME => function($item) {
                    return data_get($item, Constant::DB_TABLE_REVIEW_TIME, Constant::PARAMETER_STRING_DEFAULT) == '2019-01-01 00:00:00' ? '' : data_get($item, Constant::DB_TABLE_REVIEW_TIME);
                },
                Constant::DB_TABLE_QUANTITY => function($item) {
                    $orderItems = data_get($item, 'items', Constant::PARAMETER_ARRAY_DEFAULT);
                    $quantities = 0;
                    foreach ($orderItems as $orderItem) {
                        $quantities += data_get($orderItem, Constant::DB_TABLE_QUANTITY, Constant::PARAMETER_INT_DEFAULT);
                    }
                    return $quantities;
                },
                Constant::DB_TABLE_SKU => function($item) {
                    $sku = data_get($item, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT);
                    if (empty($sku)) {
                        $sku = data_get($item, 'items.0.'.Constant::DB_TABLE_SKU);
                    }
                    return $sku;
                },
                Constant::DB_TABLE_ASIN => function($item) {
                    $asin = data_get($item, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT);
                    if (empty($asin)) {
                        $asin = data_get($item, 'items.0.'.Constant::DB_TABLE_ASIN);
                    }
                    return $asin;
                },
                'items' => function($item) {
                    $sku = data_get($item, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT);
                    $asin = data_get($item, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT);
                    return collect(data_get($item, 'items', Constant::PARAMETER_ARRAY_DEFAULT))->filter(function ($value) use ($sku, $asin) {
                        return data_get($value, Constant::DB_TABLE_SKU) == $sku && data_get($value, Constant::DB_TABLE_ASIN) == $asin;
                    })->values();
                },
                'title' => function($item) {
                    return data_get($item, 'items.0.title', Constant::PARAMETER_STRING_DEFAULT);
                }
            ];

            if ($isExport) {
                $itemHandleDataCallback['product_quantity'] = function($item) {
                    return data_get($item, 'items.0.quantity', Constant::PARAMETER_INT_DEFAULT);
                };
            }

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
     * 获取公共参数
     * @param array $params 请求参数
     * @param array $order 排序
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $_where = Constant::PARAMETER_ARRAY_DEFAULT;
        $customizeWhere = Constant::PARAMETER_ARRAY_DEFAULT;

        $where = Constant::PARAMETER_ARRAY_DEFAULT;
        $account = $params[Constant::DB_TABLE_ACCOUNT] ?? Constant::PARAMETER_STRING_DEFAULT; //账号
        $orderno = $params[Constant::DB_TABLE_ORDER_NO] ?? Constant::PARAMETER_STRING_DEFAULT; //订单号
        $country = $params[Constant::DB_TABLE_COUNTRY] ?? Constant::PARAMETER_STRING_DEFAULT; //国家
        $asin = $params[Constant::DB_TABLE_ASIN] ?? Constant::PARAMETER_STRING_DEFAULT; //asin
        $type = $params[Constant::DB_TABLE_TYPE] ?? Constant::PARAMETER_STRING_DEFAULT; //礼品类型
        $startTime = $params[Constant::START_TIME] ?? Constant::PARAMETER_STRING_DEFAULT; //开始时间
        $endTime = $params[Constant::DB_TABLE_END_TIME] ?? Constant::PARAMETER_STRING_DEFAULT; //结束时间
        $auditStatus = $params[Constant::AUDIT_STATUS] ?? Constant::PARAMETER_STRING_DEFAULT; //审核状态
        $star = $params[Constant::DB_TABLE_STAR] ?? Constant::PARAMETER_INT_DEFAULT; //星级
        $reviewStartTime = data_get($params, 'review_start_time', Constant::PARAMETER_STRING_DEFAULT); //评论开始时间
        $reviewEndTime = data_get($params, 'review_end_time', Constant::PARAMETER_STRING_DEFAULT); //评论结束时间

//        if ($account) {
//            $where[] = [static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_ACCOUNT, '=', $account];
//        }
//
//        if ($type !== '') {
//            $where[] = [static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_TYPE, '=', $type];
//        }

        if ($reviewStartTime !== '') {
            $where[] = [static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_REVIEW_TIME, '>=', $reviewStartTime];
        }

        if ($reviewEndTime !== '') {
            $where[] = [static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_REVIEW_TIME, '<=', $reviewEndTime];
        }

        if ($auditStatus !== '' && !is_array($auditStatus)) {
            $auditStatus = intval($auditStatus);
            switch ($auditStatus) {
                case -2 : //售后邮件已发
                    $where[] = [static::$orderReviewName . Constant::LINKER . Constant::AUDIT_STATUS, '=', -1];
                    $where[] = [static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_CONTACT_US_ID, '>', 0];
                    break;
                case -1 : //索评流程没完成
                    $where[] = [static::$orderReviewName . Constant::LINKER . Constant::AUDIT_STATUS, '=', $auditStatus];
                    $where[] = [static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_CONTACT_US_ID, '=', 0];
                    break;
                default :
                    $where[] = [static::$orderReviewName . Constant::LINKER . Constant::AUDIT_STATUS, '=', $auditStatus];
            }
        }

//        if ($star) {
//            $where[] = [static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_STAR, '=', $star];
//        }

        if (data_get($params, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT)) {
            $_where[static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_PRIMARY] = $params[Constant::DB_TABLE_PRIMARY];
        }

        if ($where) {
            $_where[] = $where;
        }

        $orderno = data_get($params, Constant::DB_TABLE_ORDER_NO); //订单号
        if ($orderno) {
            $_where[static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_ORDER_NO] = $orderno;
        }

        $account = data_get($params, Constant::DB_TABLE_ACCOUNT); //邮箱
        if ($account) {
            $_where[static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_ACCOUNT] = $account;
        }

        $star = data_get($params, Constant::DB_TABLE_STAR); //星级
        if ($star) {
            $_where[static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_STAR] = $star;
        }

        $type = data_get($params, Constant::DB_TABLE_TYPE); //礼品类型
        if ($type) {
            $_where[static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_TYPE] = $type;
        }

        $reviewer = data_get($params, Constant::DB_TABLE_REVIEWER); //审核人
        if ($reviewer) {
            $_where[static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_REVIEWER] = $reviewer;
        }

        if ($startTime || $endTime) {
            $startTime = $startTime ? $startTime : '2018-01-01 00:00:00'; //开始时间
            $endTime = $endTime ? $endTime : Carbon::now()->toDateTimeString(); //结束时间
            $customizeWhere = [
                [
                    Constant::METHOD_KEY => 'whereBetween',
                    Constant::PARAMETERS_KEY => [static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_CREATED_AT, [$startTime, $endTime]],
                ]
            ];
        }

        if (is_array($auditStatus)) {
            $statusCustomizeWhere = [
                [
                    Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                    Constant::PARAMETERS_KEY => [function ($query) use ($auditStatus) {
                        if (is_array($auditStatus)) {
                            foreach ($auditStatus as $status) {
                                $status = intval($status);
                                switch ($status) {
                                    case -2 : //售后邮件已发
                                        $query->orWhere(static::$orderReviewName . Constant::LINKER . Constant::AUDIT_STATUS, '=', -1)
                                            ->Where(static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_CONTACT_US_ID, '>', 0);
                                        break;
                                    case -1 : //索评流程没完成
                                        $query->orWhere(static::$orderReviewName . Constant::LINKER . Constant::AUDIT_STATUS, '=', $status)
                                            ->Where(static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_CONTACT_US_ID, '=', 0);
                                        break;
                                    default :
                                        $query->orWhere(static::$orderReviewName . Constant::LINKER . Constant::AUDIT_STATUS, '=', $status);
                                }
                            }
                        }
                    }],
                ]
            ];

            $customizeWhere = Arr::collapse([$customizeWhere, $statusCustomizeWhere]);
        }

        if ($country) {//国家简写
            $_where[static::$platformOrderName . Constant::LINKER . Constant::DB_TABLE_COUNTRY] = $country;
        }

        $sku = $params['sku'] ?? '';
        $whereFields = [];
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

        $isLeftCategory = false;
        $one_category_code = $params['one_category_code'] ?? '';
        if ($one_category_code) {
            $whereFields[] = [
                'field' => 'ppc.one_category_code',
                Constant::DB_TABLE_VALUE => $one_category_code,
            ];

//            $whereFields[] = [
//                'field' => 'ppc.one_category_code',
//                Constant::METHOD_KEY => 'where',
//                Constant::PARAMETERS_KEY => [
//                    'ppc.one_category_code', $one_category_code,
//                ],
//            ];
            $isLeftCategory = true;
        }

        $two_category_code = $params['two_category_code'] ?? '';
        if ($two_category_code) {
            $whereFields[] = [
                'field' => 'ppc.two_category_code',
                Constant::DB_TABLE_VALUE => $two_category_code,
            ];
            $isLeftCategory = true;
        }

        $three_category_code = $params['three_category_code'] ?? '';
        if ($three_category_code) {
            $whereFields[] = [
                'field' => 'ppc.three_category_code',
                Constant::DB_TABLE_VALUE => $three_category_code,
            ];
            $isLeftCategory = true;
        }

        if ($whereFields) {
            $whereColumns = [
                [
                    'foreignKey' => Constant::DB_TABLE_ORDER_UNIQUE_ID,
                    'localKey' => static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_ORDER_UNIQUE_ID,
                ]
            ];
            $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0); //商城id

            $customizeWhere = Arr::collapse([$customizeWhere, OrderItemService::buildCustomizeWhere($storeId, $whereFields, $whereColumns, ['isLeftCategory' => $isLeftCategory])]);
        }

        if ($customizeWhere) {
            $_where['{customizeWhere}'] = $customizeWhere;
        }

        $order = $order ? $order : [[static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_PRIMARY, Constant::ORDER_DESC]];
        return Arr::collapse([parent::getPublicData($params, $order), [
                        Constant::DB_EXECUTION_PLAN_WHERE => $_where,
        ]]);
    }

    /**
     * 审核
     * @param int $storeId 官网id
     * @param array $ids 审核id
     * @param int $auditStatus 审核状态 0:未审核 1:审核通过 2:审核不通过 3:其他 4:二次催评
     * @param string $reviewer 审核人
     * @param string $remarks 备注
     * @param boolean $isAutoAudit 是否自动审核  true:是  false:否  默认：false
     * @return array
     */
    public static function audit($storeId, $ids, $auditStatus, $reviewer, $remarks, $isAutoAudit = false) {
        $result = Response::getDefaultResponseData(Constant::RESPONSE_SUCCESS_CODE);

        //获取索评记录
        $where = [
            Constant::DB_TABLE_PRIMARY => $ids,
        ];
        $reviews = static::getModel($storeId)->buildWhere($where)->get();
        if ($reviews->isEmpty()) {
            data_set($result, Constant::RESPONSE_CODE_KEY, 0);
            data_set($result, Constant::RESPONSE_MSG_KEY, '数据不存在');
            return $result;
        }

        $make = static::getMake();
        $updateData = [
            Constant::AUDIT_STATUS => $auditStatus, //审核状态
            Constant::DB_TABLE_REMARKS => $remarks, //审核备注
            Constant::DB_TABLE_REVIEWER => $reviewer, //审核人
        ];

        $activityAuditLog = static::createModel($storeId, 'ActivityAuditLog');
        $updateIds = [];
        $auditLogData = [];
        $creditHistory = [];
        $toEmailReviews = [];

        /**
         * 1.审核通过邮件只发送一次。
         * 2.其他邮件不限制次数，当超过5次以后提示
         */
        $tipEmail = [];
        foreach ($reviews as $item) {

            $oldAuditStatus = data_get($item, Constant::AUDIT_STATUS);
            if ($oldAuditStatus == 1) {
                continue;
            }

            //订单索评id
            $extId = Arr::get($item, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
            if ($auditStatus != 3) {//如果审核状态不是其他，就判断是否超过5次审核
                $auditLogWhere = [
                    Constant::DB_TABLE_EXT_TYPE => $make,
                    Constant::DB_TABLE_EXT_ID => $extId,
                    Constant::AUDIT_STATUS => $auditStatus, //审核状态 0:未审核 1:审核通过 2:审核不通过 3:其他 4:二次催评
                ];
                $count = $activityAuditLog->buildWhere($auditLogWhere)->count();
                if ($count >= 5) {//超过5次在确认按钮下面，给一个提示文案：你已经重复审核多次，可能会收到投诉邮件，请注意！
                    $tipEmail[] = data_get($item, Constant::DB_TABLE_ACCOUNT);
                }
            }

            //需要更新的订单索评id
            $updateIds[] = $extId;

            //审核流水
            $auditLogData[] = [
                Constant::DB_TABLE_EXT_TYPE => $make,
                Constant::DB_TABLE_EXT_ID => $extId,
                'audit_data' => json_encode($updateData, JSON_UNESCAPED_UNICODE),
                Constant::AUDIT_STATUS => $auditStatus,
                'reviewer' => $reviewer, //审核人
            ];

            $star = data_get($item, Constant::DB_TABLE_STAR, Constant::PARAMETER_INT_DEFAULT);
            $type = data_get($item, Constant::DB_TABLE_TYPE, Constant::PARAMETER_INT_DEFAULT); //礼品类型: 0:其他 1:礼品卡 2:coupon 3:实物 5:积分

            if ($auditStatus == 4) {//如果是索要rv链接，就都发送邮件
                //需发送邮件
                $toEmailReviews[] = $item;
            } else {

                if ($isAutoAudit === true) {
                    //需发送邮件
                    $toEmailReviews[] = $item;
                } else {
                    //非 折扣码/积分奖励(即：礼品卡，实物奖励以及其他) 并且 4星级以上评星,奖励为 礼品卡，实物奖励以及其他,审核时发送奖励及邮件
                    if (in_array($type, [0, 1, 3]) && $star >= 4) {

                        //需发送邮件
                        $toEmailReviews[] = $item;

                        //礼品卡奖励
                        if ($type == 1 && $auditStatus == 2) {//审核失败时，需要更新礼品卡的状态为未使用
                            $couponWhere = [
                                Constant::DB_EXECUTION_PLAN_GROUP => 'reward',
                                Constant::RESPONSE_CODE_KEY => data_get($item, Constant::DB_TABLE_TYPE_VALUE),
                                Constant::DB_TABLE_COUNTRY => data_get($item, Constant::DB_TABLE_COUNTRY),
                                Constant::DB_TABLE_ASIN => data_get($item, Constant::DB_TABLE_ASIN),
                            ];
                            $couponUpdate = [
                                Constant::DB_TABLE_STATUS => 1,
                                'extinfo' => ''
                            ];
                            CouponService::update($storeId, $couponWhere, $couponUpdate);
                        }
                    }
                }
            }
        }

        //加积分奖励
        if (!empty($creditHistory)) {
            foreach ($creditHistory as $creditItem) {
                CreditService::handle($creditItem);
            }
        }

        //更新审核状态
        if ($updateIds) {
            static::getModel($storeId)->buildWhere([Constant::DB_TABLE_PRIMARY => $updateIds])->update($updateData);
        }

        //添加审核流水
        if ($auditLogData) {
            $activityAuditLog->insert($auditLogData);
        }

        //邮件发送
        if (!empty($toEmailReviews)) {
            foreach ($toEmailReviews as $toEmailReview) {
                $ret = static::handleReviewEmail($storeId, 0, $auditStatus, $toEmailReview);
                $result[Constant::RESPONSE_DATA_KEY]['email_debug'] = $ret;
            }
        }

        if (!empty($tipEmail)) {
            $tipEmail = implode(',', array_unique(array_filter($tipEmail)));
            return Response::getDefaultResponseData(10, $tipEmail . ' 你已经重复审核多次，可能会收到投诉邮件，请注意！');
        }

        return $result;
    }

    /**
     * 审核邮件发送
     * @param int $storeId 官网id
     * @param int $reviewId 订单索评主键id
     * @param int $auditStatus 审核状态
     * @param array $review 索评数据
     * @return mixed
     */
    public static function handleReviewEmail($storeId, $reviewId = 0, $auditStatus = 1, $review = []) {

        $result = Response::getDefaultResponseData(1);

        // 邮件开关
        $notSendEmail = DictStoreService::getByTypeAndKey($storeId, Constant::ORDER_REVIEW, 'not_send_email', true);
        if ($notSendEmail) {
            return false;
        }

        if (empty($review) && empty($reviewId)) {
            return false;
        }

        if (!empty($reviewId)) {
            $review = static::getModel($storeId)->buildWhere([Constant::DB_TABLE_PRIMARY => $reviewId])->first();
            if (empty($review)) {
                return false;
            }

            //非 折扣码/积分奖励 只能后台审核
            $type = data_get($review, Constant::DB_TABLE_TYPE, Constant::PARAMETER_INT_DEFAULT); //礼品类型: 0:其他 1:礼品卡 2:coupon 3:实物 5:积分
            if (!in_array($type, [2, 5])) {
                return false;
            }
        }

        $group = 'order_review';
        $emailType = 'audit' . ($auditStatus == 1 ? '' : ('_' . $auditStatus));
        $toEmail = data_get($review, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT);
        $extId = data_get($review, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
        $extType = static::getMake();
        $actId = 0;

        if ($auditStatus == 1) {
            //审核通过邮件是否已经发送
            $where = [
                Constant::DB_TABLE_STORE_ID => $storeId,
                Constant::DB_EXECUTION_PLAN_GROUP => $group,
                Constant::DB_TABLE_TYPE => $emailType,
                'to_email' => $toEmail,
                Constant::DB_TABLE_EXT_ID => $extId,
                Constant::DB_TABLE_EXT_TYPE => $extType,
                Constant::DB_TABLE_ACT_ID => $actId,
            ];
            $isExists = EmailService::exists($storeId, '', $where);
            if ($isExists) {
                return Response::getDefaultResponseData(39003, 'email exist');
            }
        }

        $extService = static::getNamespaceClass();
        $extMethod = 'getEmailData';
        $extParameters = [$storeId, $auditStatus, $review];

        $extData = [
            Constant::SERVICE_KEY => $extService,
            Constant::METHOD_KEY => $extMethod,
            Constant::PARAMETERS_KEY => $extParameters,
            Constant::DB_EXECUTION_PLAN_CALLBACK => [],
            'storeDictType' => 'audit_email'
        ];

        $isUrgeRv = data_get($review, 'is_urge_rv', Constant::PARAMETER_STRING_DEFAULT);
        if ($isUrgeRv) {
            $extData['storeDictType'] = 'urge_rv_email';
        }

        $service = EmailService::getNamespaceClass();
        $method = 'handle';
        $parameters = [$storeId, $toEmail, $group, $emailType, '', $extId, $extType, $extData];

        return FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters));
    }

    /**
     * 获取审核结果邮件数据
     * @param int $storeId
     * @param int $auditStatus
     * @param array $review
     * @return array
     */
    public static function getEmailData($storeId, $auditStatus, $review) {

        $type = data_get($review, Constant::DB_TABLE_TYPE, Constant::PARAMETER_INT_DEFAULT); //礼品类型: 0:其他 1:礼品卡 2:coupon 3:实物 5:积分
        $country = data_get($review, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT);

        $emailCountry = '';
        $langCountries = DictStoreService::getByTypeAndKey($storeId, 'lang', 'country', true);
        $orderData = OrderService::getOrderData(data_get($review, Constant::DB_TABLE_ORDER_NO), '', Constant::PLATFORM_SERVICE_AMAZON, $storeId, true);
        if (!empty($langCountries)) {
            $langCountries = explode(',', $langCountries);
            if (!empty($langCountries)) {
                $orderCountry = strtoupper(data_get($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT));
                if (in_array($orderCountry, $langCountries)) {
                    $emailCountry = $orderCountry;
                }
            }
        }

        $contentKey = '';
        $subjectKey = '';
        switch ($auditStatus) {
            case 1://审核通过
                //折扣码或者积分奖励或者mpow实物
                if ($type == 2 || $type == 5 || (in_array($type, [3]) && in_array($storeId, [1,2,3]))) {
                    $contentKey = "view_review_audit_{$auditStatus}_{$type}";
                    $subjectKey = "view_review_audit_{$auditStatus}_{$type}_subject";

                } elseif (in_array($type, [0, 1, 3])) { //现金或者礼品卡或者实物
                    $contentKey = "view_review_audit_cash";
                    $subjectKey = "view_review_audit_cash_subject";
                }

                break;

            case 2://审核不通过
                $contentKey = "view_review_audit_{$auditStatus}";
                $subjectKey = "view_review_audit_{$auditStatus}_subject";
                break;

            case 4://索要邮箱rv
                $contentKey = "view_review_link";
                $subjectKey = "view_review_link_subject";
                break;

            case 6://系统催评
                $contentKey = "view_review_urge_link";
                $subjectKey = "view_review_urge_link_subject";
                break;

            default:
                break;
        }

        if (empty($contentKey) || empty($subjectKey)) {
            return [];
        }

        $emailData = [
            Constant::RESPONSE_CODE_KEY => 1,
            'storeId' => $storeId,
            'actId' => '',
            'content' => '',
            'subject' => '',
            Constant::DB_TABLE_COUNTRY => '',
            Constant::DB_TABLE_IP => '',
            'extId' => '',
            'extType' => '',
        ];

        $where = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_TYPE => 'audit_email',
            'conf_key' => [
                $contentKey,
                $subjectKey,
                'from',
            ],
        ];

        if (!empty($emailCountry)) {
            $where[Constant::DB_TABLE_COUNTRY] = $emailCountry; //获取对应国家的邮件模板
        } else {
            $where[Constant::DB_TABLE_COUNTRY] = ['', 'all'];
        }

        $emailConf = DictStoreService::getModel($storeId)->buildWhere($where)->get();
        $emailConf = $emailConf->isEmpty() ? [] : array_column($emailConf->toArray(), NULL, 'conf_key');
        if (empty($emailConf)) {
            return [];
        }

        $emailView = Arr::get($emailConf, "$contentKey.conf_value", Constant::PARAMETER_STRING_DEFAULT);
        $subject = Arr::get($emailConf, "$subjectKey.conf_value", Constant::PARAMETER_STRING_DEFAULT);
        $from = Arr::get($emailConf, "from.conf_value", Constant::PARAMETER_STRING_DEFAULT);

        $sku = data_get($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . 'items' . Constant::LINKER . '0' . Constant::LINKER . Constant::DB_TABLE_SKU, '');
        data_set($emailData, 'subject', $subject . ($sku ? ('-' . $sku) : $sku));
        if (in_array($storeId, [1, 3])) {
            $reviewSku = data_get($review, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT);
            empty($reviewSku) && $reviewSku = $sku;
            $_subject = $subject . "[$reviewSku]";
            data_set($emailData, 'subject', $_subject);
        }
        if (in_array($storeId, [2])) {
            $_subject = $subject . data_get($review, Constant::DB_TABLE_ORDER_NO);
            data_set($emailData, 'subject', $_subject);
        }

        $data = CustomerInfoService::exists($storeId, data_get($review, Constant::DB_TABLE_CUSTOMER_PRIMARY), '', true);
        $firstName = data_get($data, Constant::DB_TABLE_FIRST_NAME, Constant::PARAMETER_STRING_DEFAULT);
        $fName = $firstName ? $firstName : FunctionHelper::handleAccount(data_get($data, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT));

        if (in_array($storeId, [1, 2, 3, 6, 8, 10])) {//mpow和vt和homasy和litom和atmoko  直接使用账户即可
            $fName = data_get($data, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT);
        }

        $replacePairs = [
            '*|FNAME|*' => $fName,
            '{{$account_name}}' => $fName,
            '{{$subject}}' => $subject . ($sku ? ('-' . $sku) : $sku),
            '*|FROM|*' => $from,
            '*|ORDER|*' => data_get($review, Constant::DB_TABLE_ORDER_NO, Constant::PARAMETER_INT_DEFAULT),
            '*|STAR|*' => data_get($review, Constant::DB_TABLE_STAR, Constant::PARAMETER_INT_DEFAULT),
        ];

        //审核通过
        if ($auditStatus == 1) {
            $replacePairs['*|REWARD|*'] = data_get($review, Constant::DB_TABLE_REWARD_NAME, Constant::PARAMETER_STRING_DEFAULT);
            $replacePairs['*|TYPE_VALUE|*'] = data_get($review, Constant::DB_TABLE_TYPE_VALUE, Constant::PARAMETER_STRING_DEFAULT);

            //奖励为折扣码
            if ($type == 2) {
                $startAt = data_get($review, Constant::DB_TABLE_START_AT, Constant::PARAMETER_STRING_DEFAULT);
                $endAt = data_get($review, Constant::DB_TABLE_END_AT, Constant::PARAMETER_STRING_DEFAULT);

                $replacePairs['*|ORDER_ID|*'] = data_get($review, Constant::DB_TABLE_TYPE_VALUE, Constant::PARAMETER_STRING_DEFAULT);
                $replacePairs['*|VALID_TIME|'] = "$startAt-$endAt";
                $replacePairs['*|VALID_TIME|*'] = "$startAt-$endAt";

                $amazonHostData = DictService::getListByType('amazon_host', 'dict_key', 'dict_value');
                $amazonHost = data_get($amazonHostData, $country, data_get($amazonHostData, 'US', ''));
                $replacePairs['*|AMZ_URL|*'] = "$amazonHost/dp/" . data_get($review, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT);
            }

            //实物
            if (in_array($type, [3])) {
                $replacePairs['*|IMG_URL|*'] = data_get($review, Constant::DB_TABLE_TYPE_VALUE, Constant::PARAMETER_STRING_DEFAULT);
            }
        }

        if (in_array($storeId, [1,2,3]) && in_array($type, [0, 1, 2])) { //mpow,vt,其他跟折扣码跟礼品卡
            preg_match('/[0-9]+/', data_get($review, Constant::DB_TABLE_REWARD_NAME, Constant::PARAMETER_STRING_DEFAULT), $matches);
            $value = data_get($matches, '0', Constant::PARAMETER_INT_DEFAULT);
            if (in_array($type, [2])) {
                $replacePairs['*|COUPON_VALUE|*'] = $value;
            }
            if (in_array($type, [1])) {
                $order = OrderService::existsOrFirst($storeId, '', [Constant::DB_TABLE_UNIQUE_ID => data_get($review, Constant::DB_TABLE_ORDER_UNIQUE_ID)], true);
                $currency = data_get($order, Constant::DB_TABLE_PRESENTMENT_CURRENCY, Constant::PARAMETER_STRING_DEFAULT);
                $currencyData = static::getConfig($storeId, 'currency');
                $replacePairs['*|GIFT_VALUE|*'] =  (data_get($currencyData, $currency, '') . '' . $value);
            }
            if (in_array($type, [0])) {
                $replacePairs['*|GIFT_VALUE|*'] = data_get($review, Constant::DB_TABLE_TYPE_VALUE, Constant::PARAMETER_STRING_DEFAULT);
            }
        }

        if ($auditStatus == 6 && in_array($storeId, [1, 2, 3])) { //系统催评
            $where = [
                Constant::DB_TABLE_STORE_ID => $storeId,
                Constant::DB_TABLE_TYPE => 'audit_email',
                'conf_key' => [
                    'view_review_urge_link_product',
                    'view_review_urge_link_side'
                ],
            ];

            $emailConf = DictStoreService::getModel($storeId)->buildWhere($where)->get();
            $emailConf = $emailConf->isEmpty() ? [] : array_column($emailConf->toArray(), NULL, 'conf_key');
            if (empty($emailConf)) {
                return [];
            }

            $viewReviewLinkProduct = Arr::get($emailConf, "view_review_urge_link_product.conf_value", Constant::PARAMETER_STRING_DEFAULT);
            $viewReviewLinkSide = Arr::get($emailConf, "view_review_urge_link_side.conf_value", Constant::PARAMETER_STRING_DEFAULT);

            $customerId = data_get($review, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
            $orderId = data_get($review, Constant::DB_TABLE_ORDER_NO, Constant::PARAMETER_INT_DEFAULT);
            $reward = RewardService::getOrderReviewRewardV2($storeId, $customerId, $orderId, []);
            $rewardItems = data_get($reward, Constant::RESPONSE_DATA_KEY . Constant::LINKER . 'items', Constant::PARAMETER_ARRAY_DEFAULT);

            $cnt = 0;
            $productListView = '';
            foreach ($rewardItems as $rewardItem) {
                $productName = data_get($rewardItem, Constant::FILE_TITLE, Constant::PARAMETER_STRING_DEFAULT);
                $productImg = data_get($rewardItem, Constant::DB_TABLE_IMG, Constant::PARAMETER_STRING_DEFAULT);
                $rewardName = data_get($rewardItem, 'reward.name', Constant::PARAMETER_STRING_DEFAULT);

                $_replacePairs = [
                    '*|PRODUCT_NAME|*' => $productName,
                    '*|PRODUCT_IMG|*' => $productImg,
                    '*|REWARD|*' => $rewardName,
                    '*|ORDER|*' => $orderId
                ];

                $viewReviewLinkProductRs = strtr($viewReviewLinkProduct, $_replacePairs);
                $productListView .= $viewReviewLinkProductRs;

                $cnt++;
                if ($cnt < count($rewardItems)) {
                    $productListView .= $viewReviewLinkSide;
                }
            }

            $replacePairs['*|PRODUCT_LIST|*'] = $productListView;
        }

        data_set($emailData, 'content', strtr($emailView, $replacePairs));

        return $emailData;
    }

    /**
     * 导出
     * @param array $requestData 请求参数
     * @return array
     */
    public static function export($requestData) {
        $header = [
            '订单号' => Constant::DB_TABLE_ORDER_NO,
            '邮箱' => Constant::DB_TABLE_ACCOUNT,
            '延保订单国家' => Constant::DB_TABLE_COUNTRY,
            '三级类目' => 'items.0.product_category.three_category_name',
            '产品sku' => Constant::DB_TABLE_SKU,
            'asin' => Constant::DB_TABLE_ASIN,
            '件数' => 'product_quantity',
            '礼品类型' => Constant::DB_TABLE_TYPE,
            '星级' => Constant::DB_TABLE_STAR,
            '评论链接' => Constant::DB_TABLE_REVIEW_LINK,
            '评论截图' => Constant::DB_TABLE_REVIEW_IMG_URL,
            '评论时间' => Constant::DB_TABLE_REVIEW_TIME,
            '订单时间' => Constant::DB_TABLE_ORDER_TIME,
            '订单延保时间' => Constant::DB_TABLE_CREATED_AT,
            '审核结果' => Constant::AUDIT_STATUS,
            '审核人' => Constant::DB_TABLE_REVIEWER,
            '备注' => Constant::DB_TABLE_REMARKS,
            Constant::EXPORT_DISTINCT_FIELD => [
                Constant::EXPORT_PRIMARY_KEY => Constant::DB_TABLE_PRIMARY,
                Constant::EXPORT_PRIMARY_VALUE_KEY => Constant::DB_TABLE_PRIMARY,
                Constant::DB_EXECUTION_PLAN_SELECT => [static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_PRIMARY]
            ],
        ];

        $service = static::getNamespaceClass();
        $method = 'getListData';
        $select = static::getSelect();
        $parameters = [$requestData, true, true, $select, false, false];
        $countMethod = $method;
        $countParameters = Arr::collapse([$parameters, [true]]);
        $file = ExcelService::createCsvFile($header, $service, $countMethod, $countParameters, $method, $parameters);

        return [Constant::FILE_URL => $file];
    }

    /**
     * 获取统计数据
     * @param array $params 请求参数
     * @return array
     */
    public static function statList($params) {
        static::setDefaultTime($params);
        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);
        $reviewStartTime = $params['review_start_time'] ?? Constant::PARAMETER_STRING_DEFAULT; //评论开始时间
        $reviewEndTime = $params['review_end_time'] ?? Constant::PARAMETER_STRING_DEFAULT; //评论结束时间

        //获取查询相关条件
        $publicData = static::getPublicData($params);
        $where = data_get($publicData, Constant::DB_EXECUTION_PLAN_WHERE, []);

        $dbRaw = "";
        $dbRawOrder = "";
        $result = [];
        $statType = data_get($params, 'stat_type', 'day');
        $groupType = 'date';
        if (empty($reviewStartTime) && empty($reviewEndTime)) {
            //按延保时间分组
            if ($statType == 'day') {
                $dbRaw = "date_format(crm_or.created_at, '%Y-%m-%d') $groupType"; //按天分组
                $dbRawOrder = "date_format(crm_co.ctime, '%Y-%m-%d') $groupType"; //按天分组
            } elseif ($statType == 'week') {
                $dbRaw = "date_format(crm_or.created_at, '%x%v') $groupType"; //按周分组
                $dbRawOrder = "date_format(crm_co.ctime, '%x%v') $groupType"; //按周分组
            } elseif ($statType == 'month') {
                $dbRaw = "date_format(crm_or.created_at, '%Y-%m') $groupType"; //按月分组
                $dbRawOrder = "date_format(crm_co.ctime, '%Y-%m') $groupType"; //按月分组
            }
        } else {
            //按延保时间分组
            if ($statType == 'day') {
                $dbRaw = "date_format(crm_or.review_time, '%Y-%m-%d') $groupType"; //按天分组
                $dbRawOrder = "date_format(crm_co.ctime, '%Y-%m-%d') $groupType"; //按天分组
            } elseif ($statType == 'week') {
                $dbRaw = "date_format(crm_or.review_time, '%x%v') $groupType"; //按周分组
                $dbRawOrder = "date_format(crm_co.ctime, '%x%v') $groupType"; //按周分组
            } elseif ($statType == 'month') {
                $dbRaw = "date_format(crm_or.review_time, '%Y-%m') $groupType"; //按月分组
                $dbRawOrder = "date_format(crm_co.ctime, '%Y-%m') $groupType"; //按月分组
            }
        }

        //延保订单数量
        $wOrderRet = static::countOrder($storeId, $dbRawOrder, $groupType, $params);
        if (empty($wOrderRet)) {
            //return $result;
        }

        //星级跟评论数量
        $oneStarRet = static::countData($storeId, $where, $dbRaw, $groupType);

        //结果聚合处理
        foreach ($oneStarRet as $key => $value) {
            $oneStarRet[$key]['reviewLinkCnt'] = intval(data_get($oneStarRet, "$key.reviewLinkCnt", Constant::PARAMETER_INT_DEFAULT));
            $oneStarRet[$key]['orderCnt'] = intval(data_get($oneStarRet, "$key.orderCnt", Constant::PARAMETER_INT_DEFAULT));
            $oneStarRet[$key]['oneStarCnt'] = intval(data_get($oneStarRet, "$key.oneStarCnt", Constant::PARAMETER_INT_DEFAULT));
            $oneStarRet[$key]['twoStarCnt'] = intval(data_get($oneStarRet, "$key.twoStarCnt", Constant::PARAMETER_INT_DEFAULT));
            $oneStarRet[$key]['threeStarCnt'] = intval(data_get($oneStarRet, "$key.threeStarCnt", Constant::PARAMETER_INT_DEFAULT));
            $oneStarRet[$key]['fourStarCnt'] = intval(data_get($oneStarRet, "$key.fourStarCnt", Constant::PARAMETER_INT_DEFAULT));
            $oneStarRet[$key]['fiveStarCnt'] = intval(data_get($oneStarRet, "$key.fiveStarCnt", Constant::PARAMETER_INT_DEFAULT));
            $oneStarRet[$key]['productReviewLinkCnt'] = intval(data_get($oneStarRet, "$key.productReviewLinkCnt", Constant::PARAMETER_INT_DEFAULT));
            $oneStarRet[$key]['warrantyOrderCnt'] = intval(data_get($wOrderRet, "$key.warrantyOrderCnt", Constant::PARAMETER_INT_DEFAULT));

            if ($statType == 'week') {
                $dateParse = date_parse_from_format("Ym", $key);
                $weekStartTime = strtotime($dateParse['year'] . '-01-01 00:00:00') + ($dateParse['month'] - 1) * 86400 * 7;
                $weekDays = static::getWeekDays($weekStartTime, 1);
                $oneStarRet[$key][$groupType] = $weekDays['week_start'] . '_' . $weekDays['week_end'];
            }
        }

        return array_values($oneStarRet);
    }

    /**
     * 统计订单,索评,星级数量
     * @param int $storeId 官网id
     * @param array $where 查询条件
     * @param string $dbRaw 原始sql
     * @param string $groupType 统计分组
     * @return array
     */
    public static function countData($storeId, $where, $dbRaw, $groupType) {
        $subSelect = [
            DB::raw($dbRaw),
            DB::raw('COUNT(distinct crm_or.star = 1 or null) oneStarCnt'),
            DB::raw('COUNT(distinct crm_or.star = 2 or null) twoStarCnt'),
            DB::raw('COUNT(distinct crm_or.star = 3 or null) threeStarCnt'),
            DB::raw('COUNT(distinct crm_or.star = 4 or null) fourStarCnt'),
            DB::raw('COUNT(distinct crm_or.star = 5 or null) fiveStarCnt'),
            DB::raw("COUNT(distinct crm_or.review_time != '2019-01-01 00:00:00' or null) reviewLinkCnt"),
            DB::raw("COUNT(crm_or.review_time != '2019-01-01 00:00:00' or null) productReviewLinkCnt"),
            DB::raw('COUNT(distinct crm_or.orderno or null) orderCnt'),
        ];

        $where['or.status'] = 1;
        $dbObj = static::getModel($storeId)
            ->withTrashed()
            ->select($subSelect)
            ->from('order_reviews as or')
            ->leftjoin('customer_order as co', function ($join) use($storeId) {
                $join->on('or.orderno', '=', 'co.orderno')
                    ->where('co.store_id', '=', $storeId)
                    ->where('co.status', '=', 1)
                    ->where('co.type', '=', 'platform');
                })
            ->leftjoin(DB::RAW('ptxcrm.crm_platform_orders as crm_po'), function ($join) use($storeId) {
                $join->on('or.order_unique_id', '=', 'po.unique_id')
                    ->where('po.status', '=', 1);
                })
            ->buildWhere($where)
            ->groupBy($groupType, DB::raw('crm_or.orderno'));

        $selectTotal = [
            $groupType,
            DB::raw('SUM(oneStarCnt) oneStarCnt'),
            DB::raw('SUM(twoStarCnt) twoStarCnt'),
            DB::raw('SUM(threeStarCnt) threeStarCnt'),
            DB::raw('SUM(fourStarCnt) fourStarCnt'),
            DB::raw('SUM(fiveStarCnt) fiveStarCnt'),
            DB::raw('SUM(reviewLinkCnt) reviewLinkCnt'),
            DB::raw('SUM(productReviewLinkCnt) productReviewLinkCnt'),
            DB::raw('SUM(orderCnt) orderCnt'),
        ];

        $dbRet = static::getModel($storeId)->from(DB::raw("({$dbObj->toSql()}) as tmp"))
            ->withTrashed()
            ->mergeBindings($dbObj->getQuery())
            ->select($selectTotal)
            ->groupBy($groupType)
            ->get();

        return $dbRet->isEmpty() ? [] : array_column($dbRet->toArray(), NULL, $groupType);
    }

    /**
     * 统计延保订单数量
     * @param int $storeId 官网id
     * @param string $dbRaw sql
     * @param string $groupType 分组
     * @param array $params 请求参数
     * @return array
     */
    public static function countOrder($storeId, $dbRaw, $groupType, $params) {
        $reviewStartTime = $params['review_start_time'] ?? Constant::PARAMETER_STRING_DEFAULT; //评论开始时间
        $reviewEndTime = $params['review_end_time'] ?? Constant::PARAMETER_STRING_DEFAULT; //评论结束时间
        $startTime = $params[Constant::START_TIME] ?? Constant::PARAMETER_STRING_DEFAULT; //开始时间
        $endTime = $params[Constant::DB_TABLE_END_TIME] ?? Constant::PARAMETER_STRING_DEFAULT; //结束时间
        if (empty($startTime) && empty($endTime) && !empty($reviewEndTime) && !empty($reviewStartTime)) {
            return [];
        }

        unset($params[Constant::DB_TABLE_STORE_ID]);
        unset($params[Constant::DB_TABLE_TYPE]);
        if (isset($params['review_start_time'])) {
            unset($params['review_start_time']);
        }
        if (isset($params['review_end_time'])) {
            unset($params['review_end_time']);
        }

        $publicData = OrderWarrantyService::getPublicData($params);
        $where = data_get($publicData, Constant::DB_EXECUTION_PLAN_WHERE, []);
        $country = data_get($params, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT); //国家
        if ($country) {
            $where['{customizeWhere}'][] = [
                Constant::METHOD_KEY => 'whereExists',
                Constant::PARAMETERS_KEY => function ($query) use ($country) {
                    $query->select(DB::raw(1))->from(DB::RAW('ptxcrm.crm_platform_orders'))->whereIn(Constant::DB_TABLE_COUNTRY, $country);
                },
            ];
        }

        $select = [
            DB::raw($dbRaw),
            DB::raw('COUNT(*) warrantyOrderCnt')
        ];

        $dbRet = OrderWarrantyService::getModel($storeId)->from('customer_order as co')->buildWhere($where)->select($select)->groupBy($groupType)->get();
        return $dbRet->isEmpty() ? [] : array_column($dbRet->toArray(), NULL, $groupType);
    }

    /**
     * 统计时间参数校验并设置
     * @param $params
     */
    public static function setDefaultTime(&$params) {
        $startTime = $params[Constant::START_TIME] ?? Constant::PARAMETER_STRING_DEFAULT; //开始时间
        $endTime = $params[Constant::DB_TABLE_END_TIME] ?? Constant::PARAMETER_STRING_DEFAULT; //结束时间
        $reviewStartTime = $params['review_start_time'] ?? Constant::PARAMETER_STRING_DEFAULT; //评论开始时间
        $reviewEndTime = $params['review_end_time'] ?? Constant::PARAMETER_STRING_DEFAULT; //评论结束时间
        if (empty($startTime) && empty($endTime) && empty($reviewStartTime) && empty($reviewEndTime)) {
            $curDate = date("Y-m-d H:i:s", time());
            $startDate = '2020-05-15 00:00:00';
            data_set($params, Constant::START_TIME, $startDate);
            data_set($params, Constant::DB_TABLE_END_TIME, $curDate);
        }
        if (empty($reviewStartTime) && empty($reviewEndTime)) {
            $curDate = date("Y-m-d H:i:s", time());
            $startDate = '2020-05-15 00:00:00';
//            data_set($params, 'review_start_time', $startDate);
//            data_set($params, 'review_end_time', $curDate);
        }
    }

    /**
     * 某一周的开始日期跟结束日期
     * @param int $time 时间戳
     * @param int $first 一周的开始按周一还是周日开始
     * @return array
     */
    public static function getWeekDays($time, $first = 1) {
        $format = 'Y-m-d';
        $sdefaultDate = date($format, $time);
        $w = date('w', strtotime($sdefaultDate));
        $week_start = date($format, strtotime("$sdefaultDate -" . ($w ? $w - $first : 6) . ' days'));
        $week_end = date($format, strtotime("$week_start +6 days"));

        return [
            "week_start" => $week_start,
            "week_end" => $week_end
        ];
    }

    /**
     * 编辑订单索评数据
     * @param int $storeId 商城id
     * @param string $orderno 订单id
     * @param array $requestData 请求参数
     * @return type
     */
    public static function input($storeId, $orderno, $requestData) {

        $rs = Response::getDefaultResponseData(1);
        $customerId = data_get($requestData, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::PARAMETER_INT_DEFAULT);

        $isExists = OrderWarrantyService::checkExists($storeId, $customerId, Constant::DB_TABLE_PLATFORM, $orderno);
        if (empty($isExists)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 30003);
            return $rs;
        }

        $nowTime = Carbon::now()->toDateTimeString();
        $where = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ORDER_NO => $orderno,
        ];

        $orderReviewData = static::existsOrFirst($storeId, '', $where, true);

        $account = data_get($requestData, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT);
        $data = [
            Constant::DB_TABLE_ACCOUNT => $account,
        ];

        $star = data_get($requestData, Constant::DB_TABLE_STAR, null);
        if ($star !== null) {

            if ($orderReviewData && (data_get($orderReviewData, Constant::DB_TABLE_REVIEW_TIME, Constant::PARAMETER_STRING_DEFAULT) > '2019-01-01 00:00:00' || data_get($orderReviewData, Constant::DB_TABLE_CONTACT_US_ID, Constant::PARAMETER_INT_DEFAULT))) {//如果已经提交了review 或者 发送了联系我们邮件，就提示用户不可以评星
                data_set($rs, Constant::RESPONSE_CODE_KEY, 30006);
                return $rs;
            }

            //vt订单评星不能重复评星
            if ($storeId == 2 && $orderReviewData && (data_get($orderReviewData, Constant::DB_TABLE_STAR, Constant::PARAMETER_INT_DEFAULT)) >= 0) {
                data_set($rs, Constant::RESPONSE_CODE_KEY, 30006);
                return $rs;
            }

            data_set($data, Constant::DB_TABLE_STAR, $star);
            data_set($data, Constant::DB_TABLE_STAR_AT, $nowTime);
        }

        $reviewLink = data_get($requestData, Constant::DB_TABLE_REVIEW_LINK, null);
        if ($reviewLink !== null) {
            data_set($data, Constant::DB_TABLE_REVIEW_LINK, $reviewLink);
        }

        $reviewImgUrl = data_get($requestData, Constant::DB_TABLE_REVIEW_IMG_URL, null);
        if ($reviewImgUrl !== null) {
            data_set($data, Constant::DB_TABLE_REVIEW_IMG_URL, $reviewImgUrl);
        }

        if ($reviewLink !== null || $reviewImgUrl !== null) {
            data_set($data, Constant::DB_TABLE_REVIEW_TIME, $nowTime);
        }

        if ($data) {
            $orderData = OrderService::getOrderData($orderno, '', Constant::PLATFORM_SERVICE_AMAZON, $storeId);
            if (data_get($orderData, Constant::RESPONSE_CODE_KEY, 0) == 1) {
                data_set($data, Constant::DB_TABLE_ORDER_UNIQUE_ID, data_get($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_UNIQUE_ID, 0)); //订单 唯一id
            }
        }

        $orderReviewData = static::updateOrCreate($storeId, $where, $data);

        //订单评星是否发放奖励
        $starReward = DictStoreService::getByTypeAndKey($storeId, Constant::ORDER_REVIEW, 'star_reward', true);
        if (data_get($data, Constant::DB_TABLE_REVIEW_TIME, null) || !empty($starReward)) {
            $rs = RewardService::handleOrderReviewReward($storeId, $customerId, data_get($orderReviewData, Constant::RESPONSE_DATA_KEY, Constant::PARAMETER_ARRAY_DEFAULT), $orderno, $account);
        }

        $orderReviewId = data_get($orderReviewData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
        data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_EXT_ID, $orderReviewId);
        data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_EXT_TYPE, static::getModelAlias()); //订单索评关联模型

        return $rs;
    }

    /**
     * 获取订单review列表
     * @param array $params 请求参数
     * @param boolean $isPage  是否分页 true:是 false:否 默认:true
     * @param array $select  查询字段
     * @param boolean $isGetQuery 是否获取 query
     * @param boolean $isOnlyGetCount 是否仅仅获取总记录数
     * @return array|\Hyperf\Database\Model\Builder 列表数据|Builder
     */
    public static function getReviewList($params, $isPage = true, $select = [], $isGetQuery = false, $isOnlyGetCount = false) {

        $_data = static::getPublicData($params);
        $where = [
            static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_CUSTOMER_PRIMARY => data_get($params, Constant::DB_TABLE_CUSTOMER_PRIMARY, -1),
        ];
        $order = [[static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_OLD_CREATED_AT, Constant::DB_EXECUTION_PLAN_ORDER_DESC]];
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::ACT_LIMIT_KEY, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0));
        $storeId = Arr::get($params, Constant::DB_TABLE_STORE_ID, 1);

        $select = $select ? $select : [
            static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_ORDER_NO,
            static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_ORDER_TIME,
            static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_ORDER_STATUS,
            static::$customerOrderName . Constant::LINKER . Constant::WARRANTY_DATE,
            static::$customerOrderName . Constant::LINKER . Constant::WARRANTY_DES,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_COUNTRY,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_SKU,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_ASIN,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_TYPE,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_TYPE_VALUE,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_STAR,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_REVIEW_LINK,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_REVIEW_IMG_URL,
            static::$orderReviewName . Constant::LINKER . Constant::AUDIT_STATUS,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_REWARD_NAME,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_START_AT,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_END_AT,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_CONTACT_US_ID,
            static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_PRIMARY . ' as ' . Constant::DB_TABLE_EXT_ID,
            DB::raw("'" . OrderWarrantyService::getModelAlias() . "' as " . Constant::DB_TABLE_EXT_TYPE),
            static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_PLATFORM,
        ];

        $orderStatusData = DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //订单状态 -1:匹配中 0:未支付 1:已经支付 2:取消 默认:-1
        $orderStatusData[1] = '';
        $orderStatusData[4] = '';
        $orderStatusData[5] = '';

        $_orderStatusData = DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //订单状态 -1:匹配中 0:未支付 1:已经支付 2:取消 默认:-1
        $field = Constant::DB_TABLE_ORDER_STATUS;
        $data = $_orderStatusData;
        $dataType = 'string';
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = Constant::PARAMETER_STRING_DEFAULT;
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];


        $amazonHostData = DictService::getListByType('amazon_host', Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);
        $handleData = [
            'orderStatusShow' => FunctionHelper::getExePlanHandleData(...$parameters),
            'orderStatus' => FunctionHelper::getExePlanHandleData($field, $default, $orderStatusData, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            'amazon' => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_COUNTRY, data_get($amazonHostData, 'US', ''), $amazonHostData),
            'amazon_url' => FunctionHelper::getExePlanHandleData('amazon{connection}' . Constant::DB_TABLE_ASIN, $default, Constant::PARAMETER_ARRAY_DEFAULT, 'string', $dateFormat, $time, '/dp/', $isAllowEmpty, $callback, $only), //亚马逊链接 asin
        ];

        $joinData = [
            FunctionHelper::getExePlanJoinData('order_reviews as ' . static::$orderReviewName, function ($join) {
                        $join->on([[static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_ORDER_NO, '=', static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_ORDER_NO]])->where(static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_STATUS, '=', 1);
                    }),
        ];
        $with = Constant::PARAMETER_ARRAY_DEFAULT;
        $unset = ['orderStatus', 'amazon', Constant::WARRANTY_DATE, Constant::WARRANTY_DES];
        $exePlan = FunctionHelper::getExePlan($storeId, null, OrderWarrantyService::getModelAlias(), 'customer_order as ' . static::$customerOrderName, $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $orderConfig = OrderWarrantyService::getOrderEmailConfig($storeId, '');
        $itemHandleDataCallback = [
            "warranty" => function($item) use($storeId, $orderConfig) {//延保时间
                $warrantyDate = data_get($item, Constant::WARRANTY_DATE, '');
                $handle = FunctionHelper::getExePlanHandleData('orderStatus{or}' . Constant::DB_TABLE_ORDER_TIME, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, 'datetime', data_get($orderConfig, 'warranty_date_format', 'Y-m-d H:i'), ($warrantyDate ? $warrantyDate : data_get($orderConfig, 'warranty_date', '+2 years')));
                if ($storeId == 5) {
                    $handle = FunctionHelper::getExePlanHandleData('orderStatus{or}88', '1-Year Extended');
                }
                return FunctionHelper::handleData($item, $handle);
            },
            Constant::DB_TABLE_ORDER_TIME => function($item) {//延保时间
                $handle = FunctionHelper::getExePlanHandleData('orderStatus{or}' . Constant::DB_TABLE_ORDER_TIME, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, 'datetime', 'Y-m-d H:i'); //订单时间
                return FunctionHelper::handleData($item, $handle);
            },
        ];
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
        ];

        $flatten = false;
        $dataStructure = 'list';
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * 获取select的字段数组
     * @return array
     */
    public static function getSelect() {
        return [
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_PRIMARY,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_ORDER_UNIQUE_ID,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_ORDER_NO,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_ACCOUNT,
            //static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_COUNTRY,
            static::$platformOrderName . Constant::LINKER . Constant::DB_TABLE_COUNTRY . ' as ' . Constant::DB_TABLE_COUNTRY,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_SKU,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_ASIN,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_TYPE,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_STAR,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_REVIEW_LINK,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_REVIEW_IMG_URL,
            static::$orderReviewName . Constant::LINKER . Constant::AUDIT_STATUS,
            //static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_ORDER_TIME,
            static::$platformOrderName . Constant::LINKER . Constant::DB_TABLE_ORDER_AT . ' as ' . Constant::DB_TABLE_ORDER_TIME,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_CREATED_AT,
            //static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_ORDER_STATUS,
            static::$platformOrderName . Constant::LINKER . Constant::DB_TABLE_ORDER_STATUS,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_CONTACT_US_ID,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_REVIEWER,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_REMARKS,
            static::$orderReviewName . Constant::LINKER . Constant::DB_TABLE_REVIEW_TIME,
        ];
    }

    /**
     * 提交订单评星
     * @param int $storeId 商城id
     * @param string $orderno 订单id
     * @param array $requestData 请求参数
     * @return array
     */
    public static function playStar($storeId, $orderno, $requestData) {

        $rs = Response::getDefaultResponseData(1);
        $star = data_get($requestData, Constant::DB_TABLE_STAR, '');
        $account = data_get($requestData, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT);
        $customerId = data_get($requestData, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::PARAMETER_INT_DEFAULT);

        //获取订单数据
        $orderRs = static::checkAndGetOrder($storeId, $customerId, $orderno, $requestData);
        if (data_get($orderRs, Constant::RESPONSE_CODE_KEY) != 1) {
            return $orderRs;
        }

        //只有shipped状态的才能评星
        $orderStatus = data_get($orderRs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_ORDER_STATUS);
        $orderData = data_get($orderRs, Constant::RESPONSE_DATA_KEY, Constant::PARAMETER_ARRAY_DEFAULT);

        if (strtolower($orderStatus) != 'shipped') {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 50007);
            return $rs;
        }

        $_orderItemData = data_get($orderRs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::RESPONSE_DATA_KEY . '.items');
        $orderItemData = collect($_orderItemData)->sortByDesc(Constant::DB_TABLE_AMOUNT);

        //能否评星
        $isCanPlayStar = static::checkIsCanPlayStar($storeId, $customerId, $orderno);
        if (!$isCanPlayStar) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 50003);
            return $rs;
        }

        //是否存在返现
        $rewardRs = BusGiftCardApplyService::rewardWarrantyHandle($storeId, $orderno);
        if (data_get($rewardRs, Constant::RESPONSE_CODE_KEY) != 1) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 50002);
            return $rs;
        }

        //订单评星是否发放奖励
        $starReward = DictStoreService::getByTypeAndKey($storeId, Constant::ORDER_REVIEW, 'star_reward', true);

        //将未提交Rv或未提交反馈的数据设置为无效,sku为空,asin为空
        $where = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ORDER_NO => $orderno,
            Constant::DB_TABLE_SKU => '',
            Constant::DB_TABLE_ASIN => '',
            Constant::AUDIT_STATUS => -1,
            Constant::DB_TABLE_CONTACT_US_ID => 0
        ];
        $exists = static::existsOrFirst($storeId, '', $where);
        $exists && static::delete($storeId, $where);

        $keyCnt = 0;
        $nowTime = Carbon::now()->toDateTimeString();
        foreach ($orderItemData as $key => $item) {
            $orderStatus = data_get($item, Constant::DB_TABLE_ORDER_STATUS);
            if (strtolower($orderStatus) != 'shipped') { //shipped状态的order_item数据才能匹配奖励
                continue;
            }

            $where = [
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                Constant::DB_TABLE_ORDER_NO => $orderno,
                Constant::DB_TABLE_SKU => data_get($item, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT),
                Constant::DB_TABLE_ASIN => data_get($item, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT),
            ];

            $data = [
                Constant::DB_TABLE_ACCOUNT => $account,
                Constant::DB_TABLE_STAR => $star,
                Constant::DB_TABLE_STAR_AT => $nowTime,
                Constant::DB_TABLE_COUNTRY => data_get($item, Constant::DB_TABLE_ORDER_COUNTRY, Constant::PARAMETER_STRING_DEFAULT),
                Constant::DB_TABLE_ORDER_UNIQUE_ID => data_get($orderRs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_UNIQUE_ID, Constant::ORDER_STATUS_PENDING_INT)
            ];

            $orderReviewData = static::updateOrCreate($storeId, $where, $data);

            if (!empty($starReward)) {
                $rs = RewardService::handleOrderReviewRewardV2($storeId, $customerId, data_get($orderReviewData, Constant::RESPONSE_DATA_KEY, Constant::PARAMETER_ARRAY_DEFAULT), $account, $orderData);
            }

            $orderReviewId = data_get($orderReviewData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
            data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . $keyCnt . Constant::LINKER . Constant::DB_TABLE_EXT_ID, $orderReviewId);
            data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . $keyCnt . Constant::LINKER . Constant::DB_TABLE_EXT_TYPE, static::getModelAlias());
            data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . $keyCnt . Constant::LINKER . Constant::DB_TABLE_ORDER_NO, $orderno);
            data_set($rs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . $keyCnt . Constant::LINKER . Constant::DB_TABLE_SKU, data_get($item, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT));
            $keyCnt++;
        }

        return $rs;
    }

    /**
     * 判断用户是否能评星
     * @param int $storeId 官网id
     * @param int $customerId 会员id
     * @param string $orderNo 订单号
     * @return bool
     */
    public static function checkIsCanPlayStar($storeId, $customerId, $orderNo) {
        $isCanPlayStar = true;

        $where = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ORDER_NO => $orderNo,
        ];
        $orderReviewData = static::getModel($storeId)->buildWhere($where)->get();
        if (empty($orderReviewData)) {
            return $isCanPlayStar;
        }

        foreach ($orderReviewData as $item) {
            $star = data_get($item, Constant::DB_TABLE_STAR);
            //1-3星,如果存在反馈信息,就不能再次评星
            if ($star >= 1 && $star <= 3) {
                $contactUsId = data_get($item, Constant::DB_TABLE_CONTACT_US_ID, Constant::PARAMETER_INT_DEFAULT);
                if (!empty($contactUsId)) {
                    $isCanPlayStar = false;
                    break;
                }
            }
            //评了4-5星，不让重复评星
            elseif ($star >= 4 && $star <= 5) {
                $isCanPlayStar = false;
            }
        }

        return $isCanPlayStar;
    }


    /**
     * 提交订单产品评论数据
     * @param int $storeId 商城id
     * @param string $orderno 订单id
     * @param array $requestData 请求参数
     * @return array
     */
    public static function orderReview($storeId, $orderno, $requestData) {

        $rs = Response::getDefaultResponseData(1);
        $account = data_get($requestData, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT);
        $customerId = data_get($requestData, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
        $reviews = data_get($requestData, 'reviews', []);

        //获取订单数据
        $orderRs = static::checkAndGetOrder($storeId, $customerId, $orderno, $requestData);
        if (data_get($orderRs, Constant::RESPONSE_CODE_KEY) != 1) {
            return $orderRs;
        }

        //只有shipped状态的才能提交Rv
        $orderStatus = data_get($orderRs, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::DB_TABLE_ORDER_STATUS);
        $orderData = data_get($orderRs, Constant::RESPONSE_DATA_KEY);
        if (strtolower($orderStatus) != 'shipped') {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 50007);
            return $rs;
        }

        //是否能提交Rv
        $reviewRs = static::checkIsCanReview($storeId, $customerId, $reviews);
        if (data_get($reviewRs, Constant::RESPONSE_CODE_KEY) != 1) {
            return $reviewRs;
        }

        //是否存在返现
        $rewardRs = BusGiftCardApplyService::rewardWarrantyHandle($storeId, $orderno);
        if (data_get($rewardRs, Constant::RESPONSE_CODE_KEY) != 1) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 50002);
            return $rs;
        }

        //已经提交过的Rv不能再次提交，设置为空
        $reviews = static::reviewHandle($storeId, $customerId, $orderno, $reviews);
        if (empty($reviews)) {
            return $rs;
        }

        //提交订单Rv是否发放奖励
        $reviewReward = DictStoreService::getByTypeAndKey($storeId, Constant::ORDER_REVIEW, 'review_reward', true);

        $nowTime = Carbon::now()->toDateTimeString();
        foreach ($reviews as $review) {
            $sku = data_get($review, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT);
            $asin = data_get($review, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT);
            $reviewLink = data_get($review, Constant::DB_TABLE_REVIEW_LINK, Constant::PARAMETER_STRING_DEFAULT);
            $reviewImgUrl = data_get($review, Constant::DB_TABLE_REVIEW_IMG_URL, Constant::PARAMETER_STRING_DEFAULT);
            if (!empty($reviewLink) || !empty($reviewImgUrl)) {
                $where = [
                    Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                    Constant::DB_TABLE_ORDER_NO => $orderno,
                    Constant::DB_TABLE_SKU => $sku,
                    Constant::DB_TABLE_ASIN => $asin,
                ];

                $data = [
                    Constant::DB_TABLE_REVIEW_TIME => $nowTime
                ];
                !empty($reviewLink) && data_set($data, Constant::DB_TABLE_REVIEW_LINK, $reviewLink);
                !empty($reviewImgUrl) && data_set($data, Constant::DB_TABLE_REVIEW_IMG_URL, $reviewImgUrl);

                $orderReviewData = static::updateOrCreate($storeId, $where, $data);
                if ($reviewReward) {
                    RewardService::handleOrderReviewRewardV2($storeId, $customerId, data_get($orderReviewData, Constant::RESPONSE_DATA_KEY, Constant::PARAMETER_ARRAY_DEFAULT), $account, $orderData);
                }
            }
        }

        return $rs;
    }

    /**
     * 判断是否已经提交过产品Rv,已经提交过的,置为空
     * @param int $storeId 官网id
     * @param int $customerId 会员id
     * @param string $orderNo 订单号
     * @param array $reviews 用户提交的Rv
     * @return mixed
     */
    public static function reviewHandle($storeId, $customerId, $orderNo, $reviews) {
        $result = [];

        $where = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ORDER_NO => $orderNo,
        ];
        $orderReviewData = static::getModel($storeId)->buildWhere($where)->get();
        if ($orderReviewData->isEmpty()) {
            return [];
        }

        foreach ($reviews as $review) {
            $sku = data_get($review, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT);
            $asin = data_get($review, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT);

            foreach ($orderReviewData as $item) {
                $itemSku = data_get($item, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT);
                $itemAsin = data_get($item, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT);
                $reviewTime = data_get($item, Constant::DB_TABLE_REVIEW_TIME, Constant::PARAMETER_STRING_DEFAULT);

                if ($itemSku == $sku && $itemAsin == $asin) {
                    //已经提交过的产品不能再次提交，设为空值
//                    if ($reviewTime > '2019-01-01 00:00:00') {
//                        data_set($review, Constant::DB_TABLE_REVIEW_LINK, Constant::PARAMETER_STRING_DEFAULT);
//                        data_set($review, Constant::DB_TABLE_REVIEW_IMG_URL, Constant::PARAMETER_STRING_DEFAULT);
//                    }

                    $result[] = $review;
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * 检查review是否合法及是否能提交rv
     * @param int $storeId 官网id
     * @param int $customerId 会员id
     * @param array $reviews 评论数据
     * @return array
     */
    public static function checkIsCanReview($storeId, $customerId, $reviews) {
        $rs = Response::getDefaultResponseData(1);

        $time = 10;
        $key = "br_{$storeId}_{$customerId}";
        //次key存在不能提交Rv
        if (Redis::EXISTS($key)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 50001);
            return $rs;
        }

        foreach ($reviews as $review) {
            $reviewLink = data_get($review, Constant::DB_TABLE_REVIEW_LINK, Constant::PARAMETER_STRING_DEFAULT);
            //提交的reviewLink包含edit单词，time时间内不能提交
            if (stripos($reviewLink, 'edit') !== false) {
                data_set($rs, Constant::RESPONSE_CODE_KEY, 50001);
                Redis::SETEX($key, $time, '1');
                break;
            }
        }

        return $rs;
    }

    /**
     * 获取订单数据
     * @param int $storeId 官网id
     * @param int $customerId 会员id
     * @param string $orderNo 订单号
     * @param array $requestData 请求参数
     * @return array
     */
    public static function checkAndGetOrder($storeId, $customerId, $orderNo, $requestData) {
        $rs = Response::getDefaultResponseData(1);
        //延保订单是否存在
        $isExists = OrderWarrantyService::checkExists($storeId, $customerId, Constant::DB_TABLE_PLATFORM, $orderNo);
        if (empty($isExists)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, 30003);
            return $rs;
        }

        $orderData = data_get($requestData, 'order_data', Constant::PARAMETER_ARRAY_DEFAULT);
        $orderItemData = data_get($requestData, 'order_data.data.items', Constant::PARAMETER_ARRAY_DEFAULT);
        if (empty($orderData) || empty($orderItemData)) {
            $orderData = OrderService::getOrderData($orderNo, '', Constant::PLATFORM_SERVICE_AMAZON, $storeId);
            if (data_get($orderData, Constant::RESPONSE_CODE_KEY, 0) != 1) {
                data_set($rs, Constant::RESPONSE_CODE_KEY, 30002);
                return $rs;
            }

            //订单item是否存在
            $orderItemData = data_get($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . 'items', []);
            if (empty($orderItemData)) {
                data_set($rs, Constant::RESPONSE_CODE_KEY, 30001);
                return $rs;
            }
        }

        data_set($rs, Constant::RESPONSE_DATA_KEY, $orderData);

        return $rs;
    }

    /**
     * 获取订单review列表_V2
     * @param array $params 请求参数
     * @param boolean $isPage  是否分页 true:是 false:否 默认:true
     * @param array $select  查询字段
     * @param boolean $isGetQuery 是否获取 query
     * @param boolean $isOnlyGetCount 是否仅仅获取总记录数
     * @return array|\Hyperf\Database\Model\Builder 列表数据|Builder
     */
    public static function getReviewListV2($params, $isPage = true, $select = [], $isGetQuery = false, $isOnlyGetCount = false) {

        $_data = static::getPublicData($params);
        $where = [
            static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_CUSTOMER_PRIMARY => data_get($params, Constant::DB_TABLE_CUSTOMER_PRIMARY, -1),
        ];
        $order = [[static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_OLD_CREATED_AT, Constant::DB_EXECUTION_PLAN_ORDER_DESC]];
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::ACT_LIMIT_KEY, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0));
        $storeId = Arr::get($params, Constant::DB_TABLE_STORE_ID, 1);

        $select = $select ? $select : [
            static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_ORDER_UNIQUE_ID,
            static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_ORDER_NO,
            static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_ORDER_TIME,
            static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_ORDER_STATUS,
            static::$customerOrderName . Constant::LINKER . Constant::WARRANTY_DATE,
            static::$customerOrderName . Constant::LINKER . Constant::WARRANTY_DES,
            static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_PRIMARY . ' as ' . Constant::DB_TABLE_EXT_ID,
            DB::raw("'" . OrderWarrantyService::getModelAlias() . "' as " . Constant::DB_TABLE_EXT_TYPE),
            static::$customerOrderName . Constant::LINKER . Constant::DB_TABLE_PLATFORM,
        ];

        $orderStatusData = DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //订单状态 -1:匹配中 0:未支付 1:已经支付 2:取消 默认:-1
        $orderStatusData[1] = '';
        $orderStatusData[4] = '';
        $orderStatusData[5] = '';

        $_orderStatusData = DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //订单状态 -1:匹配中 0:未支付 1:已经支付 2:取消 默认:-1
        $field = Constant::DB_TABLE_ORDER_STATUS;
        $data = $_orderStatusData;
        $dataType = 'string';
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = Constant::PARAMETER_STRING_DEFAULT;
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];

        $amazonHostData = DictService::getListByType('amazon_host', Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);
        $handleData = [
            'orderStatusShow' => FunctionHelper::getExePlanHandleData(...$parameters),
            'orderStatus' => FunctionHelper::getExePlanHandleData($field, $default, $orderStatusData, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            'amazon' => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_COUNTRY, data_get($amazonHostData, 'US', ''), $amazonHostData),
            'amazon_url' => FunctionHelper::getExePlanHandleData('amazon{connection}' . Constant::DB_TABLE_ASIN, $default, Constant::PARAMETER_ARRAY_DEFAULT, 'string', $dateFormat, $time, '/dp/', $isAllowEmpty, $callback, $only), //亚马逊链接 asin
        ];

        $joinData = Constant::PARAMETER_ARRAY_DEFAULT;
        $unset = ['orderStatus', 'amazon', Constant::WARRANTY_DATE, Constant::WARRANTY_DES, 'items'];
        $exePlan = FunctionHelper::getExePlan($storeId, null, OrderWarrantyService::getModelAlias(), 'customer_order as ' . static::$customerOrderName, $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $reviewSelect = [
            Constant::DB_TABLE_ORDER_NO,
            Constant::DB_TABLE_COUNTRY,
            Constant::DB_TABLE_SKU,
            Constant::DB_TABLE_ASIN,
            Constant::DB_TABLE_TYPE,
            Constant::DB_TABLE_TYPE_VALUE,
            Constant::DB_TABLE_STAR,
            Constant::DB_TABLE_REVIEW_LINK,
            Constant::DB_TABLE_REVIEW_IMG_URL,
            Constant::AUDIT_STATUS,
            Constant::DB_TABLE_REWARD_NAME,
            Constant::DB_TABLE_START_AT,
            Constant::DB_TABLE_END_AT,
            Constant::DB_TABLE_CONTACT_US_ID,
            Constant::DB_TABLE_REVIEW_TIME,
        ];
        $itemsSelect = [
            '*'
        ];
        $orderSelect = [
            Constant::DB_TABLE_UNIQUE_ID,
            Constant::DB_TABLE_PRESENTMENT_CURRENCY,
        ];
        $with = [
            'reviews' => FunctionHelper::getExePlan(
                $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $reviewSelect, [], [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false
            ),
            'items' => FunctionHelper::getExePlan(
                $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $itemsSelect, [], [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false
            ),
            'order' => FunctionHelper::getExePlan(
                $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $orderSelect, [], [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], Constant::PARAMETER_ARRAY_DEFAULT, 'hasOne', false
            ),
        ];

        $orderConfig = OrderWarrantyService::getOrderEmailConfig($storeId, Constant::PARAMETER_STRING_DEFAULT);
        $itemHandleDataCallback = [
            "warranty" => function($item) use($storeId, $orderConfig) {//延保时间
                $warrantyDate = data_get($item, Constant::WARRANTY_DATE, '');
                $handle = FunctionHelper::getExePlanHandleData('orderStatus{or}' . Constant::DB_TABLE_ORDER_TIME, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, 'datetime', data_get($orderConfig, 'warranty_date_format', 'Y-m-d H:i'), ($warrantyDate ? $warrantyDate : data_get($orderConfig, 'warranty_date', '+2 years')));
                if ($storeId == 5) {
                    $handle = FunctionHelper::getExePlanHandleData('orderStatus{or}88', '1-Year Extended');
                }
                return FunctionHelper::handleData($item, $handle);
            },
            Constant::DB_TABLE_ORDER_TIME => function($item) {//延保时间
                $handle = FunctionHelper::getExePlanHandleData('orderStatus{or}' . Constant::DB_TABLE_ORDER_TIME, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, 'datetime', 'Y-m-d H:i'); //订单时间
                return FunctionHelper::handleData($item, $handle);
            },
            'reviews' => function($item) use($storeId) {
                return static::handleReviews($storeId, $item);
            },
            'review_status' => function($item) use ($storeId) {
                if (in_array($storeId, [2])) { //VT特除处理历史数据
                    $reviewStatus = static::vtOldReviewStatus($item);
                    if (!empty($reviewStatus)) {
                        return $reviewStatus;
                    }
                }

                $star = 0;
                $reviewStatus = 1;
                $auditStatusCount = 0;
                $reviews = data_get($item, 'reviews', Constant::PARAMETER_ARRAY_DEFAULT);
                $orderNo = data_get($item, Constant::DB_TABLE_ORDER_NO, Constant::PARAMETER_INT_DEFAULT);
                $rs = BusGiftCardApplyService::rewardWarrantyHandle($storeId, $orderNo);
                if (data_get($rs, Constant::RESPONSE_CODE_KEY) != 1) { //已返现
                    $reviewStatus = 7;
                    return $reviewStatus;
                }

                foreach ($reviews as $review) {
                    $star = data_get($review, Constant::DB_TABLE_STAR);
                    $auditStatus = data_get($review, Constant::AUDIT_STATUS, -1);
                    $contactUsId = data_get($review, Constant::DB_TABLE_CONTACT_US_ID, Constant::PARAMETER_INT_DEFAULT);
                    if ($auditStatus == 5) {//已返现
                        $reviewStatus = 7;
                        break;
                    }
                    if ($star >= 1 && $star <= 3) {
                        $reviewStatus = empty($contactUsId) ? 2 : 3;
                        break;
                    } elseif ($star >= 4 && $star <= 5) {
                        $reviewStatus = 4;
                    }
                    if ($auditStatus > -1) {
                        $auditStatusCount++;
                    }
                }
                if ($star >= 4 && $star <= 5 && $auditStatusCount > 0 && $auditStatusCount < count($reviews)) {//提交了部分Rv
                    $reviewStatus = 5;
                } elseif ($star >= 4 && $star <= 5 && $auditStatusCount > 0 && $auditStatusCount == count($reviews)) {//全部产品的Rv已经提交
                    $reviewStatus = 6;
                }
                return $reviewStatus;
            },
            'order_country' => function($item) {
                return data_get($item, 'items.0.country', Constant::PARAMETER_STRING_DEFAULT);
            }
        ];
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
        ];

        $flatten = false;
        $dataStructure = 'list';
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * @param $storeId
     * @param $itemData
     * @return array
     */
    public static function handleReviews($storeId, $itemData) {
        $items = data_get($itemData, 'items', Constant::PARAMETER_ARRAY_DEFAULT);
        $reviews = data_get($itemData, 'reviews', Constant::PARAMETER_ARRAY_DEFAULT);
        $order = data_get($itemData, 'order', Constant::PARAMETER_ARRAY_DEFAULT);
        $currency = data_get($order, Constant::DB_TABLE_PRESENTMENT_CURRENCY, Constant::PARAMETER_STRING_DEFAULT);

        $reviewsTmp = [];
        foreach ($reviews as $review) {
            $sku = data_get($review, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT);
            $asin = data_get($review, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT);
            $reviewsTmp["{$sku}_{$asin}"] = $review;
            data_set($reviewsTmp["{$sku}_{$asin}"], 'title', Constant::PARAMETER_STRING_DEFAULT);
            data_set($reviewsTmp["{$sku}_{$asin}"], 'img', Constant::PARAMETER_STRING_DEFAULT);
        }

        foreach ($items as $item) {
            $sku = data_get($item, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT);
            $asin = data_get($item, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT);

            if (isset($reviewsTmp["{$sku}_{$asin}"])) {
                data_set($reviewsTmp["{$sku}_{$asin}"], 'title', data_get($item, 'title', Constant::PARAMETER_STRING_DEFAULT));
                data_set($reviewsTmp["{$sku}_{$asin}"], 'img', data_get($item, 'img', Constant::PARAMETER_STRING_DEFAULT));
                data_set($reviewsTmp["{$sku}_{$asin}"], 'reward_value', '');

                $type = data_get($reviewsTmp["{$sku}_{$asin}"], Constant::DB_TABLE_TYPE, Constant::PARAMETER_STRING_DEFAULT);
                if (in_array($type, [1, 2])) {//礼品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:积分
                    preg_match('/[0-9]+/', data_get($reviewsTmp["{$sku}_{$asin}"], Constant::DB_TABLE_REWARD_NAME, Constant::PARAMETER_STRING_DEFAULT), $matches);
                    $typeValue = data_get($matches, '0', Constant::PARAMETER_INT_DEFAULT);
                    if (in_array($type, [1])) {
                        $currencyData = static::getConfig($storeId, 'currency');
                        data_set($reviewsTmp["{$sku}_{$asin}"], 'reward_value', (data_get($currencyData, $currency, '') . '' . $typeValue));
                    }
                    if (in_array($type, [2])) {
                        data_set($reviewsTmp["{$sku}_{$asin}"], 'reward_value', $typeValue);
                    }
                }

            }
        }
        return array_values($reviewsTmp);
    }

    /**
     * 历史数据处理
     * @param int $storeId 官网id
     * @param int $customerId 会员id
     * @param string $orderNo 订单号
     * @param array $orderItemData 订单数据
     * @return bool
     */
    public static function oldReviewsDataHandle($storeId, $customerId, $orderNo, $orderItemData) {
        //历史数据处理，sku为空，asin为空，审核状态-1，未反馈信息，设置为无效
        $where = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ORDER_NO => $orderNo,
            Constant::DB_TABLE_SKU => '',
            Constant::DB_TABLE_ASIN => '',
            Constant::AUDIT_STATUS => -1,
            Constant::DB_TABLE_CONTACT_US_ID => 0
        ];
        $exists = static::existsOrFirst($storeId, '', $where, true);
        if (empty($exists)) {
            return true;
        }

        static::delete($storeId, $where);

        foreach ($orderItemData as $item) {
            $orderStatus = data_get($item, Constant::DB_TABLE_ORDER_STATUS);
            if ($orderStatus != 'Shipped') {
                continue;
            }

            $where = [
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                Constant::DB_TABLE_ORDER_NO => $orderNo,
                Constant::DB_TABLE_SKU => data_get($item, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT),
                Constant::DB_TABLE_ASIN => data_get($item, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT),
            ];

            $data = [
                Constant::DB_TABLE_ORDER_UNIQUE_ID => data_get($exists, Constant::DB_TABLE_ORDER_UNIQUE_ID),
                Constant::DB_TABLE_CUSTOMER_PRIMARY => data_get($exists, Constant::DB_TABLE_CUSTOMER_PRIMARY),
                Constant::DB_TABLE_ACCOUNT => data_get($exists, Constant::DB_TABLE_ACCOUNT),
                Constant::DB_TABLE_ORDER_NO => data_get($exists, Constant::DB_TABLE_ORDER_NO),
                Constant::DB_TABLE_COUNTRY => data_get($item, Constant::DB_TABLE_ORDER_COUNTRY),
                Constant::BUSINESS_TYPE => data_get($exists, Constant::BUSINESS_TYPE),
                Constant::PRODUCT_TYPE => data_get($exists, Constant::PRODUCT_TYPE),
                Constant::DB_TABLE_TYPE => data_get($exists, Constant::DB_TABLE_TYPE),
                Constant::DB_TABLE_TYPE_VALUE => data_get($exists, Constant::DB_TABLE_TYPE_VALUE),
                Constant::DB_TABLE_STAR => data_get($exists, Constant::DB_TABLE_STAR),
                Constant::DB_TABLE_STAR_AT => data_get($exists, Constant::DB_TABLE_STAR_AT),
                Constant::DB_TABLE_CREATED_AT => data_get($exists, Constant::DB_TABLE_CREATED_AT),
                Constant::DB_TABLE_UPDATED_AT => data_get($exists, Constant::DB_TABLE_UPDATED_AT),
            ];

            static::updateOrCreate($storeId, $where, $data);
        }

        return true;
    }

    public static function orderWarrantyCredit($storeId, $customerId, $orderNo, $orderCountry) {
        $orderWarranty = OrderWarrantyService::existsOrFirst($storeId, '', [Constant::DB_TABLE_ORDER_NO => $orderNo], true);
        $id = data_get($orderWarranty, Constant::DB_TABLE_PRIMARY);

        $creditWhere = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ADD_TYPE => 1,
            Constant::DB_TABLE_ACTION => Constant::ORDER_BIND,
            Constant::DB_TABLE_EXT_ID => $id,
            Constant::DB_TABLE_EXT_TYPE => Constant::CUSTOMER_ORDER,
        ];
        $credit = CreditService::exists($storeId, $creditWhere, true, [Constant::DB_TABLE_VALUE]);
        $credit = data_get($credit, Constant::DB_TABLE_VALUE, Constant::PARAMETER_INT_DEFAULT);

        $remark = static::pointRemark($storeId, $orderCountry);

        return [
            'credit' => $credit,
            'remark' => $remark,
        ];
    }

    public static function pointRemark($storeId, $orderCountry) {
        $remark = 'points';
        if (in_array($storeId, [1]) && in_array(strtoupper($orderCountry), ['DE', 'ES', 'IT', 'FR'])) {
            $pointRemark = [
                'DE' => 'Punkte',
                'ES' => 'puntos',
                'IT' => 'punti',
                'FR' => 'points',
            ];
            $remark = $pointRemark[$orderCountry];
        }
        return $remark;
    }

    /**
     * vt历史评星数据
     * @param $item
     * @return int|string
     */
    public static function vtOldReviewStatus($item) {
        $reviewStatus = '';
        $reviews = data_get($item, 'reviews', Constant::PARAMETER_ARRAY_DEFAULT);
        foreach ($reviews as $review) {
            $reviewTime = data_get($review, Constant::DB_TABLE_REVIEW_TIME, Constant::PARAMETER_STRING_DEFAULT);
            $auditStatus = data_get($review, Constant::AUDIT_STATUS, Constant::PARAMETER_STRING_DEFAULT);
            $type = data_get($review, Constant::DB_TABLE_TYPE, Constant::PARAMETER_STRING_DEFAULT);
            $star = data_get($review, Constant::DB_TABLE_STAR, Constant::PARAMETER_STRING_DEFAULT);
            $contactUsId = data_get($review, Constant::DB_TABLE_CONTACT_US_ID, Constant::PARAMETER_STRING_DEFAULT);
            if ($reviewTime == '2019-01-01 00:00:00' && $auditStatus == 1 && $type == 5) {//VT历史数据
                if ($star >= 1 && $star <= 3 && empty($contactUsId)) {
                    $reviewStatus = 2;
                }
                if ($star >= 1 && $star <= 3 && !empty($contactUsId)) {
                    $reviewStatus = 3;
                }
                if ($star >= 4 && $star <= 5) {
                    $reviewStatus = 4;
                }
            }
            break;
        }
        return $reviewStatus;
    }

    /**
     * Vt历史数据处理
     * @param int $storeId 官网id
     * @param int $customerId 会员id
     * @param string $orderNo 订单号
     * @param array $orderItemData 订单数据
     * @return bool
     */
    public static function vtOldReviewsDataHandle($storeId, $customerId, $orderNo, $orderItemData) {
        //历史数据处理
        $where = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ORDER_NO => $orderNo,
            Constant::AUDIT_STATUS => 1,
            Constant::DB_TABLE_CONTACT_US_ID => 0,
            Constant::DB_TABLE_REVIEW_TIME => '2019-01-01 00:00:00',
            Constant::DB_TABLE_TYPE => 5
        ];
        $exists = static::existsOrFirst($storeId, '', $where, true);
        if (empty($exists)) {
            return true;
        }

        static::delete($storeId, $where);

        $oldSku = data_get($exists, Constant::DB_TABLE_SKU);
        $oldAsin = data_get($exists, Constant::DB_TABLE_ASIN);
        foreach ($orderItemData as $item) {
            $orderStatus = data_get($item, Constant::DB_TABLE_ORDER_STATUS);
            if ($orderStatus != 'Shipped') {
                continue;
            }

            $sku = data_get($item, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT);
            $asin = data_get($item, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT);
            $where = [
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                Constant::DB_TABLE_ORDER_NO => $orderNo,
                Constant::DB_TABLE_SKU => data_get($item, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT),
                Constant::DB_TABLE_ASIN => data_get($item, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT),
            ];

            $data = [
                Constant::DB_TABLE_ORDER_UNIQUE_ID => data_get($exists, Constant::DB_TABLE_ORDER_UNIQUE_ID),
                Constant::DB_TABLE_CUSTOMER_PRIMARY => data_get($exists, Constant::DB_TABLE_CUSTOMER_PRIMARY),
                Constant::DB_TABLE_ACCOUNT => data_get($exists, Constant::DB_TABLE_ACCOUNT),
                Constant::DB_TABLE_ORDER_NO => data_get($exists, Constant::DB_TABLE_ORDER_NO),
                Constant::DB_TABLE_COUNTRY => data_get($item, Constant::DB_TABLE_ORDER_COUNTRY),
                Constant::BUSINESS_TYPE => data_get($exists, Constant::BUSINESS_TYPE),
                Constant::PRODUCT_TYPE => data_get($exists, Constant::PRODUCT_TYPE),
                Constant::DB_TABLE_STAR => data_get($exists, Constant::DB_TABLE_STAR),
                Constant::DB_TABLE_STAR_AT => data_get($exists, Constant::DB_TABLE_STAR_AT),
                Constant::DB_TABLE_CREATED_AT => data_get($exists, Constant::DB_TABLE_CREATED_AT),
                Constant::DB_TABLE_UPDATED_AT => data_get($exists, Constant::DB_TABLE_UPDATED_AT),
            ];

            if ($oldSku == $sku && $oldAsin == $asin) {
                $data[Constant::DB_TABLE_TYPE] = data_get($exists, Constant::DB_TABLE_TYPE);
                $data[Constant::DB_TABLE_TYPE_VALUE] = data_get($exists, Constant::DB_TABLE_TYPE_VALUE);
                $data[Constant::DB_TABLE_EXT_ID] = data_get($exists, Constant::DB_TABLE_EXT_ID);
                $data[Constant::DB_TABLE_EXT_TYPE] = data_get($exists, Constant::DB_TABLE_EXT_TYPE);
                $data[Constant::DB_TABLE_REWARD_NAME] = data_get($exists, Constant::DB_TABLE_REWARD_NAME);
            }

            static::updateOrCreate($storeId, $where, $data);
        }

        return true;
    }
}
