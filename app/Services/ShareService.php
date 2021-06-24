<?php

/**
 * 分享服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use Hyperf\Utils\Arr;
use Carbon\Carbon;
use App\Models\Share;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;

class ShareService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 添加记录
     * @param $storeId
     * @param $data
     * @return bool
     */
    public static function insert($data) {
        $id = Share::add($data);
        return $id;
    }

    /**
     * 更新汇总数据
     * @param int $storeId 商城id
     * @param int $customerId 会员id
     * @param int $actId 活动id
     * @param int $type 榜单类型 1:分享 2:邀请
     * @param int $score 积分
     * @return int
     */
    public static function handle($storeId = 0, $customerId = 0, $actId = 0, $type = 1, $score = 1) {
        return RankService::handle($storeId, $customerId, $actId, 1, $type, $score);
    }

    /**
     * 获取db query
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getQuery($where = []) {
        $query = Share::leftJoin('customer as a', 'a.customer_id', '=', 'shares.customer_id')
                ->leftJoin('customer_info as b', 'b.customer_id', '=', 'shares.customer_id')
                ->buildWhere($where)
        ;
        return $query;
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $where = [];

        $params['account'] = $params['account'] ?? '';
        $params['country'] = $params['country'] ?? '';
        $params['audit_status'] = $params['audit_status'] ?? '';

        if ($params['store_id']) {//商城id
            $where[] = ['a.store_id', '=', $params['store_id']];
        }

        if ($params['account']) {//会员
            $where[] = ['a.account', '=', $params['account']];
        }

        if ($params['country']) {//国家
            $where[] = ['b.country', '=', $params['country']];
        }

        if ($params['audit_status'] !== '') {//状态
            $where[] = ['shares.audit_status', '=', $params['audit_status']];
        }

        $_where = [];
        if (data_get($params, 'id', 0)) {
            $_where['shares.id'] = $params['id'];
        }

        if ($where) {
            $_where[] = $where;
        }

        $order = $order ? $order : ['shares.id', 'desc'];
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

        $storeId = data_get($params, 'store_id', 0);
        $_data = static::getPublicData($params);

        $where = data_get($_data, 'where', []);
        $order = data_get($params, 'orderBy', data_get($_data, 'order', []));
        $pagination = $_data['pagination'];
        $limit = data_get($params, 'limit', data_get($pagination, 'page_size', 10));
        $offset = data_get($params, 'offset', data_get($pagination, 'offset', 0));

        $select = $select ? $select : ['shares.id', 'a.account', 'b.country', 'shares.content', 'shares.audit_status', 'shares.created_at'];

        //审核状态 0:未审核 1:审核通过 2:审核不通过 3:其他
        $auditStatus = [
            0 => '未审核',
            1 => '审核通过',
            2 => '审核未通过',
            3 => '其他',
        ];

        $dbExecutionPlan = [
            'parent' => [
                'setConnection' => true,
                'storeId' => 0,
                'builder' => null,
                'make' => static::getModelAlias(),
                'from' => '',
                'joinData' => [
                    [
                        'table' => 'customer as a',
                        'first' => 'a.customer_id',
                        'operator' => '=',
                        'second' => 'shares.customer_id',
                        'type' => 'left',
                    ],
                    [
                        'table' => 'customer_info as b',
                        'first' => 'b.customer_id',
                        'operator' => '=',
                        'second' => 'shares.customer_id',
                        'type' => 'left',
                    ],
                ],
                'select' => $select,
                'where' => $where,
                'orders' => [
                    $order
                ],
                'offset' => $offset,
                'limit' => $limit,
                'isPage' => $isPage,
                'isOnlyGetCount' => $isOnlyGetCount,
                'pagination' => $pagination,
                'handleData' => [
                    'audit_status' => [//增加方式|1加,2减
                        'field' => 'audit_status',
                        'data' => $auditStatus,
                        'dataType' => '',
                        'dateFormat' => '',
                        'time' => '',
                        'glue' => '',
                        'default' => '',
                    ]
                ],
                'unset' => [],
            ],
            'with' => [
            ],
            'itemHandleData' => [
            ],
                //'sqlDebug' => true,
        ];

        if (data_get($params, 'isOnlyGetPrimary', false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            data_set($dbExecutionPlan, 'parent.handleData', []);
            data_set($dbExecutionPlan, 'with', []);
        }

        $dataStructure = 'list';
        $flatten = true;
        $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);

        return $data;
    }

    /**
     * 检查会员是否存在
     * @param int $storeId
     * @param int $customerId
     * @param string $account
     * @param int $storeCustomerId
     * @return bool
     */
    public static function exists($id = 0, $getData = false) {
        if (empty($id)) {
            return $getData ? [] : true;
        }
        $query = Share::where('id', $id);
        if ($getData) {
            $rs = $query->first();
        } else {
            $rs = $query->exists();
        }

        return $rs;
    }

    /**
     * 审核
     * @param int $id 分享id
     * @param int $auditStatus 审核状态 0:未审核 1:审核通过 2:审核不通过 3:其他
     * @param string $remarks 备注
     * @param int $storeId 商城ID
     * @param int $value 积分值
     * @param int $addType 增加方式|1加,2减
     * @param string $action 动作
     * @return array $rs ['code' => 1, 'msg' => '', 'data' => []]
     */
    public static function audit($ids, $auditStatus, $remarks, $storeId, $value, $addType, $action) {

        $rs = ['code' => 1, 'msg' => '', 'data' => []];

        if (empty($ids) || !is_array($ids)) {
            return $rs;
        }

        $shareData = Share::select(['customer_id', 'id'])->whereIn('id', $ids)->get();
        if (empty($shareData)) {
            $rs['code'] = 0;
            $rs['msg'] = '数据不存';
            return $rs;
        }

        $updateData = [
            'audit_status' => $auditStatus,
            'remarks' => $remarks,
            'updated_at' => Carbon::now()->toDateTimeString(),
        ];
        $isUpdate = Share::whereIn('id', $ids)->update($updateData);

        if ($isUpdate) {
            $expansionData = [
                'store_id' => $storeId,
                'remark' => $remarks,
            ];

            $type = 'share';
            $orderby = 'sorts asc';
            $keyField = 'conf_key';
            $valueField = 'conf_value';
            $shareConfig = DictStoreService::getListByType($storeId, $type, $orderby, $keyField, $valueField);
            $supportCredit = data_get($shareConfig, 'support_credit', 0); //分享促销活动/产品链接  是否可以获取积分 1:可以  0:不可以  默认:0
            $supportExp = data_get($shareConfig, 'support_exp', 0); //分享促销活动/产品链接  是否可以获取经验 1:可以  0:不可以  默认:0

            foreach ($shareData as $item) {
                $data = FunctionHelper::getHistoryData([
                            'customer_id' => $item->customer_id,
                            'value' => $value,
                            'add_type' => $addType,
                            'action' => $action,
                            'ext_id' => $item->id, //关联id
                            'ext_type' => 'share', //关联模型
                                ], $expansionData);

                if ($supportCredit) {//如果可以获取积分，就添加积分
                    CreditService::handle($data); //记录积分流水
                }

                if ($supportExp) {//如果可以获取经验，就添加经验
                    ExpService::handle($data); //记录经验流水
                }
            }
        }

        return $rs;
    }

}
