<?php

/**
 * 红人系统服务
 * User: Bo
 * Date: 2019-10-17
 * Time: 16:50
 */

namespace App\Services\Redman;

use Hyperf\Utils\Arr;
use Carbon\Carbon;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;

class InfluencerService extends BaseService {

    use GetDefaultConnectionModel;

    /**
     * 获取模型别名
     * @return string
     */
//    public static function getModelAlias() {
//        return 'Influencer';
//    }

    /**
     * 红人系统申请表单提交
     * @param int $username 用户名称
     * @param string $email 用户邮箱
     * @param string $country 用户国家
     * @param string $blogLink 社媒链接
     * @param string $blogDescription 社媒描述
     * @param string $otherSocial 其他社媒
     * @return int 插入记录的ID
     */
    public static function add($platform, $username, $email, $country, $blogLink, $blogDescription, $otherSocial) {
        $ip = FunctionHelper::getClientIP();
        $created_at = Carbon::now()->toDateTimeString();
        $where = [
            'platform' => $platform,
            'username' => $username,
            'email' => $email,
            'country' => $country,
            'social_link' => $blogLink,
            'social_description' => $blogDescription,
            'other_social' => $otherSocial
        ];
        $update = [
            'ip' => $ip,
            'created_at' => $created_at
        ];
        //updateOrInsert()首先尝试使用第一个参数的键和值对来查找匹配的数据库记录。 如果记录存在，则使用第二个参数中的值去更新记录。 如果找不到记录，将插入一个新记录，更新的数据是两个数组的集合
        $id = static::getModel(0, '', [])->updateOrInsert($where, $update);
        return $id;
    }

    /**
     * 获取db query
     * @param int $storeId 商城id
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getQuery($where = []) {
        $query = static::getModel(0, '', [])->buildWhere($where);
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
        $platform = $params['platform'] ?? ''; //平台
        $username = $params['username'] ?? ''; //用户名
        $email = $params['email'] ?? ''; //邮箱
        $country = $params['country'] ?? ''; //国家
        $startTime = $params['start_time'] ?? ''; //开始时间
        $endTime = $params['end_time'] ?? ''; //结束时间

        if (strlen($platform)) {
            $where[] = ['platform', '=', $platform];
        }

        if ($username) {
            $where[] = ['username', '=', $username];
        }

        if ($email) {
            $where[] = ['email', '=', $email];
        }

        if ($country) {
            $where[] = ['country', 'like', '%' . $country . '%'];
        }

        if ($startTime) {
            $where[] = ['created_at', '>=', $startTime];
        }

        if ($endTime) {
            $where[] = ['created_at', '<=', $endTime];
        }

        $_where = [];
        if (data_get($params, 'id', 0)) {
            $_where['id'] = $params['id'];
        }

        if ($where) {
            $_where[] = $where;
        }

        $order = $order ? $order : ['id', 'DESC'];
        return Arr::collapse([parent::getPublicData($params, $order), [
                        'where' => $_where,
        ]]);
    }

    /**
     * 红人系统申请表单列表
     * @param array $params 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认:true
     * @param boolean $isPage  是否分页 true:是 false:否 默认:true
     * @param array $select  查询字段
     * @param boolean $isRaw 是否原始 select true:是 false:否 默认:false
     * @param boolean $isGetQuery 是否获取 query
     * @param boolean $isOnlyGetCount 是否仅仅获取总记录数
     * @return array|\Hyperf\Database\Model\Builder 列表数据|Builder
     */
    public static function getDataList($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $_data = static::getPublicData($params);

        $where = data_get($_data, 'where', []);
        $order = data_get($params, 'orderBy', data_get($_data, 'order', []));
        $pagination = data_get($_data, 'pagination', []);
        $limit = data_get($params, 'limit', data_get($pagination, 'page_size', 10));
        $offset = data_get($params, 'offset', data_get($pagination, 'offset', 0));

        $select = ['id', 'platform', 'username', 'email', 'country', 'social_link', 'social_description', 'other_social', 'ip', 'created_at'];
        $platform = [
            0 => "未选择",
            1 => "Facebook",
            2 => "Twitter",
            3 => "YouTube",
            4 => 'Instagram',
            5 => 'Blog'
        ];
        $dbExecutionPlan = [
            'parent' => [
                'setConnection' => true,
                'storeId' => 'default_connection_redman',
                'builder' => null,
                'make' => 'Influencer',
                'from' => '',
                'select' => $select,
                'where' => $where,
                'orders' => [$order],
                'offset' => $offset,
                'limit' => $limit,
                'isPage' => $isPage,
                'isOnlyGetCount' => $isOnlyGetCount,
                'pagination' => $pagination,
                'handleData' => [
                    'platform' => [
                        'field' => 'platform',
                        'data' => $platform,
                        'dataType' => '',
                        'dateFormat' => '',
                        'glue' => '',
                        'default' => data_get($platform, '0', ''),
                    ],
                ],
            ],
                //'sqlDebug' => true,
        ];
        $dataStructure = 'list';
        $flatten = true;
        $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);

        return $data;
    }

}
