<?php

/**
 * 活动体验申请服务
 * User: Jmiy
 * Date: 2019-08-27
 * Time: 16:50
 */

namespace App\Services;

use App\Jobs\PublicJob;
use App\Constants\Constant;
use Hyperf\DbConnection\Db as DB;
use Hyperf\Utils\Arr;
use App\Utils\FunctionHelper;
use App\Utils\Support\Facades\Queue;
use App\Utils\Response;

class ActivityApplyInfoService extends BaseService {

    public static function getCacheTags() {
        return 'applyInfoLock';
    }

    /**
     * 获取db query
     * @param int $storeId 商城id
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getQuery($storeId, $where = []) {
        return static::getModel($storeId)->buildWhere($where);
    }

    /**
     * 检查是否存在
     * @param int $id 角色id
     * @param bool $getData 是否获取数据 true:是 false:否 默认:false
     * @return mixed|static
     */
    public static function exists($storeId = 1, $actId = '', $customerId = 0, $getData = false) {
        $where = [];

        if ($actId) {
            $where['act_id'] = $actId;
        }

        if ($customerId) {
            $where[Constant::DB_TABLE_CUSTOMER_PRIMARY] = $customerId;
        }

        if (empty($where)) {
            return $getData ? [] : true;
        }

        $query = static::getModel($storeId)->where($where);

        if ($getData) {
            $rs = $query->first();
        } else {
            $rs = $query->exists();
        }

        return $rs;
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @param array $order 排序控制
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $where = [];

        $account = $params[Constant::DB_TABLE_ACCOUNT] ?? ''; //账号
        $sku = $params['sku'] ?? ''; //sku
        $country = $params[Constant::DB_TABLE_COUNTRY] ?? ''; //国家
        $auditStatus = $params['audit_status'] ?? ''; //商城id
        $startAt = $params['start_at'] ?? ''; //申请开始时间
        $entAt = $params['end_at'] ?? ''; //申请结束时间

        if ($sku) {
            $where[] = ['sku', '=', $sku];
        }

        if ($country) {
            $where[] = [Constant::DB_TABLE_COUNTRY, '=', $country];
        }

        if ($auditStatus !== '') {
            $where[] = ['audit_status', '=', $auditStatus];
        }

        if ($startAt) {
            $where[] = ['created_at', '>=', $startAt];
        }

        if ($entAt) {
            $where[] = ['created_at', '<=', $entAt];
        }

        if ($account) {
            $where[] = [Constant::DB_TABLE_ACCOUNT, '=', $account];
        }

        $order = $order ? $order : ['id', 'DESC'];
        return Arr::collapse([parent::getPublicData($params, $order), [
                        Constant::DB_EXECUTION_PLAN_WHERE => $where,
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

        $_data = static::getPublicData($params);

        $where = $_data[Constant::DB_EXECUTION_PLAN_WHERE];
        $order = $_data['order'];
        $pagination = $_data[Constant::DB_EXECUTION_PLAN_PAGINATION];
        $limit = $pagination['page_size'];

        $customerCount = true;
        $storeId = Arr::get($params, 'store_id', 0);
        $query = static::getQuery($storeId, $where);
        if ($isPage || $isOnlyGetCount) {
            $customerCount = $query->count();
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

        $query = $query->orderBy($order[0], $order[1]);
        $data = [
            'query' => $query,
            Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
        ];

        //static::createModel($storeId, 'VoteItem')->getConnection()->enableQueryLog();
        //var_dump(static::createModel($storeId, 'VoteItem')->getConnection()->getQueryLog());
        $select = $select ? $select : ['*'];
        $data = static::getList($data, $toArray, $isPage, $select, $isRaw, $isGetQuery);

        if ($isGetQuery) {
            return $data;
        }

        $statusData = DictService::getListByType(Constant::DB_TABLE_STATUS, 'dict_key', 'dict_value');
        foreach ($data['data'] as $key => $row) {

            $field = [
                Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_STATUS,
                'data' => $statusData,
                Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                Constant::DB_EXECUTION_PLAN_DEFAULT => $data['data'][$key][Constant::DB_TABLE_STATUS],
            ];
            $data['data'][$key][Constant::DB_TABLE_STATUS] = FunctionHelper::handleData($row, $field);
        }

        return $data;
    }

    /**
     * 添加记录

     * @param int $storeId  商城id
     * @param array $where where条件
     * @param array $data  权限数据
     * @return int
     */
    public static function insert($storeId, $where, $data) {

        $model = static::getModel($storeId);

        if ($where) {//编辑
            $id = $model->where($where)->update($data);
        } else {//添加
            $id = $model->insertGetId($data);
        }

        return $id;
    }

    /**
     * 申请资料详情
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param boolean $isAdmin 是否后台 true:是 false:否 默认：false
     * @return type
     */
    public static function info($storeId, $actId, $customerId, $isAdmin = false) {

        $customerSelect = [Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::DB_TABLE_ACCOUNT];
        $dbExecutionPlan = [
            'parent' => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => false,
                Constant::DB_EXECUTION_PLAN_STOREID => 0,
                'make' => 'Customer',
                'from' => '',
                Constant::DB_EXECUTION_PLAN_SELECT => $customerSelect,
                Constant::DB_EXECUTION_PLAN_WHERE => [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId],
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                    Constant::IS_HAS_APPLY_INFO => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'act_apply_info.customer_id',
                        'data' => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        'glue' => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                ],
                Constant::DB_EXECUTION_PLAN_UNSET => [Constant::DB_TABLE_CUSTOMER_PRIMARY],
            ],
            'with' => [
                'info' => [
                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => false,
                    Constant::DB_EXECUTION_PLAN_STOREID => 0,
                    Constant::DB_EXECUTION_PLAN_RELATION => Constant::HAS_ONE,
                    Constant::DB_EXECUTION_PLAN_SELECT => [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY,
                        'first_name',
                        'last_name',
                        Constant::DB_TABLE_COUNTRY,
                        'brithday',
                        'gender',
                        'profile_url',
                        'isactivate',
                    ],
                    Constant::DB_EXECUTION_PLAN_DEFAULT => [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => Constant::DB_TABLE_CUSTOMER_PRIMARY,
                    ],
                    Constant::DB_EXECUTION_PLAN_WHERE => [],
                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                        'info.brithday' => [
                            Constant::DB_EXECUTION_PLAN_FIELD => 'info.brithday',
                            'data' => [],
                            Constant::DB_EXECUTION_PLAN_DATATYPE => 'datetime',
                            Constant::DB_EXECUTION_PLAN_DATA_FORMAT => 'Y-m-d',
                            'glue' => '',
                            Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                        ],
                    ],
                    Constant::DB_EXECUTION_PLAN_UNSET => ['info'],
                ],
                'act_apply_info' => [
                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                    Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                    Constant::DB_EXECUTION_PLAN_RELATION => Constant::HAS_ONE,
                    Constant::DB_EXECUTION_PLAN_SELECT => [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY,
                        'social_media',
                        'youtube_channel',
                        'blogs_tech_websites',
                        'deal_forums',
                        'others',
                        'products',
                        'is_purchased',
                        Constant::DB_TABLE_ORDER_NO,
                        'order_country',
                        'remarks',
                        Constant::DB_TABLE_ORDER_STATUS,
                        'phone_model',
                        'product_video'
                    ],
                    Constant::DB_EXECUTION_PLAN_DEFAULT => [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => Constant::DB_TABLE_CUSTOMER_PRIMARY,
                    ],
                    Constant::DB_EXECUTION_PLAN_WHERE => [
                        'act_id' => $actId,
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                    ],
                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                        'act_apply_info.products' => [
                            Constant::DB_EXECUTION_PLAN_FIELD => 'act_apply_info.json|products',
                            'data' => [],
                            Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                            Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                            'glue' => '',
                            Constant::DB_EXECUTION_PLAN_DEFAULT => [],
                        ],
                        'act_apply_info.is_purchased' => [
                            Constant::DB_EXECUTION_PLAN_FIELD => 'act_apply_info.is_purchased',
                            'data' => [],
                            Constant::DB_EXECUTION_PLAN_DATATYPE => 'int',
                            Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                            'glue' => '',
                            Constant::DB_EXECUTION_PLAN_DEFAULT => 0,
                        ],
                    ],
                    Constant::DB_EXECUTION_PLAN_UNSET => ['act_apply_info', 'order'],
                ],
                'address_home' => [
                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                    Constant::DB_EXECUTION_PLAN_STOREID => 0,
                    Constant::DB_EXECUTION_PLAN_RELATION => Constant::HAS_ONE,
                    Constant::DB_EXECUTION_PLAN_SELECT => [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY,
                        'region',
                        'city',
                    ],
                    Constant::DB_EXECUTION_PLAN_DEFAULT => [
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => Constant::DB_TABLE_CUSTOMER_PRIMARY,
                    ],
                    Constant::DB_EXECUTION_PLAN_WHERE => [],
                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                        'address_home.city' => [
                            Constant::DB_EXECUTION_PLAN_FIELD => 'address_home.city{or}address_home.region',
                            'data' => [],
                            Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                            Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                            'glue' => '',
                            Constant::DB_EXECUTION_PLAN_DEFAULT => '',
//                        Constant::DB_EXECUTION_PLAN_FIELD => 'address_home.city{connection}address_home.region',
//                        'data' => [],
//                        Constant::DB_EXECUTION_PLAN_DATATYPE => 'string',
//                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
//                        'glue' => ' ',
//                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                        ],
                    ],
                    Constant::DB_EXECUTION_PLAN_UNSET => ['address_home', 'region'],
                ],
            ]
        ];

        if ($isAdmin) {
            $orderStatusData = DictService::getListByType(Constant::DB_TABLE_ORDER_STATUS, 'dict_key', 'dict_value'); //订单状态 -1:无订单 0:未支付 1:已经支付 2:取消 默认:0
            if (in_array($storeId, [2, 3, 5, 6, 7, 8, 9, 10])) {//www.homasy.com||www.iseneo.com||holife.com等官网导出
                Arr::set($dbExecutionPlan, 'parent.handleData.show_order_status', [
                    Constant::DB_EXECUTION_PLAN_FIELD => 'act_apply_info.order_status',
                    'data' => $orderStatusData,
                    Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                    Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                    'glue' => '',
                    Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                ]);
            } else {
                Arr::set($dbExecutionPlan, 'parent.handleData.show_order_status', [
                    Constant::DB_EXECUTION_PLAN_FIELD => 'act_apply_info.order.order_status',
                    'data' => $orderStatusData,
                    Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                    Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                    'glue' => '',
                    Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                ]);

                Arr::set($dbExecutionPlan, 'with.act_apply_info.with.order', [
                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                    Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                    Constant::DB_EXECUTION_PLAN_RELATION => Constant::HAS_ONE,
                    Constant::DB_EXECUTION_PLAN_SELECT => [
                        Constant::DB_TABLE_ORDER_NO,
                        Constant::DB_TABLE_ORDER_STATUS,
                    ],
                    Constant::DB_EXECUTION_PLAN_WHERE => [
                        'store_id' => $storeId,
                    ],
                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
                    Constant::DB_EXECUTION_PLAN_UNSET => [],
                ]);
            }
        }
        //dd($dbExecutionPlan);

        $data = CustomerService::getCustomerData($storeId, $customerId, $customerSelect, $dbExecutionPlan, true);
        Arr::set($data, Constant::IS_HAS_APPLY_INFO, (Arr::get($data, Constant::IS_HAS_APPLY_INFO, 0) ? 1 : 0)); //是否填写了申请资料 1:是  0:否

        return $data;
    }

    /**
     * 申请资料及地址写入
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param string $account 账号
     * @param int $activityWinningId 申请id
     * @param array $requestData 请求参数
     * @return array
     */
    public static function applyInfoSubmit($storeId, $actId, $customerId, $account, $activityWinningId, $requestData) {

        static::getModel($storeId, '')->getConnection()->beginTransaction();

        try {
            ActivityApplyService::update($storeId, [Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId, Constant::DB_TABLE_ACT_ID => $actId], [Constant::AUDIT_STATUS => 1]);

            $where = [
                Constant::DB_TABLE_ACT_ID => $actId,
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                Constant::DB_TABLE_EXT_ID => $activityWinningId,
                Constant::DB_TABLE_EXT_TYPE => ActivityProductService::getModelAlias()
            ];
            $infoData = [
                Constant::DB_TABLE_ORDER_NO => data_get($requestData, 'order_no', ''),
                'remarks' => data_get($requestData, 'description', '')
            ];

            $result = ActivityApplyInfoService::updateOrCreate($storeId, $where, $infoData);
            if (empty(data_get($result, Constant::RESPONSE_DATA_KEY, []))) {
                //添加申请资料记录失败
                throw new \Exception(null, 60010);
            }

            data_set($requestData, 'ext_type', 'apply');
            $result = ActivityAddressService::add($storeId, $actId, $customerId, $account, $activityWinningId, $requestData);
            if (empty($result)) {
                //添加地址失败
                throw new \Exception(null, 60010);
            }

            //邮件发送
            $service = static::getNamespaceClass();
            $method = 'emailToStoreEmail';
            $parameters = [$storeId, $actId, 'email', $requestData];
            $data = [
                Constant::SERVICE_KEY => $service,
                Constant::METHOD_KEY => $method,
                Constant::PARAMETERS_KEY => $parameters,
            ];
            Queue::push(new PublicJob($data));

            static::getModel($storeId, '')->getConnection()->commit();
        } catch (\Exception $exception) {
            static::getModel($storeId, '')->getConnection()->rollBack();
            return Response::getDefaultResponseData($exception->getCode(), $exception->getMessage());
        }

        return Response::getDefaultResponseData(1);
    }

    /**
     * 获取用户活动申请资料
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @return mixed
     */
    public static function getApplyInfo($storeId, $actId, $customerId) {
        $where = [
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId
        ];
        return static::getModel($storeId)->buildWhere($where)->get();
    }

    /**
     * 发送邮件
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param string $type 类型
     * @param array $extData 扩展参数
     * @return array
     */
    public static function emailToStoreEmail($storeId, $actId, $type = 'email', $extData = []) {
        $applyId = data_get($extData, 'apply_id', '');
        if (empty($applyId)) {
            return [];
        }

        $where = [
            'aa.id' => $applyId
        ];
        $select = ['aa.ip', 'ap.in_stock', 'ap.name as product_name', 'apc.name as category_name'];
        $apply = ActivityApplyService::getModel($storeId)
                        ->from('activity_applies as aa')
                        ->leftJoin('activity_products as ap', function ($join) {
                            $join->on('ap.id', '=', 'aa.ext_id')->where('aa.ext_type', 'ActivityProduct');
                        })
                        ->leftJoin('activity_product_categories as apc', 'apc.id', '=', 'ap.category_id')
                        ->buildWhere($where)->select($select)->first();

        if (!$apply) {
            return [];
        }

        //无货
        if (data_get($apply, 'in_stock') === 0) {
            return [];
        }

        $productName = data_get($apply, 'product_name', '');
        $categoryName = data_get($apply, 'category_name', '');
        if (empty($productName) || empty($categoryName)) {
            return [];
        }

        $applyInfo = [
            '{{$account}}' => data_get($extData, Constant::DB_TABLE_ACCOUNT, ''),
            '{{$model}}' => $categoryName,
            '{{$accessory}}' => $productName,
            '{{$orderno}}' => data_get($extData, 'order_no', ''),
            '{{$country}}' => data_get($extData, Constant::DB_TABLE_COUNTRY, ''),
            '{{$full_name}}' => data_get($extData, 'full_name', ''),
            '{{$street}}' => data_get($extData, 'street', ''),
            '{{$apartment}}' => data_get($extData, 'apartment', ''),
            '{{$city}}' => data_get($extData, 'city', ''),
            '{{$state}}' => data_get($extData, 'state', ''),
            '{{$zip_code}}' => data_get($extData, 'zip_code', ''),
            '{{$phone}}' => data_get($extData, 'phone', ''),
            '{{$description}}' => data_get($extData, 'description', ''),
        ];

        $data = ActivityService::getActivityConfigData($storeId, $actId, $type, 'to_store_email');
        if (empty($data)) {
            return [];
        }

        $toEmail = data_get($data, 'email_to_store_email.value', '');
        if (empty($toEmail)) {
            return [];
        }

        //测试
        $ip = data_get($apply, 'ip', '');

        static::emailQueue($storeId, $actId, $toEmail, $ip, $type, 'view_apply_accessory', $applyId, 'ActivityApply', $applyInfo);

        return [];
    }

    /**
     * 邮件任务发送到队列
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param string $toEmail 收件人
     * @param string $ip 用户ip
     * @param string $type 类型
     * @param string $key 配置key
     * @param int $extId 关联id
     * @param int $extType 关联类型
     * @param array $applyInfo 申请资料
     * @return array
     */
    public static function emailQueue($storeId, $actId, $toEmail, $ip, $type, $key, $extId, $extType, $applyInfo) {

        $rs = [Constant::RESPONSE_CODE_KEY => 1, Constant::RESPONSE_MSG_KEY => '', Constant::RESPONSE_DATA_KEY => []];

        $group = 'apply'; //分组
        $emailType = $key; //类型
        $actData = ActivityService::getActivityData($storeId, $actId);
        $remark = data_get($actData, 'name', '') . '配件申请';

        //判断邮件是否已经发送
        $where = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_EXECUTION_PLAN_GROUP => $group,
            Constant::DB_TABLE_TYPE => $emailType,
            'to_email' => $toEmail,
            'ext_id' => $extId,
            'ext_type' => $extType,
            Constant::DB_TABLE_ACT_ID => $actId,
        ];
        $isExists = EmailService::exists($storeId, '', $where);
        if ($isExists) {
            $rs[Constant::RESPONSE_CODE_KEY] = 39003;
            $rs[Constant::RESPONSE_MSG_KEY] = 'Email exist.';
            return $rs;
        }

        $extService = static::getNamespaceClass();
        $extMethod = 'getEmailData';
        $extParameters = [$storeId, $actId, $toEmail, $ip, $type, $key, $extId, $extType, $applyInfo];

        $extData = [
            'actId' => $actId,
            Constant::SERVICE_KEY => $extService,
            Constant::METHOD_KEY => $extMethod,
            Constant::PARAMETERS_KEY => $extParameters,
            'callBack' => [
            ],
        ];

        $service = EmailService::getNamespaceClass();
        $method = 'handle'; //邮件处理
        $parameters = [$storeId, $toEmail, $group, $emailType, $remark, $extId, $extType, $extData];

        $data = [
            Constant::SERVICE_KEY => $service,
            Constant::METHOD_KEY => $method,
            Constant::PARAMETERS_KEY => $parameters,
            'extData' => [
                Constant::SERVICE_KEY => $service,
                Constant::METHOD_KEY => $method,
                Constant::PARAMETERS_KEY => $parameters,
            ],
        ];

        Queue::push(new PublicJob($data));

        return $rs;
    }

    /**
     * 获取邮件数据
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param string $toEmail 收件人
     * @param string $ip 用户ip
     * @param string $type 类型
     * @param string $key 配置key
     * @param int $extId 关联id
     * @param string $extType 关联类型
     * @param array $applyInfo 申请资料
     * @return array
     */
    public static function getEmailData($storeId, $actId, $toEmail, $ip, $type, $key, $extId, $extType, $applyInfo) {

        $rs = [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::DB_EXECUTION_PLAN_STOREID => $storeId, //商城id
            'actId' => $actId, //活动id
            'content' => '', //邮件内容
            'subject' => '',
            Constant::DB_TABLE_COUNTRY => '',
            'ip' => $ip,
            'extId' => $extId,
            'extType' => $extType,
            'replyTo' => [
                'address' => data_get($applyInfo, '{{$account}}', '')
            ]
        ];

        $where = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_ACT_ID => $actId
        ];
        $total = EmailStatisticsService::getModel($storeId)->buildWhere($where)->select(DB::raw('SUM(send_nums) as total'))->value('total');

        $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, $type, [$key, ($key . '_subject')]);
        $emailView = Arr::get($activityConfigData, $type . '_' . $key . '.value', ''); //邮件模板
        $subject = Arr::get($activityConfigData, $type . '_' . $key . '_subject' . '.value', ''); //邮件主题
        data_set($rs, 'subject', strtr($subject, ['{{$total}}' => $total + 1]));

        $replacePairs = $applyInfo;
        data_set($rs, 'content', strtr($emailView, $replacePairs));

        unset($data);
        unset($replacePairs);

        return $rs;
    }

}
