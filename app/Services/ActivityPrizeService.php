<?php

/**
 * 抽奖奖品服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use Hyperf\DbConnection\Db as DB;
use Hyperf\Utils\Arr;
use Carbon\Carbon;
use App\Utils\Support\Facades\Cache;
use App\Utils\FunctionHelper;
use App\Constants\Constant;

class ActivityPrizeService extends BaseService {

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @param array $order 排序控制
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $where = [];

        $actId = data_get($params, Constant::DB_TABLE_ACT_ID, 0); //活动id
        $name = data_get($params, 'name', 0); //奖品名称
        $type = data_get($params, 'type', null); //奖品类型
        $prizeAsin = data_get($params, 'asin', 0); //asin
        $country = data_get($params, Constant::DB_TABLE_COUNTRY, ''); //国家
        if ($actId) {//活动id
            $where[] = [Constant::DB_TABLE_ACT_ID, '=', $actId];
        }

        if ($name) {//奖品名称
            $where[] = ['name', 'like', "%$name%"];
        }

        if ($type !== null) {//奖品类型
            $where[] = ['type', '=', $type];
        }

        if ($prizeAsin) {
            $where[] = ['asin', '=', $prizeAsin];
        }

        if ($country) {//国家
            $where[] = [Constant::DB_TABLE_COUNTRY, '=', $country];
        }
        $order = $order ? $order : [['sort', 'DESC']];

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
     * 列表
     * @param array $params 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认:true
     * @param boolean $isPage  是否分页 true:是 false:否 默认:true
     * @param array $select  查询字段
     * @param boolean $isRaw  是否是原生sql true:是 false:否 默认:false
     * @return array 列表数据
     */
    public static function getListData($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $storeId = data_get($params, 'store_id', 0);
        $_data = static::getPublicData($params, data_get($params, 'orderby', []));

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, []);
        $order = data_get($_data, 'order', []);
        $pagination = data_get($_data, 'pagination', []);
        $limit = data_get($pagination, 'page_size', 10);
        $offset = data_get($pagination, 'offset', 0);

        $select = $select ? $select : ['id', 'name', 'img_url', 'mb_img_url', 'url', 'url', 'is_participation_award']; //
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                Constant::DB_EXECUTION_PLAN_BUILDER => null,
                Constant::DB_EXECUTION_PLAN_MAKE => static::getModelAlias(),
                Constant::DB_EXECUTION_PLAN_FROM => '',
                'joinData' => [
                ],
                Constant::DB_EXECUTION_PLAN_SELECT => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                'orders' => $order,
                'offset' => $offset,
                'limit' => $limit,
                'isPage' => $isPage,
                'isOnlyGetCount' => $isOnlyGetCount,
                'pagination' => $pagination,
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
            //'unset' => ['customer_id'],
            ],
            Constant::DB_EXECUTION_PLAN_WITH => [
            ],
            'itemHandleData' => [
            ],
                //Constant::DB_EXECUTION_PLAN_DEBUG => true,
        ];

        $dataStructure = 'list';
        $flatten = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * 添加记录
     * @param int $storeId 商城id
     * @param array $where where条件
     * @param array $data  数据
     * @return int
     */
    public static function insert($storeId, $where, $data) {
        return static::getModel($storeId, '')->updateOrCreate($where, $data); //updateOrCreate：不可以添加主键id的值  updateOrInsert：可以添加主键id的值
    }

    /**
     * 获取选项数据
     * @param int $storeId    商城id
     * @param int $actId      活动id
     * @param string $account 会员账号
     * @param int $page       当前页码
     * @param int $pageSize   每页记录条数
     * @return array
     */
    public static function getItemData($storeId = 0, $actId = 0, $page = 1, $pageSize = 10) {

        //获取活动配置数据，并保存到缓存中
        $tags = config('cache.tags.activity', ['{activity}']);
        $ttl = config('cache.ttl', 86400); //认证缓存时间 单位秒
        $cacheKey = 'prizes:' . md5(json_encode(func_get_args()));
        return Cache::tags($tags)->remember($cacheKey, $ttl, function () use($storeId, $actId, $page, $pageSize) {

                    $publicData = [
                        'store_id' => $storeId,
                        Constant::DB_TABLE_ACT_ID => $actId,
                        'page' => $page,
                        'page_size' => $pageSize,
                        'orderby' => [['sort', 'ASC']],
                    ];
                    return static::getListData($publicData);
                });
    }

    /**
     * 获取中奖概率数据
     * @param mix $probability 概率 单位 %
     * @return array
     */
    public static function getWinProbability($probability) {

        $max = 100;
        if (empty($probability)) {
            return [
                Constant::DB_TABLE_MAX => $max, //概率最大值
                Constant::DB_TABLE_WINNING_VALUE => 0, //概率边界值
            ];
        }

        $multiple = 10;
        while ($probability < 1) {
            $max = $max * $multiple;
            $probability = $probability * $multiple;
        }

        return [
            Constant::DB_TABLE_MAX => $max, //概率最大值
            Constant::DB_TABLE_WINNING_VALUE => $probability, //概率边界值
        ];
    }

    /**
     * 导入活动奖品数据
     * @param int $storeId 商城id
     * @param int $actId   活动id
     * @param string $fileFullPath 文件完整路径
     * @param string $user 上传人
     * @return array 导入结果
     */
    public static function import($storeId, $actId, $fileFullPath, $user) {

        $rs = [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => '导入成功',
            Constant::RESPONSE_DATA_KEY => []
        ];

        $typeData = [
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
        ];

        $actData = [];
        $prizeTypeData = DictService::getListByType('prize_type', 'dict_value', 'dict_key'); //获取奖品类型配置
        //获取活动数据
        $where = [
            Constant::DB_TABLE_PRIMARY => $actId,
        ];
        $actSrcData = ActivityService::existsOrFirst($storeId, '', $where, true, [Constant::DB_TABLE_ACT_TYPE]);
        $countLimit = [
            1 => 8,
        ];

        $connection = static::getModel($storeId, '')->getConnection();
        $connection->beginTransaction();
        try {

            ExcelService::parseExcelFile($fileFullPath, $typeData, function ($row) use ($storeId, $actId, $fileFullPath, $user, $prizeTypeData, &$actData, $actSrcData, $countLimit, &$rs) {
                $sort = data_get($row, 0, Constant::PARAMETER_STRING_DEFAULT);
                if ($sort == '序号' || empty($sort)) {
                    return true;
                }

                $actConfigWhere = [
                    Constant::DB_TABLE_ACTIVITY_ID => $actId, //活动id
                    Constant::DB_TABLE_TYPE => 'winning', //配置类型
                    Constant::DB_TABLE_KEY => 'participation_award_in_winning_log', //配置KEY
                ];
                $actUniqueKey = implode('-', $actConfigWhere);
                if (data_get($actData, $actUniqueKey, null) === null) {
                    //$whether = FunctionHelper::getWhetherData(data_get($row, 1, Constant::WHETHER_NO_VALUE_CN)); //参与奖是否放入中奖列表
                    $actConfigData = [
                        Constant::DB_TABLE_VALUE => 1, //参与奖是否放入中奖列表
                        Constant::DB_TABLE_REMARKS => '参与奖是否放入到中奖列表中 1：是  0：否', //参与奖是否放入中奖列表
                    ];
                    $_actConfigData = ActivityConfigService::updateOrCreate($storeId, $actConfigWhere, $actConfigData);
                    data_set($actData, $actUniqueKey, $_actConfigData, false);
                    unset($actConfigData);
                    unset($_actConfigData);
                }

                /*                 * *****************处理奖品 start********************************** */
                //实物唯一性：类型+asin
                //coupon唯一性：类型+名称+sku
                //其他/礼品卡/活动积分：类型+名称
                $prizeName = data_get($row, 1, ''); //奖品名称
                $prizeType = data_get($prizeTypeData, data_get($row, 2, ''), -1); //奖品类型（礼品卡/coupon/实物/活动积分/其他） 奖品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:活动积分
                $typeValue = data_get($row, 3, Constant::PARAMETER_STRING_DEFAULT); //类型数据
                $qty = data_get($row, 4, 0); //库存
                $prizeSku = data_get($row, 5, ''); //店铺sku
                $probability = trim(data_get($row, 6, '')); //中奖概率 %
                $asin = data_get($row, 7, ''); //asin
                $imgUrl = data_get($row, 8, ''); //商品主图
                $country = data_get($row, 9, '不限');
                $country = $country ? $country : '不限';
                $country = FunctionHelper::getDbCountry($country); //国家
                $isParticipationAward = FunctionHelper::getWhetherData(data_get($row, 10, '否')); //是否为安慰奖

                if (empty($prizeName) || !in_array($prizeType, [0, 1, 2, 3, 5]) || empty($qty) || $probability === '' || empty($imgUrl)) {
                    $code = 2;
                    $msg = '产品名称，产品类型，产品库存，中奖概率，图片链接地址，不得为空，其他不限，产品库存初始化不得为0 ';
                    throw new \Exception($msg, $code);
                }


                $winProbability = static::getWinProbability($probability);
                $where = [
                    Constant::DB_TABLE_ACT_ID => $actId,
                    Constant::DB_TABLE_TYPE => $prizeType,
                ];

                if (in_array($prizeType, [3])) {//如果是 实物 就使用asin作为唯一性判断条件
                    data_set($where, Constant::DB_TABLE_ASIN, $asin);
                } else if (in_array($prizeType, [2])) {//如果是 coupon 就使用 名称+sku 作为唯一性判断条件
                    data_set($where, Constant::DB_TABLE_NAME, $prizeName);
                    data_set($where, Constant::DB_TABLE_SKU, $prizeSku);
                } else {//如果是 礼品卡/活动积分 就使用 名称 作为唯一性判断条件
                    data_set($where, Constant::DB_TABLE_NAME, $prizeName);
                }

                $prizeUniqueKey = 'prize.' . implode('-', $where);
                if (data_get($actData, $prizeUniqueKey, null) === null) {
                    $prizeData = Arr::collapse([static::getWinProbability(0), [
                                    Constant::DB_TABLE_SORT => $sort, //排序
                                    Constant::DB_TABLE_TYPE => $prizeType,
                                    Constant::DB_TABLE_NAME => $prizeName,
                                    Constant::DB_TABLE_SKU => $prizeSku, //sku
                                    Constant::DB_TABLE_UPLOAD_USER => $user,
                                    Constant::DB_TABLE_IS_PARTICIPATION_AWARD => $isParticipationAward,
                                    Constant::DB_TABLE_IMG_URL => $imgUrl, //商品主图
                                    Constant::DB_TABLE_MB_IMG_URL => $imgUrl, //移动端商品主图
                                    Constant::DB_TABLE_ASIN => $asin,
                    ]]);
                    $prizesData = static::updateOrCreate($storeId, $where, $prizeData);
                    data_set($actData, $prizeUniqueKey, $prizesData, false);
                }

                $prizeId = data_get($actData, ($prizeUniqueKey . '.' . Constant::RESPONSE_DATA_KEY . '.' . Constant::DB_TABLE_PRIMARY), null);
                if (empty($prizeId)) {
                    return false;
                }
                /*                 * *****************处理奖品 end********************************** */

                /*                 * **************处理奖品 item start******************** */

                $prizeItemWhere = [
                    Constant::DB_TABLE_PRIZE_ID => $prizeId,
                    Constant::DB_TABLE_COUNTRY => $country, //国家
                ];
                if (in_array($prizeType, [1, 2])) {//如果是 礼品卡/coupon 就使用asin作为唯一性判断条件 奖品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:活动积分
                    data_set($prizeItemWhere, Constant::DB_TABLE_TYPE_VALUE, $typeValue);
                }

                $prizeItem = Arr::collapse([$winProbability, [
                                Constant::DB_TABLE_TYPE => $prizeType,
                                Constant::DB_TABLE_TYPE_VALUE => $typeValue,
                                Constant::DB_TABLE_QTY => $qty, //库存
                                Constant::DB_TABLE_ASIN => $asin,
                                Constant::DB_TABLE_SKU => $prizeSku, //sku
                                Constant::DB_TABLE_NAME => $prizeName, //奖品名字
                ]]);
                ActivityPrizeItemService::updateOrCreate($storeId, $prizeItemWhere, $prizeItem);
            });

            $actType = data_get($actSrcData, Constant::DB_TABLE_ACT_TYPE, null); //活动类型 1:九宫格 2:转盘 3:砸金蛋 4:翻牌 5:邀请好友注册 6:上传图片投票 7:免费评测活动 8:会员deal 9:通用deal
            //获取安慰奖总数
            $participationAwardWhere = [
                Constant::DB_TABLE_ACT_ID => $actId,
                Constant::DB_TABLE_IS_PARTICIPATION_AWARD => 1
            ];
            $count = static::existsOrFirst($storeId, '', $participationAwardWhere);
            if (empty($count)) {
                static::update($storeId, [Constant::DB_TABLE_ACT_ID => $actId, Constant::DB_TABLE_NAME => 'Oops!'], $participationAwardWhere);
            }

            $count = static::existsOrFirst($storeId, '', $participationAwardWhere);
            if (empty($count)) {
                $code = 3;
                $msg = '奖品必须包含产品名称为：Oops! 的奖品';
                throw new \Exception($msg, $code);
            }

            $_countLimit = data_get($countLimit, $actType, null);
            if ($_countLimit !== null) {//如果活动有产品限制，就直接返回
                $count = static::existsOrFirst($storeId, '', [Constant::DB_TABLE_ACT_ID => $actId]);
                if ($count > $_countLimit) {
                    $code = 0;
                    $msg = '九宫格抽奖活动只能放 ' . $_countLimit . ' 个产品，上传产品超过 ' . $_countLimit . ' 个，请删除活动产品后在上传';
                    throw new \Exception($msg, $code); //超过限制
                }
            }

            $prizeIds = data_get($actData, ('prize.*.' . Constant::RESPONSE_DATA_KEY . '.' . Constant::DB_TABLE_PRIMARY), []);
            if ($prizeIds) {
                $data = ActivityPrizeItemService::getModel($storeId)->select([Constant::DB_TABLE_PRIZE_ID, DB::raw('sum(qty) as qty')])->buildWhere([Constant::DB_TABLE_PRIZE_ID => $prizeIds])->groupBy(Constant::DB_TABLE_PRIZE_ID)->pluck('qty', Constant::DB_TABLE_PRIZE_ID); //
                foreach ($data as $prizeId => $qty) {
                    static::update($storeId, [Constant::DB_TABLE_PRIMARY => $prizeId], [Constant::DB_TABLE_QTY => $qty]);
                }
            }

            $connection->commit();
        } catch (\Exception $exc) {
            // 出错回滚
            $connection->rollBack();

            return [
                Constant::RESPONSE_CODE_KEY => $exc->getCode(),
                Constant::RESPONSE_MSG_KEY => $exc->getMessage(),
                Constant::RESPONSE_DATA_KEY => []
            ];
        }

        return $rs;
    }

    /**
     * 获取奖品公共sql
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param string $prizeCountry 奖品国家
     * @return array $dbExecutionPlan
     */
    public static function getPrizeDbExecutionPlan($storeId = 0, $actId = 0, $customerId = 0, $prizeCountry = 'all') {
        $prefix = DB::getConfig(Constant::PREFIX);

        $select = [
            'p.id', 'p.name', 'p.img_url', 'p.mb_img_url', 'p.is_participation_award', 'p.url',
            'p.max', 'p.winning_value',
            'pi.max as item_max', 'pi.winning_value as item_winning_value',
            'p.type', 'p.type_value',
            'pi.type as item_type', 'pi.type_value as item_type_value',
            'p.asin',
            'pi.asin as item_asin',
            'p.qty', 'p.qty_receive',
            'pi.qty as item_qty', 'pi.qty_receive as item_qty_receive',
            'pi.id as item_id',
            'pi.country',
            'p.created_at',
            'p.' . Constant::DB_TABLE_UPDATED_AT,
        ]; //
        $amazonHostData = DictService::getListByType('amazon_host', 'dict_key', 'dict_value');
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                Constant::DB_EXECUTION_PLAN_BUILDER => null,
                Constant::DB_EXECUTION_PLAN_MAKE => static::getModelAlias(),
                Constant::DB_EXECUTION_PLAN_FROM => 'activity_prizes as p',
                'joinData' => [
                    [
                        'table' => 'activity_prize_items as pi',
                        'first' => function ($join) {
                            $join->on([['pi.prize_id', '=', 'p.id']])->where('pi.status', '=', 1);
                        },
                        'operator' => null,
                        'second' => null,
                        'type' => 'left',
                    ],
                ],
                Constant::DB_EXECUTION_PLAN_SELECT => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => [
                    "({$prefix}p.act_id={$actId})",
                ],
                'orders' => [],
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                    Constant::DB_TABLE_MAX => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'item_max{or}max',
                        Constant::RESPONSE_DATA_KEY => '',
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        Constant::DB_EXECUTION_PLAN_GLUE => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => 0,
                    ],
                    Constant::DB_TABLE_WINNING_VALUE => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'item_winning_value{or}winning_value',
                        Constant::RESPONSE_DATA_KEY => '',
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        Constant::DB_EXECUTION_PLAN_GLUE => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => 0,
                    ],
                    'type' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'item_type{or}type',
                        Constant::RESPONSE_DATA_KEY => '',
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        Constant::DB_EXECUTION_PLAN_GLUE => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => 0,
                    ],
                    'type_value' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'item_type_value{or}type_value',
                        Constant::RESPONSE_DATA_KEY => '',
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        Constant::DB_EXECUTION_PLAN_GLUE => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    'qty' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'item_qty{or}qty',
                        Constant::RESPONSE_DATA_KEY => '',
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        Constant::DB_EXECUTION_PLAN_GLUE => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => 0,
                    ],
                    'qty_receive' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'item_qty_receive{or}qty_receive',
                        Constant::RESPONSE_DATA_KEY => '',
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        Constant::DB_EXECUTION_PLAN_GLUE => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    'amazon' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_COUNTRY,
                        Constant::RESPONSE_DATA_KEY => $amazonHostData,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        Constant::DB_EXECUTION_PLAN_GLUE => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => data_get($amazonHostData, 'US', ''),
                    ],
                    'asin' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'item_asin{or}asin',
                        Constant::RESPONSE_DATA_KEY => '',
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        Constant::DB_EXECUTION_PLAN_GLUE => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    'amazon_url' => [//亚马逊链接 asin
                        Constant::DB_EXECUTION_PLAN_FIELD => 'amazon{connection}asin',
                        Constant::RESPONSE_DATA_KEY => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => 'string',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        Constant::DB_EXECUTION_PLAN_GLUE => '/dp/',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                    'url' => [//亚马逊链接
                        Constant::DB_EXECUTION_PLAN_FIELD => 'url{or}amazon_url',
                        Constant::RESPONSE_DATA_KEY => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => 'string',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        Constant::DB_EXECUTION_PLAN_GLUE => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                    ],
                ],
                'unset' => ['amazon', 'item_asin', 'amazon_url', 'item_max', 'item_winning_value', 'item_type', 'item_type_value', 'item_qty', 'item_qty_receive'], //'asin',
            ],
            Constant::DB_EXECUTION_PLAN_WITH => [
            ],
            'itemHandleData' => [
            ],
                //Constant::DB_EXECUTION_PLAN_DEBUG => true,
        ];

        if ($prizeCountry) {
            data_set($dbExecutionPlan, 'parent.where.1', "({$prefix}pi.country='{$prizeCountry}' OR {$prefix}pi.country='all')");
        }

        return $dbExecutionPlan;
    }

    /**
     * 获取抽奖奖品数据
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param string $prizeCountry 奖品国家
     * @param array $activityConfigData 活动配置
     * @return array $data
     */
    public static function getPrizeData($storeId = 0, $actId = 0, $customerId = 0, $prizeCountry = 'all', $extWhere = [], $activityConfigData = []) {

        $dbExecutionPlan = static::getPrizeDbExecutionPlan($storeId, $actId, $customerId, $prizeCountry);

        $prefix = DB::getConfig(Constant::PREFIX);
        $where = data_get($dbExecutionPlan, (Constant::DB_EXECUTION_PLAN_PARENT . Constant::LINKER . Constant::DB_EXECUTION_PLAN_WHERE), []);
        //holife翻牌需求，能多次中coupon
        $winManyPrizes = data_get($activityConfigData, 'winning_win_many_prizes.value', '');
        if (!empty($winManyPrizes)) {
            $where[] = "({$prefix}w.id is null OR {$prefix}p.type in ($winManyPrizes))"; //奖品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:活动积分 6:参与奖
        } else {
            $where[] = "({$prefix}w.id is null)";
        }
        $where[] = "({$prefix}p.qty_receive < {$prefix}p.qty)";
        $where[] = "({$prefix}pi.qty_receive < {$prefix}pi.qty)";
        $where = Arr::collapse([$where, $extWhere]);
        data_set($dbExecutionPlan, (Constant::DB_EXECUTION_PLAN_PARENT . Constant::LINKER . Constant::DB_EXECUTION_PLAN_WHERE), $where);

        $orders = [['pi.winning_value', 'DESC']];
        data_set($dbExecutionPlan, 'parent.orders', $orders);

        $joinData = data_get($dbExecutionPlan, 'parent.joinData', []);
        $joinData[] = [
            'table' => 'activity_winning_logs as w',
            'first' => function ($join) use($actId, $customerId) {
                $join->on([['w.prize_id', '=', 'p.id'],])->where([['w.act_id', '=', $actId], ['w.customer_id', '=', $customerId], ['w.status', '=', 1]]);
            },
            'operator' => null,
            'second' => null,
            'type' => 'left',
        ];
        data_set($dbExecutionPlan, 'parent.joinData', $joinData);

        $dataStructure = 'list';
        $flatten = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);
    }

    /**
     * 获取指定奖品数据
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param string $prizeCountry 奖品国家
     * @param array $where 指定奖品条件
     * @param int|null $limit 获取记录条数 默认：null
     * @return array $data
     */
    public static function getData($storeId = 0, $actId = 0, $customerId = 0, $prizeCountry = 'all', $where = [], $limit = null) {
        $dbExecutionPlan = static::getPrizeDbExecutionPlan($storeId, $actId, $customerId, $prizeCountry);

        $publicWhere = data_get($dbExecutionPlan, (Constant::DB_EXECUTION_PLAN_PARENT . Constant::LINKER . Constant::DB_EXECUTION_PLAN_WHERE), []);
        data_set($dbExecutionPlan, (Constant::DB_EXECUTION_PLAN_PARENT . Constant::LINKER . Constant::DB_EXECUTION_PLAN_WHERE), Arr::collapse([$publicWhere, $where]));
        data_set($dbExecutionPlan, 'parent.limit', $limit);

        $dataStructure = 'one';
        $flatten = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);
    }

    public static function getPrizes($storeId, $actId, $type = 2) {
        //static
    }

    /**
     * 获取抽奖奖品数据
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param int $customerId 会员id
     * @param string $prizeCountry 奖品国家
     * @param array $activityConfigData 活动配置
     * @return array $data
     */
    public static function getCreditPrizeData($storeId = 0, $actId = 0, $customerId = 0, $prizeCountry = 'all', $extWhere = [], $activityConfigData = []) {

        $dbExecutionPlan = static::getPrizeDbExecutionPlan($storeId, $actId, $customerId, $prizeCountry);

        $prefix = DB::getConfig(Constant::PREFIX);
        $where = data_get($dbExecutionPlan, (Constant::DB_EXECUTION_PLAN_PARENT . Constant::LINKER . Constant::DB_EXECUTION_PLAN_WHERE), []);

        $where[] = "({$prefix}p.qty_receive < {$prefix}p.qty)";
        $where[] = "({$prefix}pi.qty_receive < {$prefix}pi.qty)";
        $where = Arr::collapse([$where, $extWhere]);
        data_set($dbExecutionPlan, (Constant::DB_EXECUTION_PLAN_PARENT . Constant::LINKER . Constant::DB_EXECUTION_PLAN_WHERE), $where);

        $orders = [['pi.winning_value', 'DESC']];
        data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT . Constant::LINKER . 'orders', $orders);

        $dataStructure = 'list';
        $flatten = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);
    }

}
