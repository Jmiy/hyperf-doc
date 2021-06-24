<?php

/**
 * 积分服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use Hyperf\DbConnection\Db as DB;
use Hyperf\Utils\Arr;
use Carbon\Carbon;
use App\Models\Role;
use App\Models\RolePermission;
use App\Utils\FunctionHelper;

class RoleService extends BaseService {

    /**
     * 获取db query
     * @param int $storeId 商城id
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getQuery($storeId, $where = []) {
        $query = Role::where($where)->with('permissions.parents.parents');
        return $query;
    }

    /**
     * 检查是否存在
     * @param int $id 角色id
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

        $query = Role::where($where);

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

        $name = $params['name'] ?? ''; //名称
        $storeId = $params['store_id'] ?? ''; //商城id

        if ($storeId) {
            $where[] = ['store_id', '=', $storeId];
        }

        if ($name) {
            $where[] = ['name', '=', $name];
        }

        $order = $order ? $order : ['id', 'DESC'];
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
        foreach ($data['data'] as $key => $row) {

            $field = [
                'field' => 'status',
                'data' => $statusData,
                'dataType' => '',
                'default' => $data['data'][$key]['status'],
            ];
            $data['data'][$key]['status'] = FunctionHelper::handleData($row, $field);
        }

        return $data;
    }

    /**
     * 添加记录
     * @param array $where where条件
     * @param array $data  数据
     * @param array $permissionData  权限数据
     * @return int
     */
    public static function insert($where, $data, $permissionData = []) {
        $nowTime = Carbon::now()->toDateTimeString();

        $data['created_at'] = DB::raw("IF(created_at='2019-01-01 00:00:00','$nowTime',created_at)");
        $data['updated_at'] = $nowTime;

//        \Illuminate\Support\Facades\DB::enableQueryLog();
//        var_dump(\Illuminate\Support\Facades\DB::getQueryLog());
        if ($where) {
            $id = Arr::get($where, 'id', 0); //角色id
            Role::where($where)->update($data); //updateOrCreate：不可以添加主键id的值  updateOrInsert：可以添加主键id的值
            if ($id) {
                RolePermission::where('role_id', $id)->delete();
            }
        } else {
            $id = Role::insertGetId($data); //updateOrCreate：不可以添加主键id的值  updateOrInsert：可以添加主键id的值
        }

        $rolePermissionData = [];
        foreach ($permissionData as $permissionId => $item) {
            $rolePermissionData[] = [
                'role_id' => $id,
                'permission_id' => $permissionId,
                'select' => $item['select'],
                'update' => $item['update'],
            ];
        }
        RolePermission::insert($rolePermissionData);


        return $id;
    }

    /**
     * 删除记录
     * @param int $storeId 商城id
     * @param array $ids id
     * @return int 删除的记录条数
     */
    public static function delete($storeId, $ids) {
//        $connection = Role::getConnection();
//        $connection->enableQueryLog();
//        var_dump($connection->getQueryLog());

        $id = Role::whereIn('id', $ids)->delete(); //->where('store_id',$storeId)

        RolePermission::whereIn('role_id', $ids)->delete();

        return $id;
    }

    /**
     * 下拉选择
     * @param int $storeId 商城id
     * @return int
     */
    public static function select($storeId) {

        $where = [
            'store_id' => $storeId,
        ];
        $data = static::getQuery($storeId, $where)->select(['id', 'name'])->get(); //->where('store_id',$storeId)

        return $data;
    }

}
