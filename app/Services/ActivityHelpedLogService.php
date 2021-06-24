<?php

/**
 * 解锁服务
 * User: Jmiy
 * Date: 2019-12-11
 * Time: 15:00
 */

namespace App\Services;

use Hyperf\DbConnection\Db as DB;
use Hyperf\Utils\Arr;
use Carbon\Carbon;
use App\Utils\Support\Facades\Cache;
use App\Utils\FunctionHelper;
use App\Constants\Constant;
use App\Utils\Response;

class ActivityHelpedLogService extends BaseService {

    /**
     * 检查是否存在
     * @param int $storeId 商城id
     * @param string $country 国家
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param array $where where条件
     * @param array $getData 是否获取记录  true:是  false:否
     * @return int|object
     */
    public static function exists($storeId = 0, $country = '', $actId = 0, $ip = '', $where = [], $getData = false) {

        $_where = [];
        if ($actId) {
            data_set($_where, Constant::DB_TABLE_ACT_ID, $actId);
        }

        if ($ip) {
            data_set($_where, 'ip', $ip);
        }

        if ($_where) {
            $where = Arr::collapse([$_where, $where]);
        }

        if (empty($where)) {
            return $getData ? [] : true;
        }

        $query = static::getModel($storeId, $country)->buildWhere($where);
        if ($getData) {
            $rs = $query->first();
        } else {
            $rs = $query->count();
        }

        return $rs;
    }

    /**
     * 获取邮件数据
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param string $toEmail 收件者邮箱
     * @param int $customerId 收件者账号id
     * @param string $ip 收件人ip
     * @param int $extId 关联id
     * @param string $extType 关联模型
     * @param string $type 活动配置类型
     * @param string $key 活动配置key
     * @param array $extData 扩展参数
     * @return array $rs 邮件任务进入消息队列结果
     */
    public static function getEmailData($storeId, $actId, $toEmail, $customerId, $ip, $extId, $extType, $type = Constant::DB_TABLE_EMAIL, $key = Constant::OUT_STOCK, $extData = []) {

        $rs = [
            'code' => 1,
            Constant::DB_EXECUTION_PLAN_STOREID => $storeId, //商城id
            Constant::ACT_ID => $actId, //活动id
            'content' => '', //邮件内容
            'subject' => '',
            Constant::DB_TABLE_COUNTRY => '',
            'ip' => $ip,
            'extId' => $extId,
            'extType' => $extType,
        ];

        $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, $type, [$key, ($key . '_subject')]);
        $emailView = Arr::get($activityConfigData, $type . '_' . $key . '.value', ''); //邮件模板
        $subject = Arr::get($activityConfigData, $type . '_' . $key . '_subject' . '.value', ''); //邮件主题
        data_set($rs, 'subject', $subject);

        //获取邮件模板
        $data = CustomerInfoService::exists($storeId, $customerId, '', true);
        $firstName = data_get($data, 'first_name', '');
        $account =  $firstName ? $firstName : data_get($data, Constant::DB_TABLE_ACCOUNT, '');

        $replacePairs = [
            '{{$account}}' => $account,
        ];
        //如果key是获取解锁者的邮件模板，就从扩展参数里获取申请用户的first name
        if ($key == 'view_audit_unlock_1') {
            $replacePairs['{{$first_name}}'] = data_get($extData, 'applyUser.firstName', '');
        }
        data_set($rs, 'content', strtr($emailView, $replacePairs));

        unset($data);
        unset($replacePairs);

        return $rs;
    }

    /**
     * 邮件任务进入消息队列
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param string $toEmail 收件者邮箱
     * @param int $customerId 收件者账号id
     * @param string $ip 收件人ip
     * @param int $extId 关联id
     * @param string $extType 关联模型
     * @param string $type 活动配置类型
     * @param string $key 活动配置key
     * @param array $extArrayData 扩展参数
     * @return array $rs 邮件任务进入消息队列结果
     */
    public static function emailQueue($storeId, $actId, $toEmail, $customerId, $ip, $extId, $extType, $type = Constant::DB_TABLE_EMAIL, $key = Constant::OUT_STOCK, $extArrayData = []) {

        $rs = ['code' => 1, 'msg' => '', 'data' => []];

        $group = 'apply'; //分组
        $emailType = $key; //类型
        $actData = ActivityService::getActivityData($storeId, $actId);
        $remark = data_get($actData, 'name', '') . ($key == Constant::OUT_STOCK ? '库存不足' : '解锁成功');

        //判断邮件是否已经发送
        $where = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            'group' => $group,
            'type' => $emailType,
            Constant::DB_TABLE_COUNTRY => '',
            'to_email' => $toEmail,
            Constant::DB_TABLE_EXT_ID => $extId,
            Constant::DB_TABLE_EXT_TYPE => $extType,
            Constant::DB_TABLE_ACT_ID => $actId,
        ];
        $isExists = EmailService::exists($storeId, '', $where);
        if ($isExists) {//如果订单邮件已经发送，就提示
            $retult['code'] = 39003;
            $retult['msg'] = 'Order Email exist.';
            return $retult;
        }

        $extService = static::getNamespaceClass();
        $extMethod = 'getEmailData'; //获取审核邮件数据
        $extParameters = [$storeId, $actId, $toEmail, $customerId, $ip, $extId, $extType, $type, $key, $extArrayData];

        //解除任务
        $extData = [
            Constant::ACT_ID => $actId,
            Constant::SERVICE_KEY => $extService,
            Constant::METHOD_KEY => $extMethod,
            Constant::PARAMETERS_KEY => $extParameters,
            'callBack' => [
            ]
        ];

        $service = EmailService::getNamespaceClass();
        $method = 'handle'; //邮件处理
        $parameters = [$storeId, $toEmail, $group, $emailType, $remark, $extId, $extType, $extData];
        FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters));

        return $rs;
    }

    /**
     * 处理解锁邮件业务
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $extId 关联id
     * @param string $extType 关联模型
     * @param string $type 活动配置类型
     * @param string $key 活动配置key
     * @return array $rs
     */
    public static function handleEmail($storeId, $actId, $extId, $extType, $type = Constant::DB_TABLE_EMAIL, $key = Constant::OUT_STOCK) {

        $rs = ['code' => 1, 'msg' => '', 'data' => []];

        $where = [];
        $select = [
            Constant::DB_TABLE_ACCOUNT, Constant::DB_TABLE_CUSTOMER_PRIMARY, 'ip'
        ];
        $dbExecutionPlan = [
            'parent' => [
                'setConnection' => true,
                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                'builder' => null,
                'make' => ActivityApplyService::getModelAlias(),
                'from' => '',
                'joinData' => [
                ],
                'select' => $select,
                'where' => $where,
                'orders' => [],
                Constant::DB_EXECUTION_PLAN_OFFSET => null,
                'limit' => null,
                'isPage' => false,
                Constant::DB_EXECUTION_PLAN_PAGINATION => [],
                'handleData' => [
                ],
                'unset' => [],
            ],
            'with' => [
            ],
            'itemHandleData' => [
            ],
                //'sqlDebug' => true,
        ];

        switch ($key) {
            case Constant::OUT_STOCK:
                $where = [
                    Constant::DB_TABLE_EXT_TYPE => $extType,
                    Constant::DB_TABLE_EXT_ID => $extId, //关联id 活动产品id
                    Constant::DB_TABLE_ACT_ID => $actId, //活动id
                    Constant::AUDIT_STATUS => 0, //解锁状态 0:未助力 1:解锁成功 2:解锁失败 3:其他
                ];
                break;

            case 'view_audit_1':
                $where = [
                    'id' => $extId
                ];
                break;

            default:
                break;
        }

        if (empty($where)) {
            return $rs;
        }

        if ($where) {
            $dataStructure = 'list';
            data_set($dbExecutionPlan, 'parent.where', $where);
            $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, false, false, $dataStructure);
            foreach ($data as $item) {
                $toEmail = data_get($item, Constant::DB_TABLE_ACCOUNT, '');
                $customerId = data_get($item, Constant::DB_TABLE_CUSTOMER_PRIMARY, '');
                $ip = data_get($item, 'ip', '');
                if (empty($toEmail)) {
                    continue;
                }
                static::emailQueue($storeId, $actId, $toEmail, $customerId, $ip, $extId, $extType, $type, $key);
            }
        }
        return $rs;
    }

    public static function handle($storeId, $actId, $inviteCode, $helpAccount, $productId, $ip, $helpCountry, $extData = []) {

        if (empty($storeId) || empty($actId) || empty($inviteCode) || empty($helpAccount) || empty($productId) || empty($ip)) {
            return Response::getDefaultResponseData(9999999999);
        }

        //判断邮箱是否有效,ikich解锁活动不验证邮箱
        $isEffectiveEmail = false;
        $defaultRs = Response::getDefaultResponseData(61111);

        $tags = config('cache.tags.activity', ['{activity}']);
        //dump(Cache::tags($tags)->lock('help:' . $storeId . ':' . $actId . ':' . $ip)->forceRelease());
        $rs = Cache::tags($tags)->lock('help:' . $storeId . ':' . $actId . ':' . $ip)->get(function () use ($storeId, $actId, $inviteCode, $helpAccount, $productId, $ip, $helpCountry, $isEffectiveEmail, $extData) {
            // 获取无限期锁并自动释放...

            try {

                //判断申请的产品是否存在
                $productData = ActivityProductService::exists($storeId, $productId, $actId, '', true); //获取产品数据
                if (empty($productData)) {
                    return Response::getDefaultResponseData(60006);
                }

                $service = static::getNamespaceClass();
                $method = 'handleEmail'; //邮件处理
                $outStockParameters = [$storeId, $actId, $productId, ActivityProductService::getModelAlias(), Constant::DB_TABLE_EMAIL, Constant::OUT_STOCK];
                //判断申请的产品库存
                if (data_get($productData, Constant::DB_TABLE_QTY_APPLY, -1) >= data_get($productData, 'qty', 0)) {//如果产品已经被申请完，就提示用户
                    //发送库存不足邮件
                    static::handleEmail(...$outStockParameters);

                    //库存不足时，更新未审核通过的数据为解锁失败状态
                    static::applyFail($storeId, $actId, $productId);

                    return Response::getDefaultResponseData(60007);
                }

                $customerId = 0; //邀请者会员id
                $account = ''; //邀请者会员账号
                if ($inviteCode) {
                    $inviteData = InviteService::getCustomerData($inviteCode);
                    $customerId = data_get($inviteData, 'customer.customer_id', 0);
                    $account = data_get($inviteData, 'customer.account', '');
                    unset($inviteData);
                }

                if (empty($account)) {
                    return Response::getDefaultResponseData(61100);
                }

                if ($account == $helpAccount) {
                    return Response::getDefaultResponseData(61105);
                }

                //获取申请数据
                $actctivityApplyData = ActivityApplyService::exists($storeId, $customerId, $actId, $productId, 'ActivityProduct', true);
                if (empty($actctivityApplyData)) {
                    return Response::getDefaultResponseData(61102);
                }

                $applyId = data_get($actctivityApplyData, 'id', 0); //申请id
                if (empty($applyId)) {
                    return Response::getDefaultResponseData(61103);
                }

                //判断 ip 或者 账号  是否助力过
                $activityHelpedLogWhere = [
                    'or' => [
                        'ip' => $ip,
                        Constant::HELP_ACCOUNT => $helpAccount,
                    ]
                ];
                $isExists = ActivityHelpedLogService::exists($storeId, '', $actId, '', $activityHelpedLogWhere);
                if ($isExists) {
                    return Response::getDefaultResponseData(61101);
                }

                $isCanHelped = static::isHelped($storeId, $actId, $helpAccount, $ip, $extData);
                if (!$isCanHelped) {
                    return Response::getDefaultResponseData(61101);
                }

                //判断是否已经解锁成功
                $prefix = DB::getConfig(Constant::PREFIX);
                $actctivityApplyWhere = [
                    'w.id' => $applyId,
                    'w.audit_status' => 1,
                ];
                $order = [];
                $dbExecutionPlan = ActivityApplyService::getDbExecutionPlan($storeId, $actctivityApplyWhere, $order, null, 1);
                data_set($dbExecutionPlan, 'parent.select', ['w.id']);
                $dataStructure = 'one';
                $flatten = false;
                $actctivityApplyData = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);
                if ($actctivityApplyData) {
                    return Response::getDefaultResponseData(61104);
                }

                static::getModel($storeId, '')->getConnection()->beginTransaction();
                try {
                    $nowTime = Carbon::now()->toDateTimeString();

                    //添加解锁流水
                    $where = [
                        Constant::DB_TABLE_ACT_ID => $actId,
                        'ip' => $ip,
                    ];
                    $verifiedEmail = $isEffectiveEmail ? 1 : 0;
                    $data = [
                        Constant::DB_TABLE_EXT_TYPE => 'ActivityApply', //关联模型
                        Constant::DB_TABLE_EXT_ID => $applyId, //申请id
                        Constant::DB_TABLE_ACCOUNT => $account,
                        Constant::HELP_ACCOUNT => $helpAccount,
                        'help_country' => $helpCountry,
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                        'verified_help_email' => $verifiedEmail,
                        'device' => data_get($extData, 'clientData.device', ''), //设备信息
                        'device_type' => data_get($extData, 'clientData.device_type', 0), // 设备类型 1:手机 2：平板 3：桌面
                        'platform' => data_get($extData, 'clientData.platform', ''), //系统信息
                        'platform_version' => data_get($extData, 'clientData.platform_version', ''), //系统版本
                        'browser' => data_get($extData, 'clientData.browser', ''), // 浏览器信息  (Chrome, IE, Safari, Firefox, ...)
                        'browser_version' => data_get($extData, 'clientData.browser_version', ''), // 浏览器版本
                        'languages' => data_get($extData, 'clientData.languages', ''), // 语言 ['nl-nl', 'nl', 'en-us', 'en']
                        'is_robot' => data_get($extData, 'clientData.is_robot', 0), //是否是机器人
                    ];
                    $activityHelpedData = static::updateOrCreate($storeId, $where, $data);
                    $dbOperation = data_get($activityHelpedData, 'dbOperation', 'no');
                    if ($dbOperation == 'no') {
                        throw new \Exception(null, 61106);
                    }

                    if ($dbOperation != 'insert') {//如果不是新增，就提示被邀请者已经助力过
                        throw new \Exception(null, 61112);
                    }

                    //更新助力数量
                    $updateData = [
                        'helped_sum' => DB::raw('helped_sum+1'),
                        Constant::DB_TABLE_UPDATED_AT => $nowTime,
                    ];
                    $actctivityApplyWhere = [
                        'id' => $applyId,
                        [[Constant::AUDIT_STATUS, '=', 0]]
                    ];
                    $isUpdated = ActivityApplyService::update($storeId, $actctivityApplyWhere, $updateData);
                    if (empty($isUpdated)) {
                        throw new \Exception(null, 61108);
                    }

                    /*                     * *************************更新解锁状态***************************** */
                    //判断是否已经解锁成功
                    $actctivityApplyWhere = [
                        'w.id' => $applyId,
                        "{$prefix}w.helped_sum >= {$prefix}p.help_sum",
                        [['w.audit_status', '=', 0]]
                    ];
                    $order = [];
                    $dbExecutionPlan = ActivityApplyService::getDbExecutionPlan($storeId, $actctivityApplyWhere, $order, null, 1);
                    data_set($dbExecutionPlan, 'parent.select', ['w.id', 'w.country']);
                    $dataStructure = 'one';
                    $flatten = false;
                    $actctivityApplyData = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);
                    if ($actctivityApplyData) {//如果已经解锁成功，就更新解锁状态，发送解锁成功邮件
                        $productCountry = data_get($actctivityApplyData, Constant::DB_TABLE_COUNTRY, ''); //申请者国家
                        $extWhere = ['p.id' => $productId];
                        $mayApplyProduct = ActivityProductService::getMayApplyProduct($storeId, $actId, $productCountry, $extWhere, 'one', [], null, 1);
                        if (empty($mayApplyProduct)) {
                            //发送库存不足邮件
                            throw new \Exception(null, 61109);
                        }

                        $productItemId = data_get($mayApplyProduct, 'product_item_id', 0);
                        $helpSum = data_get($mayApplyProduct, 'help_sum', 0);
                        if (empty($productItemId)) {
                            throw new \Exception(null, 61109);
                        }

                        //更新库存
                        $updateData = [
                            Constant::DB_TABLE_QTY_APPLY => DB::raw('qty_apply+1'),
                            Constant::DB_TABLE_UPDATED_AT => $nowTime,
                        ];
                        $updateWhere = [
                            'id' => $productId,
                            "qty > qty_apply",
                        ];
                        $isUpdated = ActivityProductService::update($storeId, $updateWhere, $updateData);
                        if (empty($isUpdated)) {//如果更新库存失败，就抛出异常，回滚所有的更新操作
                            //库存已经使用完，发送库存不足邮件
                            throw new \Exception(null, 61107);
                        }

                        //更新库存
                        $updateWhere = [
                            'id' => $productItemId,
                            "qty > qty_apply",
                        ];
                        $isUpdated = ActivityProductItemService::update($storeId, $updateWhere, $updateData);
                        if (empty($isUpdated)) {//如果更新库存失败，就抛出异常，回滚所有的更新操作
                            //库存已经使用完，发送库存不足邮件
                            throw new \Exception(null, 61107);
                        }

                        $updateData = [
                            'product_item_id' => $productItemId, //产品item id => crm_activity_product_items.id
                            Constant::AUDIT_STATUS => 1, //解锁状态 0:未助力 1:解锁成功 2:解锁失败 3:其他
                            'review_at' => $nowTime,
                            Constant::DB_TABLE_UPDATED_AT => $nowTime,
                            'help_sum' => $helpSum,
                        ];
                        $actctivityApplyWhere = [
                            'id' => $applyId
                        ];
                        $isUpdated = ActivityApplyService::update($storeId, $actctivityApplyWhere, $updateData);
                        if (empty($isUpdated)) {//如果更新解锁状态失败，回滚所有的更新操作
                            //更新解锁状态失败
                            throw new \Exception(null, 61110);
                        }

                        //发送解锁成功邮件
                        $extType = ActivityApplyService::getModelAlias();
                        $parameters = [$storeId, $actId, $applyId, $extType, Constant::DB_TABLE_EMAIL, 'view_audit_1'];
                        FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters));
                    }

                    $isSubcribe = SubcribeService::exists($storeId, $helpAccount);
                    if (!$isSubcribe) {//如果 $helpAccount 未订阅，就添加订阅
                        //添加订阅流水
                        data_set($extData, Constant::ACT_ID, $actId);
                        data_set($extData, 'verifiedEmail', $verifiedEmail);
                        SubcribeService::addSubcribe($storeId, $helpAccount, $helpCountry, $ip, '解锁订阅', '', $extData);
                    }

                    //添加邀请流水
                    InviteService::addInviteLogs($storeId, $actId, $account, $helpAccount, $customerId, 0, $verifiedEmail, '', '', $inviteCode, $extData);

                    //给解锁者发送助力成功感谢邮件
                    $method = 'emailUnlockedUsers'; //邮件处理
                    $extType = ActivityApplyService::getModelAlias();
                    $parameters = [$storeId, $actId, $helpAccount, $applyId, $extType, Constant::DB_TABLE_EMAIL, 'view_audit_unlock_1'];
                    FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters));

                    static::getModel($storeId, '')->getConnection()->commit();
                } catch (\Exception $exc) {
                    // 出错回滚
                    static::getModel($storeId, '')->getConnection()->rollBack();

                    return Response::getDefaultResponseData($exc->getCode(), $exc->getMessage());
                }

                $productData = ActivityProductService::exists($storeId, $productId, $actId, '', true); //获取产品数据
                //判断申请的产品库存
                if (data_get($productData, Constant::DB_TABLE_QTY_APPLY, -1) >= data_get($productData, 'qty', 0)) {//如果产品已经被申请完，就提示用户
                    //发送库存不足邮件
                    static::handleEmail(...$outStockParameters);

                    //如果产品已经被申请完，更新未审核通过的数据为解锁失败状态
                    static::applyFail($storeId, $actId, $productId);
                }
            } catch (\Exception $exception) {
                return Response::getDefaultResponseData($exception->getCode(), $exception->getMessage());
            }

            return Response::getDefaultResponseData(1);
        });

        return $rs === false ? $defaultRs : $rs;
    }

    /**
     * 获取公共sql
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param string $productCountry 奖品国家
     * @return array $dbExecutionPlan
     */
    public static function getDbExecutionPlan($storeId = 0, $actId = 0, $order = [], $offset = null, $limit = null, $isPage = false, $pagination = []) {

        $select = [Constant::HELP_ACCOUNT]; //

        $dbExecutionPlan = [
            'parent' => [
                'setConnection' => true,
                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                'builder' => null,
                'make' => static::getModelAlias(),
                'from' => '',
                'joinData' => [
                ],
                'select' => $select,
                'where' => [],
                'orders' => $order,
                Constant::DB_EXECUTION_PLAN_OFFSET => $offset,
                'limit' => $limit,
                'isPage' => $isPage,
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
                'handleData' => [
                ],
                'unset' => [],
            ],
            'with' => [
            ],
            'itemHandleData' => [
                'field' => null, //数据字段
                'data' => [], //数据映射map
                'dataType' => '', //数据类型
                'dateFormat' => '', //数据格式
                'time' => '', //时间处理句柄
                'glue' => '', //分隔符或者连接符
                'is_allow_empty' => true, //是否允许为空 true：是  false：否
                'default' => '', //默认值$default
                'callback' => [
                    Constant::HELP_ACCOUNT => function ($item) {
                        return FunctionHelper::handleAccount(data_get($item, Constant::HELP_ACCOUNT, ''));
                    }
                ],
            ],
                //'sqlDebug' => true,
        ];

        if ($actId) {
            data_set($dbExecutionPlan, 'parent.where.act_id', $actId);
        }

        return $dbExecutionPlan;
    }

    /**
     * 获取排行榜数据
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param string $account 会员账号
     * @param int $page 当前页码
     * @param int $pageSize 每页记录条数
     * @return array
     */
    public static function getData($storeId = 0, $actId = 0, $applyId = 0, $page = 1, $pageSize = 10) {

        $publicData = [
            'page' => $page,
            'page_size' => $pageSize,
        ];
        $_data = parent::getPublicData($publicData);

        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $offset = data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0);
        $limit = data_get($pagination, 'page_size', 10);
        $order = [['id', 'DESC']];

        $dbExecutionPlan = static::getDbExecutionPlan($storeId, $actId, $order, $offset, $limit, false, $pagination);

        data_set($dbExecutionPlan, 'parent.where.ext_id', $applyId);
        $dataStructure = 'list';
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, false, false, $dataStructure);
    }

    /**
     * 库存不足时，更新解锁活动的状态为解锁失败
     * @param int $storeId
     * @param int $actId
     * @param int $productId
     * @param string $extType
     * @return bool
     */
    public static function applyFail($storeId, $actId, $productId, $extType = 'ActivityProduct') {

        $nowTime = Carbon::now()->toDateTimeString();

        $updateData = [
            Constant::AUDIT_STATUS => -2, //解锁状态 0:未助力 1:解锁成功 2:解锁失败 3:其他 -2:库存被申请完时,其他在解锁过程的申请状态置为-2
            'review_at' => $nowTime,
            Constant::DB_TABLE_UPDATED_AT => $nowTime
        ];
        $actctivityApplyWhere = [
            Constant::DB_TABLE_EXT_TYPE => $extType,
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_EXT_ID => $productId,
            Constant::AUDIT_STATUS => 0
        ];

        $isExists = ActivityApplyService::getModel($storeId)->buildWhere($actctivityApplyWhere)->exists();
        if ($isExists) {
            $isUpdated = ActivityApplyService::update($storeId, $actctivityApplyWhere, $updateData);
            //更新失败，重试
            if (empty($isUpdated)) {
                ActivityApplyService::update($storeId, $actctivityApplyWhere, $updateData);
            }
        }

        return true;
    }

    /**
     * 给解锁者发送助力成功感谢邮件
     * @param int $storeId
     * @param int $actId
     * @param string $helpAccount
     * @param int $extId
     * @param string $extType
     * @param string $type
     * @param string $key
     * @param array $extData
     * @return array
     */
    public static function emailUnlockedUsers($storeId, $actId, $helpAccount, $extId, $extType = 'ActivityApply', $type = Constant::DB_TABLE_EMAIL, $key = 'view_audit_unlock_1', $extData = []) {

        $country = data_get($extData, Constant::DB_TABLE_COUNTRY, '');

        //获取申请产品记录
        $where = [
            'id' => $extId
        ];
        $activityApply = ActivityApplyService::getModel($storeId, $country)->buildWhere($where)->first();
        if (empty($activityApply)) {
            return [];
        }

        //获取申请者的用户数据
        $applyUser = CustomerInfoService::exists($storeId, data_get($activityApply, Constant::DB_TABLE_CUSTOMER_PRIMARY, 0), '', true);
        $firstName = data_get($applyUser, 'first_name', '');
        data_set($extData, 'applyUser.firstName', $firstName);

        //获取解锁者用户数据
        $where = [
            Constant::DB_TABLE_ACCOUNT => $helpAccount,
            Constant::DB_TABLE_STORE_ID => $storeId,
        ];
        $unlockedUsers = CustomerInfoService::getModel($storeId, $country)->buildWhere($where)->get();
        if (empty($unlockedUsers)) {
            return [];
        }

        //dump($unlockedUsers);

        foreach ($unlockedUsers as $unlockedUser) {
            $toEmail = data_get($unlockedUser, Constant::DB_TABLE_ACCOUNT, '');
            $ip = data_get($unlockedUser, 'ip', '');
            $customerId = data_get($unlockedUser, Constant::DB_TABLE_CUSTOMER_PRIMARY, 0);

            $isTrueEmail = SocialMediaLoginService::getModel($storeId)->buildWhere([Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId])->value('true_email');
            //如果是社媒账号并且是假账号，就不发邮件
            if ($isTrueEmail !== null && $isTrueEmail == 0) {
                continue;
            }

            static::emailQueue($storeId, $actId, $toEmail, $customerId, $ip, $extId, $extType, $type, $key, $extData);
        }

        return [];
    }

    /**
     * 第三方真假账号存在时，判断是否已经助力过
     * @param $storeId
     * @param $actId
     * @param $helpAccount
     * @param $ip
     * @param array $extData
     * @return bool
     */
    public static function isHelped($storeId, $actId, $helpAccount, $ip, $extData = []) {
        $loginSource = data_get($extData, 'login_source', '');
        if (!in_array($loginSource, ['facebook', 'twitter'])) {
            return true;
        }

        $thirdUserId = data_get($extData, 'third_user_id', '');
        if (empty($thirdUserId)) {
            return false;
        }

        $params = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            'third_source' => $loginSource,
            'third_user_id' => $thirdUserId
        ];
        $userInfos = SocialMediaLoginService::getSocialMediaUserInfo($params);
        if (empty($userInfos)) {
            return false;
        }

        if (count($userInfos) == 1 && $userInfos[0][Constant::DB_TABLE_ACCOUNT] != $helpAccount) {
            return false;
        }

        if (count($userInfos) > 1) {
            foreach ($userInfos as $userInfo) {
                if ($userInfo[Constant::DB_TABLE_ACCOUNT] != $helpAccount) {
                    //判断 ip 或者 账号  是否助力过
                    $activityHelpedLogWhere = [
                        'or' => [
                            'ip' => $ip,
                            Constant::HELP_ACCOUNT => $userInfo[Constant::DB_TABLE_ACCOUNT],
                        ]
                    ];
                    $isExists = ActivityHelpedLogService::exists($storeId, '', $actId, '', $activityHelpedLogWhere);
                    if ($isExists) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

}
