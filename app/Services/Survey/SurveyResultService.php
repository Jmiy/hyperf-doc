<?php

/**
 * 调查问券服务
 * User: Jmiy
 * Date: 2020-01-04
 * Time: 12:00
 */

namespace App\Services\Survey;

use Hyperf\Utils\Arr;
use App\Utils\Support\Facades\Queue;
use App\Utils\FunctionHelper;
use App\Jobs\PublicJob;
use App\Services\BaseService;
use App\Services\Traits\GetDefaultConnectionModel;
use App\Services\ActivityService;
use App\Services\EmailService;
use App\Utils\Response;

class SurveyResultService extends BaseService {

    use GetDefaultConnectionModel;

    public static function getCacheTags() {
        return 'survey';
    }

    /**
     * 获取邮件数据
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param string $toEmail 收件者邮箱
     * @param string $name  收件者名字
     * @param string $ip 收件人ip
     * @param int $extId 关联id
     * @param string $extType 关联模型
     * @param string $type 活动配置类型
     * @param string $key 活动配置key
     * @return array $rs 邮件任务进入消息队列结果
     */
    public static function getEmailData($storeId, $actId, $toEmail, $name, $ip, $extId, $extType, $type = 'email', $key = 'survey_submit', $extData = []) {

        $rs = [
            'code' => 1,
            'storeId' => $storeId, //商城id
            'actId' => $actId, //活动id
            'content' => '', //邮件内容
            'subject' => '',
            'country' => '',
            'ip' => $ip,
            'extId' => $extId,
            'extType' => $extType,
            'requestData' => $extData,
        ];

        $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, $type, [$key, ($key . '_subject')]);
        $emailView = Arr::get($activityConfigData, $type . '_' . $key . '.value', ''); //邮件模板
        $subject = Arr::get($activityConfigData, $type . '_' . $key . '_subject' . '.value', ''); //邮件主题
        data_set($rs, 'subject', $subject);

        //获取邮件模板
        $replacePairs = [
            '{{$account}}' => $name,
        ];
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
     * @param string $name  收件者名字
     * @param string $ip 收件人ip
     * @param int $extId 关联id
     * @param string $extType 关联模型
     * @param string $type 活动配置类型
     * @param string $key 活动配置key
     * @return array $rs 邮件任务进入消息队列结果
     */
    public static function emailQueue($storeId, $actId, $toEmail, $name, $ip, $extId, $extType, $type = 'email', $key = 'survey_submit', $extData = []) {

        $defaultRs = Response::getDefaultResponseData(100002);

        $tag = static::getCacheTags();
        $_parameters = func_get_args();
        data_set($_parameters, 9, '');
        $cacheKey = $tag . ':emailQueue：' . md5(implode(':', $_parameters));
        $actionData = [
            'service' => static::getNamespaceClass(),
            'method' => 'lock',
            'parameters' => [
                $cacheKey,
            ],
            'serialHandle' => [
                [
                    'service' => static::getNamespaceClass(),
                    'method' => 'get',
                    'parameters' => [
                        function () use($storeId, $actId, $toEmail, $name, $ip, $extId, $extType, $type, $key, $extData) {

                            $group = static::getCacheTags(); //分组
                            $emailType = $key; //类型
                            $actData = ActivityService::getActivityData($storeId, $actId);
                            $remark = data_get($actData, 'name', '');

                            //判断邮件是否已经发送
                            $where = [
                                'store_id' => $storeId,
                                'group' => $group,
                                'type' => $emailType,
                                'country' => '',
                                'to_email' => $toEmail,
                                'ext_id' => $extId,
                                'ext_type' => $extType,
                                'act_id' => $actId,
                            ];
                            $isExists = EmailService::existsOrFirst($storeId, '', $where);
                            if ($isExists) {//如果订单邮件已经发送，就提示
                                $retult['code'] = 100003;
                                return $retult;
                            }

                            $extService = static::getNamespaceClass();
                            $extMethod = 'getEmailData'; //获取审核邮件数据
                            $extParameters = [$storeId, $actId, $toEmail, $name, $ip, $extId, $extType, $type, $key, $extData];

                            //解除任务
                            $extData = [
                                'actId' => $actId,
                                'service' => $extService,
                                'method' => $extMethod,
                                'parameters' => $extParameters,
                                'callBack' => [
                                ],
                            ]; //扩展数据

                            $service = EmailService::getNamespaceClass();
                            $method = 'handle'; //邮件处理 
                            $parameters = [$storeId, $toEmail, $group, $emailType, $remark, $extId, $extType, $extData];

                            $data = [
                                'service' => $service,
                                'method' => $method,
                                'parameters' => $parameters,
                                'extData' => [
                                    'service' => $service,
                                    'method' => $method,
                                    'parameters' => $parameters,
                                ],
                            ];

                            Queue::push(new PublicJob($data));

                            return [
                                'code' => 1,
                                'msg' => '',
                                'data' => [],
                            ];
                        }
                    ],
                ]
            ]
        ];

        $rs = static::handleCache($tag, $actionData);

        return $rs === false ? $defaultRs : $rs;
    }

    /**
     * 处理邮件业务
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $extId 关联id
     * @param string $extType 关联模型
     * @param string $type 活动配置类型
     * @param string $key 活动配置key
     * @return array $rs
     */
    public static function handleEmail($storeId, $actId, $extId, $extType, $type = 'email', $key = 'survey_submit', $extData = []) {

        $rs = ['code' => 1, 'msg' => '', 'data' => []];

        $where = [
            'id' => $extId, //结果id
        ];
        $select = [
            'account', 'name', 'ip'
        ];
        $dbExecutionPlan = [
            'parent' => [
                'setConnection' => true,
                'storeId' => 'default_connection_survey',
                'builder' => null,
                'make' => static::getModelAlias(),
                'from' => '',
                'joinData' => [
                ],
                'select' => $select,
                'where' => $where,
                'orders' => [],
                'offset' => null,
                'limit' => null,
                'isPage' => false,
                'pagination' => [],
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

        if (empty($where)) {
            return $rs;
        }

        $dataStructure = 'list';
        $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, false, false, $dataStructure);
        foreach ($data as $item) {
            $toEmail = data_get($item, 'account', '');
            $name = data_get($item, 'name', '');
            $ip = data_get($item, 'ip', '');
            if (empty($toEmail)) {
                continue;
            }
            static::emailQueue($storeId, $actId, $toEmail, $name, $ip, $extId, $extType, $type, $key, $extData);
        }
        return $rs;
    }

    /**
     * 提交调查问券
     * @param int $storeId 商城id
     * @param int $actId   活动id
     * @param int $surveyId  问券id
     * @param int $ip ip
     * @param array $extData 请求参数
     * @return array 提交结果
     */
    public static function handle($storeId, $actId, $surveyId, $ip, $extData = []) {

        if (empty($storeId) || empty($actId) || empty($surveyId) || empty($ip)) {
            return Response::getDefaultResponseData(9999999999);
        }

        $defaultRs = Response::getDefaultResponseData(100000);

        $tag = static::getCacheTags();
        $cacheKey = $tag . ':' . $storeId . ':' . $actId . ':' . $ip;
        $actionData = [
            'service' => static::getNamespaceClass(),
            'method' => 'lock',
            'parameters' => [
                $cacheKey,
            ],
            'serialHandle' => [
                [
                    'service' => static::getNamespaceClass(),
                    'method' => 'get',
                    'parameters' => [
                        function () use($storeId, $actId, $surveyId, $ip, $extData) {
                            // 获取无限期锁并自动释放...
                            $surveyResultModel = static::getModel($storeId, '', []);
                            $conection = $surveyResultModel->getConnection();
                            $conection->beginTransaction();
                            $surveyResultData = [];
                            try {
                                //添加 答券流水
                                $account = data_get($extData, 'account', '');
                                $where = [
                                    'store_id' => $storeId,
                                    'act_id' => $actId,
                                    'or' => [
                                        'ip' => $ip,
                                        'account' => $account,
                                    ]
                                ];

                                $surveyResultData = static::existsOrFirst($storeId, '', $where, true);
                                if ($surveyResultData) {

                                    if ($ip == data_get($surveyResultData, 'ip', '')) {
                                        return Response::getDefaultResponseData(100001);
                                    }

                                    if ($account == data_get($surveyResultData, 'account', '')) {
                                        return Response::getDefaultResponseData(100004);
                                    }
                                }

                                $data = [
                                    'store_id' => $storeId,
                                    'act_id' => $actId,
                                    'ip' => $ip,
                                    'survey_id' => $surveyId,
                                    'country' => data_get($extData, 'country', ''),
                                    'name' => data_get($extData, 'name', ''),
                                    'account' => $account,
                                ];
                                $surveyResultId = $surveyResultModel->insertGetId($data);

                                $surveyItems = data_get($extData, 'survey_items', []);
                                $surveyResultItems = [];
                                $surveyResultItemModel = SurveyResultItemService::getModel($storeId, '', []);
                                $surveyResultItemOptionModel = SurveyResultItemOptionService::getModel($storeId, '', []);
                                foreach ($surveyItems as $key => $surveyItem) {

                                    $itemId = data_get($surveyItem, 'item_id', 0); //题目id
                                    $resultItemId = data_get($surveyResultItems, $itemId, 0);
                                    if (empty($resultItemId)) {
                                        $surveyResultItemData = [
                                            'result_id' => $surveyResultId,
                                            'survey_id' => $surveyId, //问券id
                                            'item_id' => data_get($surveyItem, 'item_id', 0), //题目id
                                        ];

                                        $resultItemId = $surveyResultItemModel->insertGetId($surveyResultItemData);
                                        data_set($surveyResultItems, $itemId, $resultItemId, false);
                                    }

                                    $surveyResultItemOptionData = [
                                        'result_item_id' => $resultItemId,
                                        'option_id' => data_get($surveyItem, 'option_id', 0), //答案选项id
                                        'option_data' => data_get($surveyItem, 'option_data', ''), //答案内容
                                    ];
                                    $surveyResultItemOptionModel->insertGetId($surveyResultItemOptionData);
                                }

                                $conection->commit();
                            } catch (\Exception $exc) {
                                // 出错回滚
                                $conection->rollBack();

                                return [
                                    'code' => $exc->getCode(),
                                    'msg' => $exc->getMessage(),
                                    'data' => [],
                                ];
                            }

                            //发送提交成功邮件
                            $service = static::getNamespaceClass();
                            $method = 'handleEmail'; //邮件处理
                            $extId = $surveyResultId;
                            $extType = static::getModelAlias();
                            $parameters = [$storeId, $actId, $extId, $extType, 'email', 'survey_submit', $extData];

                            $data = [
                                'service' => $service,
                                'method' => $method,
                                'parameters' => $parameters,
                            ];

                            Queue::push(new PublicJob($data));

                            return [
                                'code' => 1,
                                'msg' => '',
                                'data' => [],
                            ];
                        }
                    ],
                ]
            ]
        ];

        $rs = static::handleCache($tag, $actionData);

        return $rs === false ? $defaultRs : $rs;
    }

    /**
     * 获取公共sql
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param string $productCountry 奖品国家
     * @return array $dbExecutionPlan
     */
    public static function getDbExecutionPlan($storeId = 0, $order = [], $offset = null, $limit = null, $isPage = false, $pagination = []) {

        $select = ['*']; //

        $dbExecutionPlan = [
            'parent' => [
                'setConnection' => false,
                'storeId' => 'default_connection_survey',
                'builder' => null,
                'make' => static::getModelAlias(),
                'from' => '',
                'joinData' => [
                ],
                'select' => $select,
                'where' => ['store_id' => $storeId],
                'orders' => $order,
                'offset' => $offset,
                'limit' => $limit,
                'isPage' => $isPage,
                'pagination' => $pagination,
                'handleData' => [
                ],
                'unset' => [],
            ],
            'with' => [
                'survey_result_items' => [
                    'setConnection' => false,
                    'storeId' => 'default_connection_survey',
                    'relation' => 'hasMany',
                    'default' => [
                    ],
                    'select' => ['id', 'result_id', 'item_id'],
                    'where' => [],
                    'orders' => [],
                    'handleData' => [
                    ],
                    'with' => [
                        'survey_item' => [
                            'setConnection' => false,
                            'storeId' => 'default_connection_survey',
                            'relation' => 'hasOne',
                            'default' => [
                                'item_id' => 'item_id',
                            ],
                            'select' => ['id', 'name'],
                            'where' => [],
                            'orders' => [['sort', 'ASC']],
                            'handleData' => [
                            ],
                        ],
                        'survey_result_item_options' => [
                            'setConnection' => false,
                            'storeId' => 'default_connection_survey',
                            'relation' => 'hasMany',
                            'default' => [
                                'option_id' => 'option_id',
                            ],
                            'select' => ['id', 'result_item_id', 'option_id', 'option_data'],
                            'where' => [],
                            'orders' => [],
                            'handleData' => [
                            ],
                            'with' => [
                                'survey_item_option' => [
                                    'setConnection' => false,
                                    'storeId' => 'default_connection_survey',
                                    'relation' => 'hasOne',
                                    'default' => [
                                    ],
                                    'select' => ['id', 'item_id', 'name'],
                                    'where' => [],
                                    'orders' => [['sort', 'ASC']],
                                    'handleData' => [
                                    ],
                                ],
                            ]
                        ],
                    ]
                ],
            ],
                //'sqlDebug' => true,
        ];

        return $dbExecutionPlan;
    }

    /**
     * 获取详情
     * @param int $storeId    商城id
     * @param int $surveyId      问券id
     * @return array 
     */
    public static function getData($storeId = 0, $actId = 0) {

        $pagination = [];
        $offset = null;
        $limit = null;
        $order = [];

        $dbExecutionPlan = static::getDbExecutionPlan($storeId, $order, $offset, $limit, false, $pagination);

        $dataStructure = 'list';
        $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, false, false, $dataStructure);
        foreach ($data as $key => $value) {
            $survey_result_items = data_get($value, 'survey_result_items', []);
            foreach ($survey_result_items as $_key => $_value) {
                data_set($data, $key . '.survey_result_items.' . $_key . '.item_name', data_get($_value, 'survey_item.name', ''), true);

                $item_option_names = array_filter(data_get($_value, 'survey_result_item_options.*.survey_item_option.name', []));
                $item_option_names = !empty($item_option_names) ? $item_option_names : [data_get($_value, 'survey_result_item_options.0.option_data', '')];
                data_set($data, $key . '.survey_result_items.' . $_key . '.item_option_names', $item_option_names, true);
            }
        }

        return $data;
    }

}
