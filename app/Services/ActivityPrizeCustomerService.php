<?php

/**
 * 中奖名单服务
 * User: Jmiy
 * Date: 2020-08-27
 * Time: 16:16
 */

namespace App\Services;

use Hyperf\DbConnection\Db as DB;
use Hyperf\Utils\Arr;
use App\Utils\FunctionHelper;
use App\Constants\Constant;
use App\Utils\Response;

class ActivityPrizeCustomerService extends BaseService {

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @param array $order 排序控制
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $where = [];

        $actId = data_get($params, Constant::DB_TABLE_ACT_ID, 0); //活动id

        if ($actId) {//活动id
            $where[] = [Constant::DB_TABLE_ACT_ID, '=', $actId];
        }

        $order = $order ? $order : [[Constant::DB_TABLE_PRIMARY, 'DESC']];

        $_where = [];
        if (data_get($params, 'id', 0)) {
            $_where['id'] = $params['id'];
        }
        if ($where) {
            $_where[] = $where;
        }

        return Arr::collapse([parent::getPublicData($params, $order), [
                        Constant::DB_EXECUTION_PLAN_WHERE => $_where,
        ]]);
    }

    /**
     * 获取选项数据
     * @param int $storeId    商城id
     * @param int $actId      活动id
     * @param int $page       当前页码
     * @param int $pageSize   每页记录条数
     * @return array
     */
    public static function getItemData($storeId = 0, $actId = 0, $page = 1, $pageSize = 10) {

        $publicData = [
            Constant::DB_TABLE_ACT_ID => $actId,
            'page' => $page,
            'page_size' => $pageSize,
            'orderby' => [['prize_at', 'DESC']],
        ];

        $_data = static::getPublicData($publicData, data_get($publicData, 'orderby', []));

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, []);
        $order = data_get($_data, 'order', []);
        $pagination = data_get($_data, 'pagination', []);
        $limit = data_get($pagination, 'page_size', 10);
        $offset = data_get($pagination, 'offset', 0);

        $select = [
            Constant::DB_TABLE_ACCOUNT,
            'prize_at',
        ];

        $handleData = [
            'prize_at' => FunctionHelper::getExePlanHandleData('prize_at', '', Constant::PARAMETER_ARRAY_DEFAULT, Constant::DB_EXECUTION_PLAN_DATATYPE_DATETIME, 'Y-m-d'), //延保时间
        ];
        $joinData = [];
        $with = Constant::PARAMETER_ARRAY_DEFAULT;
        $unset = [];
        $isPage = true;
        $isOnlyGetCount = false;
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), '', $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $itemHandleDataCallback = [
            Constant::DB_TABLE_ACCOUNT => function($item) {
                return FunctionHelper::handleAccount(data_get($item, Constant::DB_TABLE_ACCOUNT, Constant::PARAMETER_STRING_DEFAULT));
            }
        ];
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback),
        ];

        $dataStructure = 'list';
        $flatten = false;

        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);
    }

    /**
     * 导入活动产品数据
     * @param int $storeId 商城id
     * @param int $actId   活动id
     * @param string $fileFullPath 文件完整路径
     * @param string $user 上传人
     * @param array $requestData 请求参数
     * @return array 导入结果
     */
    public static function import($storeId, $actId, $fileFullPath, $user, $requestData = []) {

        $rs = Response::getDefaultResponseData(1, '导入成功');

        $typeData = [
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_TIMESTAMP,
        ];

        $connection = static::getModel($storeId, '')->getConnection();
        $connection->beginTransaction();
        try {
            ExcelService::parseExcelFile($fileFullPath, $typeData, function ($row) use ($storeId, $actId, $fileFullPath, $user, &$rs) {
                $sort = data_get($row, 0, Constant::PARAMETER_STRING_DEFAULT);
                if ($sort == '邮箱' || empty($sort)) {
                    return true;
                }

                /*                 * *****************处理产品 start********************************** */
                $account = data_get($row, 0, ''); //账号
                $prizeAt = data_get($row, 1, ''); //中奖日期

                $where = [
                    Constant::DB_TABLE_ACT_ID => $actId,
                    'prize_at' => $prizeAt,
                ];

                $data = [
                    Constant::DB_TABLE_ACCOUNT => $account,
                ];
                static::updateOrCreate($storeId, $where, $data);
            });

            $connection->commit();
        } catch (\Exception $exc) {
            // 出错回滚
            $connection->rollBack();

            $rs = Response::getDefaultResponseData($exc->getCode(), $exc->getMessage());
        }

        return $rs;
    }

}
