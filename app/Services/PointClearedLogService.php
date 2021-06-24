<?php

/**
 * 积分定时清空服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use Hyperf\DbConnection\Db as DB;
use Carbon\Carbon;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;

class PointClearedLogService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 检查是否存在
     * @param int $id 角色id
     * @param bool $getData 是否获取数据 true:是 false:否 默认:false
     * @return mixed|static
     */
    public static function exists($storeId = 0, $where = [], $getData = false) {
        return static::existsOrFirst($storeId, '', $where, $getData);
    }

    /**
     * 还原积分
     * @param int $storeId 商城id
     * @param int $id 清空批次id
     * @return int 影响记录条数
     */
    public static function resetPoint($storeId, $id = 0) {
        if (empty($id)) {//如果未指定要还原的 批次id  就还原最近的
            $where = [
                'store_id' => $storeId,
            ];
            $id = static::getModel($storeId, '')->orderBy('created_at', 'DESC')->value('id');
        }

        $sql = 'UPDATE `crm_customer_info` a,`crm_vip_backup_logs` b 
SET 
a.total_credit=b.total_credit,
a.credit=b.credit,
a.mtime=b.updated_at,
a.updated_mark=b.updated_mark
WHERE b.point_cleared_log_id=? and a.customer_id=b.customer_id AND a.status=? AND a.store_id=?
;';
        return DB::update($sql, [$id, 1, $storeId]);
    }

    /**
     * 积分变动
     * @param $params
     * @return bool
     */
    public static function handle($storeId, $restore = 0, $rid = 0) {

        FunctionHelper::setTimezone($storeId); //设置时区

        $nowTime = Carbon::now()->toDateTimeString();

        if ($restore) {
            return static::resetPoint($storeId, $rid);
        }

//        if ($nowTime > Carbon::now()->rawFormat('Y-m-d 00:30:00')) {
//            return false;
//        };

        $currentDateTime = Carbon::now()->rawFormat('Y-01-01 00:00:00');
        $timeUnit = 'year';
        $timeNumber = 1;
        $divideTime = strtotime(('-' . ($timeNumber - 1) . ' ' . $timeUnit), strtotime($currentDateTime));
        $divideDateTime = Carbon::createFromTimestamp($divideTime)->toDateTimeString();

        $startTime = strtotime(('-' . $timeNumber . ' ' . $timeUnit), strtotime($currentDateTime));
        $startDateTime = Carbon::createFromTimestamp($startTime)->toDateTimeString();

//        dump($startDateTime, $divideDateTime);

        $where = [
            'store_id' => $storeId,
            'lot_number' => $divideTime,
        ];

//        $isExist = static::existsOrFirst($storeId, '', $where);
//        if ($isExist) {
//            return false;
//        }

        $data = [
            'store_id' => $storeId,
            'lot_number' => $divideTime,
        ];
        $id = static::getModel($storeId, '')->insertGetId($data);
        if (empty($id)) {
            return false;
        }

        $createdMark = app('request')->input('request_mark', '');

        //备份积分
        $sql = "INSERT INTO `crm_vip_backup_logs`
(customer_id, credit, total_credit,`exp`,vip,created_at,updated_at,created_mark,updated_mark,point_cleared_log_id,store_id)
SELECT customer_id, credit, total_credit,`exp`,vip, ? as created_at,? as updated_at,? as created_mark,? as updated_mark,? as point_cleared_log_id,store_id FROM `crm_customer_info` WHERE `store_id`=? AND `status`=?";
        DB::insert($sql, [$nowTime, $nowTime, $createdMark, $createdMark, $id, $storeId, 1]);


        //备注积分流水已经清空
        $creditWhere = [
            [
                ['ctime', '>=', $startDateTime],
                ['ctime', '<', $divideDateTime],
            ]
        ];
        $creditData = [
            'point_cleared_log_id' => $id,
            'point_cleared_remark' => $nowTime . ' 清空积分',
        ];
        CreditService::update($storeId, $creditWhere, $creditData);

        //汇总 $divideDateTime 以后产生的积分
        $connectionName = CreditService::getModel($storeId, '')->getConnectionName();
        $sql = 'INSERT INTO `crm_vip_reset_logs`
(point_cleared_log_id,customer_id,reset_total_points,reset_points,created_at,updated_at,created_mark,updated_mark)
SELECT 
? as point_cleared_log_id,customer_id,SUM(IF(add_type=1,VALUE,0)) AS total_credit,(SUM(IF(add_type=1,VALUE,0))-SUM(IF(add_type=2,VALUE,0))) AS credit, ? as created_at,? as updated_at,? as created_mark,? as updated_mark 
FROM `crm_credit_logs` 
WHERE ctime >=? AND `status`=? group by customer_id';
        DB::connection($connectionName)->insert($sql, [$id, $nowTime, $nowTime, $createdMark, $createdMark, $divideDateTime, 1]);

        $_where = [
            'store_id' => $storeId,
        ];
        $_data = [
            'total_credit' => 0,
            'credit' => 0,
        ];
        CustomerInfoService::update($storeId, $_where, $_data);

        try {
            $sql = 'UPDATE `ptxcrm`.`crm_customer_info` a,`crm_vip_reset_logs` b 
SET 
a.total_credit=b.reset_total_points,
a.credit=b.reset_points,
a.mtime=b.updated_at,
a.updated_mark=b.updated_mark
WHERE a.customer_id=b.customer_id AND a.status=? AND a.store_id=? and b.point_cleared_log_id=?
;';
            DB::connection($connectionName)->update($sql, [1, $storeId, $id]);
        } catch (\Exception $e) {

            static::resetPoint($storeId, $id); //还原积分
            static::update($storeId, ['id' => $id], ['rs' => 0]);

            LogService::addSystemLog('error', 'exception', 'signup', '周期清空积分失败', [
                'id' => $id,
                'store_id' => $storeId,
                'startDateTime' => $startDateTime,
                'divideDateTime' > $divideDateTime,
                'exception' => $e->getTraceAsString()
            ]); //添加系统日志
        }

        return 1;
    }

}
