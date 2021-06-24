<?php

/**
 * 申请产品服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use App\Services\Erp\ErpAmazonService;
use App\Services\Store\Amazon\Customers\Customer;
use Hyperf\HttpServer\Contract\RequestInterface as Request;
use Hyperf\DbConnection\Db as DB;
use Hyperf\Utils\Arr;
use Carbon\Carbon;
use App\Utils\FunctionHelper;
use App\Constants\Constant;
use App\Utils\Response;
use App\Services\Platform\OrderService;

class ActivityApplyService extends BaseService {

    /**
     * 是否能够申请
     * @param int $storeId 商城id
     * @param int $actId  活动id
     * @param int $customerId  会员id
     * @param int $extId  产品id
     * @param int $extType 申请类型
     * @param string $ip ip
     * @return array
     */
    public static function isCanApply($storeId, $actId, $customerId, $extId, $extType, $ip = '') {

        //判断是否填写了申请资料
        if ($storeId == 1) {
            $isExists = ActivityApplyInfoService::exists($storeId, $actId, $customerId);
            if (empty($isExists)) {
                return [
                    'code' => 60002,
                    'msg' => 'Please fill in the application materials and participate in the event.',
                    'data' => [],
                ];
            }
        } else {
            $isExists = ActivityApplyInfoService::exists($storeId, $actId, $customerId);
            if (!empty($isExists)) {
                return [
                    'code' => 60005,
                    'msg' => "You've already applied, do not submit repeatedly.",
                    'data' => [],
                ];
            }
        }

        //判断申请的产品是否存在
        $productData = ActivityProductService::exists($storeId, $extId, $actId, '', true);
        if (empty($productData)) {
            return [
                'code' => 60006,
                'msg' => 'The requested product does not exist',
                'data' => [],
            ];
        }

        //判断是否存在 审核中\审核通过\其他 的申请
        $where = [
            Constant::DB_TABLE_EXT_TYPE => $extType,
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::AUDIT_STATUS => [0, 1, 3],
        ];
        $applyData = static::getModel($storeId)->buildWhere($where)->first();
        if ($applyData) {//如果有 审核中\审核通过\其他 的申请，就不可以继续申请 并根据审核状态进行提示
            switch ($applyData->audit_status) {
                case 1://审核通过
                    return [
                        'code' => 60005,
                        'msg' => "You've already applied, do not submit repeatedly.",
                        'data' => [],
                    ];

                    break;

                case 0://审核中
                case 3://其他
                    return [
                        'code' => 60003,
                        'msg' => 'Your application is still in progress, please wait for the result.',
                        'data' => [],
                    ];
                    break;

                default:
                    break;
            }
        }

        return [
            'code' => 1,
            'msg' => '',
            'data' => [],
        ];
    }

    public static function isCanApplyFree($storeId, $actId, $customerId, $extId, $extType, $account) {

        //判断申请的产品是否存在
        $productData = ActivityProductService::exists($storeId, $extId, $actId, '', true);
        if (empty($productData)) {
            return [
                'code' => 60006,
                'msg' => 'The requested product does not exist',
                'data' => [],
            ];
        }

        //判断申请的产品库存
        if ($productData[Constant::DB_TABLE_QTY_APPLY] >= $productData['qty']) {//如果产品已经被申请完，就提示用户
            //发送库存不足邮件
            return [
                'code' => 60007,
                'msg' => 'Aplications are ran out, please apply other products.',
                'data' => [],
            ];
        }

        if (data_get($productData, 'type', 0) == 3) {//如果申请的是实物，就判断当前用户是否已经申请了实物，如果申请了就
            //解锁活动2.0判断用户能否申请产品
            return static::isCanApplyProduct($storeId, $actId, $customerId, $extId, $extType);
        }

        //判断 $extId  对应的产品是否已申请
        $applyData = static::exists($storeId, $customerId, $actId, $extId, $extType, false);
        if ($applyData) {//如果已申请，就提示已经申请
            return [
                'code' => 60005,
                'msg' => "You've already applied, do not submit repeatedly.",
                'data' => [],
            ];
        }

        return [
            'code' => 1,
            'msg' => '',
            'data' => [],
        ];
    }

    /**
     * 获取审核状态
     * @param int $storeId 商城id
     * @param int $actId  活动id
     * @param int $customerId  会员id
     * @param int $extId  产品id
     * @param int $extType 申请类型
     * @return array
     */
    public static function getAuditStatus($storeId, $actId, $customerId, $extType) {

        $where = [
            Constant::DB_TABLE_EXT_TYPE => $extType,
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId
        ];

        $auditStatus = static::getModel($storeId)->buildWhere($where)->orderBy(Constant::DB_TABLE_PRIMARY, 'DESC')->value(Constant::AUDIT_STATUS);
        $auditStatusData = [
            0 => 0, //审核中
            1 => 1, //审核通过
            2 => 2, //审核不通过
            3 => 0, //其他
        ];

        return [
            Constant::AUDIT_STATUS => $auditStatus === null ? -1 : Arr::get($auditStatusData, $auditStatus, -1)
        ];
    }

    public static function getDbExecutionPlan($storeId = 0, $where = [], $order = [], $offset = null, $limit = null, $isPage = false, $pagination = []) {

        $select = [
            'w.id as apply_id', 'w.helped_sum', 'w.audit_status', 'w.product_item_id',
            'p.' . Constant::DB_TABLE_PRIMARY, 'p.name', 'p.qty', 'p.qty_apply', 'p.img_url', 'p.mb_img_url', 'p.type', 'p.type_value', 'p.help_sum',
            'pi.type as item_type', 'pi.type_value as item_type_value',
        ];
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                Constant::DB_EXECUTION_PLAN_BUILDER => null,
                'make' => static::getModelAlias(),
                'from' => 'activity_applies as w',
                'joinData' => [
                    [
                        'table' => 'activity_products as p',
                        'first' => 'p.' . Constant::DB_TABLE_PRIMARY,
                        'operator' => '=',
                        'second' => 'w.ext_id',
                        'type' => 'left',
                    ],
                    [
                        'table' => 'activity_product_items as pi',
                        'first' => function ($join) {
                            $join->on([['pi.' . Constant::DB_TABLE_PRIMARY, '=', 'w.product_item_id'], ['pi.product_id', '=', 'w.ext_id']]); //->where('b.status', '=', 1);
                        },
                        'operator' => null,
                        'second' => null,
                        'type' => 'left',
                    ],
                ],
                Constant::DB_EXECUTION_PLAN_SELECT => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                'orders' => $order,
                'offset' => $offset,
                Constant::DB_EXECUTION_PLAN_LIMIT => $limit,
                'isPage' => $isPage,
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                    'type' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'item_type{or}type',
                        'data' => '',
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    'type_value' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'item_type_value{or}type_value',
                        'data' => '',
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                ],
                Constant::DB_EXECUTION_PLAN_UNSET => ['item_type', 'item_type_value'],
            ],
            'with' => [
            ],
            'itemHandleData' => [
            ],
                //'sqlDebug' => true,
        ];

        return $dbExecutionPlan;
    }

    /**
     * 获取db query
     * @param int $storeId 商城id
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getQuery($storeId, $where = []) {
        return static::getModel($storeId)
                        ->from('activity_applies as aa')
                        ->leftJoin('activities as act', 'act.' . Constant::DB_TABLE_PRIMARY, '=', 'aa.' . Constant::DB_TABLE_ACT_ID)
                        ->leftJoin('activity_products as ap', function ($join) {
                            $join->on('ap.' . Constant::DB_TABLE_PRIMARY, '=', 'aa.ext_id')->where('aa.ext_type', ActivityProductService::getModelAlias()); //->where('b.status', '=', 1);
                        })
                        ->buildWhere($where)
        ;
    }

    /**
     * 检查是否存在
     * @param int $id 角色id
     * @param bool $getData 是否获取数据 true:是 false:否 默认:false
     * @return mixed|static
     */
    public static function exists($storeId = 1, $customerId = 0, $actId = '', $extId = 0, $extType = 'ActivityProduct', $getData = false, $select = null) {
        $where = [];

        if ($extType) {
            $where[Constant::DB_TABLE_EXT_TYPE] = $extType;
        }

        if ($extId) {
            $where[Constant::DB_TABLE_EXT_ID] = $extId;
        }

        if ($actId) {
            $where[Constant::DB_TABLE_ACT_ID] = $actId;
        }

        if ($customerId) {
            $where[Constant::DB_TABLE_CUSTOMER_PRIMARY] = $customerId;
        }

        return static::existsOrFirst($storeId, '', $where, $getData, $select);
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @param array $order 排序控制
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $where = [];

        $account = data_get($params, Constant::DB_TABLE_ACCOUNT, ''); //账号
        $sku = data_get($params, 'sku', ''); //sku
        $country = data_get($params, 'country', ''); //站点
        $auditStatus = data_get($params, Constant::AUDIT_STATUS, ''); //审核状态
        $startAt = data_get($params, 'start_at', ''); //申请开始时间
        $entAt = data_get($params, 'end_at', ''); //申请结束时间
        $productName = data_get($params, 'product_name', ''); //产品名称
        $customerStartAt = data_get($params, 'customer_start_at', ''); //申请开始时间
        $customerEntAt = data_get($params, 'customer_end_at', ''); //申请结束时间
        $applyCountry = data_get($params, 'apply_country', ''); //申请者国家
        $actId = data_get($params, Constant::DB_TABLE_ACT_ID, ''); //活动id
        $applyType = data_get($params, 'apply_type', Constant::PARAMETER_STRING_DEFAULT); //申请类型
        $actName = data_get($params, 'act_name', '');

        if ($account) {//账号
            $where[] = ['aa.account', '=', $account];
        }

        if ($auditStatus !== '') {//审核状态
            $where[] = ['aa.audit_status', '=', $auditStatus];
        }

        if ($startAt) {//申请开始时间
            $where[] = ['aa.' . Constant::DB_TABLE_CREATED_AT, '>=', $startAt];
        }

        if ($entAt) {//申请结束时间
            $where[] = ['aa.' . Constant::DB_TABLE_CREATED_AT, '<=', $entAt];
        }

        if ($applyCountry !== '') {//申请者国家
            $where[] = ['aa.country', '=', $applyCountry];
        }

        if (!data_get($params, 'free_test_new', false) && $country) {
            $where[] = ['ap.country', '=', $country];
        }

        if (data_get($params, 'old_free_testing', 0)) {
            if ($sku) {//sku
                $where[] = ['ap.sku', '=', $sku];
            }
        }

        if ($customerStartAt) {//账号注册开始时间
            $where[] = ['c.' . Constant::DB_TABLE_OLD_CREATED_AT, '>=', $customerStartAt];
        }

        if ($customerEntAt) {//账号注册结束时间
            $where[] = ['c.' . Constant::DB_TABLE_OLD_CREATED_AT, '<=', $customerEntAt];
        }

        $_where = [];
        if ($actId) {//活动id
            $where[] = [Constant::ACT_ALIAS . Constant::LINKER . Constant::DB_TABLE_PRIMARY, '=', $actId];
            $_where[] = 'crm_aa.' . Constant::DB_TABLE_CREATED_AT . '<=crm_' . Constant::ACT_ALIAS . '.end_at';
        }

        if (data_get($params, Constant::DB_TABLE_PRIMARY, 0)) {
            $_where['aa.' . Constant::DB_TABLE_PRIMARY] = $params[Constant::DB_TABLE_PRIMARY];
        }
        if ($where) {
            $_where[] = $where;
        }

        $customizeWhere = [];
        if ($productName) {
            $customizeWhere[] =
                [
                    'method' => Constant::DB_EXECUTION_PLAN_WHERE,
                    'parameters' => function ($query) use ($productName) {
                        $query->OrWhere('ap.name', 'like', '%' . $productName . '%');
                        //->OrWhere('ap.name', 'like', '%' . $productName . '%');
                    },
                ];
        }

        if ($applyType !== Constant::PARAMETER_STRING_DEFAULT) {
            $customizeWhere[] =
                [
                    Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                    Constant::PARAMETERS_KEY => function ($query) use($applyType) {
                        $query->OrWhere('act.act_type', 7)->OrWhere('aa.apply_type', $applyType);
                    },
                ];
        }

        if ($actName !== Constant::PARAMETER_STRING_DEFAULT) {
            $customizeWhere[] =
                [
                    Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                    Constant::PARAMETERS_KEY => function ($query) use($actName) {
                        $query->Where('act.name', $actName);
                    },
                ];
        }

        if (!data_get($params, 'old_free_testing', 0) && $sku) {
            $customizeWhere[] =
                [
                    Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                    Constant::PARAMETERS_KEY => function ($query) use($sku) {
                        $query->OrWhere('ap.sku', $sku)->OrWhere('ap.shop_sku', $sku);
                    },
                ];
        }

        if (data_get($params, 'free_test_new', false) && $country) {
            //$where[] = ['aa.country', '=', $applyCountry];
            $customizeWhere[] =
                [
                    'method' => Constant::DB_EXECUTION_PLAN_WHERE,
                    'parameters' => function ($query) use($country) {
//                        $query->OrWhere('ap.country', '=', $country)
//                            ->orWhereRaw("EXISTS(select 1 from `ptxcrm`.`crm_metafields` WHERE owner_resource = 'ActivityProduct' and owner_id = `crm_ap`.`id` and namespace = 'free_testing' and `key` = 'country' and status = 1 and `value` = '$country')")
//                            ->where('aa.country', '=', $country);
                          $query->where('aa.country', '=', $country);
                    },
                ];
        }

        $_where['{customizeWhere}'] = $customizeWhere;

        $order = $order ? $order : [['aa.' . Constant::DB_TABLE_PRIMARY, 'DESC']];
        return Arr::collapse([parent::getPublicData($params, $order), [
                        Constant::DB_EXECUTION_PLAN_WHERE => $_where,
        ]]);
    }

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

        if (empty(data_get($params, Constant::DB_TABLE_PRIMARY, []))) {
            //data_set($params, 'old_free_testing', 1);
        }
        $_data = static::getPublicData($params);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);
        $order = data_get($params, Constant::DB_EXECUTION_PLAN_ORDER_BY, data_get($_data, Constant::ORDER, Constant::PARAMETER_ARRAY_DEFAULT));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, Constant::PARAMETER_ARRAY_DEFAULT);
        $limit = data_get($params, Constant::DB_EXECUTION_PLAN_LIMIT, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0));

        if (empty(data_get($params, Constant::DB_TABLE_PRIMARY, []))) {
            $where[Constant::ACT_ALIAS . Constant::LINKER . Constant::DB_TABLE_ACT_TYPE] = 7;
        }

        $storeId = data_get($params, 'store_id', 0); //商城id
        $select = $select ? $select : [
            'aa.' . Constant::DB_TABLE_PRIMARY,
            'act.name as act_name',
            'aa.' . Constant::DB_TABLE_CREATED_AT,
            'aa.' . Constant::DB_TABLE_ACT_ID,
            'aa.ip',
            'aa.' . Constant::DB_TABLE_CUSTOMER_PRIMARY,
            'aa.audit_status',
            'aa.reviewer',
            'aa.review_at',
            'aa.account',
            'aa.remarks',
            'ap.country',
            'ap.sku',
            'ap.name as product_name',
            'ap.shop_sku',
        ];

        $isOnlyGetPrimary = data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false);
        $isExport = data_get($params, 'is_export', data_get($params, 'srcParameters.0.is_export', 0));

        $joinData = [
            FunctionHelper::getExePlanJoinData('activities as act', Constant::ACT_ALIAS . Constant::LINKER . Constant::DB_TABLE_PRIMARY, '=', 'aa.' . Constant::DB_TABLE_ACT_ID),
            FunctionHelper::getExePlanJoinData('activity_products as ap', function ($join) {
                        $join->on('ap.' . Constant::DB_TABLE_PRIMARY, '=', 'aa.ext_id')->where('aa.ext_type', ActivityProductService::getModelAlias());
                    }),
        ];

        $isActivateData = DictService::getListByType('is_activate', Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //激活状态
        $auditStatusData = DictService::getListByType(Constant::AUDIT_STATUS, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //审核状态

        $field = 'customer_info.first_name{connection}customer_info.last_name';
        $data = Constant::PARAMETER_ARRAY_DEFAULT;
        $dataType = Constant::DB_EXECUTION_PLAN_DATATYPE_STRING;
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = Constant::PARAMETER_STRING_DEFAULT;
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, ' ', $isAllowEmpty, $callback, $only];

        $handleData = [
            'customer_name' => FunctionHelper::getExePlanHandleData(...$parameters), //会员名
            'is_activate' => FunctionHelper::getExePlanHandleData('customer_info.isactivate', $default, $isActivateData, Constant::PARAMETER_STRING_DEFAULT, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only), //账号激活
            Constant::AUDIT_STATUS => FunctionHelper::getExePlanHandleData(Constant::AUDIT_STATUS, $default, $auditStatusData, Constant::PARAMETER_STRING_DEFAULT, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only), //审核状态
            Constant::REVIEW_AT => FunctionHelper::getExePlanHandleData(Constant::REVIEW_AT, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only), //审核时间
            'register_country' => FunctionHelper::getExePlanHandleData('customer_info.country', $default, [], Constant::PARAMETER_STRING_DEFAULT, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
        ];

        if ($isExport && empty($isOnlyGetPrimary)) {
            $select = Arr::collapse([$select, [
                            'aai.social_media',
                            'aai.youtube_channel',
                            'aai.blogs_tech_websites',
                            'aai.deal_forums',
                            'aai.others',
                            'aai.is_purchased',
                            'aai.' . Constant::DB_TABLE_ORDER_NO,
                            'aai.order_country',
                            'aai.remarks as apply_remarks',
                            'aai.products',
            ]]);
            $joinData[] = FunctionHelper::getExePlanJoinData('activity_apply_infos as aai', function ($join) {
                        $join->on('aai.' . Constant::DB_TABLE_ACT_ID, '=', 'aa.' . Constant::DB_TABLE_ACT_ID)
                                ->whereColumn('aai.' . Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', 'aa.' . Constant::DB_TABLE_CUSTOMER_PRIMARY)
                                ->where('aai.status', '=', 1);
                    });

            //www.homasy.com||www.iseneo.com||holife.com等官网数据导出不需要关联customer_order
            if (in_array($storeId, [2, 3, 5, 6, 7, 8, 9, 10])) {
                $select[] = 'aai.order_status';
            } else {
                $joinData[] = FunctionHelper::getExePlanJoinData('customer_order as co', function ($join) {
                            $join->on('co.' . Constant::DB_TABLE_ORDER_NO, '=', 'aai.' . Constant::DB_TABLE_ORDER_NO)
                                    ->whereColumn('co.' . Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', 'aai.' . Constant::DB_TABLE_CUSTOMER_PRIMARY)
                                    ->where('co.status', '=', 1);
                        });
                $select[] = 'co.order_status';
            }

            $orderStatusData = DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //订单状态 -1:匹配中 0:未支付 1:已经支付 2:取消 默认:-1
            //订单状态
            data_set($handleData, Constant::DB_TABLE_ORDER_STATUS, FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_ORDER_STATUS, $default, $orderStatusData, Constant::PARAMETER_STRING_DEFAULT, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only));

            //个人资料网址
            data_set($handleData, 'profile_url', FunctionHelper::getExePlanHandleData('customer_info.profile_url', $default, [], Constant::PARAMETER_STRING_DEFAULT, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only));

            //感兴趣的产品
            data_set($handleData, 'products', FunctionHelper::getExePlanHandleData('json|products', $default, [], $dataType, $dateFormat, $time, ' , ', $isAllowEmpty, $callback, $only));

            //sku
            data_set($handleData, 'sku', FunctionHelper::getExePlanHandleData('shop_sku{or}sku', $default, [], Constant::PARAMETER_STRING_DEFAULT, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only));
        }

        $unset = ['customer_info'];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), 'activity_applies as aa', $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $with = [
            'customer_info' => FunctionHelper::getExePlan(0, null, '', '', [Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::DB_TABLE_FIRST_NAME, Constant::DB_TABLE_LAST_NAME, 'isactivate', Constant::DB_TABLE_COUNTRY, 'profile_url'], [], [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::HAS_ONE),
        ];

        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
        ];

        if ($isOnlyGetPrimary) {//如果仅仅获取主键id，就不需要处理数据，不关联
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT . Constant::LINKER . Constant::DB_EXECUTION_PLAN_HANDLE_DATA, Constant::PARAMETER_ARRAY_DEFAULT);
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_WITH, Constant::PARAMETER_ARRAY_DEFAULT);
        }

        $dataStructure = 'list';
        $flatten = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * 审核
     * @param int $id 分享id
     * @param int $auditStatus 审核状态 0:未审核 1:审核通过 2:审核不通过 3:其他
     * @param string $reviewer 审核人
     * @param string $remarks 备注
     * @return array $rs ['code' => 1, 'msg' => '', 'data' => []]
     */
    public static function audit($storeId, $ids, $auditStatus, $reviewer, $remarks, $applyType = 0) {

        $rs = ['code' => 1, 'msg' => '', 'data' => []];

        if (empty($ids) || !is_array($ids)) {
            return $rs;
        }

        $where = [
            Constant::DB_TABLE_PRIMARY => $ids,
        ];
        $select = [Constant::DB_TABLE_PRIMARY, Constant::DB_TABLE_EXT_ID, Constant::DB_TABLE_ACCOUNT, Constant::DB_TABLE_ACT_ID, Constant::DB_TABLE_EXT_TYPE];
        $make = static::getModelAlias();
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                Constant::DB_EXECUTION_PLAN_BUILDER => null,
                'make' => $make,
                'from' => '',
                Constant::DB_EXECUTION_PLAN_SELECT => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
                Constant::DB_EXECUTION_PLAN_UNSET => [],
            ],
            'with' => [
                'activity' => [
                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                    Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                    'relation' => Constant::HAS_ONE,
                    Constant::DB_EXECUTION_PLAN_SELECT => [Constant::DB_TABLE_PRIMARY, Constant::DB_TABLE_NAME, 'start_at', 'end_at'],
                    Constant::DB_EXECUTION_PLAN_DEFAULT => [],
                    Constant::DB_EXECUTION_PLAN_WHERE => [],
                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
                    Constant::DB_EXECUTION_PLAN_UNSET => [],
                ],
            ]
        ];

        $dataStructure = 'list';
        $flatten = false;
        $isGetQuery = false;
        $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
        if (empty($data)) {
            $rs['code'] = 0;
            $rs['msg'] = '数据不存在';
            return $rs;
        }

//        $ret = static::hasEnoughStock($storeId, $data, $auditStatus);
//        if ($ret[Constant::RESPONSE_CODE_KEY] != 1) {
//            return $ret;
//        }

        $model = static::getModel($storeId, '');
        $nowTime = Carbon::now()->toDateTimeString();
        $updateData = [
            Constant::AUDIT_STATUS => $auditStatus, //审核状态
            'reviewer' => $reviewer, //审核人
            'remarks' => $remarks, //审核意见
            Constant::REVIEW_AT => $nowTime, //审核时间
            Constant::DB_TABLE_UPDATED_AT => $nowTime, //记录更新时间
        ];
        $model->buildWhere([Constant::DB_TABLE_PRIMARY => $ids])->update($updateData);

        $activityAuditLog = static::createModel($storeId, 'ActivityAuditLog', [], '');
        $activityAuditLogData = [];
        foreach ($data as $item) {

            $extId = Arr::get($item, Constant::DB_TABLE_PRIMARY, 0); //申请id
            $extType = Arr::get($item, Constant::DB_TABLE_EXT_TYPE, ''); //活动产品模型
            $actId = Arr::get($item, Constant::DB_TABLE_ACT_ID, 0); //活动id
            if ($extType && $auditStatus == 1) {//申请产品审核通过，就更新产品库存
                $activityAuditLogWhere = [
                    Constant::DB_TABLE_EXT_TYPE => $make,
                    Constant::DB_TABLE_EXT_ID => $extId,
                    Constant::AUDIT_STATUS => $auditStatus, //审核状态
                ];
                $isExists = $activityAuditLog->buildWhere($activityAuditLogWhere)->exists();
                //如果没有审核通过的流水，就更新库存  && holife,iseneo,homasy等官网众测不更新qty_apply字段
                if (!$isExists && !in_array($storeId, [2, 3, 5, 6, 7, 8, 9, 10])) {
                    $where = [Constant::DB_TABLE_PRIMARY => Arr::get($item, Constant::DB_TABLE_EXT_ID, '')]; //活动产品id
                    $opData = [
                        1 => '+',
                        2 => '-',
                    ];
                    $upData = [Constant::DB_TABLE_QTY_APPLY => DB::raw(Constant::DB_TABLE_QTY_APPLY . $opData[$auditStatus] . '1')];
                    ActivityProductService::insert($storeId, $where, $upData);
                }
            }

            $activityAuditLogData[] = [
                Constant::DB_TABLE_EXT_TYPE => $make,
                Constant::DB_TABLE_EXT_ID => $extId,
                'audit_data' => json_encode($updateData, JSON_UNESCAPED_UNICODE),
                Constant::AUDIT_STATUS => $auditStatus, //审核状态
                'reviewer' => $reviewer, //审核人
                Constant::DB_TABLE_CREATED_AT => $nowTime,
                Constant::DB_TABLE_UPDATED_AT => $nowTime,
            ];

            if (in_array($auditStatus, [1, 2])) {
                //评测2.0，homasy官网|iseneo官网|mpow官网|vt
                if ($applyType == 1 && in_array($storeId, [1,2,6,9])) {
                    $actId = -1;
                }

                $remark = data_get($item, 'activity.name', '') . '审核';
                $service = static::getNamespaceClass();
                $method = 'getAuditMailData'; //获取审核邮件数据
                $parameters = [$storeId, $extId, $auditStatus, $actId, $applyType];
                $extData = [
                    Constant::ACT_ID => $actId, //活动id
                    'service' => $service,
                    'method' => $method,
                    'parameters' => $parameters,
                ];
                $group = 'activity';
                $type = 'audit';
                $emailRs = EmailService::handle($storeId, data_get($item, Constant::DB_TABLE_ACCOUNT, ''), $group, $type, $remark, $extId, $make, $extData);
                if (data_get($emailRs, 'code', 0) != 1) {
                    return $emailRs;
                }
            }
        }

        //添加审核流水
        if ($activityAuditLogData) {
            $activityAuditLog->insert($activityAuditLogData);
        }

        return $rs;
    }

    /**
     * 添加评论链接（新的）
     * @param string $order_id
     * @param string $customer_id 客户id
     * @param string $review_link 评论链接
     * @return array|objects
     */
    public static function newAddReviewLink($store_id, $order_id, $customer_id, $account, $review_link = '') {
        $where = [];
        if ($order_id) {
            $where[Constant::DB_TABLE_EXT_ID] = $order_id;
        }
        if ($customer_id) {
            $where[Constant::DB_TABLE_CUSTOMER_PRIMARY] = $customer_id;
        }
        $model = static::getModel($store_id);
        $reviewLink = trim($review_link); //格式化链接的空格
        $reviewTime = Carbon::now()->toDateTimeString();
        $review = [
            'review_link' => $reviewLink,
            Constant::DB_TABLE_CREATED_AT => $reviewTime,
            Constant::DB_TABLE_UPDATED_AT => $reviewTime,
            Constant::DB_TABLE_ACCOUNT => $account,
            Constant::AUDIT_STATUS => 0,
            Constant::DB_TABLE_EXT_TYPE => 'CustomerOrder',
            Constant::DB_TABLE_ACT_ID => 2
        ]; //提交成功，-1为初始 0为审核中 1为审核通过 2为审核失败 3为其他
        return $model->updateOrCreate($where, $review); //更新添加
    }

    /**
     * 判断用户能否申请产品
     * @param int $storeId
     * @param int $actId
     * @param int $customerId
     * @param int $productId
     * @param string $extType
     * @return array
     */
    public static function isCanApplyProduct($storeId, $actId, $customerId, $productId, $extType) {
        $where = [
            Constant::DB_TABLE_EXT_TYPE => $extType,
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::PRODUCT_TYPE => 3,
        ];
        $applyDatas = static::getModel($storeId)->buildWhere($where)->get()->toArray();
        //未申请过产品，能申请
        if (empty($applyDatas)) {
            return [
                'code' => 1,
                'msg' => '',
                'data' => []
            ];
        }

        $applyDatas = array_column($applyDatas, null, Constant::DB_TABLE_EXT_ID);
        //已申请过此产品
        if (isset($applyDatas[$productId])) {
            return [
                'code' => 60005,
                'msg' => "You've already applied, do not submit repeatedly.",
                'data' => [],
            ];
        }

        $isCanApply = true;
        //判断已申请过的产品，是不是因为库存不足，解锁失败
        foreach ($applyDatas as $item) {
            if ($item[Constant::AUDIT_STATUS] != -2) {
                $isCanApply = false;
            }
        }

        //申请过的产品，因库存不足，解锁失败，可申请其他库存足的产品
        if ($isCanApply) {
            return [
                'code' => 1,
                'msg' => '',
                'data' => []
            ];
        }

        //已有在申请的产品
        return [
            'code' => 60003,
            'msg' => "Your application is still in progress, please wait for the result.",
            'data' => [],
        ];
    }

    public static function getCount($storeId, $actId, $extId, $extType = 'ActivityProduct') {
        $where = [
            Constant::DB_TABLE_EXT_TYPE => $extType,
            Constant::DB_TABLE_EXT_ID => $extId,
            Constant::DB_TABLE_ACT_ID => $actId
        ];

        return static::getModel($storeId)->buildWhere($where)->count();
    }

    /**
     * 判断用户是否已经申请过
     * @param int $storeId
     * @param int $actId
     * @param int $customerId
     * @param array $extIds
     * @param string $extType
     * @param string $ip
     * @return array
     */
    public static function isCanApplyAccessories($storeId, $actId, $customerId, array $extIds, $extType, $ip) {

        //判断申请的产品是否存在
        $where = [
            Constant::DB_TABLE_PRIMARY => $extIds,
            Constant::DB_TABLE_ACT_ID => $actId
        ];
        $products = ActivityProductService::getModel($storeId)->buildWhere($where)->get();
        if (empty($products)) {
            return [
                'code' => 60006,
                'msg' => 'The requested product does not exist',
                'data' => [],
            ];
        }
        $products = $products->toArray();
        if (count($products) < count($extIds)) {
            return [
                'code' => 60006,
                'msg' => 'The requested some product does not exist',
                'data' => [],
            ];
        }

        //判断申请的配件数量是否正确
        $countArr = [0 => 0, 1 => 0];
        foreach ($products as $product) {
            $countArr[$product['in_stock']] ++;
        }
        if ($countArr[0] > 1 || $countArr[1] > 1) {
            //申请产品的数量错误
            return [
                'code' => 60009,
                'msg' => 'The requested quantity of products is illega',
                'data' => [],
            ];
        }

        //判断当前用户能否申请
        $_applyInfoData = ActivityApplyInfoService::getApplyInfo($storeId, $actId, $customerId);
        if ($_applyInfoData->count() >= 2) {
            return [
                'code' => 60100,
                'msg' => "Your apply has reached the maximum, please contact customer service manually <support@holife.com>",
                'data' => [],
            ];
        }

        //判断是否已经申请过该配件
        foreach ($_applyInfoData as $applyInfoDatum) {
            $applyExtId = data_get($applyInfoDatum, Constant::DB_TABLE_EXT_ID, Constant::DB_TABLE_INVITE_ACCOUNT);
            $applyData = ActivityApplyService::existsOrFirst($storeId, '', [Constant::DB_TABLE_PRIMARY => $applyExtId], true);
            $productId = data_get($applyData, Constant::DB_TABLE_EXT_ID, Constant::PARAMETER_INT_DEFAULT);
            if (in_array($productId, $extIds)) {
                return [
                    'code' => 60100,
                    'msg' => "Your apply has reached the maximum, please contact customer service manually <support@holife.com>",
                    'data' => [],
                ];
            }
        }

        //判断是否已经申请过该配件
        foreach ($extIds as $extId) {
            $where = [
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                Constant::DB_TABLE_ACT_ID => $actId,
                Constant::DB_TABLE_EXT_ID => $extId,
                Constant::AUDIT_STATUS => 1,
            ];
            $exists = ActivityApplyService::existsOrFirst($storeId, '', $where);
            if ($exists) {
                return [
                    'code' => 60100,
                    'msg' => "Your apply has reached the maximum, please contact customer service manually <support@holife.com>",
                    'data' => [],
                ];
            }
        }

        //判断当前IP下用户能否申请
        $where = [
            Constant::DB_TABLE_EXT_TYPE => $extType,
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::PRODUCT_TYPE => 3,
            'ip' => $ip,
        ];
        $_applyData = static::getModel($storeId)->select([Constant::DB_TABLE_CUSTOMER_PRIMARY])->buildWhere($where)->get();
        if (!$_applyData->isEmpty()) {
            $customerIds = array_column($_applyData->toArray(), Constant::DB_TABLE_CUSTOMER_PRIMARY);
            $_applyInfoData = ActivityApplyInfoService::getApplyInfo($storeId, $actId, $customerIds);
            if ($_applyInfoData->count() >= 2) {
                return [
                    'code' => 60003,
                    'msg' => "Your IP/Account Is Applid.",
                    'data' => [],
                ];
            }
        }

        return [
            'code' => 1,
            'msg' => '',
            'data' => [
                'products' => $products
            ],
        ];
    }

    /**
     * 申请配件
     * @param Request $request
     * @param array $products
     * @param string $ip
     * @return array
     */
    public static function addApply(Request $request, $products, $ip) {
        $storeId = $request->input(Constant::DB_TABLE_STORE_ID, 0); //商城id
        $account = $request->input(Constant::DB_TABLE_ACCOUNT, ''); //会员账号
        $actId = $request->input(Constant::DB_TABLE_ACT_ID, 0); //活动id
        $customerId = $request->input(Constant::DB_TABLE_CUSTOMER_PRIMARY, 0); //会员id
        $extIds = $request->input('product_ids', []); //关联id 活动产品id

        $result = [];
        static::getModel($storeId, '')->getConnection()->beginTransaction();
        try {
            $nowTime = Carbon::now()->toDateTimeString();

            static::_findPartsProcess($storeId, $actId, $customerId);

            //申请产品体验
            foreach ($products as $product) {
                $where = [
                    Constant::DB_TABLE_EXT_TYPE => ActivityProductService::getModelAlias(), //关联模型 活动产品
                    Constant::DB_TABLE_EXT_ID => data_get($product, Constant::DB_TABLE_PRIMARY), //关联id 活动产品id
                    Constant::DB_TABLE_ACT_ID => $actId, //活动id
                    Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId, //会员id
                    Constant::AUDIT_STATUS => 0
                ];

                $applyData = [
                    Constant::DB_TABLE_IP => $ip,
                    Constant::DB_TABLE_ACCOUNT => $account,
                    Constant::PRODUCT_TYPE => data_get($product, Constant::DB_TABLE_TYPE, 0),
                    Constant::DB_TABLE_COUNTRY => $request->input(Constant::DB_TABLE_COUNTRY, ''),
                    Constant::DB_TABLE_EXT_ID => data_get($product, Constant::DB_TABLE_PRIMARY, 0),
                ];

                $isInserted = ActivityApplyService::updateOrCreate($storeId, $where, $applyData);
                if (empty($isInserted)) {
                    //添加申请记录失败
                    throw new \Exception(null, 60010);
                }

                if (count($products) == 1) {
                    data_set($result, 'apply_id', data_get($isInserted, 'data.' . Constant::DB_TABLE_PRIMARY, 0));
                }
                if (data_get($product, 'in_stock', 0) == 1 && count($products) > 1) {
                    data_set($result, 'apply_id', data_get($isInserted, 'data.' . Constant::DB_TABLE_PRIMARY, 0));
                }
            }

            //更新申请数量字段
            $updateWhere = [
                Constant::DB_TABLE_PRIMARY => $extIds
            ];
            $updateData = [
                Constant::DB_TABLE_QTY_APPLY => DB::raw('qty_apply+1'),
                Constant::DB_TABLE_UPDATED_AT => $nowTime,
            ];
            $isUpdated = ActivityProductService::update($storeId, $updateWhere, $updateData);
            if (empty($isUpdated)) {
                //更新申请数量失败
                throw new \Exception(null, 61113);
            }

            static::getModel($storeId, '')->getConnection()->commit();
        } catch (\Exception $exception) {
            static::getModel($storeId, '')->getConnection()->rollBack();
            return Response::getDefaultResponseData($exception->getCode(), $exception->getMessage());
        }

        return Response::getDefaultResponseData(1, null, $result);
    }

    /**
     * 获取审核邮件数据
     * @param int $storeId 商城id
     * @param int $id 审核id
     * @param int $auditStatus 审核状态
     * @param int $actId
     * @param int $applyType
     * @return array
     */
    public static function getAuditMailData($storeId, $id, $auditStatus, $actId, $applyType) {

        $where = [
            Constant::DB_TABLE_PRIMARY => $id,
        ];
        $select = [Constant::DB_TABLE_PRIMARY, Constant::DB_TABLE_EXT_ID, Constant::DB_TABLE_EXT_TYPE, Constant::DB_TABLE_ACCOUNT, Constant::DB_TABLE_ACT_ID, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::DB_TABLE_COUNTRY . ' as apply_country'];
        $make = static::getModelAlias();
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                Constant::DB_EXECUTION_PLAN_BUILDER => null,
                'make' => $make,
                'from' => '',
                Constant::DB_EXECUTION_PLAN_SELECT => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                    'product_name' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'ext.name',
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    'country' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'ext.country',
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    'url' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'ext.url',
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    'account_name' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'customer_info.first_name{connection}customer_info.last_name',
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => 'string',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => ' ',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    Constant::DB_TABLE_ACCOUNT => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'account{or}account_name',
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => ' ',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    'first_name' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'customer_info.first_name',
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => 'string',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => ' ',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    'img_url' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'ext.img_url',
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                ],
                Constant::DB_EXECUTION_PLAN_UNSET => ['ext', 'account_name'],
            ],
            'with' => [
//                'ext' => [
//                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
//                    Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
//                    'relation' => Constant::HAS_ONE,
//                    //Constant::DB_EXECUTION_PLAN_SELECT => [Constant::DB_TABLE_PRIMARY],
//                    Constant::DB_EXECUTION_PLAN_DEFAULT => [],
//                    Constant::DB_EXECUTION_PLAN_WHERE => [],
//                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
//                //Constant::DB_EXECUTION_PLAN_UNSET => ['customer_info'],
//                ],
                'customer_info' => [
                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                    Constant::DB_EXECUTION_PLAN_STOREID => 0,
                    'relation' => Constant::HAS_ONE,
                    Constant::DB_EXECUTION_PLAN_SELECT => [Constant::DB_TABLE_CUSTOMER_PRIMARY, 'first_name', 'last_name', 'account'],
                    Constant::DB_EXECUTION_PLAN_DEFAULT => [],
                    Constant::DB_EXECUTION_PLAN_WHERE => [],
                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
                    Constant::DB_EXECUTION_PLAN_UNSET => ['customer_info'],
                ],
            ],
                //'sqlDebug' => true,
        ];

        $dataStructure = 'one';
        $flatten = false;
        $isGetQuery = true;
        $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
        $data = $data->get();
        $rs = [
            'code' => 1,
            Constant::DB_EXECUTION_PLAN_STOREID => $storeId, //商城id
            Constant::ACT_ID => 0, //活动id
            Constant::DB_TABLE_CONTENT => '', //邮件内容
            Constant::SUBJECT => '',
        ];
        if (empty($data)) {
            return $rs;
        }

        if ($auditStatus == 1) {
            foreach ($data as $item) {
                $item->ext;
            }
        }

        FunctionHelper::dbDebug($dbExecutionPlan); //sql Debug
        $data = FunctionHelper::handleResponseData($data, $dbExecutionPlan, $flatten, false, $dataStructure);

        //获取活动配置数据
        $actId = Arr::get($data, Constant::DB_TABLE_ACT_ID, 0);
        $type = 'email';
        //评测2.0,homasy官网
        if ($applyType == 1 && in_array($storeId, [1,6,9,2])) {
            $actId = -1;
            $applyCountry = data_get($data, 'apply_country', '');
            //$productCountry = data_get($data, 'country', '');

            $where = [
                Constant::DB_TABLE_STORE_ID => $storeId,
                Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId(Constant::PLATFORM_SHOPIFY),
                Constant::OWNER_RESOURCE => ActivityProductService::getMake(),
                Constant::OWNER_ID => data_get($data, Constant::DB_TABLE_EXT_ID),
                Constant::NAME_SPACE => 'free_testing',
            ];
            $select = [Constant::DB_TABLE_KEY, Constant::DB_TABLE_VALUE];
            $metaFields = MetafieldService::getModel($storeId)->select($select)->buildWhere($where)->get();
            if (!$metaFields->isEmpty()) {
                $metaFields = $metaFields->toArray();
                $counties = [];
                $urls = [];
                foreach ($metaFields as $metaField) {
                    if ($metaField[Constant::DB_TABLE_KEY] == Constant::DB_TABLE_COUNTRY) {
                        $counties[] = $metaField[Constant::DB_TABLE_VALUE];
                    }
                    if ($metaField[Constant::DB_TABLE_KEY] == 'url') {
                        $exp = explode('{@#}', $metaField[Constant::DB_TABLE_VALUE]);
                        $urls[$exp[0]] = $exp[1];
                    }
                }
                if ($counties) {
                    if (in_array($applyCountry, $counties)) {
                        data_set($data, Constant::DB_TABLE_COUNTRY, $applyCountry);
                        if (data_get($urls, $applyCountry, '')) {
                            data_set($data, 'url', data_get($urls, $applyCountry, ''));
                        }
                    } else {
                        data_set($data, Constant::DB_TABLE_COUNTRY, $counties[0]);
                        if (data_get($urls, $counties[0], '')) {
                            data_set($data, 'url', data_get($urls, $counties[0], ''));
                        }
                    }
                }
            }
        }
        $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, $type);
        $emailView = Arr::get($activityConfigData, $type . '_view_audit_' . $auditStatus . '.' . Constant::DB_TABLE_VALUE, ''); //邮件模板
        $subject = Arr::get($activityConfigData, $type . '_audit_subject_' . $auditStatus . '.' . Constant::DB_TABLE_VALUE, ''); //邮件主题
        $from = Arr::get($activityConfigData, $type . '_from.' . Constant::DB_TABLE_VALUE, ''); //回复邮件默认邮件内容
        $reportBody = Arr::get($activityConfigData, $type . '_audit_report_body_' . $auditStatus . '.' . Constant::DB_TABLE_VALUE, ''); //回复邮件默认邮件内容
        $reportSubject = Arr::get($activityConfigData, $type . '_audit_report_subject_' . $auditStatus . '.' . Constant::DB_TABLE_VALUE, $subject); //回复邮件默认邮件标题
        Arr::set($rs, Constant::ACT_ID, $actId);
        Arr::set($rs, Constant::SUBJECT, $subject);

        //获取邮件模板
        $reportData = [
            Constant::SUBJECT . '=' . $reportSubject,
            'body=' . $reportBody,
        ];
        $replacePairs = [
            '{{$reportData}}' => $from . '?' . implode('&', $reportData),
            '{{$from}}' => $from,
            '{{$subject}}' => $subject,
            '{{$body}}' => $reportBody,
        ];
        foreach ($data as $key => $value) {
            $replacePairs['{{$' . $key . '}}'] = $value;
        }
        Arr::set($rs, Constant::DB_TABLE_CONTENT, strtr($emailView, $replacePairs));

        unset($data);
        unset($replacePairs);

        return $rs;
    }

    /**
     * 审核通过时，库存是否足够
     * @param int $storeId 官网id
     * @param array $applies 申请数据
     * @param int $auditStatus 审核状态
     * @return array
     */
    public static function hasEnoughStock($storeId, $applies, $auditStatus) {
        $ret = [Constant::RESPONSE_CODE_KEY => 1, Constant::RESPONSE_MSG_KEY => '', Constant::RESPONSE_DATA_KEY => []];

        //无申请数据或者不是审核通过的状态
        if (empty($applies) || $auditStatus != 1) {
            return $ret;
        }

        $countMap = [];
        $applyGroup = [];
        foreach ($applies as $apply) {
            $applyId = data_get($apply, Constant::DB_TABLE_PRIMARY, 0);
            $extId = data_get($apply, Constant::DB_TABLE_EXT_ID, 0);
            if (!empty($extId)) {
                isset($countMap[$extId]) ? $countMap[$extId] ++ : data_set($countMap, $extId, 1);
                isset($applyGroup[$extId]) ? $applyGroup[$extId][] = $applyId : data_set($applyGroup, "$extId.0", $applyId);
            }
        }

        if (empty($countMap)) {
            return $ret;
        }

        $where = [
            Constant::DB_TABLE_PRIMARY => array_keys($countMap)
        ];

        $products = ActivityProductService::getModel($storeId)->buildWhere($where)->select([Constant::DB_TABLE_PRIMARY, 'sku', 'qty', Constant::DB_TABLE_QTY_APPLY])->get();
        if (empty($products)) {
            return $ret;
        }

        $hasEnough = true;
        $msg = "产品库存不足\n";
        $activityAuditLog = static::createModel($storeId, 'ActivityAuditLog', [], '');
        foreach ($products as $product) {
            $sku = data_get($product, 'sku', '');
            $qty = data_get($product, 'qty');
            $qtyApply = data_get($product, Constant::DB_TABLE_QTY_APPLY);
            $productId = data_get($product, Constant::DB_TABLE_PRIMARY);
            $applyIds = data_get($applyGroup, $productId);

            //去掉已经审核通过的数据
            $activityAuditLogWhere = [
                Constant::DB_TABLE_EXT_TYPE => static::getMake(),
                Constant::DB_TABLE_EXT_ID => $applyIds,
                Constant::AUDIT_STATUS => $auditStatus,
            ];
            $counts = $activityAuditLog->buildWhere($activityAuditLogWhere)->count(DB::Raw('distinct ext_id'));

            //审批的数量大于剩余库存
            if (($countMap[$productId] - $counts) > ($qty - $qtyApply)) {
                $lessQty = $qty - $qtyApply > 0 ? $qty - $qtyApply : 0;
                $auditQty = $countMap[$productId] - $counts;
                if ($auditQty > 0) {
                    $hasEnough = false;
                    $msg .= "$sku,当前剩余库存:$lessQty,申请库存:$auditQty\n";
                }
            }
        }

        if (!$hasEnough) {
            $ret[Constant::RESPONSE_CODE_KEY] = 0;
            $ret[Constant::RESPONSE_MSG_KEY] = $msg;
            return $ret;
        }

        return $ret;
    }

    /**
     * 活动申请产品列表
     * @param array $params 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认:true
     * @param boolean $isPage  是否分页 true:是 false:否 默认:true
     * @param array $select  查询字段
     * @param boolean $isRaw 是否原始 select true:是 false:否 默认:false
     * @param boolean $isGetQuery 是否获取 query
     * @param boolean $isOnlyGetCount 是否仅仅获取总记录数
     * @return array|\Hyperf\Database\Model\Builder 列表数据|Builder
     */
    public static function getActApplyList($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $orderByData = data_get($params, 'order_by_data', []);
        $_data = static::getPublicData($params, $orderByData);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);
        $order = data_get($params, Constant::DB_EXECUTION_PLAN_ORDER_BY, data_get($_data, Constant::ORDER, Constant::PARAMETER_ARRAY_DEFAULT));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::DB_EXECUTION_PLAN_LIMIT, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0));
        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0);

        if (empty(data_get($params, Constant::DB_TABLE_PRIMARY, []))) {
            $where[Constant::ACT_ALIAS . Constant::LINKER . Constant::DB_TABLE_ACT_TYPE] = 5;
        }

        $select = $select ? $select : [
            Constant::ACT_ALIAS . Constant::LINKER . Constant::DB_TABLE_NAME . ' as act_' . Constant::DB_TABLE_NAME,
            'aa' . Constant::LINKER . Constant::DB_TABLE_PRIMARY,
            'aa' . Constant::LINKER . Constant::DB_TABLE_ACCOUNT,
            'ci' . Constant::LINKER . Constant::DB_TABLE_FIRST_NAME,
            'ci' . Constant::LINKER . Constant::DB_TABLE_LAST_NAME,
            'ci' . Constant::LINKER . Constant::DB_TABLE_IP,
            'aa' . Constant::LINKER . Constant::DB_TABLE_COUNTRY,
            Constant::ACT_PRODUCT_ALIAS . Constant::LINKER . Constant::DB_TABLE_NAME . ' as product_' . Constant::DB_TABLE_NAME,
            'c' . Constant::LINKER . Constant::DB_TABLE_OLD_CREATED_AT,
        ];

        $field = Constant::DB_TABLE_FIRST_NAME . '{connection}' . Constant::DB_TABLE_LAST_NAME;
        $data = Constant::PARAMETER_ARRAY_DEFAULT;
        $dataType = 'string';
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = Constant::PARAMETER_STRING_DEFAULT;
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;

        $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];
        $handleData = [
            'use_name' => FunctionHelper::getExePlanHandleData(...$parameters),
        ];

        $joinData = [
            FunctionHelper::getExePlanJoinData('activities as ' . Constant::ACT_ALIAS, function ($join) {
                        $join->on([['aa' . Constant::LINKER . Constant::DB_TABLE_ACT_ID, '=', Constant::ACT_ALIAS . Constant::LINKER . Constant::DB_TABLE_PRIMARY]]);
                    }),
            FunctionHelper::getExePlanJoinData('activity_products as ' . Constant::ACT_PRODUCT_ALIAS, function ($join) {//
                        $join->on([['aa.' . Constant::DB_TABLE_EXT_ID, '=', Constant::ACT_PRODUCT_ALIAS . Constant::LINKER . Constant::DB_TABLE_PRIMARY]])->where('aa.' . Constant::DB_TABLE_EXT_TYPE, '=', ActivityProductService::getModelAlias());
                    }),
            FunctionHelper::getExePlanJoinData(DB::raw('`ptxcrm`.`crm_customer` as `crm_c`'), function ($join) {//
                        $join->on([['aa.' . Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', 'c.' . Constant::DB_TABLE_CUSTOMER_PRIMARY]]);
                    }),
            FunctionHelper::getExePlanJoinData(DB::raw('`ptxcrm`.`crm_customer_info` as `crm_ci`'), function ($join) {//
                        $join->on([['ci.' . Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', 'c.' . Constant::DB_TABLE_CUSTOMER_PRIMARY]]);
                    }),
        ];

        $unset = [
            Constant::DB_TABLE_FIRST_NAME,
            Constant::DB_TABLE_LAST_NAME,
        ];

        $isExport = data_get($params, 'is_export', data_get($params, 'srcParameters.0.is_export', 0));
        $with = Constant::PARAMETER_ARRAY_DEFAULT;
        if ($storeId == 3 && $isExport && !data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false)) {//如果是导出并且不是仅仅获取主键id，就关联上报流水获取 客户浏览数据
            $select[] = 'aa' . Constant::LINKER . Constant::DB_TABLE_CREATED_MARK;
            $clientDataSelect = [
                Constant::DB_TABLE_CREATED_MARK,
                Constant::DEVICE, //设备信息
                Constant::DEVICE_TYPE, // 设备类型 1:手机 2：平板 3：桌面
                Constant::DB_TABLE_PLATFORM, //系统信息
                Constant::PLATFORM_VERSION, //系统版本
                Constant::BROWSER, // 浏览器信息  (Chrome, IE, Safari, Firefox, ...)
                Constant::BROWSER_VERSION, // 浏览器版本
                Constant::LANGUAGES, // 语言 ['nl-nl', 'nl', 'en-us', 'en']
                Constant::IS_ROBOT, //是否是机器人
            ];
            $with = [
                'client_data' => FunctionHelper::getExePlan(
                        $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $clientDataSelect, [], [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], [], 'hasOne', false, Constant::PARAMETER_ARRAY_DEFAULT), //关联客户端基本数据
            ];

            $deviceTypeData = data_get($data, 'srcParameters.0.deviceTypeData', FunctionHelper::getDeviceType($storeId)); //审核状态 -1:未提交审核 0:未审核 1:已通过 2:未通过 3:其他
            $isRobotData = data_get($data, 'srcParameters.0.isRobotData', FunctionHelper::getWhetherData(null)); //订单状态 -1:匹配中 0:未支付 1:已经支付 2:取消 默认:-1
            $handleData = Arr::collapse([$handleData,
                        [
                            'client_data' . Constant::LINKER . Constant::DEVICE_TYPE => FunctionHelper::getExePlanHandleData('client_data' . Constant::LINKER . Constant::DEVICE_TYPE, $default, $deviceTypeData, Constant::PARAMETER_STRING_DEFAULT, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
                            'client_data' . Constant::LINKER . Constant::IS_ROBOT => FunctionHelper::getExePlanHandleData('client_data' . Constant::LINKER . Constant::IS_ROBOT, $default, $isRobotData, Constant::PARAMETER_STRING_DEFAULT, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
                        ]
            ]);
            $unset[] = 'client_data';
        }


        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), 'activity_applies as aa', $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
        ];

        if (data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT . '.' . Constant::DB_EXECUTION_PLAN_HANDLE_DATA, Constant::PARAMETER_ARRAY_DEFAULT);
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_WITH, Constant::PARAMETER_ARRAY_DEFAULT);
        }

        $dataStructure = 'list';
        $flatten = true;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * 获取邮件数据
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $customerId 收件者账号id
     * @param string $toEmail 收件者邮箱
     * @param string $ip 收件人ip
     * @param int $extId 关联id
     * @param string $extType 关联模型
     * @param string $type 活动配置类型
     * @param array $extData 扩展参数
     * @return array $rs 邮件任务进入消息队列结果
     */
    public static function getJoinActEmailData($storeId, $actId, $customerId, $ip, $extId, $extType, $type = 'join_email', $extData = []) {

        $rs = [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::DB_EXECUTION_PLAN_STOREID => $storeId, //商城id
            Constant::ACT_ID => $actId, //活动id
            Constant::DB_TABLE_CONTENT => '', //邮件内容
            Constant::SUBJECT => '',
            Constant::DB_TABLE_COUNTRY => '',
            'ip' => $ip,
            'extId' => $extId,
            'extType' => $extType,
        ];

        $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, $type);
        $emailView = data_get($activityConfigData, $type . '_view.value', ''); //邮件模板
        $subject = data_get($activityConfigData, $type . '_subject.value', ''); //邮件主题
        data_set($rs, Constant::SUBJECT, $subject);

        //获取邮件模板
        $data = CustomerInfoService::exists($storeId, $customerId, '', true, [Constant::DB_TABLE_FIRST_NAME, Constant::DB_TABLE_ACCOUNT]);
        $firstName = data_get($data, Constant::DB_TABLE_FIRST_NAME);
        $account = $firstName ? $firstName : FunctionHelper::handleAccount(data_get($data, Constant::DB_TABLE_ACCOUNT, ''));

        $replytoAddress = data_get($activityConfigData, $type . '_replyto_address.value', '');
        $replytoName = data_get($activityConfigData, $type . '_replyto_name.value', '');
        $replytoSubject = data_get($activityConfigData, $type . '_replyto_subject.value', '');
        $replacePairs = [
            '{{$account}}' => $account,
            '{{$' . Constant::SUBJECT . '}}' => $subject,
            '{{$replyto_address}}' => $replytoAddress,
            '{{$replyto_name}}' => $replytoName,
            '{{$replyto_subject}}' => $replytoSubject,
        ];
        data_set($rs, Constant::DB_TABLE_CONTENT, strtr($emailView, $replacePairs));

        $rs['replyTo'] = [
            Constant::DB_TABLE_ADDRESS => $replytoAddress,
            Constant::DB_TABLE_NAME => $replytoName,
            'subject' => $replytoSubject,
        ];

        unset($data);
        unset($replacePairs);

        return $rs;
    }

    /**
     * 处理参与活动邮件业务
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $customerId 账号id
     * @param string $toEmail 收件人邮箱
     * @param int $extId 关联id
     * @param string $extType 关联模型
     * @param string $type 活动配置类型
     * @return array $rs
     */
    public static function handleJoinActEmail($storeId, $actId, $customerId, $toEmail, $extId, $extType, $type = 'join_email', $extData = []) {

        $group = 'apply'; //分组
        $emailType = $type; //类型
        $actData = ActivityService::getActivityData($storeId, $actId);
        $remark = data_get($actData, Constant::DB_TABLE_NAME, '') . '感谢邮件';

        //判断邮件是否已经发送
        $where = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            'group' => $group,
            'type' => $emailType,
//            Constant::DB_TABLE_COUNTRY => '',
            Constant::TO_EMAIL => $toEmail,
//            Constant::DB_TABLE_EXT_ID => $extId,
//            Constant::DB_TABLE_EXT_TYPE => $extType,
            Constant::DB_TABLE_ACT_ID => $actId,
        ];
        $isExists = EmailService::exists($storeId, '', $where);
        if ($isExists) {//如果订单邮件已经发送，就提示
            return Response::getDefaultResponseData(39003);
        }

        $extService = static::getNamespaceClass();
        $extMethod = 'getJoinActEmailData'; //获取审核邮件数据
        $extParameters = [$storeId, $actId, $customerId, data_get($extData, Constant::DB_TABLE_IP), $extId, $extType, $type];

        //解除任务
        $_extData = FunctionHelper::getJobData($extService, $extMethod, $extParameters, null, [
                    Constant::ACT_ID => $actId,
                    Constant::STORE_DICT_TYPE => Constant::DB_TABLE_EMAIL, //订单邮件配置 crm_dict_store.type
                    Constant::ACTIVITY_CONFIG_TYPE => $type, //订单邮件配置 crm_activity_configs.type
        ]);

        $service = EmailService::getNamespaceClass();
        $method = 'handle'; //邮件处理
        $parameters = [$storeId, $toEmail, $group, $emailType, $remark, $extId, $extType, $_extData];
        FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters));

        return Response::getDefaultResponseData(1);
    }

    /**
     * 配件申请
     * @param $storeId
     * @param $actId
     * @param $customerId
     * @return bool
     * @throws \Exception
     */
    public static function _findPartsProcess($storeId, $actId, $customerId)
    {
        //获取用户申请的配件
        $where = [
            Constant::DB_TABLE_EXT_TYPE => ActivityProductService::getModelAlias(),
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::PRODUCT_TYPE => 3,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::AUDIT_STATUS => 0,
        ];
        $_applyData = static::getModel($storeId)->select([Constant::DB_TABLE_EXT_ID])->buildWhere($where)->get();
        if ($_applyData->isEmpty()) {
            return false;
        }

        //更新申请数量字段
        $extIds = array_column($_applyData->toArray(), Constant::DB_TABLE_EXT_ID);
        $updateWhere = [Constant::DB_TABLE_PRIMARY => $extIds];
        $updateData = [Constant::DB_TABLE_QTY_APPLY => DB::raw('qty_apply-1')];
        $isUpdated = ActivityProductService::update($storeId, $updateWhere, $updateData);
        if (empty($isUpdated)) {
            throw new \Exception(null, 61113);
        }

        $isUpdated = static::delete($storeId, $where);
        if (empty($isUpdated)) {
            throw new \Exception(null, 61113);
        }

        return true;
    }

    /**
     * 评测2.0产品申请
     * @param int $storeId 官网id
     * @param int $customerId 会员id
     * @param int $id 申请的产品主键id
     * @param array $requestData 请求参数
     * @return array
     */
    public static function freeTestingProductApply($storeId, $customerId, $id, $requestData) {
        $responseRet = Response::getDefaultResponseData(1);

        //30天申请限制
        $activityConfigData = ActivityService::getActivityConfigData($storeId, -1, 'apply_limit');
        if (!empty($activityConfigData)) {
            $startAt = data_get($activityConfigData, 'apply_limit_start_at.value', '');
            $timeInterval = data_get($activityConfigData, 'apply_limit_time_interval.value', '');
            $currentTime = date('Y-m-d H:i:s', time());
            $timeIntervalAt = date('Y-m-d H:i:s', strtotime('-' . $timeInterval, strtotime($currentTime)));
            $timeIntervalAt > $startAt && $startAt = $timeIntervalAt;
            if (!empty($startAt)) {
                $applyWhere[0] = [
                    [Constant::DB_TABLE_CUSTOMER_PRIMARY, $customerId],
                    ['apply_type', 1],
                    [Constant::DB_TABLE_CREATED_AT, '>=', $startAt],
                    [Constant::DB_TABLE_CREATED_AT, '<=', $currentTime],
                ];
                $limit = 1;
                $list = ActivityApplyService::getModel($storeId)->buildWhere($applyWhere)
                    ->orderBy(Constant::DB_TABLE_PRIMARY, Constant::DB_EXECUTION_PLAN_ORDER_DESC)
                    ->limit($limit)
                    ->get();
                if (!$list->isEmpty()) {
                    $list = $list->toArray();
                    $activityCheck = DictStoreService::getByTypeAndKey($storeId, 'act_check', 'check_status', true);
                    if ($list[0]) {
                        if ($list[0]['audit_status'] !== 2 || !$activityCheck) {
                            data_set($responseRet, Constant::RESPONSE_CODE_KEY, 62010);
                            return $responseRet;
                        }
                    }
                }
            }
        }

        $activityProduct = ActivityProductService::existsOrFirst($storeId, '', [Constant::DB_TABLE_PRIMARY => $id], true);
        //产品不存在
        if (empty($activityProduct)) {
            data_set($responseRet, Constant::RESPONSE_CODE_KEY, 60006);
            return $responseRet;
        }

        //产品不能申请
        if (data_get($activityProduct, Constant::DB_TABLE_PRODUCT_STATUS, Constant::PARAMETER_INT_DEFAULT) != 1) {
            data_set($responseRet, Constant::RESPONSE_CODE_KEY, 60006);
            return $responseRet;
        }

        //产品不能申请
        $nowTimesAt = Carbon::now()->toDateTimeString();
        $expireTime = data_get($activityProduct, Constant::EXPIRE_TIME, Constant::PARAMETER_STRING_DEFAULT);
        if (!empty($expireTime) || $expireTime != '2000-01-01 00:00:00') {
            if ($nowTimesAt >= $expireTime) {
                data_set($responseRet, Constant::RESPONSE_CODE_KEY, 60006);
                return $responseRet;
            }
        }

        //已经申请过该产品
        $exists = static::existsOrFirst($storeId, '', [Constant::DB_TABLE_EXT_TYPE => ActivityProductService::getMake(), Constant::DB_TABLE_EXT_ID => $id,
                    Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId]);
        if ($exists) {
            data_set($responseRet, Constant::RESPONSE_CODE_KEY, 60005);
            return $responseRet;
        }

        $orderId = data_get($requestData, Constant::DB_TABLE_ORDER_NO, Constant::PARAMETER_INT_DEFAULT);
        if (!empty($orderId)) {
            //$orderData = ErpAmazonService::getFctOrderItem($storeId, $orderId);
            $orderData = OrderService::isExists($storeId, $orderId);
            //订单不存在
            if (empty($orderData)) {
                data_set($responseRet, Constant::RESPONSE_CODE_KEY, 39001);
                return $responseRet;
            }
        }

        //申请数据,activity_applies表
        $applyData = [
            Constant::DB_TABLE_EXT_TYPE => ActivityProductService::getMake(),
            Constant::DB_TABLE_EXT_ID => $id,
            Constant::DB_TABLE_ACT_ID => data_get($requestData, Constant::DB_TABLE_ACT_ID, Constant::PARAMETER_INT_DEFAULT),
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ACCOUNT => data_get($requestData, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_IP => data_get($requestData, Constant::DB_TABLE_IP, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_COUNTRY => data_get($requestData, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_FIRST_NAME => data_get($requestData, Constant::DB_TABLE_FIRST_NAME, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_LAST_NAME => data_get($requestData, Constant::DB_TABLE_LAST_NAME, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_CITY => data_get($requestData, Constant::DB_TABLE_CITY, Constant::PARAMETER_STRING_DEFAULT),
            'birthday' => data_get($requestData, 'birthday', Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_GENDER => data_get($requestData, Constant::DB_TABLE_GENDER, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_PROFILE_URL => data_get($requestData, Constant::DB_TABLE_PROFILE_URL, Constant::PARAMETER_STRING_DEFAULT),
            'apply_type' => data_get($requestData, 'apply_type', 1),
        ];

        //申请数据,activity_apply_infos表
        $applyInfoData = [
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
            Constant::DB_TABLE_ORDER_NO => $orderId,
            Constant::DB_TABLE_ORDER_COUNTRY => data_get($requestData, Constant::DB_TABLE_ORDER_COUNTRY, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_REMARKS => data_get($requestData, Constant::DB_TABLE_REMARKS, Constant::PARAMETER_STRING_DEFAULT),
        ];

        //申请数据,metafields表
        $socialPlatforms = data_get($requestData, 'reviews', Constant::PARAMETER_ARRAY_DEFAULT);
        $metaFieldsData = [];
        if (!empty($socialPlatforms)) {
            $metaFields = array_map(function ($item) {
                return [
                    Constant::DB_TABLE_KEY => data_get($item, Constant::DB_TABLE_KEY, Constant::PARAMETER_STRING_DEFAULT),
                    Constant::DB_TABLE_VALUE => data_get($item, Constant::DB_TABLE_VALUE, Constant::PARAMETER_STRING_DEFAULT),
                ];
            }, $socialPlatforms);
            //添加属性
            data_set($metaFieldsData, Constant::METAFIELDS, $metaFields);
            data_set($metaFieldsData, Constant::OWNER_RESOURCE, static::getModelAlias());
            data_set($metaFieldsData, Constant::OP_ACTION, 'add');
            data_set($metaFieldsData, Constant::NAME_SPACE, data_get($requestData, Constant::NAME_SPACE, 'free_testing_reviews'));
        }

        //产品数据更新,activity_products表
        $updateData = [
            Constant::DB_TABLE_QTY_APPLY => DB::raw('qty_apply + 1'),
            'show_apply' => DB::raw('show_apply + 1'),
                //Constant::DB_TABLE_PRODUCT_STATUS => DB::raw('IF(qty_apply >= qty, 3, 1)')
        ];
        $where[] = [
            [Constant::DB_TABLE_PRIMARY, $id],
            [Constant::DB_TABLE_QTY, '>', Constant::DB_TABLE_QTY_APPLY]
        ];

        static::getModel($storeId, '')->getConnection()->beginTransaction();
        try {
            $applyId = static::getModel($storeId)->insertGetId($applyData);
            if (empty($applyId)) { //申请失败
                throw new \Exception(null, 60010);
            }

            $applyInfoId = ActivityApplyInfoService::insert($storeId, [], $applyInfoData);
            if (empty($applyInfoId)) { //申请失败
                throw new \Exception(null, 60010);
            }

            MetafieldService::batchHandle($storeId, $applyId, $metaFieldsData);

            $updateRet = ActivityProductService::update($storeId, $where, $updateData);
            if (empty($updateRet)) { //申请失败
                throw new \Exception(null, 60010);
            }

            static::getModel($storeId, '')->getConnection()->commit();
        } catch (\Exception $exception) {
            static::getModel($storeId, '')->getConnection()->rollBack();
            data_set($responseRet, Constant::RESPONSE_CODE_KEY, $exception->getCode());
        }

        return $responseRet;
    }

    /**
     * 获取申请数据的用户基本数据
     * @param int $storeId
     * @param int $customerId
     * @param int $applyType
     * @return mixed
     */
    public static function getApply($storeId, $customerId, $applyType = 1) {
        $where = [
            'apply_type' => $applyType,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId
        ];
        $select = [
            Constant::DB_TABLE_ACCOUNT,
            Constant::DB_TABLE_FIRST_NAME,
            Constant::DB_TABLE_LAST_NAME,
            Constant::DB_TABLE_COUNTRY,
            Constant::DB_TABLE_CITY,
            'birthday',
            Constant::DB_TABLE_GENDER,
            Constant::DB_TABLE_PROFILE_URL
        ];
        $applyInfo = static::getModel($storeId)->select($select)->where($where)->orderBy(Constant::DB_TABLE_PRIMARY, Constant::ORDER_DESC)->first();

        $customerInfo  = CustomerInfoService::existsOrFirst($storeId,'', [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId], true);

        $applyFirstName = data_get($applyInfo, Constant::DB_TABLE_FIRST_NAME, Constant::PARAMETER_STRING_DEFAULT);
        $applyLastName = data_get($applyInfo, Constant::DB_TABLE_LAST_NAME, Constant::PARAMETER_STRING_DEFAULT);
        if (empty($applyFirstName) && empty($applyLastName)) {
            data_set($applyInfo, Constant::DB_TABLE_FIRST_NAME, data_get($customerInfo, Constant::DB_TABLE_FIRST_NAME, Constant::PARAMETER_STRING_DEFAULT));
            data_set($applyInfo,Constant::DB_TABLE_LAST_NAME,  data_get($customerInfo, Constant::DB_TABLE_LAST_NAME, Constant::PARAMETER_STRING_DEFAULT));
        }

        return $applyInfo;
    }

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
    public static function applyList($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {
        //排序
        $order = [];
        $order[] = ['aa.' . Constant::DB_TABLE_PRIMARY, Constant::DB_EXECUTION_PLAN_ORDER_DESC];

        //查询条件
        $_data = static::getPublicData($params, $order);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);
        $order = data_get($params, Constant::ORDER_BY, data_get($_data, Constant::ORDER, []));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::ACT_LIMIT_KEY, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, Constant::PARAMETER_INT_DEFAULT));
        $storeId = Arr::get($params, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);

        $select = $select ? $select : ['*'];

        $joinData = [
            FunctionHelper::getExePlanJoinData('activity_products as ap', function ($join) {
                        $join->on([['aa.' . Constant::DB_TABLE_EXT_ID, '=', 'ap.' . Constant::DB_TABLE_PRIMARY]])
                                ->where('aa.' . Constant::DB_TABLE_EXT_TYPE, '=', ActivityProductService::getModelAlias());
                    }),
            FunctionHelper::getExePlanJoinData('activities as act', Constant::ACT_ALIAS . Constant::LINKER . Constant::DB_TABLE_PRIMARY, '=', 'aa.' . Constant::DB_TABLE_ACT_ID),
        ];
        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $handleData = Constant::PARAMETER_ARRAY_DEFAULT;

        $with = Constant::PARAMETER_ARRAY_DEFAULT;

        $unset = [Constant::DB_TABLE_ASIN];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), 'activity_applies as aa', $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        //数据处理
        $amazonHostData = DictService::getListByType(Constant::AMAZON_HOST, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);
        $itemHandleDataCallback = [
            'url' => function($item) use($params, $amazonHostData) {
                //填写了产品url
                if (!empty($item[Constant::FILE_URL])) {
                    return $item[Constant::FILE_URL];
                }
                //根据国家参数返回产品url
                $country = $params[Constant::DB_TABLE_COUNTRY] ?? Constant::PARAMETER_STRING_DEFAULT;
                if (!empty($country)) {
                    $amazonHost = data_get($amazonHostData, $country, Constant::PARAMETER_STRING_DEFAULT);
                    if (!empty($amazonHost)) {
                        return $amazonHost . '/dp/' . $item[Constant::DB_TABLE_ASIN];
                    }
                }
                //根据导入的产品国家返回产品url
                $countries = array_filter(array_map(function ($it) {
                                    return $it[Constant::DB_TABLE_KEY] == Constant::DB_TABLE_COUNTRY ? $it[Constant::DB_TABLE_VALUE] : '';
                                }, $item[Constant::METAFIELDS]));
                if (!empty($countries)) {
                    foreach ($countries as $country) {
                        $amazonHost = data_get($amazonHostData, $country, Constant::PARAMETER_STRING_DEFAULT);
                        if (!empty($amazonHost)) {
                            return $amazonHost . '/dp/' . $item[Constant::DB_TABLE_ASIN];
                        }
                    }
                }
                //返回默认US产品url
                return data_get($amazonHostData, 'US', Constant::PARAMETER_STRING_DEFAULT) . '/dp/' . $item[Constant::DB_TABLE_ASIN];
            }
        ];

        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
        ];

        if (data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT . '.' . Constant::DB_EXECUTION_PLAN_HANDLE_DATA, Constant::PARAMETER_ARRAY_DEFAULT);
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_WITH, Constant::PARAMETER_ARRAY_DEFAULT);
        }

        $flatten = true;
        $dataStructure = 'list';
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

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
    public static function adminApplyList($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {
        //查询条件
        $_data = static::getPublicData($params, []);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);
        $order = data_get($params, Constant::ORDER_BY, data_get($_data, Constant::ORDER, []));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::ACT_LIMIT_KEY, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, Constant::PARAMETER_INT_DEFAULT));
        $storeId = Arr::get($params, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);

        $select = $select ? $select : ['*'];

        //产品属性
        $with = [];
        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $productWithWhere[] = [
            [Constant::DB_TABLE_STORE_ID, '=', $storeId],
            [Constant::DB_TABLE_PLATFORM, '=', FunctionHelper::getUniqueId(Constant::PLATFORM_SHOPIFY)],
            [Constant::OWNER_RESOURCE, '=', ActivityProductService::getMake()],
            [Constant::NAME_SPACE, '=', 'free_testing'],
        ];
        $productWithSelect = [Constant::OWNER_ID, Constant::DB_TABLE_KEY, Constant::DB_TABLE_VALUE, Constant::NAME_SPACE];
        $with['product_metafields'] = FunctionHelper::getExePlan(
                        $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $productWithSelect, $productWithWhere, [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false
        );

        $joinData = [
            FunctionHelper::getExePlanJoinData('activity_products as ap', function ($join) {
                        $join->on([['aa.' . Constant::DB_TABLE_EXT_ID, '=', 'ap.' . Constant::DB_TABLE_PRIMARY]])
                                ->where('aa.' . Constant::DB_TABLE_EXT_TYPE, ActivityProductService::getModelAlias());
                    }),
            FunctionHelper::getExePlanJoinData(DB::raw('ptxcrm.crm_customer_info as crm_ci'), function ($join) {
                        $join->on([['ci.' . Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', 'aa.' . Constant::DB_TABLE_CUSTOMER_PRIMARY]]);
                    }),
            FunctionHelper::getExePlanJoinData('activities as act', Constant::ACT_ALIAS . Constant::LINKER . Constant::DB_TABLE_PRIMARY, '=', 'aa.' . Constant::DB_TABLE_ACT_ID),
        ];

        $auditStatusData = DictService::getListByType(Constant::AUDIT_STATUS, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //审核状态
        $handleData = [
            //审核状态
            Constant::AUDIT_STATUS => FunctionHelper::getExePlanHandleData(Constant::AUDIT_STATUS, Constant::PARAMETER_STRING_DEFAULT, $auditStatusData, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, Constant::PARAMETER_ARRAY_DEFAULT, $only), //审核状态
        ];

        //申请列表数据导出
        $isExport = data_get($params, 'is_export', data_get($params, 'srcParameters.0.is_export', Constant::PARAMETER_INT_DEFAULT));
        if ($isExport) {
            $joinData[] = FunctionHelper::getExePlanJoinData('activity_apply_infos as aai', function ($join) {
                        $join->whereColumn('aai.' . Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', 'aa.' . Constant::DB_TABLE_CUSTOMER_PRIMARY)
                                ->where('aai.status', '=', 1)
                                ->whereRaw('((crm_aai.created_mark = crm_aa.created_mark) or (crm_aai.act_id = crm_aa.act_id and crm_aa.act_id != 0))');
                    });

            //申请属性
            $applyWithWhere[] = [
                [Constant::DB_TABLE_STORE_ID, '=', $storeId],
                [Constant::DB_TABLE_PLATFORM, '=', FunctionHelper::getUniqueId(Constant::PLATFORM_SHOPIFY)],
                [Constant::OWNER_RESOURCE, '=', static::getMake()],
                [Constant::NAME_SPACE, '=', 'free_testing_reviews'],
            ];
            $applyWithSelect = [Constant::OWNER_ID, Constant::DB_TABLE_KEY, Constant::DB_TABLE_VALUE, Constant::NAME_SPACE];
            $with['apply_metafields'] = FunctionHelper::getExePlan(
                            $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $applyWithSelect, $applyWithWhere, [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false
            );

            $itemHandleDataCallback['reviews'] = function($item) {
                $reviews = implode(PHP_EOL, array_column(array_values(array_filter(array_map(function ($it) {
                                                    return $it[Constant::NAME_SPACE] == 'free_testing_reviews' ? [
                                                        Constant::DB_TABLE_KEY => $it[Constant::DB_TABLE_KEY],
                                                        Constant::DB_TABLE_VALUE => $it[Constant::DB_TABLE_VALUE],
                                                            ] : [];
                                                }, $item['apply_metafields']))), Constant::DB_TABLE_VALUE));

                if (empty($reviews)) {
                    $reviews = [];
                    !empty($item[Constant::SOCIAL_MEDIA]) && $reviews[] = $item[Constant::SOCIAL_MEDIA];
                    !empty($item['youtube_channel']) && $reviews[] = $item['youtube_channel'];
                    !empty($item['blogs_tech_websites']) && $reviews[] = $item['blogs_tech_websites'];
                    !empty($item['deal_forums']) && $reviews[] = $item['deal_forums'];
                    !empty($item['others']) && $reviews[] = $item['others'];
                    $reviews = implode(PHP_EOL, $reviews);
                }

                return $reviews;
            };

            $itemHandleDataCallback['profile_url'] = function($item) {
                return !empty($item['profile_url']) ? $item['profile_url'] : $item['customer_profile_url'];
            };

            //订单状态
            $orderStatusData = DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //订单状态 -1:匹配中 0:未支付 1:已经支付 2:取消 默认:-1
            $itemHandleDataCallback['show_order_status'] = function ($item) use ($orderStatusData) {
                return !empty($item[Constant::DB_TABLE_ORDER_NO]) ? data_get($orderStatusData, data_get($item, Constant::DB_TABLE_ORDER_STATUS, -1)) : '';
            };

            //订单号
            $itemHandleDataCallback[Constant::DB_TABLE_ORDER_NO] = function ($item) {
                return !empty($item[Constant::DB_TABLE_ORDER_NO]) ? $item[Constant::DB_TABLE_ORDER_NO] : '';
            };

            //申请产品SKU
            $itemHandleDataCallback[Constant::DB_TABLE_SKU] = function ($item) {
                return !empty($item[Constant::DB_TABLE_SHOP_SKU]) ? $item[Constant::DB_TABLE_SHOP_SKU] : $item[Constant::DB_TABLE_SKU];
            };

            //会员名
            $itemHandleDataCallback[Constant::DB_TABLE_NAME] = function ($item) {
                if (!empty($item['apply_first_name']) || !empty($item['apply_last_name'])) {
                    return $item['apply_first_name'] . ' ' . $item['apply_last_name'];
                }
                return trim($item['first_name'] . ' ' . $item['last_name']);
            };
        }

        $unset = ['apply_first_name', 'apply_last_name', Constant::DB_TABLE_FIRST_NAME, Constant::DB_TABLE_LAST_NAME, Constant::DB_TABLE_EXT_ID, 'product_metafields', 'apply_metafields'];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), 'activity_applies as aa', $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        //数据处理
        $itemHandleDataCallback['customer_name'] = function($item) {
            if (!empty($item['apply_first_name']) || !empty($item['apply_last_name'])) {
                return $item['apply_first_name'] . ' ' . $item['apply_last_name'];
            }
            return trim($item['first_name'] . ' ' . $item['last_name']);
        };
        $itemHandleDataCallback['country'] = function($item) {
            $countries = array_values(array_filter(array_map(function ($it) {
                                        return $it[Constant::DB_TABLE_KEY] == Constant::DB_TABLE_COUNTRY ? $it[Constant::DB_TABLE_VALUE] : '';
                                    }, $item['product_metafields'])));
            if (in_array($item['apply_country'], $countries) || $item['apply_country'] == $item[Constant::DB_TABLE_COUNTRY]) {
                return $item['apply_country'];
            }
            return !empty($countries) ? data_get($countries, 0, '') : $item[Constant::DB_TABLE_COUNTRY];
        };

        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
        ];

        if (data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT . '.' . Constant::DB_EXECUTION_PLAN_HANDLE_DATA, Constant::PARAMETER_ARRAY_DEFAULT);
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_WITH, Constant::PARAMETER_ARRAY_DEFAULT);
        }

        $flatten = true;
        $dataStructure = 'list';
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

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
    public static function freeTestingInfo($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {
        //查询条件
        $_data = static::getPublicData($params, []);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);
        $order = data_get($params, Constant::ORDER_BY, data_get($_data, Constant::ORDER, []));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::ACT_LIMIT_KEY, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, Constant::PARAMETER_INT_DEFAULT));
        $storeId = Arr::get($params, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);

        $select = $select ? $select : ['*'];

        $with = [];
        $only = Constant::PARAMETER_ARRAY_DEFAULT;

        $joinData = [
            FunctionHelper::getExePlanJoinData('activity_apply_infos as aai', function ($join) {
                        $join->whereColumn('aai.' . Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', 'aa.' . Constant::DB_TABLE_CUSTOMER_PRIMARY)
                                ->where('aai.status', '=', 1)
                                ->whereRaw('((crm_aai.created_mark = crm_aa.created_mark) or (crm_aai.act_id = crm_aa.act_id and crm_aa.act_id != 0))');
                    }),
            FunctionHelper::getExePlanJoinData(DB::raw('ptxcrm.crm_customer_info as crm_ci'), function ($join) {
                        $join->on([['ci.' . Constant::DB_TABLE_CUSTOMER_PRIMARY, '=', 'aa.' . Constant::DB_TABLE_CUSTOMER_PRIMARY]]);
                    }),
        ];

        //申请属性
        $applyWithWhere[] = [
            [Constant::DB_TABLE_STORE_ID, '=', $storeId],
            [Constant::DB_TABLE_PLATFORM, '=', FunctionHelper::getUniqueId(Constant::PLATFORM_SHOPIFY)],
            [Constant::OWNER_RESOURCE, '=', static::getMake()],
            [Constant::NAME_SPACE, '=', 'free_testing_reviews'],
        ];
        $applyWithSelect = [Constant::OWNER_ID, Constant::DB_TABLE_KEY, Constant::DB_TABLE_VALUE, Constant::NAME_SPACE];
        $with['apply_metafields'] = FunctionHelper::getExePlan(
                        $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $applyWithSelect, $applyWithWhere, [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false
        );

        $itemHandleDataCallback['reviews'] = function ($item) {
            $reviews = array_column(array_values(array_filter(array_map(function ($it) {
                                        return $it[Constant::NAME_SPACE] == 'free_testing_reviews' ? [
                                            Constant::DB_TABLE_KEY => $it[Constant::DB_TABLE_KEY],
                                            Constant::DB_TABLE_VALUE => $it[Constant::DB_TABLE_VALUE],
                                                ] : [];
                                    }, $item['apply_metafields']))), Constant::DB_TABLE_VALUE);

            if (empty($reviews)) {
                $reviews = [];
                !empty($item['social_media']) && $reviews[] = $item['social_media'];
                !empty($item['youtube_channel']) && $reviews[] = $item['youtube_channel'];
                !empty($item['blogs_tech_websites']) && $reviews[] = $item['blogs_tech_websites'];
                !empty($item['deal_forums']) && $reviews[] = $item['deal_forums'];
                !empty($item['others']) && $reviews[] = $item['others'];
            }

            return $reviews;
        };

        $itemHandleDataCallback['profile_url'] = function ($item) {
            return !empty($item['profile_url']) ? $item['profile_url'] : $item['customer_profile_url'];
        };

        $orderStatusData = DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //订单状态 -1:匹配中 0:未支付 1:已经支付 2:取消 默认:-1
        $itemHandleDataCallback['show_order_status'] = function ($item) use ($orderStatusData) {
            return !empty($item[Constant::DB_TABLE_ORDER_NO]) ? data_get($orderStatusData, data_get($item, 'show_order_status', -1)) : '';
        };

        $handleData = [];
        $unset = ['apply_metafields', 'social_media', 'youtube_channel', 'blogs_tech_websites', 'deal_forums', 'others', 'customer_profile_url'];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), 'activity_applies as aa', $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
        ];

        $flatten = true;
        $dataStructure = 'one';
        $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
        return data_get($data, Constant::RESPONSE_DATA_KEY, Constant::PARAMETER_ARRAY_DEFAULT);
    }

}
