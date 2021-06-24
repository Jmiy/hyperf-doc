<?php

/**
 * 活动产品服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use Guzzle\Plugin\Backoff\ConstantBackoffStrategy;
use Hyperf\DbConnection\Db as DB;
use Hyperf\Utils\Arr;
use Carbon\Carbon;
use App\Utils\FunctionHelper;
use App\Constants\Constant;
use App\Utils\Cdn\CdnManager;

class ActivityProductService extends BaseService {

    public static $country_list = [
        '1' => ["US", "CA", "UK", "IT", "DE", "ES", "FR", "Other"],
        '2' => ["GB", "US", "CA", "UK", "DE", "FR", "IT", "ES", "MX", "JP", "AU", "Other"],
    ];
    public static $countryMap = [
        1 => [
            "GB" => "UK",
            "US" => "US",
            "CA" => "CA",
            "UK" => "UK",
            "IT" => "IT",
            "DE" => "DE",
            "ES" => "ES",
            "FR" => "FR",
            "Other" => "US",
        ],
        2 => [
            "GB" => "UK",
            "US" => "US",
            "CA" => "CA",
            "UK" => "UK",
            "DE" => "DE",
            "FR" => "FR",
            "IT" => "IT",
            "ES" => "ES",
            "MX" => "MX",
            "JP" => "US",
            "AU" => "US",
            "Other" => "US",
        ],
    ];

    public static $productStatus = [
        '未启用',
        '启用',
        '过期'
    ];

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
     * 判断是否存在
     * @param int $storeId 商城id
     * @param int $id 产品id
     * @param int $actId 活动id
     * @param string $sku sku
     * @param boolean $getData 是否获取数据  true：是 false：否 默认：false
     * @return array|boolean
     */
    public static function exists($storeId = 1, $id = 0, $actId = 0, $sku = Constant::PARAMETER_STRING_DEFAULT, $getData = false) {
        $where = [];

        if ($id) {
            $where[Constant::DB_TABLE_PRIMARY] = $id;
        }

        if ($actId) {
            $where[Constant::DB_TABLE_ACT_ID] = $actId;
        }

        if ($sku) {
            $where[Constant::DB_TABLE_SKU] = $sku;
        }
        return static::existsOrFirst($storeId, Constant::PARAMETER_STRING_DEFAULT, $where, $getData);
    }

    /**
     * 获取公共sql
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param array $order 排序数据
     * @param int $offset  分页数据的起始位置
     * @param int $limit   记录条数
     * @param boolean $isPage 是否分页 true:是 false:否
     * @param array $pagination 分页数据
     * @return array $dbExecutionPlan
     */
    public static function getDbExecutionPlan($storeId = 0, $actId = 0, $order = [], $offset = null, $limit = null, $isPage = false, $pagination = []) {
        $prefix = DB::getConfig('prefix');
        $select = [
            'p.id', 'p.name', 'p.img_url', 'p.mb_img_url', 'p.url', 'p.help_sum',
            'p.type', 'p.type_value',
            'pi.type as item_type', 'pi.type_value as item_type_value',
            'p.asin',
            'pi.asin as item_asin',
            'p.qty', 'p.qty_apply',
            'pi.qty as item_qty', 'pi.qty_apply as item_qty_apply',
            'pi.id as product_item_id',
            'pi.country as item_country', 'p.country',
        ]; //
        $amazonHostData = DictService::getListByType(Constant::AMAZON_HOST, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                Constant::DB_EXECUTION_PLAN_BUILDER => null,
                Constant::DB_EXECUTION_PLAN_MAKE => static::getModelAlias(),
                Constant::DB_EXECUTION_PLAN_FROM => 'activity_products as p',
                'joinData' => [
                    [
                        Constant::DB_EXECUTION_PLAN_TABLE => 'activity_product_items as pi',
                        Constant::DB_EXECUTION_PLAN_FIRST => function ($join) {
                            $join->on([['pi.product_id', '=', 'p.id']])->where('pi.status', '=', 1);
                        },
                        Constant::DB_TABLE_OPERATOR => null,
                        Constant::DB_EXECUTION_PLAN_SECOND => null,
                        'type' => 'left',
                    ],
                ],
                Constant::DB_EXECUTION_PLAN_SELECT => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => [],
                Constant::DB_EXECUTION_PLAN_ORDERS => $order,
                Constant::DB_EXECUTION_PLAN_OFFSET => $offset,
                Constant::DB_EXECUTION_PLAN_LIMIT => $limit,
                Constant::DB_EXECUTION_PLAN_IS_PAGE => $isPage,
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                    'type' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'item_type{or}type',
                        Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => 0,
                    ],
                    'type_value' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'item_type_value{or}type_value',
                        Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT,
                    ],
                    Constant::DB_TABLE_QTY => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'item_qty{or}qty',
                        Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => 0,
                    ],
                    'qty_apply' => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'item_qty_apply{or}qty_apply',
                        Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT,
                    ],
                    Constant::PLATFORM_AMAZON => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'country{or}item_country',
                        Constant::RESPONSE_DATA_KEY => $amazonHostData,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => data_get($amazonHostData, 'US', Constant::PARAMETER_STRING_DEFAULT),
                    ],
                    Constant::DB_TABLE_ASIN => [
                        Constant::DB_EXECUTION_PLAN_FIELD => 'item_asin{or}asin',
                        Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT,
                    ],
                    Constant::DB_TABLE_AMAZON_URL => [//亚马逊链接 asin
                        Constant::DB_EXECUTION_PLAN_FIELD => (Constant::PLATFORM_AMAZON . Constant::DB_EXECUTION_PLAN_CONNECTION . Constant::DB_TABLE_ASIN),
                        Constant::RESPONSE_DATA_KEY => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => '/dp/',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT,
                    ],
                    'url' => [//亚马逊链接
                        Constant::DB_EXECUTION_PLAN_FIELD => 'url{or}amazon_url',
                        Constant::RESPONSE_DATA_KEY => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT,
                    ],
                ],
                Constant::DB_EXECUTION_PLAN_UNSET => [Constant::PLATFORM_AMAZON, Constant::DB_TABLE_ASIN, 'item_asin', Constant::DB_TABLE_AMAZON_URL, 'item_type', 'item_type_value', 'item_qty', 'item_qty_apply'],
            ],
            'with' => [
            ],
            'itemHandleData' => [
            ],
                //'sqlDebug' => true,
        ];

        if ($actId) {
            data_set($dbExecutionPlan, 'parent.where.0', "({$prefix}p.act_id={$actId})");
        }

        return $dbExecutionPlan;
    }

    /**
     * 获取可以申请的产品数据
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param string $productCountry 产品国家
     * @param array $extWhere 扩展where
     * @param string $dataStructure 数据类型
     * @param array $order 排序数据
     * @param int $offset  分页数据的起始位置
     * @param int $limit   记录条数
     * @param boolean $isPage 是否分页 true:是 false:否
     * @param array $pagination 分页数据
     * @return array
     */
    public static function getMayApplyProduct($storeId = 0, $actId = 0, $productCountry = Constant::PARAMETER_STRING_DEFAULT, $extWhere = [], $dataStructure = 'list', $order = [], $offset = null, $limit = null, $isPage = false, $pagination = []) {

        $dbExecutionPlan = static::getDbExecutionPlan($storeId, $actId, $order, $offset, $limit, $isPage, $pagination);

        $prefix = DB::getConfig('prefix');
        $where = data_get($dbExecutionPlan, (Constant::DB_EXECUTION_PLAN_PARENT . Constant::LINKER . Constant::DB_EXECUTION_PLAN_WHERE), []);
        $where[] = "({$prefix}p.qty_apply < {$prefix}p.qty)";
        $where[] = "({$prefix}pi.qty_apply < {$prefix}pi.qty)";

        $productCountryWhere = [];
        if ($productCountry) {
            $productCountryWhere[] = "{$prefix}pi.country='{$productCountry}'";
        }
        $productCountryWhere[] = "{$prefix}pi.country='all'";
        $productCountryWhere = implode(' OR ', $productCountryWhere);
        $where[] = "({$productCountryWhere})";

        $where = Arr::collapse([$where, $extWhere]);
        data_set($dbExecutionPlan, (Constant::DB_EXECUTION_PLAN_PARENT . Constant::LINKER . Constant::DB_EXECUTION_PLAN_WHERE), $where);

        return FunctionHelper::getResponseData(null, $dbExecutionPlan, false, false, $dataStructure);
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @param array $order 排序控制
     * @return array
     */
    public static function getPublicData($params, $order = []) {

        $where = [];

        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);
        $sku = $params[Constant::DB_TABLE_SKU] ?? Constant::PARAMETER_STRING_DEFAULT; //sku
        $name = $params[Constant::DB_TABLE_NAME] ?? Constant::PARAMETER_STRING_DEFAULT; //名称
        $country = $params[Constant::DB_TABLE_COUNTRY] ?? Constant::PARAMETER_STRING_DEFAULT; //国家
        $actId = $params[Constant::DB_TABLE_ACT_ID] ?? Constant::PARAMETER_STRING_DEFAULT; //活动id
        $type = $params[Constant::DB_TABLE_MB_TYPE] ?? Constant::PARAMETER_INT_DEFAULT; //模板类型
        $productStatus = $params[Constant::DB_TABLE_PRODUCT_STATUS] ?? -1; //产品状态
        $startTime = $params['start_time'] ?? Constant::PARAMETER_STRING_DEFAULT; //创建开始时间
        $endTime = $params['end_time'] ?? Constant::PARAMETER_STRING_DEFAULT; //创建结束时间
        $asin = $params[Constant::DB_TABLE_ASIN] ?? Constant::PARAMETER_STRING_DEFAULT; //asin
        $categoryId = $params['category_id'] ?? ''; //商品类目
        $inStock = $params['in_stock'] ?? ''; //是否有货
        $isPrize = data_get($params, Constant::DB_TABLE_IS_PRIZE, null); //投票活动时 是否活动奖品 1:是 0:否
        $metafields = $params['meta_fields'] ?? ''; //属性
        $expireStartTime = $params['expire_start_time'] ?? Constant::PARAMETER_STRING_DEFAULT; //截止时间开始时间
        $expireEndTime = $params['expire_end_time'] ?? Constant::PARAMETER_STRING_DEFAULT; //截止时间结束时间
        $businessType = $params['business_type'] ?? Constant::PARAMETER_STRING_DEFAULT; //产品业务类型
        $source = $params[Constant::DB_TABLE_SOURCE] ?? ''; //接口请求来源
        $productCountry = $params['product_country'] ?? []; //产品国家

        $ap = data_get($params, 'tableAlias.actProduct', '');

        //评测2.0接口会传参数business_type = 1,C端接口评测产品列表条件
        $freeTestCustomizeWhere = [];
        if ($businessType == 1 && $source == 'api') {
            !empty($productCountry) && !is_array($productCountry) && $productCountry = [$productCountry];
            if (!empty($productCountry)) {
                foreach ($productCountry as &$value) {
                    $value = "'$value'";
                }
                $productCountries = implode(",", $productCountry);
                $freeTestCustomizeWhere[] = [
                    Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                    Constant::PARAMETERS_KEY => function ($query) use ($productCountries, $storeId) {
                        $query->whereRaw("EXISTS(select 1 from `ptxcrm`.`crm_metafields` WHERE store_id = $storeId and owner_resource = 'ActivityProduct' and owner_id = `crm_activity_products`.`id` and namespace = 'free_testing' and `key` = 'country' and status = 1 and `value` IN ($productCountries))");
                    },
                ];
            }

            $country = null;
            $actId = Constant::PARAMETER_STRING_DEFAULT;
            $currentTime = date('Y-m-d H:i:s');
            if ($productStatus == 1) {
                //启用中，未申请完，未过期
                $where[] = [$ap . Constant::DB_TABLE_PRODUCT_STATUS, '=', $productStatus];
                $where[] = [$ap . Constant::DB_TABLE_QTY, '>', DB::raw('show_apply')];
                $freeTestCustomizeWhere[] = [
                    Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                    Constant::PARAMETERS_KEY => function ($query) use ($currentTime) {
                        $query->whereRaw("expire_time >= '$currentTime' or expire_time = '2000-01-01 00:00:00'");
                    },
                ];
            } elseif ($productStatus == 2) {
                $freeTestCustomizeWhere[] = [
                    Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                    Constant::PARAMETERS_KEY => function ($query) use ($currentTime) {
                        $query->whereRaw("(product_status = 1 and qty <= show_apply) or product_status = 2 or (product_status != 0 and expire_time < '$currentTime' and expire_time != '2000-01-01 00:00:00')");
                    },
                ];
            }
            $productStatus = -1;
        }
        //评测2.0接口会传参数business_type = 1,后台接口评测产品列表条件
        if ($businessType == 1 && $source == 'admin') {
            $currentTime = date('Y-m-d H:i:s');
            if ($sku) {
                $freeTestCustomizeWhere[] = [
                    Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                    Constant::PARAMETERS_KEY => function ($query) use ($sku) {
                        $query->whereRaw("`sku` like '%$sku%' or `shop_sku` like '%$sku%'");
                    },
                ];
                $sku = '';
            }
            if ($productStatus == 1) {
                //启用中，未过期
                $where[] = [$ap . Constant::DB_TABLE_PRODUCT_STATUS, '=', $productStatus];
                $freeTestCustomizeWhere[] = [
                    Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                    Constant::PARAMETERS_KEY => function ($query) use ($currentTime) {
                        $query->whereRaw("expire_time >= '$currentTime' or expire_time = '2000-01-01 00:00:00'");
                    },
                ];
            } elseif ($productStatus == 2) {
                $freeTestCustomizeWhere[] = [
                    Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                    Constant::PARAMETERS_KEY => function ($query) use ($currentTime) {
                        $query->whereRaw("product_status = 2 or (expire_time < '$currentTime' and expire_time != '2000-01-01 00:00:00')");
                    },
                ];
            }
            $productStatus = -1;
        }

        if (!empty($metafields)) {
           foreach ($metafields as $metafield) {
               if (!empty($metafield[Constant::DB_TABLE_KEY]) && !empty($metafield[Constant::DB_TABLE_VALUE]) && $metafield[Constant::DB_TABLE_KEY] == Constant::DB_TABLE_COUNTRY) {
                   $_country = data_get($metafield, 'value.0', Constant::PARAMETER_STRING_DEFAULT);
                   if (!empty($_country)) {
                       $freeTestCustomizeWhere[] = [
                           Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                           Constant::PARAMETERS_KEY => function ($query) use ($_country) {
                               $query->whereRaw("(exists (select 1 from `ptxcrm`.`crm_metafields` as crm_m where `crm_m`.`owner_id` = `crm_activity_products`.`id` and `crm_m`.`owner_resource` = 'ActivityProduct' and `crm_m`.`key` = 'country' and `crm_m`.`value` in ('$_country') and `crm_m`.`status` = 1 limit 1) OR crm_activity_products.country = '$_country')");
                           },
                       ];
                   }
               }
           }
        }

        if ($actId !== Constant::PARAMETER_STRING_DEFAULT) {
            $where[] = [$ap . Constant::DB_TABLE_ACT_ID, '=', $actId];
        }

        if ($sku) {
            $where[] = [$ap . Constant::DB_TABLE_SKU, 'like', '%' . $sku . '%'];
        }

        if ($name) {
            $where[] = [$ap . Constant::DB_TABLE_NAME, '=', $name];
        }

        if ($country) {
            $where[] = [$ap . Constant::DB_TABLE_COUNTRY, '=', $country];
        }

        if ($type) {//模板类型
            $where[] = [$ap . Constant::DB_TABLE_MB_TYPE, '=', $type];
        }

        if ($productStatus != -1) {//产品状态
            $where[] = [$ap . Constant::DB_TABLE_PRODUCT_STATUS, '=', $productStatus];
        }

        if ($startTime) {
            $where[] = [$ap . Constant::DB_TABLE_CREATED_AT, '>=', $startTime];
        }

        if ($endTime) {
            $where[] = [$ap . Constant::DB_TABLE_CREATED_AT, '<=', $endTime];
        }
        if ($asin) {
            $where[] = [$ap . Constant::DB_TABLE_ASIN, 'like', '%' . $asin . '%'];
        }

        if ($categoryId !== '') {
            $where[] = [$ap . 'category_id', '=', $categoryId];
        }

        if ($inStock !== '') {
            $where[] = [$ap . 'in_stock', '=', $inStock];
        }

        if ($isPrize !== null) {
            $where[] = [$ap . Constant::DB_TABLE_IS_PRIZE, '=', "{$isPrize}"];
        }

        if ($expireStartTime) {
            $where[] = [$ap . Constant::EXPIRE_TIME, '>=', $expireStartTime];
        }

        if ($expireEndTime) {
            $where[] = [$ap . Constant::EXPIRE_TIME, '<=', $expireEndTime];
        }

        if ($businessType) {
            $where[] = [$ap . Constant::BUSINESS_TYPE, '=', $businessType];
        }

        $customizeWhere = [];
        if (!empty($metafields)) {
            //$customizeWhere = MetafieldService::buildCustomizeWhere($storeId, '', $metafields, 'activity_products.id');
        }

        $_where = [];
        if (data_get($params, Constant::DB_TABLE_PRIMARY, 0)) {
            $_where[$ap . Constant::DB_TABLE_PRIMARY] = $params[Constant::DB_TABLE_PRIMARY];
        }

        if ($where) {
            $_where[] = $where;
        }
        (!empty($customizeWhere) || !empty($freeTestCustomizeWhere)) && $_where['{customizeWhere}'] = array_merge($customizeWhere, $freeTestCustomizeWhere);

        $order = $order ? $order : [$ap . Constant::DB_TABLE_PRIMARY, 'DESC'];
        return Arr::collapse([parent::getPublicData($params, $order), [
            Constant::DB_EXECUTION_PLAN_WHERE => $_where,
        ]]);
    }

    /**
     * 客户端产品列表
     * @param int $storeId 品牌店铺id
     * @param int $actId 活动id
     * @param string $country  国家
     * @param int $customerId  会员id
     * @param int $page 分页页码
     * @param int $pageSize 每一页记录条数
     * @param array $extData 扩展参数
     * @return array|\Hyperf\Database\Model\Builder 列表数据|Builder
     */
    public static function getItemData($storeId, $actId, $country, $customerId, $page, $pageSize, $extData = []) {
        static::updateProductActId($storeId, $actId);
        $params = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_COUNTRY => $country,
            Constant::REQUEST_PAGE => $page,
            Constant::REQUEST_PAGE_SIZE => $pageSize,
        ];

        if (isset($extData[Constant::DB_TABLE_IS_PRIZE])) {
            data_set($params, Constant::DB_TABLE_IS_PRIZE, $extData[Constant::DB_TABLE_IS_PRIZE]);
        }

        $_data = static::getPublicData($params);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, []);
        $order = data_get($params, Constant::DB_EXECUTION_PLAN_ORDER_BY, ['sort', 'ASC']);
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::DB_EXECUTION_PLAN_LIMIT, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0));

        $amazonHostData = DictService::getListByType(Constant::AMAZON_HOST, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);

        $select = [
            Constant::DB_TABLE_PRIMARY,
            Constant::DB_TABLE_NAME,
            'sub_name',
            Constant::DB_TABLE_DES,
            Constant::DB_TABLE_QTY,
            'qty_apply',
            'is_recommend',
            Constant::DB_TABLE_IMG_URL,
            Constant::DB_TABLE_MB_IMG_URL,
            'type',
            'url',
            Constant::DB_TABLE_HELP_SUM,
            Constant::DB_TABLE_ASIN,
            Constant::DB_TABLE_COUNTRY,
        ];

        $field = Constant::DB_TABLE_DES;
        $data = Constant::PARAMETER_ARRAY_DEFAULT;
        $dataType = Constant::PARAMETER_STRING_DEFAULT;
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = '';
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $parameters = [$field, [], $data, 'array', $dateFormat, $time, '{@#}', $isAllowEmpty, $callback, $only];

        $handleData = [
            Constant::DB_TABLE_DES => FunctionHelper::getExePlanHandleData(...$parameters),
            'apply_id' => FunctionHelper::getExePlanHandleData('activity_applie.id', 0, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            Constant::AUDIT_STATUS => FunctionHelper::getExePlanHandleData('activity_applie.audit_status', -1, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            Constant::PLATFORM_AMAZON => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_COUNTRY . '{or}item_country', data_get($amazonHostData, 'US', Constant::PARAMETER_STRING_DEFAULT), $amazonHostData, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            Constant::DB_TABLE_AMAZON_URL => FunctionHelper::getExePlanHandleData((Constant::PLATFORM_AMAZON . Constant::DB_EXECUTION_PLAN_CONNECTION . Constant::DB_TABLE_ASIN), $default, $data, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $dateFormat, $time, '/dp/', $isAllowEmpty, $callback, $only), //亚马逊链接 asin
            'url' => FunctionHelper::getExePlanHandleData('url{or}amazon_url', $default, $data, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only), //亚马逊链接
            Constant::DB_TABLE_COUNTRY => FunctionHelper::getExePlanHandleData('items.*.country{or}' . Constant::DB_TABLE_COUNTRY, [], $data, 'array', $dateFormat, $time, '|', $isAllowEmpty, $callback, $only),
        ];

        $joinData = [];
        $unset = [Constant::PLATFORM_AMAZON, Constant::DB_TABLE_ASIN, Constant::DB_TABLE_AMAZON_URL, 'activity_applie', Constant::DB_TABLE_EXT_ID, 'items'];
        $exePlan = FunctionHelper::getExePlan(
                        $storeId, null, static::getModelAlias(), Constant::PARAMETER_STRING_DEFAULT, $select, $where, [$order], $limit, $offset, true, $pagination, false, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset
        );

        $with = [
            'items' => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, [Constant::DB_TABLE_COUNTRY, Constant::DB_TABLE_PRODUCT_ID], Constant::PARAMETER_ARRAY_DEFAULT, [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], Constant::PARAMETER_ARRAY_DEFAULT
                    , 'hasMany'
            ),
        ];

        if ($customerId) {
            $with['activity_applie'] = FunctionHelper::getExePlan(
                            $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, [Constant::DB_TABLE_PRIMARY, Constant::DB_TABLE_EXT_ID, Constant::AUDIT_STATUS], [
                        Constant::DB_TABLE_ACT_ID => $actId,
                        Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                            ], [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], Constant::PARAMETER_ARRAY_DEFAULT, Constant::HAS_ONE
            );
        }

//        $itemHandleDataCallback = [
//            Constant::DB_TABLE_ORDER_STATUS => function($item) use($orderStatusData) {//订单状态
//                $handle = FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_ORDER_STATUS, data_get($orderStatusData, '-1', ''), $orderStatusData);
//                return FunctionHelper::handleData($item, $handle);
//            }
//        ];
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
                //Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
        ];

        $dataStructure = 'list';
        $flatten = false;
        $_data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);

        return [
            Constant::RESPONSE_DATA_KEY => $_data,
            Constant::DB_TABLE_ACT_ID => $actId, //是否填写了申请资料 1:是  0:否
            'is_applied' => ActivityTaskService::isApplied($storeId, $actId, $customerId), //是否申请过实物产品，true是，false否
        ];
    }

    public static function getDetails($storeId, $actId, $productId, $customerId, $extType = 'ActivityProduct') {

        $applyWhere = [
            'w.ext_type' => $extType,
            'w.ext_id' => $productId,
            'w.act_id' => $actId,
            'w.customer_id' => $customerId,
        ];

        $dbExecutionPlan = static::getDbExecutionPlan($storeId, $actId, [], null, 1);

        $select = data_get($dbExecutionPlan, 'parent.select', []);
        $extSelect = ['w.id as apply_id', 'w.helped_sum', 'w.audit_status', 'w.product_item_id'];
        $select = Arr::collapse([$select, $extSelect]);
        data_set($dbExecutionPlan, 'parent.select', $select);

        data_set($dbExecutionPlan, 'parent.joinData.1', [
            Constant::DB_EXECUTION_PLAN_TABLE => 'activity_product_items as pi',
            Constant::DB_EXECUTION_PLAN_FIRST => function ($join) {
                $join->on([['pi.id', '=', 'w.product_item_id'], ['pi.product_id', '=', 'p.id']])->where('pi.status', '=', 1);
            },
            Constant::DB_TABLE_OPERATOR => null,
            Constant::DB_EXECUTION_PLAN_SECOND => null,
            'type' => 'left',
        ]);

        data_set($dbExecutionPlan, 'parent.joinData.0', [
            Constant::DB_EXECUTION_PLAN_TABLE => 'activity_applies as w',
            Constant::DB_EXECUTION_PLAN_FIRST => function ($join) use($applyWhere) {
                $join->on([['w.ext_id', '=', 'p.id']])->where($applyWhere);
            },
            Constant::DB_TABLE_OPERATOR => null,
            Constant::DB_EXECUTION_PLAN_SECOND => null,
            'type' => 'left',
        ]);

        $where = [
            'p.id' => $productId,
        ];
        $extWhere = data_get($dbExecutionPlan, (Constant::DB_EXECUTION_PLAN_PARENT . Constant::LINKER . Constant::DB_EXECUTION_PLAN_WHERE), []);
        $where = Arr::collapse([$where, $extWhere]);
        data_set($dbExecutionPlan, (Constant::DB_EXECUTION_PLAN_PARENT . Constant::LINKER . Constant::DB_EXECUTION_PLAN_WHERE), $where);

        data_set($dbExecutionPlan, 'parent.handleData.apply_id', [
            Constant::DB_EXECUTION_PLAN_FIELD => 'apply_id',
            Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
            Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::PARAMETER_STRING_DEFAULT,
            Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
            Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
            Constant::DB_EXECUTION_PLAN_IS_ALLOW_EMPTY => false, //是否允许为空 true：是  false：否
            Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_INT_DEFAULT,
        ]);

        data_set($dbExecutionPlan, 'parent.handleData.helped_sum', [
            Constant::DB_EXECUTION_PLAN_FIELD => 'helped_sum',
            Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_STRING_DEFAULT,
            Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::PARAMETER_STRING_DEFAULT,
            Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
            Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
            Constant::DB_EXECUTION_PLAN_IS_ALLOW_EMPTY => false, //是否允许为空 true：是  false：否
            Constant::DB_EXECUTION_PLAN_DEFAULT => 0,
        ]);

        data_set($dbExecutionPlan, 'parent.handleData.audit_status', [
            Constant::DB_EXECUTION_PLAN_FIELD => Constant::AUDIT_STATUS,
            Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_STRING_DEFAULT,
            Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::PARAMETER_STRING_DEFAULT,
            Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
            Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
            Constant::DB_EXECUTION_PLAN_IS_ALLOW_EMPTY => false, //是否允许为空 true：是  false：否
            Constant::DB_EXECUTION_PLAN_DEFAULT => 0,
        ]);

        data_set($dbExecutionPlan, 'parent.handleData.product_item_id', [
            Constant::DB_EXECUTION_PLAN_FIELD => 'product_item_id',
            Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_STRING_DEFAULT,
            Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::PARAMETER_STRING_DEFAULT,
            Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
            Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
            Constant::DB_EXECUTION_PLAN_IS_ALLOW_EMPTY => false, //是否允许为空 true：是  false：否
            Constant::DB_EXECUTION_PLAN_DEFAULT => 0,
        ]);

        $dataStructure = 'one';
        $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, false, false, $dataStructure);

        return [
            Constant::RESPONSE_CODE_KEY => $data ? 1 : 0,
            Constant::RESPONSE_MSG_KEY => Constant::PARAMETER_STRING_DEFAULT,
            Constant::RESPONSE_DATA_KEY => $data,
        ];
    }

    public static $act = 'act';

    /**
     * 获取产品ids
     * @param array $uniqueIds ['ActivityProduct-1','ActivityPrize-2']
     * @return array ['ActivityProduct'=>[1,2],'ActivityPrize'=>[1,6]]
     */
    public static function getActPublicIds($uniqueIds) {
        $ids = [];
        foreach ($uniqueIds as $id) {
            $idData = explode('-', $id);
            $ids[data_get($idData, 0, Constant::PARAMETER_INT_DEFAULT)][] = data_get($idData, 1, Constant::PARAMETER_INT_DEFAULT);
        }

        return $ids;
    }

    /**
     * 获取后台公共参数
     * @param array $params 请求参数
     * @param array $order 排序控制
     * @return array
     */
    public static function getAdminPublicData($params, $order = []) {

        $where = [];

        $name = $params[Constant::DB_TABLE_NAME] ?? Constant::PARAMETER_STRING_DEFAULT; //活动名称
        $actType = $params[Constant::DB_TABLE_ACT_TYPE] ?? null; //活动类型
        $type = data_get($params, Constant::DB_TABLE_TYPE, data_get($params, 'srcParameters.' . Constant::DB_TABLE_TYPE, null)); //产品类型
        $startAt = data_get($params, Constant::DB_TABLE_START_AT, Constant::PARAMETER_STRING_DEFAULT);
        $endAt = data_get($params, Constant::DB_TABLE_END_AT, Constant::PARAMETER_STRING_DEFAULT);

        if ($name) {//活动名称
            //$where[] = [Constant::ACT_ALIAS . Constant::LINKER . Constant::DB_TABLE_NAME, 'like', '%' . $name . '%'];
            $where[] = [Constant::ACT_ALIAS . Constant::LINKER . Constant::DB_TABLE_NAME, '=', $name];
        }

        if ($actType) {//活动类型
            $where[] = [Constant::ACT_ALIAS . Constant::LINKER . Constant::DB_TABLE_ACT_TYPE, '=', $actType + 0];
        }

        if ($startAt) {//活动开始时间
            $where[] = [Constant::ACT_ALIAS . Constant::LINKER . Constant::DB_TABLE_START_AT, '>=', $startAt];
        }

        if ($endAt) {//活动结束时间
            $where[] = [Constant::ACT_ALIAS . Constant::LINKER . Constant::DB_TABLE_END_AT, '<=', $endAt];
        }

        $_where = [];
        $uniqueIds = data_get($params, Constant::DB_TABLE_PRIMARY, []);
        if ($uniqueIds) {
            $ids = static::getActPublicIds($uniqueIds);
            $tableAliasData = [
                ActivityPrizeService::getModelAlias() => 'ap',
                static::getModelAlias() => 'p',
            ];

            $_where[Constant::DB_EXECUTION_PLAN_CUSTOMIZE_WHERE] = [
                [
                    Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                    Constant::PARAMETERS_KEY => function ($query) use($ids, $tableAliasData) {
                        foreach ($ids as $key => $id) {
                            $query->OrWhere(function ($_query)use($key, $id, $tableAliasData) {
                                        $tableAlias = data_get($tableAliasData, $key, '');
                                        if ($tableAlias) {
                                            $_query->whereIn($tableAlias . '.' . Constant::DB_TABLE_PRIMARY, $id);
                                        }
                                    });
                        }
                    },
                ],
            ];
        } else {
            $_where[Constant::ACT_ALIAS . Constant::LINKER . Constant::DB_TABLE_ACT_TYPE] = [1, 2, 3, 4, 5, 6];
            $_where[Constant::DB_EXECUTION_PLAN_CUSTOMIZE_WHERE] = [
                [
                    Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                    Constant::PARAMETERS_KEY => function ($query) {
                        $query->OrWhere('ap.' . Constant::DB_TABLE_PRIMARY, '>', 0)
                                ->OrWhere('p.' . Constant::DB_TABLE_PRIMARY, '>', 0);
                    },
                ],
            ];

            if ($type !== null) {//产品类型
                $type = $type + 0;
                $_where[Constant::DB_EXECUTION_PLAN_CUSTOMIZE_WHERE][] = [
                    Constant::METHOD_KEY => Constant::DB_EXECUTION_PLAN_WHERE,
                    Constant::PARAMETERS_KEY => function ($query) use($type) {
                        $query->OrWhere('ap.' . Constant::DB_TABLE_TYPE, '=', $type)
                                ->OrWhere('p.' . Constant::DB_TABLE_TYPE, '=', $type);
                    },
                ];
            }
        }

        if ($where) {
            $_where[] = $where;
        }

        $order = $order ? $order : [[Constant::DB_TABLE_UPDATED_AT, 'DESC']]; //[static::$act . '.' . Constant::DB_TABLE_PRIMARY, 'DESC'],
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
     * @param boolean $isRaw 是否原始 select true:是 false:否 默认:false
     * @param boolean $isGetQuery 是否获取 query
     * @param boolean $isOnlyGetCount 是否仅仅获取总记录数
     * @return array|\Hyperf\Database\Model\Builder 列表数据|Builder
     */
    public static function getListData($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $_data = static::getAdminPublicData($params);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);
        $order = data_get($params, Constant::DB_EXECUTION_PLAN_ORDER_BY, data_get($_data, 'order', []));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, 'limit', data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0));
        $storeId = Arr::get($params, Constant::DB_TABLE_STORE_ID, 1);

        $select = $select ? $select : [
            Constant::ACT_ALIAS . Constant::LINKER . Constant::DB_TABLE_NAME . ' as act_' . Constant::DB_TABLE_NAME,
            Constant::ACT_ALIAS . Constant::LINKER . Constant::DB_TABLE_ACT_TYPE,
            Constant::ACT_ALIAS . Constant::LINKER . Constant::DB_TABLE_START_AT,
            Constant::ACT_ALIAS . Constant::LINKER . Constant::DB_TABLE_END_AT,
            Constant::ACT_PRODUCT_ALIAS . Constant::LINKER . Constant::DB_TABLE_PRIMARY,
            Constant::ACT_PRODUCT_ALIAS . Constant::LINKER . Constant::DB_TABLE_IMG_URL,
            Constant::ACT_PRODUCT_ALIAS . Constant::LINKER . Constant::DB_TABLE_NAME,
            Constant::ACT_PRODUCT_ALIAS . Constant::LINKER . Constant::DB_TABLE_QTY,
            Constant::ACT_PRODUCT_ALIAS . Constant::LINKER . Constant::DB_TABLE_QTY_RECEIVE,
            Constant::ACT_PRODUCT_ALIAS . Constant::LINKER . Constant::DB_TABLE_TYPE,
            'p' . Constant::LINKER . Constant::DB_TABLE_PRIMARY . ' as ' . Constant::DB_TABLE_PRODUCT_ID,
            'p' . Constant::LINKER . Constant::DB_TABLE_IMG_URL . ' as p_' . Constant::DB_TABLE_IMG_URL,
            'p' . Constant::LINKER . Constant::DB_TABLE_NAME . ' as p_' . Constant::DB_TABLE_NAME,
            'p' . Constant::LINKER . Constant::DB_TABLE_QTY . ' as p_' . Constant::DB_TABLE_QTY,
            'p' . Constant::LINKER . Constant::DB_TABLE_QTY_APPLY . ' as p_' . Constant::DB_TABLE_QTY_RECEIVE,
            'p' . Constant::LINKER . Constant::DB_TABLE_TYPE . ' as p_' . Constant::DB_TABLE_TYPE,
        ];

        $select[] = DB::raw('if(`crm_p`.`' . Constant::DB_TABLE_UPDATED_AT . '` is null,`crm_ap`.`' . Constant::DB_TABLE_UPDATED_AT . '`,`crm_p`.`' . Constant::DB_TABLE_UPDATED_AT . '`) as ' . Constant::DB_TABLE_UPDATED_AT);

        $actTypeData = ActivityService::getActType();
        $productTypeData = DictService::getListByType('prize_type', Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //获取类型 0:其他 1:礼品卡 2:coupon 3:实物 5:活动积分

        $field = Constant::DB_TABLE_ACT_TYPE;
        $data = $actTypeData;
        $dataType = Constant::DB_EXECUTION_PLAN_DATATYPE_STRING;
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = Constant::PARAMETER_STRING_DEFAULT;
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;

        $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];
        $handleData = [
            Constant::DB_TABLE_ACT_TYPE . '_show' => FunctionHelper::getExePlanHandleData(...$parameters),
        ];

        $joinData = [
            FunctionHelper::getExePlanJoinData('activity_prizes as ap', function ($join) {
                        $join->on([['ap.' . Constant::DB_TABLE_ACT_ID, '=', 'act.' . Constant::DB_TABLE_PRIMARY]])->where('ap.' . Constant::DB_TABLE_STATUS, '=', 1);
                    }),
            FunctionHelper::getExePlanJoinData('activity_products as p', function ($join) {
                        $join->on([['p.' . Constant::DB_TABLE_ACT_ID, '=', 'act.' . Constant::DB_TABLE_PRIMARY]])->where('p.' . Constant::DB_TABLE_STATUS, '=', 1);
                    }),
        ];
        $with = Constant::PARAMETER_ARRAY_DEFAULT;
        $unset = [
            //Constant::DB_TABLE_ACT_TYPE,
            Constant::DB_TABLE_START_AT,
            Constant::DB_TABLE_END_AT,
            Constant::DB_TABLE_TYPE,
            Constant::DB_TABLE_QTY_RECEIVE,
            Constant::DB_TABLE_PRODUCT_ID,
            'p_' . Constant::DB_TABLE_IMG_URL,
            'p_' . Constant::DB_TABLE_NAME,
            'p_' . Constant::DB_TABLE_QTY,
            'p_' . Constant::DB_TABLE_QTY_RECEIVE,
            'p_' . Constant::DB_TABLE_TYPE,
            Constant::DB_EXECUTION_PLAN_TABLE,
            'unique_' . Constant::DB_TABLE_PRIMARY,
        ];
        $exePlan = FunctionHelper::getExePlan($storeId, null, ActivityService::getModelAlias(), 'activities as ' . Constant::ACT_ALIAS, $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $itemHandleDataCallback = [
            Constant::DB_EXECUTION_PLAN_TABLE => function ($item) {//表 model
                return data_get($item, Constant::DB_TABLE_PRIMARY, null) ? ActivityPrizeService::getModelAlias() : static::getModelAlias();
            },
            'unique_' . Constant::DB_TABLE_PRIMARY => function ($item) {
                $field = FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_PRIMARY . '{or}' . Constant::DB_TABLE_PRODUCT_ID, 0);
                return FunctionHelper::handleData($item, $field);
            },
            Constant::DB_TABLE_PRIMARY => function ($item) {
                $field = FunctionHelper::getExePlanHandleData(Constant::DB_EXECUTION_PLAN_TABLE . '{connection}unique_' . Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, '-');
                return FunctionHelper::handleData($item, $field);
            },
            Constant::DB_TABLE_START_AT => function ($item) {
                return FunctionHelper::getShowTime(data_get($item, Constant::DB_TABLE_START_AT, 'null'));
            },
            Constant::DB_TABLE_END_AT => function ($item) {
                return FunctionHelper::getShowTime(data_get($item, Constant::DB_TABLE_END_AT, 'null'));
            },
            'act_time' => function ($item) {
                $field = FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_START_AT . '{connection}' . Constant::DB_TABLE_END_AT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, '-');
                return FunctionHelper::handleData($item, $field);
            },
            Constant::DB_TABLE_IMG_URL => function ($item) {
                $field = FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_IMG_URL . '{or}' . 'p_' . Constant::DB_TABLE_IMG_URL, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING);
                return FunctionHelper::handleData($item, $field);
            },
            Constant::DB_TABLE_NAME => function ($item) {//产品名称
                $field = FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_NAME . '{or}' . 'p_' . Constant::DB_TABLE_NAME, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING);
                return FunctionHelper::handleData($item, $field);
            },
            Constant::DB_TABLE_TYPE => function ($item) {//产品类型
                $type = data_get($item, Constant::DB_TABLE_TYPE, null);
                return $type !== null ? $type : data_get($item, 'p_' . Constant::DB_TABLE_TYPE, null);
            },
            Constant::DB_TABLE_TYPE . '_show' => function ($item) use($productTypeData) {//产品类型名称
                $field = FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_TYPE, data_get($productTypeData, 0, ''), $productTypeData);
                return FunctionHelper::handleData($item, $field);
            },
            Constant::DB_TABLE_QTY => function ($item) {
                $field = FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_QTY . '{or}' . 'p_' . Constant::DB_TABLE_QTY, Constant::PARAMETER_INT_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::DB_EXECUTION_PLAN_DATATYPE_STRING);
                return FunctionHelper::handleData($item, $field);
            },
            Constant::DB_TABLE_QTY_RECEIVE => function ($item) {
                $field = FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_QTY_RECEIVE . '{or}' . 'p_' . Constant::DB_TABLE_QTY_RECEIVE, Constant::PARAMETER_INT_DEFAULT);
                return FunctionHelper::handleData($item, $field);
            },
            'last_qty' => function ($item) {
                return intval(data_get($item, Constant::DB_TABLE_QTY, Constant::PARAMETER_INT_DEFAULT)) - intval(data_get($item, Constant::DB_TABLE_QTY_RECEIVE, Constant::PARAMETER_INT_DEFAULT));
            },
        ];
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
        ];

        if (data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT . '.' . Constant::DB_EXECUTION_PLAN_HANDLE_DATA, Constant::PARAMETER_ARRAY_DEFAULT);
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_WITH, Constant::PARAMETER_ARRAY_DEFAULT);
        }

        $dataStructure = 'list';
        $flatten = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * 添加记录
     * @param array $where where条件
     * @param array $data  数据
     * @param array $permissionData  权限数据
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
     * 删除记录
     * @param int $storeId 商城id
     * @param array $ids id
     * @param array $requestData 请求参数
     * @return int 删除的记录条数
     */
    public static function delete($storeId, $ids, $requestData = []) {
        //属性删除
        data_set($requestData, Constant::OWNER_RESOURCE, static::getModelAlias());
        data_set($requestData, Constant::OP_ACTION, 'del');
        data_set($requestData, Constant::NAME_SPACE, data_get($requestData, Constant::NAME_SPACE, static::getModelAlias()));
        MetafieldService::batchHandle($storeId, $ids, $requestData);

        return static::getModel($storeId)->whereIn(Constant::DB_TABLE_PRIMARY, $ids)->delete();
    }

    /**
     * deal admin活动产品列表
     * @param array $params 请求参数
     * @param boolean $toArray 是否转化为数组 true:是 false:否 默认:true
     * @param boolean $isPage  是否分页 true:是 false:否 默认:true
     * @param array $select  查询字段
     * @param boolean $isRaw 是否原始 select true:是 false:否 默认:false
     * @param boolean $isGetQuery 是否获取 query
     * @param boolean $isOnlyGetCount 是否仅仅获取总记录数
     * @return array|\Hyperf\Database\Model\Builder 列表数据|Builder
     */
    public static function getDealList($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {
        $params['tableAlias'] = [
            'actProduct' => 'ap.',
            static::$act => static::$act . '.',
        ];
        $_data = static::getPublicData($params);
        $ap = data_get($params, 'tableAlias.actProduct', '');

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);

        if (empty(data_get($params, Constant::DB_TABLE_PRIMARY, []))) {
            $where[static::$act . '.act_type'] = [8, 9];
            $where[$ap . Constant::DB_TABLE_STATUS] = 1;
        }

        $order = [data_get($params, Constant::DB_EXECUTION_PLAN_ORDER_BY, data_get($_data, 'order', []))];
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::DB_EXECUTION_PLAN_LIMIT, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0));
        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0);

        $clickStartTime = data_get($params, 'click_start_time', data_get($params, 'srcParameters.0.click_start_time', Constant::PARAMETER_STRING_DEFAULT)); //点击开始时间
        $clickEndTime = data_get($params, 'click_end_time', data_get($params, 'srcParameters.0.click_end_time', Constant::PARAMETER_STRING_DEFAULT)); //点击结束时间
        //获取点击日志时间段内产品的点击量
        $clickWhere = [];
        if ($clickStartTime) {
            //获取点击日志时间段内产品的点击量
            $clickWhere[] = Constant::DB_TABLE_CREATED_AT . '>=' . "'" . $clickStartTime . "'";
        }

        if ($clickEndTime) {
            $clickWhere[] = Constant::DB_TABLE_CREATED_AT . '<=' . "'" . $clickEndTime . "'";
        }
        $clickWhere = implode(' and ', $clickWhere);

        $select = $select ? $select : [
            $ap . Constant::DB_TABLE_PRIMARY,
            $ap . Constant::DB_TABLE_ACT_ID,
            $ap . Constant::DB_TABLE_IMG_URL,
            $ap . Constant::DB_TABLE_NAME,
            $ap . Constant::DB_TABLE_DES,
            $ap . Constant::DB_TABLE_SKU,
            $ap . Constant::DB_TABLE_ASIN,
            $ap . Constant::DB_TABLE_COUNTRY,
            $ap . Constant::DB_TABLE_CREATED_AT,
            $ap . Constant::DB_TABLE_UPLOAD_USER,
            $ap . Constant::DB_TABLE_MB_TYPE,
            $ap . Constant::DB_TABLE_PRODUCT_STATUS,
            $ap . Constant::DB_TABLE_STAR,
            $ap . Constant::DB_TABLE_DISCOUNT,
            static::$act . '.' . Constant::DB_TABLE_NAME . ' as activity_name',
            static::$act . '.' . Constant::DB_TABLE_ACT_TYPE,
            static::$act . '.' . Constant::FILE_URL . ' as act_' . Constant::FILE_URL,
        ];

        if ($clickWhere) {
            $select[] = DB::raw("(select count(*) from `crm_activity_click_logs` as acl where acl.ext_id=crm_ap.id and acl.status=1 and acl.ext_type='" . static::getModelAlias() . "'" . ($clickWhere ? ' and ' : '') . $clickWhere . ") as " . Constant::DB_TABLE_CLICK);
        } else {
            $select[] = $ap . Constant::DB_TABLE_CLICK;
        }

        $type = 'template_type';
        $keyField = 'conf_key';
        $valueField = 'conf_value';
        $distKeyField = Constant::DB_TABLE_DICT_KEY;
        $distValueField = Constant::DB_TABLE_DICT_VALUE;
        $mbType = DictService::getDistData($storeId, $type, $keyField, $valueField, $distKeyField, $distValueField); //模板类型

        $type = [Constant::DB_TABLE_PRODUCT_STATUS, Constant::AMAZON_HOST];
        $dictData = DictService::getDistData($storeId, $type, $keyField, $valueField, $distKeyField, $distValueField); //获取字典数据
        $productStatus = data_get($dictData, Constant::DB_TABLE_PRODUCT_STATUS, Constant::PARAMETER_ARRAY_DEFAULT); //产品状态 0 未启用 1 启用 2 过期;
        $amazonHostData = data_get($dictData, Constant::AMAZON_HOST, Constant::PARAMETER_ARRAY_DEFAULT); //亚马逊链接

        $field = Constant::DB_TABLE_MB_TYPE;
        $data = $mbType;
        $dataType = Constant::PARAMETER_STRING_DEFAULT;
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = data_get($mbType, '0', Constant::PARAMETER_STRING_DEFAULT);
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;

        $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];
        $handleData = [
            Constant::DB_TABLE_MB_TYPE . '_show' => FunctionHelper::getExePlanHandleData(...$parameters),
            Constant::DB_TABLE_PRODUCT_STATUS . '_show' => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_PRODUCT_STATUS, data_get($productStatus, '0', Constant::PARAMETER_STRING_DEFAULT), $productStatus, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            Constant::PLATFORM_AMAZON => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_COUNTRY, data_get($amazonHostData, 'US', Constant::PARAMETER_STRING_DEFAULT), $amazonHostData, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            Constant::DB_TABLE_AMAZON_URL => FunctionHelper::getExePlanHandleData((Constant::PLATFORM_AMAZON . Constant::DB_EXECUTION_PLAN_CONNECTION . Constant::DB_TABLE_ASIN), Constant::PARAMETER_STRING_DEFAULT, [], Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $dateFormat, $time, '/dp/', $isAllowEmpty, $callback, $only),
        ];

        $joinData = [
            FunctionHelper::getExePlanJoinData('activity_products as ap', function ($join) use($ap) {
                        $join->on([[$ap . Constant::DB_TABLE_ACT_ID, '=', static::$act . '.' . Constant::DB_TABLE_PRIMARY]]); //->where($ap . Constant::DB_TABLE_STATUS, '=', 1);
                    }),
        ];
        $with = [];
        $unset = [Constant::PLATFORM_AMAZON];
        $exePlan = FunctionHelper::getExePlan($storeId, null, ActivityService::getModelAlias(), 'activities as ' . static::$act, $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, [], $handleData, $unset, '');

        data_set($exePlan, Constant::DB_EXECUTION_PLAN_HANDLE_DATA, $handleData);
        data_set($exePlan, Constant::DB_EXECUTION_PLAN_UNSET, $unset);

        $itemHandleDataCallback = [
            Constant::DB_TABLE_DISCOUNT_PRICE => function ($item) {
                return FunctionHelper::getDiscountPrice(data_get($item, Constant::DB_TABLE_LISTING_PRICE, Constant::PARAMETER_INT_DEFAULT), data_get($item, Constant::DB_TABLE_DISCOUNT, Constant::PARAMETER_INT_DEFAULT));
            },
        ];
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $itemHandleDataCallback, $only),
        ];

        if (data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT . '.' . Constant::DB_EXECUTION_PLAN_HANDLE_DATA, Constant::PARAMETER_ARRAY_DEFAULT);
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_WITH, Constant::PARAMETER_ARRAY_DEFAULT);
        }

        $dataStructure = 'list';
        $flatten = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * Deal VT编辑后台活动列表
     * @param int $id id
     * @param int $storeId 商城id
     * @param string $name 产品标题
     * @param string $sku 产品SKU
     * @param string $asin 产品ASIN
     * @param int $actId 活动id
     * @param string $actName 活动名称
     * @param array $requestData 请求参数
     * @return array
     */
    public static function editDeal($id, $storeId, $name, $sku, $asin, $actId, $actName, $requestData = []) {
        $retult = [
            Constant::RESPONSE_CODE_KEY => 0,
            Constant::RESPONSE_MSG_KEY => Constant::PARAMETER_STRING_DEFAULT,
            Constant::RESPONSE_DATA_KEY => [],
        ];

        if (empty($id)) {
            $retult[Constant::RESPONSE_MSG_KEY] = 'ID不允许为空';
            return $retult;
        }

        $actType = data_get($requestData, Constant::DB_TABLE_ACT_TYPE, 0);
        if ($actType == 9) {//如果是通用模板产品，才支持修改活动
            $actData = ActivityService::addActivity($storeId, $actName, [Constant::DB_TABLE_ACT_TYPE => $actType]);
            $actId = data_get($actData, Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT);
        }

        $where = [
            Constant::DB_TABLE_PRIMARY => $id,
        ];

        $data = [
            Constant::DB_TABLE_NAME => $name,
            Constant::DB_TABLE_SKU => $sku,
            Constant::DB_TABLE_SHOP_SKU => $sku,
            Constant::DB_TABLE_ASIN => $asin,
            Constant::DB_TABLE_STAR => FunctionHelper::getStar(data_get($requestData, Constant::DB_TABLE_STAR, Constant::PARAMETER_INT_DEFAULT)),
            Constant::DB_TABLE_DISCOUNT => FunctionHelper::getDiscount(data_get($requestData, Constant::DB_TABLE_DISCOUNT, Constant::PARAMETER_INT_DEFAULT)),
        ];

        if ($actType == 9) {//如果是通用模板产品，才支持修改活动
            data_set($data, Constant::DB_TABLE_ACT_ID, $actId);
        }

        return static::update($storeId, $where, $data);
    }

    /**
     * Deal VT操作后台活动列表
     * @param int $id id
     * @param int $storeId 商城id
     * @param int $mbType 模板类型 1 新品 2 常规 3 主推
     * @param int $productStatus 产品状态 0 未启用 1 启用 2 过期
     * @return array
     */
    public static function operateDeal($id, $storeId, $mbType, $productStatus, $country) {

        $retult = [
            Constant::RESPONSE_CODE_KEY => 0,
            Constant::RESPONSE_MSG_KEY => Constant::PARAMETER_STRING_DEFAULT,
            Constant::RESPONSE_DATA_KEY => []
        ];

        if (empty($id)) {
            $retult[Constant::RESPONSE_MSG_KEY] = 'ID不允许为空';
            return $retult;
        }

        $limitData = [
            1 => [
                1 => 1,
                2 => 9,
                3 => 2,
                4 => 9,
            ],
            'other' => [
                1 => 1,
                2 => 6,
                3 => 2,
                4 => 9,
            ],
        ];

        $errMsgData = [
            1 => '产品启用个数超过当前新品模板固定产品个数',
            2 => '产品启用个数超过当前常规模板固定产品个数',
            3 => '产品启用个数超过当前主推模板固定产品个数',
            4 => '产品启用个数超过当前通用模板固定产品个数',
        ];

        $model = static::getModel($storeId);
//        if ($productStatus === 1) {
//            $where = [
//                Constant::DB_TABLE_COUNTRY => $country,
//                Constant::DB_TABLE_MB_TYPE => $mbType,
//                Constant::DB_TABLE_PRODUCT_STATUS => $productStatus,
//                [[Constant::DB_TABLE_PRIMARY, '!=', $id]]
//            ];
//            $count = $model->buildWhere($where)->count(); //查询各个模板上架的产品数
//            $limit = data_get($limitData, ($storeId . '.' . $mbType), data_get($limitData, ('other.' . $mbType), 0));
//            if ($count >= $limit) {
//                $retult[Constant::RESPONSE_MSG_KEY] = data_get($errMsgData, $mbType, Constant::PARAMETER_STRING_DEFAULT);
//                return $retult;
//            }
//        }

        $retult[Constant::RESPONSE_CODE_KEY] = 1;
        $retult[Constant::RESPONSE_DATA_KEY] = $model->buildWhere([Constant::DB_TABLE_PRIMARY => $id])->update([Constant::DB_TABLE_PRODUCT_STATUS => $productStatus]); //更新

        return $retult;
    }

    /**
     * deal VT admin活动产品转换成数据表数据
     * @param array $excelData
     * @param string $type 模板类型 0 未选择 1 新品 2 常规 3 主推 4:通用
     * @param string $user 上传人
     * @param time $time 上传时间
     * @return array
     */
    public static function convToTableData($excelData, $type, $user, $time, $actId) {
        $tableData = [];
        foreach ($excelData as $k => $row) {
            if (empty(data_get($row, 0, Constant::PARAMETER_STRING_DEFAULT))) {//判断导入的数据为空时，跳出循环
                continue;
            }

            $tableData[$k][Constant::DB_TABLE_CREATED_AT] = $time;
            $tableData[$k][Constant::DB_TABLE_UPLOAD_USER] = $user;
            $tableData[$k][Constant::DB_TABLE_MB_TYPE] = $type;
            $tableData[$k][Constant::DB_TABLE_ACT_ID] = $actId;
            $tableData[$k][Constant::DB_TABLE_IMG_URL] = data_get($row, 0, Constant::PARAMETER_STRING_DEFAULT); //产品图片
            $tableData[$k][Constant::DB_TABLE_NAME] = data_get($row, 1, Constant::PARAMETER_STRING_DEFAULT); //产品名称
            $tableData[$k][Constant::DB_TABLE_SKU] = data_get($row, 2, Constant::PARAMETER_STRING_DEFAULT); //产品店铺sku
            $tableData[$k][Constant::DB_TABLE_SHOP_SKU] = $tableData[$k][Constant::DB_TABLE_SKU]; //产品店铺sku
            $tableData[$k][Constant::DB_TABLE_ASIN] = data_get($row, 3, Constant::PARAMETER_STRING_DEFAULT); //产品asin
            $tableData[$k][Constant::DB_TABLE_COUNTRY] = data_get($row, 4, Constant::PARAMETER_STRING_DEFAULT); //产品国家
            $tableData[$k][Constant::DB_TABLE_STAR] = FunctionHelper::getStar(data_get($row, 5, Constant::PARAMETER_INT_DEFAULT)); //产品星级
            $tableData[$k][Constant::DB_TABLE_DES] = data_get($row, 6, Constant::PARAMETER_STRING_DEFAULT); //产品描述
            $tableData[$k][Constant::DB_TABLE_DISCOUNT] = FunctionHelper::getDiscount(data_get($row, 7, Constant::PARAMETER_INT_DEFAULT)); //产品折扣
        }
        return $tableData;
    }

    /**
     * deal VT admin活动产品批量添加
     * @param $listData
     * @return array
     */
    public static function addBatch($storeId, $listData) {

        $result = [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => Constant::PARAMETER_STRING_DEFAULT,
            Constant::RESPONSE_DATA_KEY => []
        ];
        $success = 0;
        $failCount = 0;

        $connection = static::getModel($storeId)->getConnection();
        try {
            $connection->beginTransaction();
            foreach ($listData as $row) {
                $rs = static::addOne($storeId, $row);
                if ($rs) {
                    $success++;
                } else {
                    $failCount++;
                }
            }
            //提交
            $connection->commit();
        } catch (\Exception $exc) {
            $connection->rollBack(); //回滚
            $result[Constant::RESPONSE_CODE_KEY] = 0;
            $result[Constant::RESPONSE_MSG_KEY] = '数据异常';
            LogService::addSystemLog('error', 'import', 'product', Constant::PARAMETER_STRING_DEFAULT, $exc->getMessage());
        }

        if (!$success) {
            $result[Constant::RESPONSE_CODE_KEY] = 0;
        }

        $result[Constant::RESPONSE_MSG_KEY] = '合格 ' . $success . ' 个 and  false ' . $failCount . ' 个';

        return $result;
    }

    /**
     * deal VT admin活动产品单个添加
     * @param int $storeId 商城id
     * @param array $data 数据
     * @return int
     */
    public static function addOne($storeId, $data) {

        $where = [
            Constant::DB_TABLE_ACT_ID => data_get($data, Constant::DB_TABLE_ACT_ID, Constant::PARAMETER_INT_DEFAULT),
            Constant::DB_TABLE_COUNTRY => data_get($data, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_ASIN => data_get($data, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_MB_TYPE => data_get($data, Constant::DB_TABLE_MB_TYPE, Constant::PARAMETER_INT_DEFAULT),
        ];

        return static::updateOrCreate($storeId, $where, $data); //updateOrCreate：不可以添加主键id的值  updateOrInsert：可以添加主键id的值
    }

    /**
     * deal VT前台产品列表
     * @param array $storeId 商店id
     * @param boolean $actId 活动id
     * @param boolean $country  产品国家
     * @param array $customerId  会员id
     * @param boolean $type 模板类型
     * @param boolean $page 分页
     * @param boolean $pageSize 分页数
     * @return array|\Hyperf\Database\Model\Builder 列表数据|Builder
     */
    public static function getDealData($storeId, $actId, $country, $customerId, $type, $page, $pageSize) {

        $params = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_COUNTRY => $country,
            Constant::DB_TABLE_MB_TYPE => $type,
            Constant::DB_TABLE_PRODUCT_STATUS => 1,
            Constant::REQUEST_PAGE => $page,
            Constant::REQUEST_PAGE_SIZE => $pageSize,
        ];

        $_data = static::getPublicData($params);
        $where = $_data[Constant::DB_EXECUTION_PLAN_WHERE];
        $order = [Constant::DB_TABLE_PRIMARY, 'DESC'];
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, 'limit', data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0));


        $select = [
            'id as product_id',
            Constant::DB_TABLE_IMG_URL,
            Constant::DB_TABLE_NAME,
            Constant::DB_TABLE_ASIN,
            Constant::DB_TABLE_COUNTRY,
            Constant::DB_TABLE_CREATED_AT,
            Constant::DB_TABLE_LISTING_PRICE,
            Constant::DB_TABLE_REGULAR_PRICE,
            Constant::DB_TABLE_QUERY_RESULTS,
            Constant::DB_TABLE_STAR,
            Constant::DB_TABLE_DES,
            Constant::DB_TABLE_DISCOUNT,
        ]; //'id as p_id',
        $amazonHostData = DictService::getListByType(Constant::AMAZON_HOST, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);
        $couponSelect = ['id as coupon_id', Constant::RESPONSE_CODE_KEY, Constant::DB_TABLE_ASIN, Constant::DB_TABLE_EXTINFO, 'receive', Constant::DB_TABLE_START_TIME, Constant::DB_TABLE_END_TIME];
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                Constant::DB_EXECUTION_PLAN_BUILDER => null,
                Constant::DB_EXECUTION_PLAN_MAKE => static::getModelAlias(),
                Constant::DB_EXECUTION_PLAN_FROM => Constant::PARAMETER_STRING_DEFAULT,
                Constant::DB_EXECUTION_PLAN_SELECT => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                Constant::DB_EXECUTION_PLAN_LIMIT => $limit,
                Constant::DB_EXECUTION_PLAN_OFFSET => $offset,
                Constant::DB_EXECUTION_PLAN_IS_PAGE => true,
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
                Constant::DB_EXECUTION_PLAN_ORDERS => [[Constant::DB_TABLE_CLICK, 'DESC'], $order],
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                    Constant::PLATFORM_AMAZON => [
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_COUNTRY,
                        Constant::RESPONSE_DATA_KEY => $amazonHostData,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => data_get($amazonHostData, 'US', Constant::PARAMETER_STRING_DEFAULT),
                    ],
                    Constant::DB_TABLE_ASIN => [
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_ASIN,
                        Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT,
                    ],
                    Constant::DB_TABLE_AMAZON_URL => [//亚马逊链接
                        Constant::DB_EXECUTION_PLAN_FIELD => (Constant::PLATFORM_AMAZON . Constant::DB_EXECUTION_PLAN_CONNECTION . Constant::DB_TABLE_ASIN),
                        Constant::RESPONSE_DATA_KEY => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => '/dp/',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT,
                    ],
                ],
                Constant::DB_EXECUTION_PLAN_UNSET => [Constant::PLATFORM_AMAZON],
            ],
            'with' => [
                Constant::ACTIVITY_COUPON => [//关联优惠劵
                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                    Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                    Constant::DB_EXECUTION_PLAN_RELATION => Constant::HAS_ONE,
                    Constant::DB_EXECUTION_PLAN_SELECT => $couponSelect,
                    Constant::DB_EXECUTION_PLAN_DEFAULT => [Constant::DB_TABLE_ASIN => Constant::DB_TABLE_ASIN],
                    Constant::DB_EXECUTION_PLAN_WHERE => [Constant::DB_TABLE_EXTINFO => $customerId, Constant::DB_TABLE_COUNTRY => $country, Constant::DB_EXECUTION_PLAN_GROUP => Constant::DB_EXECUTION_PLAN_GROUP_COMMON, Constant::DB_TABLE_USE_TYPE => 1],
                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
                    Constant::DB_EXECUTION_PLAN_UNSET => [Constant::ACTIVITY_COUPON],
                ],
            ],
            'itemHandleData' => [
                Constant::DB_EXECUTION_PLAN_FIELD => null, //数据字段
                Constant::RESPONSE_DATA_KEY => [], //数据映射map
                Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::PARAMETER_STRING_DEFAULT, //数据类型
                Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT, //数据格式
                'time' => Constant::PARAMETER_STRING_DEFAULT, //时间处理句柄
                Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT, //分隔符或者连接符
                Constant::DB_EXECUTION_PLAN_IS_ALLOW_EMPTY => true, //是否允许为空 true：是  false：否
                Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT, //默认值$default
                'callback' => [
                    'coupon_qty' => function ($item) use($storeId, $type) {
                        $productAsin = data_get($item, Constant::DB_TABLE_ASIN, null);
                        $couponQty = Constant::PARAMETER_STRING_DEFAULT;
                        if ($type == 1) {//新品模板获取code的库存
                            $where = [
                                Constant::DB_TABLE_ASIN => $productAsin,
                                Constant::DB_TABLE_EXTINFO => Constant::PARAMETER_STRING_DEFAULT,
                                'receive' => 2,
                                'status' => 1,
                                Constant::DB_EXECUTION_PLAN_GROUP => Constant::DB_EXECUTION_PLAN_GROUP_COMMON,
                                Constant::DB_TABLE_USE_TYPE => 1
                            ];
                            $couponQty = CouponService::getModel($storeId)->where($where)->count();
                        }
                        return $couponQty;
                    },
                    Constant::DB_TABLE_DISCOUNT_PRICE => function ($item) {
                        return FunctionHelper::getDiscountPrice(data_get($item, Constant::DB_TABLE_LISTING_PRICE, Constant::PARAMETER_INT_DEFAULT), data_get($item, Constant::DB_TABLE_DISCOUNT, Constant::PARAMETER_INT_DEFAULT));
                    },
                ],
                'only' => [
                ],
            ],
                //'sqlDebug' => true,
        ];

        $dataStructure = 'list';
        $flatten = true;
        $isGetQuery = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * deal VT 领取code和更新点击次数
     * @param int $storeId 商城id
     * @param int $Id id
     * @param int $customerId 会员id
     * @param int $asin 产品asin
     * @return array
     */
    public static function clickReceiveData($storeId, $customerId, $account, $productId, $couponId) {

        if (empty($couponId)) {
            return [
                Constant::RESPONSE_CODE_KEY => 10058,
                Constant::RESPONSE_MSG_KEY => 'coupon_id is not allowed to be empty',
                Constant::RESPONSE_DATA_KEY => [],
            ];
        }

        $receive = CouponService::couponReceive($storeId, $customerId, $couponId); //更新coupon的领取情况

        if ($receive) {//更新成功
            $logData = [
                'click_type' => 'product',
                Constant::DB_TABLE_EXT_ID => $productId,
                Constant::DB_TABLE_EXT_TYPE => static::getModelAlias(),
                Constant::DB_TABLE_CUSTOMER_PRIMARY => $customerId,
                Constant::DB_TABLE_ACCOUNT => $account,
                Constant::DB_TABLE_IP => FunctionHelper::getClientIP(),
            ];
            ActivityClickLogService::addLog($storeId, $logData);

            static::getModel($storeId)->where(Constant::DB_TABLE_PRIMARY, $productId)->increment(Constant::DB_TABLE_CLICK); //更新产品点击数
        }

        return [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => Constant::PARAMETER_STRING_DEFAULT,
            Constant::RESPONSE_DATA_KEY => [],
        ];
    }

    /**
     * deal VT前台通用模板产品列表
     * @param array $storeId 商店id
     * @param boolean $actId 活动id
     * @param boolean $country  产品国家
     * @param boolean $type 模板类型
     * @param boolean $page 分页
     * @param boolean $pageSize 分页数
     * @return array|\Hyperf\Database\Model\Builder 列表数据|Builder
     */
    public static function getUniversaData($storeId, $actId, $country, $type, $page, $pageSize, $extData = []) {

        $params = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_COUNTRY => $country,
            Constant::DB_TABLE_MB_TYPE => $type,
            Constant::DB_TABLE_PRODUCT_STATUS => 1,
            Constant::REQUEST_PAGE => $page,
            Constant::REQUEST_PAGE_SIZE => $pageSize,
        ];

        $_data = static::getPublicData($params);
        $where = $_data[Constant::DB_EXECUTION_PLAN_WHERE];
        $order = [Constant::DB_TABLE_PRIMARY, 'DESC'];
        $pagination = $_data[Constant::DB_EXECUTION_PLAN_PAGINATION];
        $limit = data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10);
        $offset = data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0);

        $select = [
            'id as product_id',
            Constant::DB_TABLE_IMG_URL,
            Constant::DB_TABLE_NAME,
            Constant::DB_TABLE_DES,
            Constant::DB_TABLE_ASIN,
            Constant::DB_TABLE_COUNTRY,
            Constant::DB_TABLE_CREATED_AT,
            Constant::DB_TABLE_LISTING_PRICE,
            Constant::DB_TABLE_REGULAR_PRICE,
            Constant::DB_TABLE_QUERY_RESULTS,
            Constant::DB_TABLE_STAR,
            Constant::DB_TABLE_DES,
            Constant::DB_TABLE_DISCOUNT,
        ];
        $couponSelect = ['id as coupon_id', Constant::RESPONSE_CODE_KEY, Constant::DB_TABLE_ASIN, Constant::DB_TABLE_USE_TYPE, Constant::DB_TABLE_AMAZON_URL, 'satrt_time', 'end_time'];
        $amazonHostData = DictService::getListByType(Constant::AMAZON_HOST, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);


        $field = Constant::DB_TABLE_COUNTRY;
        $data = $amazonHostData;
        $dataType = Constant::PARAMETER_STRING_DEFAULT;
        $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
        $time = Constant::PARAMETER_STRING_DEFAULT;
        $glue = Constant::PARAMETER_STRING_DEFAULT;
        $isAllowEmpty = true;
        $default = data_get($amazonHostData, 'US', Constant::PARAMETER_STRING_DEFAULT);
        $callback = Constant::PARAMETER_ARRAY_DEFAULT;
        $only = Constant::PARAMETER_ARRAY_DEFAULT;

        $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];
        $handleData = [];
        $joinData = Constant::PARAMETER_ARRAY_DEFAULT;

        $withHandleData = [//
            Constant::PLATFORM_AMAZON => FunctionHelper::getExePlanHandleData(...$parameters),
            'amazon_asin_url' => FunctionHelper::getExePlanHandleData((Constant::PLATFORM_AMAZON . Constant::DB_EXECUTION_PLAN_CONNECTION . Constant::DB_TABLE_ASIN), Constant::PARAMETER_STRING_DEFAULT, [], Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $dateFormat, $time, '/dp/', $isAllowEmpty, $callback, $only), //亚马逊链接
            'activity_coupon.amazon_url' => FunctionHelper::getExePlanHandleData('activity_coupon.amazon_url{or}amazon_asin_url', Constant::PARAMETER_STRING_DEFAULT, [], Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only), //亚马逊链接
                //'activity_coupon.coupon_id' => FunctionHelper::getExePlanHandleData('activity_coupon.coupon_id', 555, [], Constant::DB_EXECUTION_PLAN_DATATYPE_STRING, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only), //亚马逊链接
        ];
        $with = [
            Constant::ACTIVITY_COUPON => FunctionHelper::getExePlan($storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $couponSelect, [Constant::DB_TABLE_USE_TYPE => 2, Constant::DB_TABLE_COUNTRY => $country, Constant::DB_EXECUTION_PLAN_GROUP => Constant::DB_EXECUTION_PLAN_GROUP_COMMON], [['id', 'DESC']], null, null, false, [], false, $joinData, [], $withHandleData, [], Constant::HAS_ONE, true, [Constant::DB_TABLE_ASIN => Constant::DB_TABLE_ASIN]), //关联优惠劵
        ];
        $unset = [Constant::ACTIVITY_COUPON, Constant::PLATFORM_AMAZON, 'amazon_asin_url'];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), Constant::PARAMETER_STRING_DEFAULT, $select, $where, [[Constant::DB_TABLE_CLICK, 'DESC'], $order], $limit, $offset, true, $pagination, false, $joinData, [], $handleData, $unset, '');

        $itemHandleDataCallback = [
            Constant::DB_TABLE_DISCOUNT_PRICE => function ($item) {
                return FunctionHelper::getDiscountPrice(data_get($item, Constant::DB_TABLE_LISTING_PRICE, Constant::PARAMETER_INT_DEFAULT), data_get($item, Constant::DB_TABLE_DISCOUNT, Constant::PARAMETER_INT_DEFAULT));
            },
            'expired' => function ($item) {
                $endTime = data_get($item, 'activity_coupon.end_time', null); //获取优惠劵结束时间
                $currentTime = Carbon::now()->toDateTimeString(); //获取当前时间
                if (strtotime($currentTime) > strtotime($endTime)) {
                    return 1;
                }
                return 0;
            },
        ];
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $itemHandleDataCallback, $only),
        ];

        $dataStructure = 'list';
        $flatten = true;
        $isGetQuery = false;
        $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);

        $avtivityData = ActivityService::getActivityData($storeId, $actId, ['start_at', 'end_at']);
        data_set($data, 'activity', $avtivityData);
        return $data;
    }

    /**
     * deal VT 前台更新通用产品点击次数
     * @param int $storeId 商城id
     * @param int $Id id
     * @param int $customerId 会员id
     * @param int $asin 产品asin
     * @return array
     */
    public static function dealClicks($storeId, $productId) {
        $model = static::getModel($storeId);
        $logData = [
            'click_type' => 'product',
            Constant::DB_TABLE_EXT_ID => $productId,
            Constant::DB_TABLE_EXT_TYPE => static::getModelAlias(),
            Constant::DB_TABLE_CUSTOMER_PRIMARY => Constant::PARAMETER_INT_DEFAULT,
            Constant::DB_TABLE_ACCOUNT => Constant::PARAMETER_STRING_DEFAULT,
            Constant::DB_TABLE_IP => FunctionHelper::getClientIP(),
        ];
        ActivityClickLogService::addLog($storeId, $logData);

        return $model->where(Constant::DB_TABLE_PRIMARY, $productId)->increment(Constant::DB_TABLE_CLICK); //产品点击数加1
    }

    /**
     * deal VT admin通用活动产品导入数据
     * @param array $excelData
     * @param string $type 模板类型
     * @param string $user 上传人
     * @param time $time 上传时间
     * @return array
     */
    public static function dealImportData($config, $realName, $storeId, $mb_type, $user, $time, $actId) {

        $rs = [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => Constant::PARAMETER_STRING_DEFAULT,
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
        ];

        $mbImgUrl = '';
        ExcelService::parseExcelFile((data_get($config, 'path', '') . '/' . $realName), $typeData, function ($row) use ($config, $realName, $storeId, $mb_type, $user, $time, $actId, &$mbImgUrl, &$rs) {

            $imgUrl = data_get($row, 0, Constant::PARAMETER_STRING_DEFAULT);

            if (empty($mbImgUrl)) {
                $mbImgUrl = trim(data_get($row, 1, Constant::PARAMETER_STRING_DEFAULT)); //移动主图
            }

            if ($mbImgUrl != '移动主图') {
                if (data_get($rs, Constant::RESPONSE_CODE_KEY, 0) == 1) {
                    data_set($rs, Constant::RESPONSE_CODE_KEY, 0);
                    data_set($rs, Constant::RESPONSE_MSG_KEY, '请检查上传文件的模板正确！');
                }
                return true;
            }

            if ($imgUrl == '产品主图' || empty($imgUrl)) {
                return true;
            }

            $tableData = [];

            $tableData[Constant::DB_TABLE_ACT_ID] = $actId;
            $tableData[Constant::DB_TABLE_UPLOAD_USER] = $user;
            $tableData[Constant::DB_TABLE_MB_TYPE] = $mb_type;
            $tableData[Constant::DB_TABLE_IMG_URL] = $imgUrl;
            $tableData[Constant::DB_TABLE_MB_IMG_URL] = data_get($row, 1, Constant::PARAMETER_STRING_DEFAULT); //移动主图
            $tableData[Constant::DB_TABLE_NAME] = data_get($row, 2, Constant::PARAMETER_STRING_DEFAULT); //产品标题
            $tableData[Constant::DB_TABLE_DES] = data_get($row, 3, Constant::PARAMETER_STRING_DEFAULT); //产品描述
            $tableData[Constant::DB_TABLE_SKU] = data_get($row, 4, Constant::PARAMETER_STRING_DEFAULT); //产品店铺sku
            $tableData[Constant::DB_TABLE_SHOP_SKU] = $tableData[Constant::DB_TABLE_SKU]; //产品店铺sku
            $tableData[Constant::DB_TABLE_ASIN] = data_get($row, 5, Constant::PARAMETER_STRING_DEFAULT); //产品asin
            $tableData[Constant::DB_TABLE_COUNTRY] = data_get($row, 6, Constant::PARAMETER_STRING_DEFAULT); //产品国家
            $tableData[Constant::DB_TABLE_ACTIVITY_NAME] = data_get($row, 7, Constant::PARAMETER_STRING_DEFAULT); //活动名称
            $tableData[Constant::DB_TABLE_STAR] = FunctionHelper::getStar(data_get($row, 8, Constant::PARAMETER_INT_DEFAULT)); //产品星级
            $tableData[Constant::DB_TABLE_DISCOUNT] = FunctionHelper::getDiscount(data_get($row, 9, Constant::PARAMETER_INT_DEFAULT)); //产品折扣

            static::increase($storeId, $tableData);
        });

        return $rs;
    }

    /**
     * deal VT admin活动产品单个添加
     * @param int $storeId 商城id
     * @param array $data 数据
     * @return int
     */
    public static function increase($storeId, $data) {

        $activityName = data_get($data, Constant::DB_TABLE_ACTIVITY_NAME, Constant::PARAMETER_STRING_DEFAULT);
        $actID = data_get($data, Constant::DB_TABLE_ACT_ID, 0);
        if (empty($actID)) {
            $idData = ActivityService::addActivity($storeId, $activityName, [Constant::DB_TABLE_ACT_TYPE => 9]); //活动类型 1:九宫格 2:转盘 3:砸金蛋 4:翻牌 5:邀请好友注册 6:上传图片投票 7:免费评测活动 8:会员deal 9:通用deal
            $actID = $idData[Constant::DB_TABLE_PRIMARY];
        }

        $where = [
            Constant::DB_TABLE_ACT_ID => $actID,
            Constant::DB_TABLE_COUNTRY => data_get($data, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_ASIN => data_get($data, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_MB_TYPE => data_get($data, Constant::DB_TABLE_MB_TYPE, 0),
        ];

        $getdata = [
            Constant::DB_TABLE_UPLOAD_USER => data_get($data, Constant::DB_TABLE_UPLOAD_USER, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_IMG_URL => data_get($data, Constant::DB_TABLE_IMG_URL, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_MB_IMG_URL => data_get($data, Constant::DB_TABLE_MB_IMG_URL, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_NAME => data_get($data, Constant::DB_TABLE_NAME, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_DES => data_get($data, Constant::DB_TABLE_DES, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_SKU => data_get($data, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT),
            Constant::DB_TABLE_SHOP_SKU => data_get($data, Constant::DB_TABLE_SHOP_SKU, Constant::PARAMETER_STRING_DEFAULT), //店铺sku
            Constant::DB_TABLE_STAR => FunctionHelper::getStar(data_get($data, Constant::DB_TABLE_STAR, Constant::PARAMETER_INT_DEFAULT)),
            Constant::DB_TABLE_DISCOUNT => FunctionHelper::getDiscount(data_get($data, Constant::DB_TABLE_DISCOUNT, Constant::PARAMETER_INT_DEFAULT)), //产品折扣
        ];

        return static::updateOrCreate($storeId, $where, $getdata);
    }

    /**
     * 获取产品价格信息
     * @param string $productAsin
     * @param string $productSku
     * @param string $productCountry
     * @param string $platform
     * @return array
     */
    public static function getProductPrice($productAsin, $productSku, $productCountry, $platform = Constant::PLATFORM_AMAZON) {
        return CompanyApiService::getPrice($productAsin, $productSku, $productCountry, $platform);
    }

    /**
     * 导入活动产品数据
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
        ];

        $actData = [];
        $productTypeData = DictService::getListByType('prize_type', Constant::DB_TABLE_DICT_VALUE, Constant::DB_TABLE_DICT_KEY); //获取类型 0:其他 1:礼品卡 2:coupon 3:实物 5:活动积分

        $countLimit = [
                //3 => 9,//邀请类活动实物产品最多9个，折扣码不限制  202009291232 holife邀请活动需要上传 12个实物 因此改为 不限制
        ];

        $connection = static::getModel($storeId, '')->getConnection();
        $connection->beginTransaction();
        try {
            ExcelService::parseExcelFile($fileFullPath, $typeData, function ($row) use ($storeId, $actId, $fileFullPath, $user, $productTypeData, &$actData, $countLimit, &$rs) {
                $sort = data_get($row, 0, Constant::PARAMETER_STRING_DEFAULT);
                if ($sort == '序号' || empty($sort)) {
                    return true;
                }

                /*                 * *****************处理产品 start********************************** */
                //实物唯一性：asin
                //coupon唯一性：名称+sku
                //礼品卡：名称
                //活动积分：名称
                $productName = data_get($row, 1, ''); //产品名称
                $productType = data_get($productTypeData, data_get($row, 2, ''), 0); //产品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:活动积分
                $typeValue = data_get($row, 3, Constant::PARAMETER_STRING_DEFAULT); //产品类型数据 0:其他 1:礼品卡 2:coupon 3:实物 5:活动积分
                $qty = data_get($row, 4, ''); //库存
                $productSku = data_get($row, 5, ''); //店铺sku
                $helpSum = data_get($row, 6, ''); //help_sum
                $asin = data_get($row, 7, ''); //asin
                $imgUrl = data_get($row, 8, ''); //商品主图
                $country = data_get($row, 9, '不限');
                $country = $country ? $country : '不限';
                $country = FunctionHelper::getDbCountry($country); //国家

                if (empty($productName) || $qty === '' || $helpSum === '' || empty($imgUrl)) {
                    $code = 2;
                    $msg = '产品名称，产品库存，被邀请者人数，图片链接地址，不得为空';
                    throw new \Exception($msg, $code);
                }

                $where = [
                    Constant::DB_TABLE_ACT_ID => $actId,
                    Constant::DB_TABLE_TYPE => $productType,
                ];

                if (in_array($productType, [3])) {//如果是 实物 就使用asin作为唯一性判断条件
                    data_set($where, Constant::DB_TABLE_ASIN, $asin);
                } else if (in_array($productType, [2])) {//如果是 coupon 就使用 名称+sku 作为唯一性判断条件
                    data_set($where, Constant::DB_TABLE_NAME, $productName);
                    data_set($where, Constant::DB_TABLE_SKU, $productSku);
                } else {//如果是 礼品卡/活动积分 就使用 名称 作为唯一性判断条件
                    data_set($where, Constant::DB_TABLE_NAME, $productName);
                }

                $productUniqueKey = 'product.' . implode('-', $where);
                if (data_get($actData, $productUniqueKey, null) === null) {
                    $productData = [
                        Constant::DB_TABLE_SORT => $sort, //排序
                        Constant::DB_TABLE_TYPE => $productType,
                        Constant::DB_TABLE_NAME => $productName,
                        Constant::DB_TABLE_SKU => $productSku, //店铺sku
                        Constant::DB_TABLE_SHOP_SKU => $productSku, //店铺sku
                        Constant::DB_TABLE_UPLOAD_USER => $user,
                        Constant::DB_TABLE_IMG_URL => $imgUrl, //商品主图
                        Constant::DB_TABLE_MB_IMG_URL => $imgUrl, //移动端商品主图
                        Constant::DB_TABLE_ASIN => $asin,
                        Constant::DB_TABLE_COUNTRY => $country, //国家
                        Constant::DB_TABLE_HELP_SUM => $helpSum, //助力总人数
                    ];
                    $productsData = static::updateOrCreate($storeId, $where, $productData);
                    data_set($actData, $productUniqueKey, $productsData, false);
                }

                $productId = data_get($actData, ($productUniqueKey . '.' . Constant::RESPONSE_DATA_KEY . '.' . Constant::DB_TABLE_PRIMARY), null);
                if (empty($productId)) {
                    return false;
                }
                /*                 * *****************处理产品 end********************************** */

                /*                 * **************处理产品 item start******************** */

                $productItemWhere = [
                    Constant::DB_TABLE_PRODUCT_ID => $productId,
                    Constant::DB_TABLE_COUNTRY => $country, //国家
                ];
                if (in_array($productType, [1, 2])) {//如果是 礼品卡/coupon 就使用 类型数据 作为唯一性判断条件
                    data_set($productItemWhere, Constant::DB_TABLE_TYPE_VALUE, $typeValue);
                }

                $productItem = [
                    Constant::DB_TABLE_TYPE => $productType,
                    Constant::DB_TABLE_TYPE_VALUE => $typeValue,
                    Constant::DB_TABLE_QTY => data_get($row, 4, 0), //库存
                    Constant::DB_TABLE_ASIN => $asin,
                    Constant::DB_TABLE_SKU => $productSku, //sku
                    Constant::DB_TABLE_NAME => $productName, //奖品名字
                ];
                ActivityProductItemService::updateOrCreate($storeId, $productItemWhere, $productItem);
            });

            //更新产品总库存
            $productIds = data_get($actData, ('product.*.' . Constant::RESPONSE_DATA_KEY . '.' . Constant::DB_TABLE_PRIMARY), []);
            if ($productIds) {
                $data = ActivityProductItemService::getModel($storeId)->select([Constant::DB_TABLE_PRODUCT_ID, DB::raw('sum(qty) as qty')])->buildWhere([Constant::DB_TABLE_PRODUCT_ID => $productIds])->groupBy(Constant::DB_TABLE_PRODUCT_ID)->pluck(Constant::DB_TABLE_QTY, Constant::DB_TABLE_PRODUCT_ID); //
                foreach ($data as $productId => $qty) {
                    static::update($storeId, [Constant::DB_TABLE_PRIMARY => $productId], [Constant::DB_TABLE_QTY => $qty]);
                }
            }

            //邀请类活动实物产品最多9个，折扣码不限制
            $productType = 3; //产品类型 0:其他 1:礼品卡 2:coupon 3:实物 5:活动积分
            $_countLimit = data_get($countLimit, $productType, null);
            if ($_countLimit !== null) {//如果活动有产品限制，就判断是否符合限制
                $where = [
                    Constant::DB_TABLE_ACT_ID => $actId,
                    Constant::DB_TABLE_TYPE => $productType,
                ];
                $count = static::getModel($storeId)->buildWhere($where)->count();
                if ($count > $_countLimit) {
                    $code = 0;
                    $msg = '邀请类活动产品上传超过限制(实物9个，折扣/积分不限)，请先将部分产品状态修改，再重新上传产品';
                    throw new \Exception($msg, $code); //超过限制
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
     * 导入投票产品
     * @param int $storeId 商城id
     * @param int $actId   活动id
     * @param string $fileFullPath 文件完整路径
     * @param string $user 上传人
     * @param array $requestData
     * @return array 导入结果
     */
    public static function importVoteProduct($storeId, $actId, $fileFullPath, $user, $requestData = []) {

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
        ];

        $actData = [];

        $connection = static::getModel($storeId, '')->getConnection();
        $connection->beginTransaction();
        try {
            ExcelService::parseExcelFile($fileFullPath, $typeData, function ($row) use ($storeId, $actId, $fileFullPath, $user, $requestData, &$actData, &$rs) {
                $sort = data_get($row, 0, Constant::PARAMETER_STRING_DEFAULT);
                if ($sort == '序号' || empty($sort)) {
                    return true;
                }

                /*                 * *****************处理产品 start********************************** */
                $isPrize = data_get($requestData, 'is_prize', 0) + 0; //投票活动时 是否活动奖项 1:是 0:否
                //唯一性：名称+产品图片
                $productName = data_get($row, 1, ''); //产品名称
                $productSku = data_get($row, 2, ''); //店铺sku
                $asin = data_get($row, 3, ''); //asin
                $imgUrl = data_get($row, 4, ''); //产品图片
                $country = data_get($row, 5, '不限');
                $country = $country ? $country : '不限';
                $country = FunctionHelper::getDbCountry($country); //国家
                $des = data_get($row, 6, ''); //产品描述
                $star = data_get($row, 7, 0.0); //商品星级 0-5

                if (empty($productName) || empty($imgUrl)) {
                    $code = 2;
                    $msg = '标题，图片链接，必填';
                    throw new \Exception($msg, $code);
                }

                $where = [
                    Constant::DB_TABLE_ACT_ID => $actId,
                    Constant::DB_TABLE_NAME => $productName, //产品名称
                    Constant::DB_TABLE_IMG_URL => $imgUrl, //商品主图
                    Constant::DB_TABLE_IS_PRIZE => $isPrize,
                ];

                $productUniqueKey = 'product.' . implode('-', $where);
                if (data_get($actData, $productUniqueKey, null) === null) {
                    $productData = [
                        Constant::DB_TABLE_SORT => $sort, //排序
                        Constant::DB_TABLE_NAME => $productName, //产品名称
                        Constant::DB_TABLE_SKU => $productSku, //店铺sku
                        Constant::DB_TABLE_SHOP_SKU => $productSku, //店铺sku
                        Constant::DB_TABLE_UPLOAD_USER => $user,
                        Constant::DB_TABLE_IMG_URL => $imgUrl, //商品主图
                        Constant::DB_TABLE_MB_IMG_URL => $imgUrl, //移动端商品主图
                        Constant::DB_TABLE_ASIN => $asin,
                        Constant::DB_TABLE_COUNTRY => $country, //国家
                        Constant::DB_TABLE_DES => $des, //产品描述
                        Constant::DB_TABLE_STAR => floatval($star), //商品星级 0-5
                    ];
                    $productsData = static::updateOrCreate($storeId, $where, $productData);
                    data_set($actData, $productUniqueKey, $productsData, false);

                    $productId = data_get($productsData, Constant::RESPONSE_DATA_KEY . '.' . Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT); //产品id
                    if (empty($productId)) {
                        $code = 0;
                        $msg = '导入失败';
                        throw new \Exception($msg, $code);
                    }

                    //投票产品同步到投票item中
                    if ($isPrize === 0) {
                        $extId = $productId; //关联id
                        $extType = static::getModelAlias(); //关联模型
                        $voteData = VoteService::sysVoteDataFromActivityProduct($storeId, $actId, $extId, $extType, $productName, $imgUrl, $des);

                        $voteId = data_get($voteData, Constant::RESPONSE_DATA_KEY . '.' . Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT); //产品id
                        if (empty($voteId)) {
                            $code = 3;
                            $msg = '导入失败';
                            throw new \Exception($msg, $code);
                        }
                    }
                }
                /*                 * *****************处理奖品 end********************************** */
            });

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
     * 导入活动产品
     * @param int $storeId 商城id
     * @param int $actId   活动id
     * @param string $fileFullPath 文件完整路径
     * @param string $user 上传人
     * @return array 导入结果
     */
    public static function importActProduct($storeId, $actId, $fileFullPath, $user, $requestData = []) {

        $data = [];

        $actWhere = [Constant::DB_TABLE_PRIMARY => $actId];
        $actData = ActivityService::existsOrFirst($storeId, '', $actWhere, true);
        $actType = data_get($actData, Constant::DB_TABLE_ACT_TYPE, 0); //活动类型 1:九宫格 2:转盘 3:砸金蛋 4:翻牌 5:邀请好友注册 6:上传图片投票

        switch ($actType) {
            case 1:
            case 2:
            case 3:
            case 4:
                $data = ActivityPrizeService::import($storeId, $actId, $fileFullPath, $user);
                break;

            case 5://邀请好友注册
                $data = static::import($storeId, $actId, $fileFullPath, $user);
                break;

            case 6://上传图片投票
                $data = static::importVoteProduct($storeId, $actId, $fileFullPath, $user, $requestData);
                break;

            default:

                break;
        }

        static::clear();

        return $data;
    }

    /**
     * 删除活动产品
     * @param int $storeId 商城id
     * @param array $uniqueIds 产品id
     * @return array 删除结果
     */
    public static function delActProducts($storeId, $uniqueIds) {

        $rs = [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => '删除成功',
            Constant::RESPONSE_DATA_KEY => []
        ];

        if (empty($storeId) || empty($uniqueIds)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT);
            data_set($rs, Constant::RESPONSE_MSG_KEY, '删除失败');
            return $rs;
        }

        $ids = static::getActPublicIds($uniqueIds);
        foreach ($ids as $make => $value) {
            static::getModel($storeId, '', [], $make)->buildWhere([Constant::DB_TABLE_PRIMARY => $value])->delete();
        }

        static::clear();

        return $rs;
    }

    /**
     * 获取活动产品item
     * @param int $storeId 商城id
     * @param int $id 产品id ActivityProduct-58  ActivityPrize-1
     * @return type
     */
    public static function getActProductItems($storeId, $id) {

        $idData = explode('-', $id);
        $tableAlias = data_get($idData, 0, Constant::PARAMETER_STRING_DEFAULT);

        $tableAliasData = [
            ActivityPrizeService::getModelAlias() => [
                Constant::DB_EXECUTION_PLAN_FROM => 'activity_prizes as p',
                Constant::DB_EXECUTION_PLAN_FROM . '_item' => 'activity_prize_items as i',
                'foreign_key' => Constant::DB_TABLE_PRIZE_ID,
                Constant::DB_EXECUTION_PLAN_SELECT => [
                    'i.' . Constant::DB_TABLE_MAX,
                    'i.' . Constant::DB_TABLE_WINNING_VALUE,
                ],
                Constant::DB_EXECUTION_PLAN_CALLBACK => [],
            ],
            static::getModelAlias() => [
                Constant::DB_EXECUTION_PLAN_FROM => 'activity_products as p',
                Constant::DB_EXECUTION_PLAN_FROM . '_item' => 'activity_product_items as i',
                'foreign_key' => Constant::DB_TABLE_PRODUCT_ID,
                Constant::DB_EXECUTION_PLAN_SELECT => [
                    'p.' . Constant::DB_TABLE_HELP_SUM,
                    'p.' . Constant::DB_TABLE_IS_PRIZE,
                    'p.' . Constant::DB_TABLE_SKU . ' as p_' . Constant::DB_TABLE_SKU,
                    'p.' . Constant::DB_TABLE_ASIN . ' as p_' . Constant::DB_TABLE_ASIN,
                    'p.' . Constant::DB_TABLE_COUNTRY . ' as p_' . Constant::DB_TABLE_COUNTRY,
                    'p.' . Constant::DB_TABLE_QTY . ' as p_' . Constant::DB_TABLE_QTY,
                    'p.' . Constant::DB_TABLE_DES . ' as p_' . Constant::DB_TABLE_DES,
                    'p.' . Constant::DB_TABLE_STAR . ' as p_' . Constant::DB_TABLE_STAR,
                ],
            ],
        ];

        $select = Arr::collapse([
                    [
                        static::$act . '.' . Constant::DB_TABLE_PRIMARY . ' as act_' . Constant::DB_TABLE_PRIMARY,
                        static::$act . '.' . Constant::DB_TABLE_NAME . ' as act_' . Constant::DB_TABLE_NAME,
                        static::$act . '.' . Constant::DB_TABLE_ACT_TYPE,
                        'p.' . Constant::DB_TABLE_NAME,
                        'p.' . Constant::DB_TABLE_IMG_URL,
                        'p.' . Constant::DB_TABLE_SORT,
                        'i.' . Constant::DB_TABLE_PRIMARY . ' as item_id',
                        'i.' . Constant::DB_TABLE_SKU,
                        'i.' . Constant::DB_TABLE_ASIN,
                        'i.' . Constant::DB_TABLE_COUNTRY,
                        'i.' . Constant::DB_TABLE_QTY,
                        'i.' . Constant::DB_TABLE_TYPE,
                        'i.' . Constant::DB_TABLE_TYPE_VALUE,
                        'i.' . Constant::DB_TABLE_NAME . ' as item_' . Constant::DB_TABLE_NAME,
                        'p.' . Constant::DB_TABLE_TYPE . ' as p_' . Constant::DB_TABLE_TYPE,
                        'p.' . Constant::DB_TABLE_TYPE_VALUE . ' as p_' . Constant::DB_TABLE_TYPE_VALUE,
                    ], data_get($tableAliasData, $tableAlias . '.' . Constant::DB_EXECUTION_PLAN_SELECT, Constant::PARAMETER_ARRAY_DEFAULT)
        ]);
        $where = [
            'p.' . Constant::DB_TABLE_PRIMARY => data_get($idData, 1, Constant::PARAMETER_INT_DEFAULT),
        ];
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
                Constant::DB_EXECUTION_PLAN_BUILDER => null,
                Constant::DB_EXECUTION_PLAN_MAKE => ActivityService::getModelAlias(),
                Constant::DB_EXECUTION_PLAN_FROM => data_get($tableAliasData, $tableAlias . '.' . Constant::DB_EXECUTION_PLAN_FROM, Constant::PARAMETER_STRING_DEFAULT),
                Constant::DB_EXECUTION_PLAN_JOIN_DATA => [
                    [
                        Constant::DB_EXECUTION_PLAN_TABLE => 'activities as act',
                        Constant::DB_EXECUTION_PLAN_FIRST => function ($join) {
                            $join->on([['p.' . Constant::DB_TABLE_ACT_ID, '=', 'act.' . Constant::DB_TABLE_PRIMARY]]);
                        },
                        Constant::DB_TABLE_OPERATOR => null,
                        Constant::DB_EXECUTION_PLAN_SECOND => null,
                        Constant::DB_TABLE_TYPE => 'left',
                    ],
                    [
                        Constant::DB_EXECUTION_PLAN_TABLE => data_get($tableAliasData, ($tableAlias . '.' . Constant::DB_EXECUTION_PLAN_FROM . '_item'), Constant::PARAMETER_STRING_DEFAULT),
                        Constant::DB_EXECUTION_PLAN_FIRST => function ($join) use($tableAliasData, $tableAlias) {//
                            $join->on([['i.' . data_get($tableAliasData, ($tableAlias . '.foreign_key'), Constant::PARAMETER_STRING_DEFAULT), '=', 'p.' . Constant::DB_TABLE_PRIMARY]])->where('i.' . Constant::DB_TABLE_STATUS, '=', 1);
                        },
                        Constant::DB_TABLE_OPERATOR => null,
                        Constant::DB_EXECUTION_PLAN_SECOND => null,
                        Constant::DB_TABLE_TYPE => 'left',
                    ],
                ],
                Constant::DB_EXECUTION_PLAN_SELECT => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                Constant::DB_EXECUTION_PLAN_ORDERS => Constant::PARAMETER_ARRAY_DEFAULT,
                Constant::DB_EXECUTION_PLAN_LIMIT => null,
                Constant::DB_EXECUTION_PLAN_OFFSET => null,
                Constant::DB_EXECUTION_PLAN_IS_PAGE => false,
                Constant::DB_EXECUTION_PLAN_PAGINATION => Constant::PARAMETER_ARRAY_DEFAULT,
                Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_COUNT => false,
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                    Constant::DB_TABLE_NAME => [//name
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_NAME . '{or}item_' . Constant::DB_TABLE_NAME,
                        Constant::RESPONSE_DATA_KEY => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT,
                    ],
                    Constant::DB_TABLE_SKU => [//sku
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_SKU . '{or}p_' . Constant::DB_TABLE_SKU,
                        Constant::RESPONSE_DATA_KEY => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT,
                    ],
                    Constant::DB_TABLE_ASIN => [//asin
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_ASIN . '{or}p_' . Constant::DB_TABLE_ASIN,
                        Constant::RESPONSE_DATA_KEY => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT,
                    ],
                    Constant::DB_TABLE_COUNTRY => [//country
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_COUNTRY . '{or}p_' . Constant::DB_TABLE_COUNTRY,
                        Constant::RESPONSE_DATA_KEY => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT,
                    ],
                    Constant::DB_TABLE_QTY => [//库存
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_QTY . '{or}p_' . Constant::DB_TABLE_QTY,
                        Constant::RESPONSE_DATA_KEY => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT,
                    ],
                    Constant::DB_TABLE_TYPE => [//类型
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_TYPE . '{or}p_' . Constant::DB_TABLE_TYPE,
                        Constant::RESPONSE_DATA_KEY => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT,
                    ],
                    Constant::DB_TABLE_TYPE_VALUE => [//类型值
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_TYPE_VALUE . '{or}p_' . Constant::DB_TABLE_TYPE_VALUE,
                        Constant::RESPONSE_DATA_KEY => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT,
                    ],
                    Constant::DB_TABLE_DES => [//产品描述
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_DES . '{or}p_' . Constant::DB_TABLE_DES,
                        Constant::RESPONSE_DATA_KEY => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT,
                    ],
                    Constant::DB_TABLE_STAR => [//产品星级
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_STAR . '{or}p_' . Constant::DB_TABLE_STAR,
                        Constant::RESPONSE_DATA_KEY => [],
                        Constant::DB_EXECUTION_PLAN_DATATYPE => Constant::DB_EXECUTION_PLAN_DATATYPE_STRING,
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_GLUE => Constant::PARAMETER_STRING_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_STRING_DEFAULT,
                    ],
                ],
                Constant::DB_EXECUTION_PLAN_UNSET => [
                    Constant::DB_TABLE_MAX,
                    Constant::DB_TABLE_WINNING_VALUE,
                    'p_' . Constant::DB_TABLE_SKU,
                    'p_' . Constant::DB_TABLE_ASIN,
                    'p_' . Constant::DB_TABLE_COUNTRY,
                    'p_' . Constant::DB_TABLE_QTY,
                    'p_' . Constant::DB_TABLE_TYPE,
                    'p_' . Constant::DB_TABLE_TYPE_VALUE,
                    'p_' . Constant::DB_TABLE_DES,
                    'p_' . Constant::DB_TABLE_STAR,
                    'item_' . Constant::DB_TABLE_NAME,
                ],
            ],
            Constant::DB_EXECUTION_PLAN_WITH => [
            ],
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => [
                Constant::DB_EXECUTION_PLAN_FIELD => null, //数据字段
                Constant::RESPONSE_DATA_KEY => [], //数据映射map
                Constant::DB_EXECUTION_PLAN_DATATYPE => '', //数据类型
                Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '', //数据格式
                Constant::DB_EXECUTION_PLAN_TIME => '', //时间处理句柄
                Constant::DB_EXECUTION_PLAN_GLUE => '', //分隔符或者连接符
                Constant::DB_EXECUTION_PLAN_IS_ALLOW_EMPTY => true, //是否允许为空 true：是  false：否
                Constant::DB_EXECUTION_PLAN_DEFAULT => '', //默认值$default
                Constant::DB_EXECUTION_PLAN_CALLBACK => Arr::collapse([
                    [
                        Constant::DB_TABLE_PRIMARY => function ($item) use($id) {
                            return $id;
                        },
                        Constant::DB_TABLE_COUNTRY => function ($item) {
                            return data_get($item, Constant::DB_TABLE_COUNTRY, '');
                        },
                        'probability' => function ($item) {//中奖概率
                            $winningValue = data_get($item, Constant::DB_TABLE_WINNING_VALUE, 0);
                            $winningValue = $winningValue ? $winningValue : 0;
                            $max = data_get($item, Constant::DB_TABLE_MAX, 0);
                            $max = $max ? $max : 100;
                            return floatval($winningValue / $max) * 100;
                        },
                    ], data_get($tableAliasData, ($tableAlias . '.' . Constant::DB_EXECUTION_PLAN_CALLBACK), Constant::PARAMETER_ARRAY_DEFAULT)
                ]),
                'only' => [
                ],
            ],
        ];

        $dataStructure = 'list';
        $flatten = false;
        $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);

        return [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => '',
            Constant::RESPONSE_DATA_KEY => $data,
        ];
    }

    /**
     * 更新活动产品
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param array $itemData 活动产品
     * @param array $requestData 请求数据
     * @return array 响应数据
     */
    public static function input($storeId, $actId, $itemData, $requestData = []) {

        $data = Constant::PARAMETER_ARRAY_DEFAULT;
        foreach ($itemData as $item) {
            $id = data_get($item, Constant::DB_TABLE_PRIMARY, null);
            if ($id) {
                $idData = explode('-', $id);
                $id = data_get($idData, 1, Constant::PARAMETER_INT_DEFAULT);
            }

            $id = $id ? $id : -1;

            $where = [
                Constant::DB_TABLE_PRIMARY => $id,
            ];

            $productName = data_get($item, Constant::DB_TABLE_NAME, Constant::PARAMETER_STRING_DEFAULT);
            $isPrize = data_get($item, Constant::DB_TABLE_IS_PRIZE, Constant::PARAMETER_INT_DEFAULT) + 0;

            $user = data_get($requestData, Constant::DB_TABLE_OPERATOR, Constant::PARAMETER_STRING_DEFAULT);
            $imgUrl = data_get($item, Constant::DB_TABLE_IMG_URL, Constant::PARAMETER_STRING_DEFAULT); //商品主图
            $des = data_get($item, Constant::DB_TABLE_DES, Constant::PARAMETER_STRING_DEFAULT);
            $productData = [
                Constant::DB_TABLE_SORT => data_get($item, Constant::DB_TABLE_SORT, Constant::PARAMETER_INT_DEFAULT), //排序
                Constant::DB_TABLE_ACT_ID => $actId,
                Constant::DB_TABLE_NAME => $productName,
                Constant::DB_TABLE_SKU => data_get($item, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT), //店铺sku
                Constant::DB_TABLE_SHOP_SKU => data_get($item, Constant::DB_TABLE_SKU, Constant::PARAMETER_STRING_DEFAULT), //店铺sku
                Constant::DB_TABLE_IMG_URL => $imgUrl, //商品主图
                Constant::DB_TABLE_MB_IMG_URL => data_get($item, Constant::DB_TABLE_IMG_URL, Constant::PARAMETER_STRING_DEFAULT), //移动端商品主图
                Constant::DB_TABLE_ASIN => data_get($item, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT),
                Constant::DB_TABLE_COUNTRY => data_get($item, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT), //国家
                Constant::DB_TABLE_DES => $des, //产品描述
                Constant::DB_TABLE_STAR => FunctionHelper::getStar(data_get($item, Constant::DB_TABLE_STAR, Constant::PARAMETER_INT_DEFAULT)), //商品星级 0-5
                Constant::DB_TABLE_UPLOAD_USER => $user,
                Constant::DB_TABLE_IS_PRIZE => $isPrize,
            ];

            if ($id == -1) {
                //唯一性：名称+产品图片
                $where = [
                    Constant::DB_TABLE_ACT_ID => $actId,
                    Constant::DB_TABLE_NAME => $productName,
                    Constant::DB_TABLE_IMG_URL => $imgUrl, //商品主图
                    Constant::DB_TABLE_IS_PRIZE => $isPrize,
                ];
            }
            $productsData = static::updateOrCreate($storeId, $where, $productData);

            $actType = data_get($requestData, Constant::DB_TABLE_ACT_TYPE, Constant::PARAMETER_INT_DEFAULT); //活动类型 1:九宫格 2:转盘 3:砸金蛋 4:翻牌 5:邀请好友注册 6:上传图片投票
            if ($actType == 6 && $isPrize === 0) {
                $extId = data_get($productsData, Constant::RESPONSE_DATA_KEY . '.' . Constant::DB_TABLE_PRIMARY, Constant::PARAMETER_INT_DEFAULT); //关联id
                $extType = static::getModelAlias(); //关联模型
                VoteService::sysVoteDataFromActivityProduct($storeId, $actId, $extId, $extType, $productName, $imgUrl, $des);
            }

            $data[] = $productsData;
        }

        static::clear();

        return [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => '',
            Constant::RESPONSE_DATA_KEY => $data,
        ];
    }

    /**
     * 编辑活动产品Items
     * @param int $storeId 商城id
     * @param string $id 活动产品id ActivityProduct-46
     * @param array $itemData 活动产品items
     * @param array $requestData 请求数据
     * @return string
     */
    public static function editActProductItems($storeId, $id, $itemData, $requestData = []) {

        $rs = [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => '',
            Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
        ];

        $idData = explode('-', $id);
        $tableAlias = data_get($idData, 0, Constant::PARAMETER_STRING_DEFAULT);
        $id = data_get($idData, 1, Constant::PARAMETER_INT_DEFAULT);
        if (empty($tableAlias) || empty($id)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT);
            data_set($rs, Constant::RESPONSE_MSG_KEY, '非法参数');
            return $rs;
        }

        switch ($tableAlias) {
            case ActivityPrizeService::getModelAlias():
                $rs = ActivityPrizeItemService::input($storeId, $id, $itemData, $requestData);
                break;

            case static::getModelAlias():
                $country = '';
                $where = [Constant::DB_TABLE_PRIMARY => $id];
                $getData = true;
                $select = [Constant::DB_TABLE_ACT_ID];
                $productData = static::existsOrFirst($storeId, $country, $where, $getData, $select);
                if (empty($productData)) {
                    data_set($rs, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT);
                    data_set($rs, Constant::RESPONSE_MSG_KEY, '产品不存在，操作执行失败');
                    return $rs;
                }

                $actId = data_get($productData, Constant::DB_TABLE_ACT_ID, Constant::PARAMETER_INT_DEFAULT);
                if (empty($actId)) {
                    data_set($rs, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT);
                    data_set($rs, Constant::RESPONSE_MSG_KEY, '产品没有关联活动');
                    return $rs;
                }

                $actWhere = [Constant::DB_TABLE_PRIMARY => $actId];
                $select = [Constant::DB_TABLE_ACT_TYPE];
                $activityData = ActivityService::existsOrFirst($storeId, $country, $actWhere, $getData, $select);
                if (empty($activityData)) {
                    data_set($rs, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT);
                    data_set($rs, Constant::RESPONSE_MSG_KEY, '活动不存在');
                    return $rs;
                }

                $actType = data_get($activityData, Constant::DB_TABLE_ACT_TYPE, Constant::PARAMETER_INT_DEFAULT); //活动类型 1:九宫格 2:转盘 3:砸金蛋 4:翻牌 5:邀请好友注册 6:上传图片投票
                if (in_array($actType, [5])) {//如果是邀请好友注册
                    $rs = ActivityProductItemService::input($storeId, $id, $itemData, $requestData);
                } else {//如果是投票活动产品
                    data_set($requestData, Constant::DB_TABLE_ACT_TYPE, $actType);
                    $rs = static::input($storeId, $actId, $itemData, $requestData);
                }

                break;

            default:
                data_set($rs, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT);
                data_set($rs, Constant::RESPONSE_MSG_KEY, '非法参数');
                break;
        }

        static::clear();

        return $rs;
    }

    /**
     * 获取要清空的tags
     * @return array
     */
    public static function getClearTags() {
        return ['activity'];
    }

    /**
     * 删除活动产品Items
     * @param int $storeId 商城id
     * @param string $id 活动产品id ActivityProduct-46
     * @param array $itemData 活动产品items
     * @param array $requestData 请求数据
     * @return string
     */
    public static function delActProductItems($storeId, $id, $itemIds, $requestData = []) {

        $rs = [
            Constant::RESPONSE_CODE_KEY => 1,
            Constant::RESPONSE_MSG_KEY => '',
            Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
        ];

        $idData = explode('-', $id);
        $tableAlias = data_get($idData, 0, Constant::PARAMETER_STRING_DEFAULT);
        $id = data_get($idData, 1, Constant::PARAMETER_INT_DEFAULT);
        if (empty($tableAlias) || empty($id)) {
            data_set($rs, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT);
            data_set($rs, Constant::RESPONSE_MSG_KEY, '非法参数');
            return $rs;
        }

        $itemIds = is_array($itemIds) ? $itemIds : [$itemIds];
        switch ($tableAlias) {
            case ActivityPrizeService::getModelAlias():
                $where = [
                    Constant::DB_TABLE_PRIMARY => $itemIds,
                    Constant::DB_TABLE_PRIZE_ID => $id,
                ];
                ActivityPrizeItemService::delete($storeId, $where);

                $isExists = ActivityPrizeItemService::existsOrFirst($storeId, '', [Constant::DB_TABLE_PRIZE_ID => $id]);
                if (empty($isExists)) {//如果item被全部删除，就删除对应的主表记录
                    ActivityPrizeService::delete($storeId, [Constant::DB_TABLE_PRIMARY => $id]);
                }
                break;

            case static::getModelAlias():
                $where = [
                    Constant::DB_TABLE_PRIMARY => $itemIds,
                    Constant::DB_TABLE_PRODUCT_ID => $id,
                ];
                ActivityProductItemService::delete($storeId, $where);

                $isExists = ActivityProductItemService::existsOrFirst($storeId, '', [Constant::DB_TABLE_PRODUCT_ID => $id]);
                if (empty($isExists)) {//如果item被全部删除，就删除对应的主表记录
                    static::delete($storeId, [Constant::DB_TABLE_PRIMARY => $id]);

                    //删除投票item
                    $voteWhere = [
                        Constant::DB_TABLE_EXT_ID => $id, //关联id
                        Constant::DB_TABLE_EXT_TYPE => static::getModelAlias(), //关联模型
                    ];
                    VoteService::delete($storeId, $voteWhere);
                }

                break;

            default:
                data_set($rs, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT);
                data_set($rs, Constant::RESPONSE_MSG_KEY, '非法参数');
                break;
        }

        static::clear();

        return $rs;
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
    public static function getFreeTestingList($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {
        $isExport = data_get($params, 'is_export', data_get($params,'srcParameters.0.is_export'));
        $customerId = data_get($params, Constant::DB_TABLE_CUSTOMER_PRIMARY, Constant::ORDER_STATUS_PENDING_INT);

        //排序
        $order = [];
        if (data_get($params, Constant::DB_TABLE_SORT, Constant::PARAMETER_STRING_DEFAULT) == Constant::DB_TABLE_SORT) {
            $order[] = [Constant::DB_TABLE_SORT, Constant::DB_EXECUTION_PLAN_ORDER_ASC];
        }
        $order[] = ['activity_products.'.Constant::DB_TABLE_PRIMARY, Constant::DB_EXECUTION_PLAN_ORDER_DESC];

        //查询条件
        $_data = static::getPublicData($params, $order);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);
        $order = data_get($params, Constant::ORDER_BY, data_get($_data, Constant::ORDER, []));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::ACT_LIMIT_KEY, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, Constant::PARAMETER_INT_DEFAULT));
        $storeId = Arr::get($params, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);

        $select = $select ? $select : ['*'];

        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $joinData = Constant::PARAMETER_ARRAY_DEFAULT;
        $handleData = Constant::PARAMETER_ARRAY_DEFAULT;

        //获取申请记录id
        if (!$isExport) {
            $joinData = [
                FunctionHelper::getExePlanJoinData('activity_applies as aa', function ($join) use ($customerId) {
                    $join->on([['aa.' . Constant::DB_TABLE_EXT_ID, '=', 'activity_products.' . Constant::DB_TABLE_PRIMARY]])
                        ->where('aa.' . Constant::DB_TABLE_CUSTOMER_PRIMARY, $customerId)->where('aa.' . Constant::DB_TABLE_STATUS, 1);
                }),
            ];
        }

        //产品属性关联
        $platform = FunctionHelper::getUniqueId(Constant::PLATFORM_SHOPIFY);
        $withWhere[] = [
            [Constant::DB_TABLE_STORE_ID, '=', $storeId],
            [Constant::DB_TABLE_PLATFORM, '=', $platform],
            [Constant::OWNER_RESOURCE, '=', static::getMake()],
            [Constant::NAME_SPACE, '=', 'free_testing'],
        ];
        $withSelect = [Constant::OWNER_ID, Constant::DB_TABLE_KEY, Constant::DB_TABLE_VALUE];
        $with[Constant::METAFIELDS] = FunctionHelper::getExePlan(
            $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $withSelect, $withWhere, [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false
        );

        $unset = [Constant::METAFIELDS, 'product_des', Constant::DB_TABLE_PRODUCT_COUNTRY];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), 'activity_products', $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        //数据处理
        $nowTimestamp = Carbon::now()->timestamp;
        $nowTimesAt = Carbon::now()->toDateTimeString();
        $amazonHostData = DictService::getListByType(Constant::AMAZON_HOST, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);
        $itemHandleDataCallback = [
            Constant::DB_TABLE_COUNTRY => function($item) use ($isExport) {
                $countries = array_values(array_filter(array_map(function ($it) {
                    return $it[Constant::DB_TABLE_KEY] == Constant::DB_TABLE_COUNTRY ? $it[Constant::DB_TABLE_VALUE] : '';
                }, $item[Constant::METAFIELDS])));
                empty($countries) && !empty($item[Constant::DB_TABLE_PRODUCT_COUNTRY]) && $countries[] = $item[Constant::DB_TABLE_PRODUCT_COUNTRY];
                return $isExport ? implode(';', $countries) : $countries;
            },
            Constant::DB_TABLE_DES => function($item) use ($isExport) {
                $des = array_values(array_filter(array_map(function ($it) {
                    return $it[Constant::DB_TABLE_KEY] == Constant::DB_TABLE_DES ? $it[Constant::DB_TABLE_VALUE] : '';
                }, $item[Constant::METAFIELDS])));
                empty($des) && !empty($item['product_des']) && $des = explode('{@#}', $item['product_des']);
                return $isExport ? implode(PHP_EOL, $des) : $des;
            },
            'country_urls' => function($item) use($params, $amazonHostData, $isExport) {
                $result = [];
                //国家优先取metaFields表的数据，再取activity_product表的
                $countries = array_values(array_filter(array_map(function ($it) {
                    return $it[Constant::DB_TABLE_KEY] == Constant::DB_TABLE_COUNTRY ? $it[Constant::DB_TABLE_VALUE] : '';
                }, $item[Constant::METAFIELDS])));
                empty($countries) && !empty($item[Constant::DB_TABLE_PRODUCT_COUNTRY]) && $countries[] = $item[Constant::DB_TABLE_PRODUCT_COUNTRY];

                $urls = [];
                //从metaFields表获取产品country=>url数据
                $metaFields = $item[Constant::METAFIELDS];
                if (!empty($metaFields)) {
                    foreach ($metaFields as $metaField) {
                        if ($metaField[Constant::DB_TABLE_KEY] == 'url') {
                            $exp = explode('{@#}', $metaField[Constant::DB_TABLE_VALUE]);
                            $urls[data_get($exp, '0', '')] = data_get($exp, '1', '');
                        }
                    }
                }

                foreach ($countries as $country) {
                    $metaFieldUrl = data_get($urls, $country, '');
                    if (!empty($metaFieldUrl)) {
                        $result[] = [
                            Constant::DB_TABLE_COUNTRY => $country,
                            'url' => data_get($urls, $country, ''),
                        ];
                        continue;
                    }

                    if (!empty($item[Constant::FILE_URL])) {
                        $result[] = [
                            Constant::DB_TABLE_COUNTRY => $country,
                            'url' => $item[Constant::FILE_URL],
                        ];
                        continue;
                    }

                    $amazonHost = data_get($amazonHostData, $country, Constant::PARAMETER_STRING_DEFAULT);
                    if (!empty($amazonHost)) {
                        $result[] = [
                            Constant::DB_TABLE_COUNTRY => $country,
                            'url' => $amazonHost . '/dp/' . $item[Constant::DB_TABLE_ASIN],
                        ];
                    }
                }

                return $result;
            },
            'expire_time' => function($item) {
                //默认时间处理成空
                return $item[Constant::EXPIRE_TIME] != '2000-01-01 00:00:00' ? $item[Constant::EXPIRE_TIME] : '';
            },
            Constant::DB_TABLE_PRODUCT_STATUS => function($item) use ($isExport) {
                return $isExport ? static::$productStatus[$item[Constant::DB_TABLE_PRODUCT_STATUS]] : $item[Constant::DB_TABLE_PRODUCT_STATUS];
            },
            'url' => function($item) {
                return data_get($item['country_urls'], '0.url', '');
            },
            'current_time' => function($item) use ($nowTimesAt) {
                return $nowTimesAt;
            },
            'countdown' => function($item) use ($nowTimestamp) {
                $expireTime = $item[Constant::EXPIRE_TIME] != '2000-01-01 00:00:00' ? $item[Constant::EXPIRE_TIME] : '';
                if (empty($expireTime)) {
                    return 0;
                }
                return strtotime($expireTime) - $nowTimestamp;
            }
        ];

        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
        ];

        if (data_get($params, Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY, false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_PARENT . '.' . Constant::DB_EXECUTION_PLAN_HANDLE_DATA, Constant::PARAMETER_ARRAY_DEFAULT);
            data_set($dbExecutionPlan, Constant::DB_EXECUTION_PLAN_WITH, Constant::PARAMETER_ARRAY_DEFAULT);
        }

        $flatten = true;
        $dataStructure = 'list';
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * 评测产品编辑
     * @param $storeId
     * @param $id
     * @param $params
     * @return array|bool
     */
    public static function editFreeTesting($storeId, $id, $params) {
        $url = data_get($params, Constant::FILE_URL, Constant::PARAMETER_STRING_DEFAULT);
        $imgUrl = data_get($params, Constant::DB_TABLE_IMG_URL, Constant::PARAMETER_STRING_DEFAULT);
        $mbImgUrl = data_get($params, Constant::DB_TABLE_MB_IMG_URL, Constant::PARAMETER_STRING_DEFAULT);
        $name = data_get($params, Constant::DB_TABLE_NAME, Constant::PARAMETER_STRING_DEFAULT);
        $shopSku = data_get($params, Constant::DB_TABLE_SHOP_SKU, Constant::PARAMETER_STRING_DEFAULT);
        $asin = data_get($params, Constant::DB_TABLE_ASIN, Constant::PARAMETER_STRING_DEFAULT);
        $qty = data_get($params, Constant::DB_TABLE_QTY, NULL);
        $showApply = data_get($params, 'show_apply', NULL);
        $productStatus = data_get($params, Constant::DB_TABLE_PRODUCT_STATUS, NULL);
        $sort = data_get($params, 'sort', NULL);
        $expireTime = data_get($params, Constant::EXPIRE_TIME, Constant::PARAMETER_STRING_DEFAULT);

        $update = [];
        !empty($url) && $update[Constant::FILE_URL] = $url;
        !empty($imgUrl) && $update[Constant::DB_TABLE_IMG_URL] = $imgUrl;
        !empty($mbImgUrl) && $update[Constant::DB_TABLE_MB_IMG_URL] = $mbImgUrl;
        !empty($name) && $update[Constant::DB_TABLE_NAME] = $name;
        !empty($shopSku) && $update[Constant::DB_TABLE_SHOP_SKU] = $shopSku;
        !empty($asin) && $update[Constant::DB_TABLE_ASIN] = $asin;
        !empty($expireTime) && $update[Constant::EXPIRE_TIME] = Carbon::parse($expireTime)->toDateTimeString();
        is_numeric($qty) && $qty >= 0 && $update[Constant::DB_TABLE_QTY] = $qty;
        is_numeric($showApply) && $showApply >= 0 && $update['show_apply'] = $showApply;
        is_numeric($productStatus) && $update[Constant::DB_TABLE_PRODUCT_STATUS] = $productStatus;
        is_numeric($sort) && $update['sort'] = $sort;

        $result = [];
        $where = [
            Constant::DB_TABLE_PRIMARY => $id
        ];
        //homasy评测3.0
        if (!empty($update) && in_array($storeId, [1, 2, 6])) {
            $result = static::update($storeId, $where, $update);
        }
        //现有评测
        if (!empty($update) && !in_array($storeId, [1, 2, 6])) {
            $metaFields = data_get($params, Constant::METAFIELDS, Constant::PARAMETER_ARRAY_DEFAULT);
            if (!empty($metaFields)) {
                foreach ($metaFields as $metaField) {
                    if (!empty($metaField[Constant::DB_TABLE_KEY]) && $metaField[Constant::DB_TABLE_KEY] == Constant::DB_TABLE_COUNTRY) {
                        !is_array($metaField[Constant::DB_TABLE_VALUE]) && $metaField[Constant::DB_TABLE_VALUE] = [$metaField[Constant::DB_TABLE_VALUE]];
                        $update[Constant::DB_TABLE_COUNTRY] = !empty($metaField[Constant::DB_TABLE_VALUE][0][Constant::DB_TABLE_KEY]) ? $metaField[Constant::DB_TABLE_VALUE][0][Constant::DB_TABLE_KEY] : '';
                        $update[Constant::FILE_URL] = !empty($metaField[Constant::DB_TABLE_VALUE][0][Constant::DB_TABLE_VALUE]) ? $metaField[Constant::DB_TABLE_VALUE][0][Constant::DB_TABLE_VALUE] : '';
                    }
                    if (!empty($metaField[Constant::DB_TABLE_KEY]) && $metaField[Constant::DB_TABLE_KEY] == Constant::DB_TABLE_DES) {
                        $update[Constant::DB_TABLE_DES] = !empty($metaField[Constant::DB_TABLE_VALUE]) ? implode('{@#}', $metaField[Constant::DB_TABLE_VALUE]) : '';
                    }
                }
            }
            $result = static::update($storeId, $where, $update);
        }

        //编辑属性
        $metaFields = data_get($params, Constant::METAFIELDS, Constant::PARAMETER_ARRAY_DEFAULT);
        if (!empty($metaFields)) {
            $newMetaFields = [];
            foreach ($metaFields as $metaField) {
                if (!empty($metaField[Constant::DB_TABLE_KEY]) && $metaField[Constant::DB_TABLE_KEY] == Constant::DB_TABLE_COUNTRY) {
                    $countryItem = [];
                    $urlItem = [];
                    foreach ($metaField[Constant::DB_TABLE_VALUE] as $item) {
                        !empty($item[Constant::DB_TABLE_KEY]) && $countryItem[] = $item[Constant::DB_TABLE_KEY];
                        !empty($item[Constant::DB_TABLE_VALUE]) && $urlItem [] = $item[Constant::DB_TABLE_KEY] . '{@#}' . $item[Constant::DB_TABLE_VALUE];
                    }
                    $newMetaFields[] = [
                        Constant::OWNER_RESOURCE => $metaField[Constant::OWNER_RESOURCE],
                        Constant::DB_TABLE_KEY => 'url',
                        Constant::DB_TABLE_VALUE => $urlItem,
                    ];
                    $newMetaFields[] = [
                        Constant::OWNER_RESOURCE => $metaField[Constant::OWNER_RESOURCE],
                        Constant::DB_TABLE_KEY => Constant::DB_TABLE_COUNTRY,
                        Constant::DB_TABLE_VALUE => $countryItem
                    ];
                } else {
                    $newMetaFields[] = $metaField;
                }
            }
            data_set($params, Constant::METAFIELDS, $newMetaFields);
        }
        data_set($params, Constant::OWNER_RESOURCE, static::getModelAlias());
        data_set($params, Constant::OP_ACTION, 'edit');
        data_set($params, Constant::NAME_SPACE, data_get($params, Constant::NAME_SPACE, static::getModelAlias()));
        MetafieldService::batchHandle($storeId, $id, $params);

        return $result;
    }

    /**
     * 导入评测产品
     * @param int $storeId 商城id
     * @param string $user 上传人
     * @param array $requestData
     * @return array 导入结果
     */
    public static function importFreeTestingProducts($storeId, $user, $requestData = []) {
        $products = static::getFreeTestingProducts($requestData);
        if (empty($products)) {
            return [];
        }

        $result = [];
        foreach ($products as $product) {
            $countries = $product[Constant::DB_TABLE_COUNTRY];
            $des = $product[Constant::DB_TABLE_DES];
            $url = $product['url'];
            if (!empty($countries)) {
                $countries = explode(';', $countries);
            }
            if (!empty($des)) {
                $des = explode(PHP_EOL, $des);
            }
            $urls = [];
            if (!empty($url)) {
                $url = explode(PHP_EOL, $url);
                foreach ($countries as $key => $country) {
                    if (data_get($url, $key, '')) {
                        $urls = $country . '{@#}' . data_get($url, $key, '');
                    }
                }
            }

            //评测2.0,homasy官网
            $product['uploader'] = $user;
            if (in_array($storeId, [1, 2, 6, 9])) {
                $metaFields = [];
                $metaFields[] = [
                    Constant::DB_TABLE_KEY => 'country',
                    Constant::DB_TABLE_VALUE => $countries
                ];
                $metaFields[] = [
                    Constant::DB_TABLE_KEY => 'des',
                    Constant::DB_TABLE_VALUE => $des
                ];
                $metaFields[] = [
                    Constant::DB_TABLE_KEY => 'url',
                    Constant::DB_TABLE_VALUE => $urls
                ];

                unset($product[Constant::DB_TABLE_COUNTRY]);
                unset($product[Constant::DB_TABLE_DES]);
                $id = static::insert($storeId, [], $product);
                if ($id) {
                    $result[] = $id;
                    //添加属性
                    $params = [];
                    data_set($params, Constant::METAFIELDS, $metaFields);
                    data_set($params, Constant::OWNER_RESOURCE, static::getModelAlias());
                    data_set($params, Constant::OP_ACTION, 'add');
                    data_set($params, Constant::NAME_SPACE, data_get($params, Constant::NAME_SPACE, 'free_testing'));
                    MetafieldService::batchHandle($storeId, $id, $params);
                }
            } else {
                //目前线上的评测活动，其他官网
                if (!empty($countries)) {
                    foreach ($countries as $country) {
                        $product[Constant::DB_TABLE_COUNTRY] = $country;
                        $product[Constant::DB_TABLE_DES] = !empty($des) ? implode('{@#}', $des) : '';
                        $id = static::insert($storeId, [], $product);

                        //属性
                        $metaFields = [];
                        $metaFields[] = [
                            Constant::DB_TABLE_KEY => 'country',
                            Constant::DB_TABLE_VALUE => $country
                        ];
                        $metaFields[] = [
                            Constant::DB_TABLE_KEY => 'des',
                            Constant::DB_TABLE_VALUE => $des
                        ];
                        $metaFields[] = [
                            Constant::DB_TABLE_KEY => 'url',
                            Constant::DB_TABLE_VALUE => $urls
                        ];

                        if ($id) {
                            $result[] = $id;
                            //添加属性
                            $params = [];
                            data_set($params, Constant::METAFIELDS, $metaFields);
                            data_set($params, Constant::OWNER_RESOURCE, static::getModelAlias());
                            data_set($params, Constant::OP_ACTION, 'add');
                            data_set($params, Constant::NAME_SPACE, data_get($params, Constant::NAME_SPACE, 'free_testing'));
                            MetafieldService::batchHandle($storeId, $id, $params);
                        }
                    }
                }
            }

        }

        return $result;
    }

    /**
     * 获取导入的数据
     * @param $requestData
     * @return array
     */
    public static function getFreeTestingProducts($requestData) {
        $data = [];
        $file = data_get($requestData, Constant::UPLOAD_FILE_KEY);
        $fileData = CdnManager::upload(Constant::UPLOAD_FILE_KEY, $file, '/upload/file/');
        if (data_get($fileData, Constant::RESPONSE_CODE_KEY, Constant::PARAMETER_INT_DEFAULT) != 1) {
            return $data;
        }

        $typeData = [
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_STRING,
            \Vtiful\Kernel\Excel::TYPE_TIMESTAMP,
        ];

        try {
            $data = ExcelService::parseExcelFile(data_get($fileData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . Constant::FILE_FULL_PATH, ''), $typeData);
        } catch (\Exception $exception) {
            return $data;
        }

        return static::convertFreeTestingProducts($data);
    }

    /**
     * 导入的数据
     * @param $excelData
     * @return array
     */
    public static function convertFreeTestingProducts($excelData) {
        if (empty($excelData)) {
            return [];
        }

        $tableData = [];
        $header = [];
        $isHeader = true;
        foreach ($excelData as $k => $row) {
            if ($isHeader) {
                $temp = array_flip($row);
                foreach (static::$freeTestingHeaderMap as $key => $value) {
                    data_set($header, $value, data_get($temp, $key));
                }
                $isHeader = false;
                continue;
            }

            $shopSku = trim(data_get($row, data_get($header, Constant::DB_TABLE_SHOP_SKU), Constant::PARAMETER_STRING_DEFAULT));
            $asin = trim(data_get($row, data_get($header, Constant::DB_TABLE_ASIN), Constant::PARAMETER_STRING_DEFAULT));
            $country = trim(data_get($row, data_get($header, Constant::DB_TABLE_COUNTRY), Constant::PARAMETER_STRING_DEFAULT));
            $name = trim(data_get($row, data_get($header, Constant::DB_TABLE_NAME), Constant::PARAMETER_STRING_DEFAULT));
            $des = trim(data_get($row, data_get($header, Constant::DB_TABLE_DES), Constant::PARAMETER_STRING_DEFAULT));
            $url = trim(data_get($row, data_get($header, Constant::FILE_URL), Constant::PARAMETER_STRING_DEFAULT));
            $qty = trim(data_get($row, data_get($header, Constant::DB_TABLE_QTY), Constant::PARAMETER_STRING_DEFAULT));
            $imgUrl = trim(data_get($row, data_get($header, Constant::DB_TABLE_IMG_URL), Constant::PARAMETER_STRING_DEFAULT));
            $mbImgUrl = trim(data_get($row, data_get($header, Constant::DB_TABLE_IMG_URL), Constant::PARAMETER_STRING_DEFAULT));
            $expireTime = trim(data_get($row, data_get($header, Constant::EXPIRE_TIME), Constant::PARAMETER_STRING_DEFAULT));
            $expireTime = !empty($expireTime) ? date('Y-m-d H:i:s', strtotime($expireTime)) : '2000-01-01 00:00:00';
            $productStatus = Constant::PARAMETER_INT_DEFAULT;

            data_set($tableData, $k . '.shop_sku', $shopSku);
            data_set($tableData, $k . '.asin', $asin);
            data_set($tableData, $k . '.country', $country);
            data_set($tableData, $k . '.name', $name);
            data_set($tableData, $k . '.des', $des);
            data_set($tableData, $k . '.url', $url);
            data_set($tableData, $k . '.qty', $qty);
            data_set($tableData, $k . '.img_url', $imgUrl);
            data_set($tableData, $k . '.mb_img_url', $mbImgUrl);
            data_set($tableData, $k . '.expire_time', $expireTime);
            data_set($tableData, $k . '.product_status', $productStatus);
            data_set($tableData, $k . '.business_type', 1);
            data_set($tableData, $k . '.sort', 1);
        }

        return $tableData;
    }

    /**
     * 评测产品导入模板表头
     * @var array
     */
    public static $freeTestingHeaderMap = [
        '店铺sku' => Constant::DB_TABLE_SHOP_SKU,
        '产品ASIN' => Constant::DB_TABLE_ASIN,
        '测评国家' => Constant::DB_TABLE_COUNTRY,
        '产品标题' => Constant::DB_TABLE_NAME,
        '产品三点描述（总数控制在150个字符内）' => Constant::DB_TABLE_DES,
        '产品跳转链接（不填写则由系统通过asin+站点拼接/邮件以及页面）' => Constant::FILE_URL,
        '所需测评数量' => Constant::DB_TABLE_QTY,
        '产品图片链接（请将图片上传至shopfiy复制其生产链接）' => Constant::DB_TABLE_IMG_URL,
        '测评截止时间' => Constant::EXPIRE_TIME,
    ];

    /**
     * 产品详情（目前用在评测2.0）
     * @param $storeId
     * @param $id
     * @param int $customerId
     * @param array $requestData
     * @return \App\Util\obj|array
     */
    public static function productDetails($storeId, $id, $customerId = 0, $requestData = []) {
        $country = data_get($requestData, Constant::DB_TABLE_COUNTRY, Constant::PARAMETER_STRING_DEFAULT);

        $select = [
            'ap.' . Constant::DB_TABLE_PRIMARY,
            'ap.' . Constant::DB_TABLE_NAME,
            'ap.' . Constant::FILE_URL,
            'ap.' . Constant::DB_TABLE_IMG_URL,
            'ap.' . Constant::DB_TABLE_MB_IMG_URL,
            'ap.' . Constant::DB_TABLE_QTY,
            'ap.' . Constant::DB_TABLE_QTY_APPLY,
            'ap.' . 'show_apply',
            'ap.' . Constant::DB_TABLE_REGULAR_PRICE,
            'ap.' . Constant::DB_TABLE_LISTING_PRICE,
            'ap.' . Constant::DB_TABLE_PRODUCT_STATUS,
            'ap.' . Constant::EXPIRE_TIME,
            'ap.' . Constant::DB_TABLE_DES,
            'ap.' . Constant::DB_TABLE_ASIN,
            'aa.' . Constant::DB_TABLE_PRIMARY . ' as apply_id',
            'ap.' . Constant::DB_TABLE_COUNTRY . ' as product_country',
        ];

        $where = [
            'ap.' . Constant::DB_TABLE_PRIMARY => $id,
        ];

        //获取属性
        $platform = FunctionHelper::getUniqueId(Constant::PLATFORM_SHOPIFY);
        $withWhere[] = [
            [Constant::DB_TABLE_STORE_ID, '=', $storeId],
            [Constant::DB_TABLE_PLATFORM, '=', $platform],
            [Constant::OWNER_RESOURCE, '=', static::getMake()],
            [Constant::NAME_SPACE, '=', 'free_testing'],
        ];
        $withSelect = [Constant::OWNER_ID, Constant::DB_TABLE_KEY, Constant::DB_TABLE_VALUE];
        $with[Constant::METAFIELDS] = FunctionHelper::getExePlan(
            $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $withSelect, $withWhere, [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false
        );

        //获取申请记录id
        $joinData = [
            FunctionHelper::getExePlanJoinData('activity_applies as aa', function ($join) use ($id, $customerId) {
                $join->on([['aa.' . Constant::DB_TABLE_EXT_ID, '=', 'ap.' . Constant::DB_TABLE_PRIMARY]])
                    ->where('aa.' . Constant::DB_TABLE_CUSTOMER_PRIMARY, $customerId)->where('aa.' . Constant::DB_TABLE_STATUS, 1);
            }),
        ];

        //处理数据
        $amazonHostData = DictService::getListByType(Constant::AMAZON_HOST, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);
        $itemHandleDataCallback = [
            Constant::DB_TABLE_COUNTRY => function($item) {
                $countries = array_values(array_filter(array_map(function ($it) {
                    return $it[Constant::DB_TABLE_KEY] == Constant::DB_TABLE_COUNTRY ? $it[Constant::DB_TABLE_VALUE] : '';
                }, $item[Constant::METAFIELDS])));
                empty($countries) && !empty($item[Constant::DB_TABLE_PRODUCT_COUNTRY]) && $countries[] = $item[Constant::DB_TABLE_PRODUCT_COUNTRY];
                return $countries;
            },
            Constant::DB_TABLE_DES => function($item) {
                $des = array_values(array_filter(array_map(function ($it) {
                    return $it[Constant::DB_TABLE_KEY] == Constant::DB_TABLE_DES ? $it[Constant::DB_TABLE_VALUE] : '';
                }, $item[Constant::METAFIELDS])));
                empty($des) && !empty($item['product_des']) && $des = explode('{@#}', $item['product_des']);
                return $des;
            },
            'url' => function($item) use($country, $amazonHostData) {
                //填写了产品url
                if (!empty($item[Constant::FILE_URL])) {
                    return $item[Constant::FILE_URL];
                }
                //根据国家参数返回产品url
                if (!empty($country)) {
                    $amazonHost = data_get($amazonHostData, $country, Constant::PARAMETER_STRING_DEFAULT);
                    if (!empty($amazonHost)) {
                        return $amazonHost . '/dp/' . $item[Constant::DB_TABLE_ASIN];
                    }
                }
                //根据导入的产品国家返回产品url
                $countries = array_filter(array_map(function ($it) {
                    return $it[Constant::DB_TABLE_KEY] == Constant::DB_TABLE_COUNTRY ? $it[Constant::DB_TABLE_VALUE] : '';
                }, $item[Constant::METAFIELDS]));
                if (!empty($countries)) {
                    foreach ($countries as $country) {
                        $amazonHost = data_get($amazonHostData, $country, Constant::PARAMETER_STRING_DEFAULT);
                        if (!empty($amazonHost)) {
                            return $amazonHost . '/dp/' . $item[Constant::DB_TABLE_ASIN];
                        }
                    }
                }
                //返回默认US产品url
                return data_get($amazonHostData, 'US', Constant::PARAMETER_STRING_DEFAULT). '/dp/' . $item[Constant::DB_TABLE_ASIN];
            },
            'complete_status' => function($item) {
                $completeStatus = 1;
                $curTime = date('Y-m-d H:i:s');
                if ($item[Constant::DB_TABLE_PRODUCT_STATUS] == 2 || ($curTime >= $item[Constant::EXPIRE_TIME] && $item[Constant::EXPIRE_TIME] != '2000-01-01 00:00:00')
                    || $item['show_apply'] >= $item[Constant::DB_TABLE_QTY]) {
                    $completeStatus = 2;
                }
                return $completeStatus;
            }
        ];

        $handleData = [];
        $unset = [Constant::METAFIELDS];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), 'activity_products as ap', $select, $where, [], null, null, false, [], false, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, []),
        ];

        $dataStructure = 'one';
        $flatten = false;

        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);
    }

    /**
     * 兼容评测2.0代码
     * @param $storeId
     * @param $actId
     */
    public static function updateProductActId($storeId, $actId) {
        $where = [
            Constant::DB_TABLE_ACT_ID => 0,
            Constant::BUSINESS_TYPE => 1,
        ];
        $update = [
            Constant::DB_TABLE_ACT_ID => $actId,
        ];
        static::update($storeId, $where, $update);
    }
}
