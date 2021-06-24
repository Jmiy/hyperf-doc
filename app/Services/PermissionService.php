<?php

/**
 * 权限服务
 * User: Jmiy
 * Date: 2019-08-21
 * Time: 16:50
 */

namespace App\Services;

use Hyperf\DbConnection\Db as DB;
use Hyperf\Utils\Arr;
use Carbon\Carbon;
use App\Models\Permission;
use App\Utils\FunctionHelper;

class PermissionService extends BaseService {

    /**
     * 检查是否存在
     * @param int $id 权限id
     * @param bool $getData 是否获取数据 true:是 false:否 默认:false
     * @return mixed|static
     */
    public static function exists($id = 0, $getData = false) {
        $where = [];

        if ($id) {
            $where['id'] = $id;
        }

        if (empty($where)) {
            return $getData ? [] : true;
        }

        $query = Permission::where($where);

        if ($getData) {
            $rs = $query->first();
        } else {
            $rs = $query->exists();
        }

        return $rs;
    }

    /**
     * 获取db query
     * @param int $storeId 商城id
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getQuery($storeId, $where = []) {
        $query = Permission::where($where);
        return $query;
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @param array $order 排序控制
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $where = [];

        $name = $params['name'] ?? ''; //名称
        if ($name) {
            $where[] = ['name', '=', $name];
        }

        $order = $order ? $order : ['updated_at', 'asc'];
        return Arr::collapse([parent::getPublicData($params, $order), [
                        'where' => $where,
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

        $where = $_data['where'];
        $order = $_data['order'];
        $pagination = $_data['pagination'];
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
                'pagination' => $pagination,
            ];
        }

        $query = $query->orderBy($order[0], $order[1]);
        $data = [
            'query' => $query,
            'pagination' => $pagination,
        ];

        //static::createModel($storeId, 'VoteItem')->getConnection()->enableQueryLog();
        //var_dump(static::createModel($storeId, 'VoteItem')->getConnection()->getQueryLog());
        $select = $select ? $select : ['*'];
        $data = static::getList($data, $toArray, $isPage, $select, $isRaw, $isGetQuery);

        if ($isGetQuery) {
            return $data;
        }

        $statusData = DictService::getListByType('status', 'dict_key', 'dict_value');
        $typeData = DictService::getListByType('permission_type', 'dict_key', 'dict_value');
        $isShowData = DictService::getListByType('is_show', 'dict_key', 'dict_value');
        foreach ($data['data'] as $key => $row) {

            $field = [
                'field' => 'status',
                'data' => $statusData,
                'dataType' => '',
                'default' => $data['data'][$key]['status'],
            ];
            $data['data'][$key]['status'] = FunctionHelper::handleData($row, $field);

            $field = [
                'field' => 'type',
                'data' => $typeData,
                'dataType' => '',
                'default' => $data['data'][$key]['type'],
            ];
            $data['data'][$key]['type'] = FunctionHelper::handleData($row, $field);

            $field = [
                'field' => 'is_show',
                'data' => $isShowData,
                'dataType' => '',
                'default' => $data['data'][$key]['is_show'],
            ];
            $data['data'][$key]['is_show'] = FunctionHelper::handleData($row, $field);
        }

        return $data;
    }

    /**
     * 添加记录
     * @param array $where where条件
     * @param array $data  数据
     * @return int
     */
    public static function insert($where, $data) {
        $nowTime = Carbon::now()->toDateTimeString();

        $data['created_at'] = DB::raw("IF(created_at='2019-01-01 00:00:00','$nowTime',created_at)");
        $data['updated_at'] = $nowTime;

//        \Illuminate\Support\Facades\DB::enableQueryLog();
//        var_dump(\Illuminate\Support\Facades\DB::getQueryLog());
        if ($where) {
            $id = Permission::where($where)->update($data); //updateOrCreate：不可以添加主键id的值  updateOrInsert：可以添加主键id的值
        } else {
            $id = Permission::insert($data); //updateOrCreate：不可以添加主键id的值  updateOrInsert：可以添加主键id的值
        }


        return $id;
    }

    /**
     * 删除记录
     * @param int $storeId 商城id
     * @param array $ids id
     * @return int 删除的记录条数
     */
    public static function delete($storeId, $ids) {
//        $connection = Permission::getConnection();
//        $connection->enableQueryLog();
//        var_dump($connection->getQueryLog());

        $id = Permission::whereIn('id', $ids)->delete();

        return $id;
    }

    /**
     * 下拉数据
     * @param int $storeId 商城id
     * @param array $ids id
     * @return int
     */
    public static function select($storeId, $parentId, $keyField = null, $valueField = null) {
//        $connection = Permission::getConnection();
//        $connection->enableQueryLog();
//        var_dump($connection->getQueryLog());

        return Permission::select(['id', 'name'])->where('parent_id', $parentId)->get()->pluck($valueField, $keyField);
    }

}
