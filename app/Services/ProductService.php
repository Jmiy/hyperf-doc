<?php

/**
 * 产品服务
 * User: Jmiy
 * Date: 2019-06-20
 * Time: 15:59
 */

namespace App\Services;

use App\Services\Platform\ProductImageService;
use App\Services\Platform\ProductVariantService;
use App\Utils\FunctionHelper;
use Hyperf\DbConnection\Db as DB;
use Hyperf\Utils\Arr;
use Carbon\Carbon;
use App\Models\CustomerInfo;
use App\Models\Statistical\SystemLog;
use App\Services\Store\PlatformServiceManager;
use App\Constants\Constant;
use App\Services\Platform\ProductService as PlatformProductService;
use App\Utils\Response;
use App\Services\Platform\OrderService;

class ProductService extends BaseService {

    const METAFIELDS_TABLE = '`ptxcrm`.`crm_metafields` as crm_m';
    const PRODUCTS_TABLE = 'product as p';
    const P_PRODUCTS_TABLE = 'platform_products as pp';
    const P_PRODUCT_VARIANTS_TABLE = 'platform_product_variants as ppv';
    const P_PRODUCT_IMAGES_TABLE = 'platform_product_images as ppi';

    /**
     * 检查是否存在
     * @param int $storeId 商城id
     * @param int $storeProductId 产品id
     * @param int $id 主键id
     * @param bool $getData 是否获取数据 true:是 false:否 默认:false
     * @return array|bool
     */
    public static function exists($storeId = 0, $storeProductId = 0, $id = 0, $getData = false) {
        $where = [];

        if ($storeProductId) {
            $where[Constant::STORE_PRODUCT_ID] = $storeProductId;
        }

        if ($id) {
            $where[Constant::DB_TABLE_PRIMARY] = $id;
        }

        if (empty($where)) {
            return $getData ? [] : true;
        }

        $query = static::getModel($storeId);
        $query = $query->withTrashed()->where($where);

        if ($getData) {
            $rs = $query->first();
        } else {
            $rs = $query->exists();
        }

        return $rs;
    }

    /**
     * 添加记录
     * @param int $storeId 商城id
     * @param array $data  添加数据
     * @return int|boolean
     */
    public static function insert($storeId, $data) {
        $nowTime = Carbon::now()->toDateTimeString();
        $data[Constant::DB_TABLE_OLD_CREATED_AT] = Arr::get($data, Constant::DB_TABLE_OLD_CREATED_AT, $nowTime);
        $data[Constant::DB_TABLE_OLD_UPDATED_AT] = Arr::get($data, Constant::DB_TABLE_OLD_UPDATED_AT, $nowTime);

        if (isset($data[Constant::EXPIRE_TIME]) && $data[Constant::EXPIRE_TIME]) {
            $data[Constant::EXPIRE_TIME] = Carbon::parse($data[Constant::EXPIRE_TIME])->toDateTimeString();
        }

        $model = static::getModel($storeId);
        $id = $model->insertGetId($data);
        if (!$id) {
            return false;
        }

        return $id;
    }

    /**
     * 编辑记录
     * @param int $storeId 商城id
     * @param array $where 编辑条件
     * @param array $data  编辑数据
     * @return boolean
     */
    public static function update($storeId, $where, $data) {

        if (empty($storeId) || empty($where) || empty($data)) {
            return false;
        }

        if (isset($data[Constant::EXPIRE_TIME]) && $data[Constant::EXPIRE_TIME]) {
            $data[Constant::EXPIRE_TIME] = Carbon::parse($data[Constant::EXPIRE_TIME])->toDateTimeString();
        }

        return static::getModel($storeId)->withTrashed()->where($where)->update($data);
    }

    /**
     * 产品详情
     * @param int $storeId 商城id
     * @param int $storeProductId 产品id
     * @param int $id 主键id
     * @return null|object
     */
    public static function info($storeId = 0, $storeProductId = 0, $id = 0) {
        $select = [
            'p.' . Constant::DB_TABLE_PRIMARY,
            'p.' . Constant::DB_TABLE_UNIQUE_ID,
            'p.' . Constant::DB_TABLE_PRODUCT_UNIQUE_ID,
            'p.' . Constant::STORE_PRODUCT_ID,
            'p.' . Constant::DB_TABLE_CREDIT,
            'p.' . Constant::DB_TABLE_QTY,
            'p.' . Constant::EXCHANGED_NUMS,
            'p.' . Constant::EXPIRE_TIME,
        ];

        $where = [];
        $storeProductId && $where[Constant::STORE_PRODUCT_ID] = $storeProductId;
        $id && $where[Constant::DB_TABLE_PRIMARY] = $id;

        $joinData = [];

        $variantsSelect = [
            Constant::DB_TABLE_PRIMARY,
            Constant::DB_TABLE_UNIQUE_ID,
            Constant::DB_TABLE_PRODUCT_UNIQUE_ID,
            Constant::DB_TABLE_PRICE,
            Constant::VARIANT_ID,
            Constant::DB_TABLE_SKU,
        ];
        $variantsWith = [
            'variants' => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $variantsSelect, [], [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false
            ),
        ];

        $platformProductSelect = [
            Constant::DB_TABLE_UNIQUE_ID,
            'title as name',
            'image_src as img_url',
        ];

        $metafieldsSelect = [
            Constant::OWNER_RESOURCE,
            Constant::OWNER_ID,
            Constant::NAME_SPACE,
            Constant::DB_TABLE_KEY,
            Constant::DB_TABLE_VALUE,
            Constant::VALUE_TYPE,
        ];
        $metafieldWhere = [
            Constant::OWNER_RESOURCE => static::getModelAlias(),
        ];
        $with = [
            'platform_product' => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $platformProductSelect, [], [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, $variantsWith, [], Constant::PARAMETER_ARRAY_DEFAULT, 'hasOne', false
            ),
            Constant::METAFIELDS => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $metafieldsSelect, $metafieldWhere, [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false
            ),
        ];

        $only = [];
        $unset = [];
        $handleData = [];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), static::PRODUCTS_TABLE, $select, $where, [], 1, 0, false, [], false, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $itemHandleDataCallback = [
            'countdown' => function($item) {
                return Carbon::parse(FunctionHelper::handleTime(data_get($item, Constant::EXPIRE_TIME)))->timestamp - (Carbon::now()->timestamp);
            },
        ];
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
        ];

        $flatten = false;
        $isGetQuery = false;
        $dataStructure = 'one';
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * @param int $storeId
     * @param array $parameters
     * @param array $productData
     * @return array
     */
    public static function sync($storeId = 2, $parameters = [], $productData = []) {

        $platform = Constant::PLATFORM_SERVICE_SHOPIFY;
        $platformProductData = PlatformProductService::handlePull($storeId, $platform, $parameters, $productData);

        $productData = data_get($platformProductData, Constant::RESPONSE_DATA_KEY);

        $retData = ['success' => [], Constant::SUCCESS_COUNT => 0, 'exists' => [], Constant::EXISTS_COUNT => 0, 'fail' => [], Constant::FAIL_COUNT => 0];
        if (empty($productData)) {
            return Response::getDefaultResponseData(($retData[Constant::FAIL_COUNT] <= 0 ? 1 : 0), ('success: ' . $retData[Constant::SUCCESS_COUNT] . '个 exists: ' . $retData[Constant::EXISTS_COUNT] . '个 fail:' . $retData[Constant::FAIL_COUNT]), $retData);
        }

        $data = PlatformServiceManager::handle($platform, 'Product', 'getProductData', [$storeId, $platform, $productData, 6]);

        foreach ($data as $row) {
            //$exists = static::exists($storeId, $row[Constant::STORE_PRODUCT_ID]);

            $exists = static::getModel($storeId)->select([Constant::DB_TABLE_PRIMARY])->withTrashed()->where([Constant::STORE_PRODUCT_ID => $row[Constant::STORE_PRODUCT_ID]])->get();

            if ($exists && $exists->isNotEmpty()) {
                unset($row[Constant::DB_TABLE_PRODUCT_STATUS]);

                foreach ($exists as $item) {
                    $id = data_get($item, Constant::DB_TABLE_PRIMARY, -1);
                    $row[Constant::DB_TABLE_UNIQUE_ID] = FunctionHelper::getUniqueId($storeId, $id, static::getModelAlias());
                    static::update($storeId, [Constant::DB_TABLE_PRIMARY => $id], $row);
                    $retData[Constant::EXISTS_COUNT] ++;
                }

                $retData['exists'][] = $row[Constant::STORE_PRODUCT_ID];
                continue;
            }

            $ret = static::insert($storeId, $row);
            if ($ret) {
                static::update($storeId, [Constant::DB_TABLE_PRIMARY => $ret], [Constant::DB_TABLE_UNIQUE_ID => FunctionHelper::getUniqueId($storeId, $ret, static::getModelAlias())]);

                $retData[Constant::SUCCESS_COUNT] ++;
                $retData['success'][] = $row[Constant::STORE_PRODUCT_ID];
            } else {
                $retData[Constant::FAIL_COUNT] ++;
                $retData['fail'][] = $row[Constant::STORE_PRODUCT_ID];
            }
        }

        return Response::getDefaultResponseData(($retData[Constant::FAIL_COUNT] <= 0 ? 1 : 0), ('success: ' . $retData[Constant::SUCCESS_COUNT] . '个 exists: ' . $retData[Constant::EXISTS_COUNT] . '个 fail:' . $retData[Constant::FAIL_COUNT]), $retData);
    }

    /**
     * 检查同步频率
     * @param $storeId
     * @param $operator
     * @return mixed
     */
    public static function checkSyncFrequent($storeId, $operator) {
        $startTime = date('Y-m-d H:i:s', time() - 300);
        return SystemLog::where(['level' => 'log', 'type' => Constant::PLATFORM_SHOPIFY, 'subtype' => 'product', 'keyinfo' => $storeId . '_' . $operator])//$operator
                        ->where([['created_at', '>', $startTime]])
                        ->exists();
    }

    /**
     * 获取产品
     * @param int $storeId 商城id
     * @param array $ids 商品id
     * @param boolean $toArray 是否转化为数组 ture:是 false:否
     * @return array|object
     */
    public static function getProducts($storeId, $ids = [], $toArray = false) {

        $model = static::getModel($storeId);
        if ($ids) {
            $model = $model->whereIn(Constant::STORE_PRODUCT_ID, $ids);
        }
        $data = $model->get();

        if (empty($data)) {
            return $toArray ? [] : $data;
        }

        return $toArray ? $data->toArray() : $data;
    }

    /**
     * 产品ID数量列表
     * @param $itemlist
     * @return array
     */
    public static function productIdQty($itemlist) {

        $productQty = [];
        foreach ($itemlist as $item) {
            $productQty[$item[Constant::DB_TABLE_PRIMARY]] = $item[Constant::DB_TABLE_QTY];
        }
        return $productQty;
    }

    /**
     * 校验产品数量
     * @param $products
     * @param $productQty
     * @return array
     */
    public static function checkProductQty($products, $productQty) {
        $result = [Constant::RESPONSE_CODE_KEY => 1, Constant::RESPONSE_MSG_KEY => '', Constant::RESPONSE_DATA_KEY => []];
        foreach ($products as $k => $row) {
            if ($row[Constant::DB_TABLE_QTY] < $productQty[$row[Constant::STORE_PRODUCT_ID]]) {
                $result[Constant::RESPONSE_CODE_KEY] = 0;
                $result[Constant::RESPONSE_MSG_KEY] = 'product low stocks';
                $result[Constant::RESPONSE_DATA_KEY][] = $row[Constant::STORE_PRODUCT_ID];
            }
        }
        return $result;
    }

    /**
     * 检查产品数量
     * @author harry
     * @param $products
     * @param $itemlist
     * @return array
     */
    public static function checkProductsQty($products, $itemlist) {
        $productIdQty = static::productIdQty($itemlist);
        return static::checkProductQty($products, $productIdQty);
    }

    /**
     * 产品积分对换合法性校验
     * @param array $products 产品数据
     * @param array $items  兑换的产品数据
     * @param int $customerId 会员id
     * @return array
     */
    public static function validExchangeProduct($products, $items, $customerId) {
        $reward = CustomerInfo::where(Constant::DB_TABLE_CUSTOMER_PRIMARY, $customerId)->value(Constant::DB_TABLE_CREDIT);
        $reward = $reward ? ($reward . '') : '0';
        $rewardNeed = 0;
        $list = [];
        $flagQty = true;
        foreach ($products as $py => $pv) {
            $qty = 0;
            foreach ($items as $item) {
                if ($pv[Constant::STORE_PRODUCT_ID] == $item[Constant::DB_TABLE_PRIMARY]) {
                    $qty = $item[Constant::DB_TABLE_QTY];
                    if ($pv[Constant::DB_TABLE_QTY] < $item[Constant::DB_TABLE_QTY]) {
                        $flagQty = false;
                    }
                }
            }
            $rewardNeed += $pv[Constant::DB_TABLE_CREDIT] * $qty;
            $list[] = [
                Constant::DB_TABLE_PRIMARY => $pv[Constant::STORE_PRODUCT_ID] . '',
                "point" => $pv[Constant::DB_TABLE_CREDIT] . '',
            ];
        }

        if (count($products) < count($items) || !$flagQty) {
            $data = [Constant::VALID => false, Constant::REWARD => $reward, "message" => "Some Items in Cart Can't be Redeem!"];
        } else if ($rewardNeed <= $reward) {
            $data = [Constant::VALID => true, Constant::REWARD => $reward, "reward-need" => $rewardNeed];
        } else if ($rewardNeed > $reward) {
            $data = [Constant::VALID => false, Constant::REWARD => $reward, "reward-need" => $rewardNeed, "message" => "Account Reward balance is insufficient!"];
        }

        $data["list"] = $list;
        $data["total_credit"] = $rewardNeed;

        return $data;
    }

    /**
     * 积分兑换
     * @param $storeId
     * @param $where
     * @param $data
     * @return array
     */
    public static function handle($storeId, $where, $data) {

        $qty = $data[Constant::DB_TABLE_QTY] ?? 0;
        if ($qty == 0 || $storeId == 0) {
            return Response::getDefaultResponseData(0);
        }

        //更新产品库存
        $_data = [
            Constant::DB_TABLE_QTY => DB::raw('qty-' . $qty),
        ];
        isset($data[Constant::EXCHANGED_NUMS]) && $_data[Constant::EXCHANGED_NUMS] = DB::raw('exchanged_nums+' . $data[Constant::EXCHANGED_NUMS]);

        $ret = static::update($storeId, $where, $_data);
        if (!$ret) {
            return Response::getDefaultResponseData(0, 'change fail');
        }

        return Response::getDefaultResponseData(1);
    }

    /**
     * 获取db query
     * @param int $storeId
     * @param string $country
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getQuery($storeId = 1, $country = '', $where = []) {
        return static::getModel($storeId)->buildWhere($where);
    }

    /**
     * 获取公共参数
     * @param $params
     * @param array $order
     * @return array
     */
    public static function getPublicData($params, $order = []) {
        $where = [];

        $storeProductId = $params[Constant::STORE_PRODUCT_ID] ?? ''; //商城产品ID
        $sku = $params[Constant::DB_TABLE_SKU] ?? ''; //商城产品sku
        $startTime = $params[Constant::START_TIME] ?? ''; //截止开始时间
        $endTime = $params[Constant::DB_TABLE_END_TIME] ?? ''; //截止结束时间
        $name = $params[Constant::DB_TABLE_NAME] ?? ''; //产品名称
        $metafields = $params[Constant::METAFIELDS] ?? ''; //属性
        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);
        $productStatus = $params[Constant::DB_TABLE_PRODUCT_STATUS] ?? ''; //产品状态

        if ($storeProductId) {
            $where[] = [Constant::STORE_PRODUCT_ID, '=', $storeProductId];
        }

        if ($sku) {
            $where[] = ['p.' . Constant::DB_TABLE_SKU, 'like', '%' . $sku . '%'];
        }

        if ($startTime) {
            $where[] = [Constant::EXPIRE_TIME, '>=', $startTime];
        }

        if ($endTime) {
            $where[] = [Constant::EXPIRE_TIME, '<=', $endTime];
        }

        if (is_int($productStatus)) {
            $where[] = [Constant::DB_TABLE_PRODUCT_STATUS, '=', $productStatus];
        }

        if ($name) {
            $where[] = [Constant::DB_TABLE_NAME, '=', $name];
        }

        if (!empty($metafields)) {
            $customizeWhere = MetafieldService::buildCustomizeWhere($storeId, '', $metafields);
        }

        $_where = [];
        if (data_get($params, Constant::DB_TABLE_PRIMARY, 0)) {
            $_where[Constant::DB_TABLE_PRIMARY] = $params[Constant::DB_TABLE_PRIMARY];
        }

        if ($where) {
            $_where[] = $where;
        }
        !empty($customizeWhere) && $_where['{customizeWhere}'] = $customizeWhere;

        $order = $order ? $order : [['p.id', 'desc']];
        return Arr::collapse([parent::getPublicData($params, $order), [
                        'where' => $_where,
        ]]);
    }

    /**
     * @param $params
     * @param bool $toArray
     * @param bool $isPage
     * @param array $select
     * @param bool $isRaw
     * @param bool $isGetQuery
     * @param bool $isOnlyGetCount
     * @return array|\Hyperf\Database\Model\Builder|mixed
     */
    public static function getListData($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $_data = static::getPublicData($params);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, []);
        if ($params[Constant::DB_TABLE_SOURCE] == 'api') {
            $where[Constant::DB_TABLE_PRODUCT_STATUS] = 1;
            $_order = [
                ['exchanged_nums', 'desc'],
                ['mtime', 'desc']
            ];
            data_set($_data, Constant::ORDER, $_order);
        }

        $order = data_get($params, Constant::DB_EXECUTION_PLAN_ORDER_BY, data_get($_data, Constant::ORDER, []));
        $pagination = $_data[Constant::DB_EXECUTION_PLAN_PAGINATION];
        $limit = data_get($params, Constant::ACT_LIMIT_KEY, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));

        $customerCount = true;
        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0);

        $query = static::getModel($storeId)->from('product as p')
                ->leftjoin('metafields as m', function ($join) {
                    $join->on('p.id', '=', 'm.owner_id')->where('m.status', 1);
                })
                ->leftjoin("platform_products as pp", function ($join) {
                    $join->on('pp.product_id', '=', 'p.store_product_id')->where('pp.status', 1);
                })
                ->leftjoin("platform_product_variants as ppv", function ($join) {
            $join->on('pp.unique_id', '=', 'ppv.product_unique_id')->where('ppv.status', 1);
        });

        if ($params[Constant::DB_TABLE_SOURCE] == 'admin') {
            $query = $query->with(['countries' => function ($q) {
                    $q->select('product_id', 'country')->where('status', 1);
                }]);
        }

        $query->buildWhere($where);

        if ($isPage || $isOnlyGetCount) {
            $customerCount = static::adminCount($params, $query);
            $pagination[Constant::TOTAL] = $customerCount;
            $pagination[Constant::TOTAL_PAGE] = ceil($customerCount / $limit);

            if ($isOnlyGetCount) {
                return $pagination;
            }
        }

        if (empty($customerCount)) {
            $query = null;
            return [
                Constant::RESPONSE_DATA_KEY => [],
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
            ];
        }

        if ($order) {
            if ($params[Constant::DB_TABLE_SOURCE] == 'api') {
                foreach ($order as $item) {
                    $query = $query->orderBy($item[0], $item[1]);
                }
            } else {
                $query = $query->orderBy($order[0], $order[1]);
            }
        }

        $data = [
            Constant::QUERY => $query,
            Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
        ];

        $select = $select ? $select : ['p.id', 'p.store_product_id', 'p.credit', 'p.qty', 'p.exchanged_nums', 'p.sku', 'p.name', 'p.url', 'p.expire_time', 'p.sorts',
            'p.status', 'p.ctime', 'p.mtime', 'p.img_url', 'p.product_status', 'ppv.price', 'ppv.variant_id'];

        $data = static::getList($data, $toArray, $isPage, $select, $isRaw, $isGetQuery);

        if ($isGetQuery) {
            return $data;
        }

        if ($params['source'] == 'admin') {
            foreach ($data[Constant::RESPONSE_DATA_KEY] as $key => $item) {
                $data[Constant::RESPONSE_DATA_KEY][$key][Constant::DB_TABLE_PRODUCT_STATUS] = $item[Constant::DB_TABLE_PRODUCT_STATUS] == 1 ? '上架' : '下架';
            }
        }

        return $data;
    }

    /**
     * shopify商品更新回调处理
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param array $productData 产品数据
     * @return array
     */
    public static function handleProduct($storeId, $platform, $productData) {
        ProductService::sync($storeId, [], $productData);
        return [];
    }

    /**
     * shopify商品删除回调处理
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param int $productId 产品id
     * @return array
     */
    public static function deleteProduct($storeId, $platform, $productId) {
        \App\Services\Platform\ProductService::delete($storeId, [Constant::DB_TABLE_PRODUCT_ID => $productId]);

        ProductVariantService::delete($storeId, [Constant::DB_TABLE_PRODUCT_ID => $productId]);

        ProductImageService::delete($storeId, [Constant::DB_TABLE_PRODUCT_ID => $productId]);

        ProductService::delete($storeId, [Constant::STORE_PRODUCT_ID => $productId]);

        return [];
    }

    /**
     * 产品详情(目前用在积分兑换)
     * @param int $storeId 官网id
     * @param string $platform 平台标识
     * @param int $variantId 商品变种id
     * @return int|object
     */
    public static function details($storeId, $platform, $variantId) {
        $variantItem = ProductVariantService::existsOrFirst($storeId, '', [Constant::VARIANT_ID => $variantId], true);

        $productId = data_get($variantItem, Constant::DB_TABLE_PRODUCT_ID, 0);

        return static::existsOrFirst($storeId, '', [Constant::STORE_PRODUCT_ID => $productId], true);
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
    public static function getL($params, $toArray = true, $isPage = true, $select = [], $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {
        $_data = static::getPublicData($params, []);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, null);
        if ($params[Constant::DB_TABLE_SOURCE] == 'api') {
            $where[Constant::DB_TABLE_PRODUCT_STATUS] = 1;
            $_order = [
                [Constant::EXCHANGED_NUMS, Constant::ORDER_DESC],
                [Constant::DB_TABLE_OLD_UPDATED_AT, Constant::ORDER_DESC]
            ];
            data_set($_data, Constant::ORDER, $_order);
        }

        $order = data_get($params, Constant::ORDER_BY, data_get($_data, Constant::ORDER, []));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, []);
        $limit = data_get($params, Constant::ACT_LIMIT_KEY, data_get($pagination, Constant::REQUEST_PAGE_SIZE, 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, Constant::PARAMETER_INT_DEFAULT));
        $storeId = Arr::get($params, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);

        $select = $select ? $select : ['*'];

        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $productStatus = [
            1 => '上架',
            0 => '下架',
        ];
        $handleData = [
            Constant::DB_TABLE_PRODUCT_STATUS . '_show' => FunctionHelper::getExePlanHandleData(Constant::DB_TABLE_PRODUCT_STATUS, data_get($productStatus, '0', Constant::PARAMETER_STRING_DEFAULT), $productStatus),
        ];

        $joinData = [];

        $variantsSelect = [
            Constant::DB_TABLE_PRIMARY,
            Constant::DB_TABLE_UNIQUE_ID,
            Constant::DB_TABLE_PRODUCT_UNIQUE_ID,
            Constant::DB_TABLE_PRICE,
            Constant::VARIANT_ID,
        ];
        $variantsWith = [
            'variants' => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $variantsSelect, [], [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false
            ),
        ];

        $platformProductSelect = [
            Constant::DB_TABLE_UNIQUE_ID,
            'title as name',
            'image_src as img_url',
        ];

        $metafieldsSelect = [
            Constant::OWNER_RESOURCE,
            Constant::OWNER_ID,
            Constant::NAME_SPACE,
            Constant::DB_TABLE_KEY,
            Constant::DB_TABLE_VALUE,
            Constant::VALUE_TYPE,
        ];
        $metafieldWhere = [
            Constant::OWNER_RESOURCE => static::getModelAlias(),
        ];
        $with = [
            'platform_product' => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $platformProductSelect, [], [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, $variantsWith, [], Constant::PARAMETER_ARRAY_DEFAULT, 'hasOne', false
            ),
            Constant::METAFIELDS => FunctionHelper::getExePlan(
                    $storeId, null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, $metafieldsSelect, $metafieldWhere, [], null, null, false, [], false, Constant::PARAMETER_ARRAY_DEFAULT, [], [], Constant::PARAMETER_ARRAY_DEFAULT, 'hasMany', false
            ),
        ];

        $unset = ['platform_product'];
        $exePlan = FunctionHelper::getExePlan($storeId, null, static::getModelAlias(), static::PRODUCTS_TABLE, $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $productTypeData = DictService::getListByType('prize_type', Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE); //获取类型 0:其他 1:礼品卡 2:coupon 3:实物 5:活动积分
        $itemHandleDataCallback = [
            'countdown' => function($item) {
                return Carbon::parse(FunctionHelper::handleTime(data_get($item, Constant::EXPIRE_TIME)))->timestamp - (Carbon::now()->timestamp);
            },
            Constant::DB_TABLE_PRODUCT_COUNTRY => function($item) {
                return MetafieldService::getMetafieldValue(data_get($item, Constant::METAFIELDS, []), Constant::DB_TABLE_COUNTRY, ',');
            },
            Constant::PRODUCT_TYPE => function($item) use($productTypeData) {
                return data_get($productTypeData, MetafieldService::getMetafieldValue(data_get($item, Constant::METAFIELDS, []), Constant::DB_TABLE_TYPE, ','), '');
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

        $flatten = true;
        $dataStructure = 'list';
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    /**
     * 兑换用户
     * @param $storeId
     * @param $actUnique
     * @return obj|array
     */
    public static function exchangeList($storeId, $actUnique) {
        //获取活动数据
        $_where = [Constant::DB_TABLE_ACT_UNIQUE => FunctionHelper::getUniqueId($actUnique)];
        $order = [[Constant::DB_TABLE_UPDATED_AT, Constant::DB_EXECUTION_PLAN_ORDER_DESC]];
        $actData = ActivityService::getActivityData($storeId, 0, [], [], false, false, $_where, $order, 1);
        if (empty($actData)) {
            return [$_where];
        }

        $startAt = data_get($actData, Constant::DB_TABLE_START_AT);
        $endAt = data_get($actData, Constant::DB_TABLE_END_AT);

        $where[] = [
            ['po.' . Constant::DB_TABLE_STORE_ID, '=', $storeId],
            ['po.' . Constant::DB_TABLE_CREATED_AT, '>=', $startAt],
            ['po.' . Constant::DB_TABLE_CREATED_AT, '<=', $endAt],
            ['po.' . Constant::DB_TABLE_ORDER_TYPE, '=', 2],
        ];
        $order = [];
        $limit = 100;
        $offset = 0;
        $pagination = [];
        $isOnlyGetCount = false;
        $isPage = 1;

        $select = ['po.email', 'poi.name', 'poi.price'];

        $only = Constant::PARAMETER_ARRAY_DEFAULT;
        $handleData = [];

        $joinData = [
            FunctionHelper::getExePlanJoinData('platform_order_items as poi', function ($join) {
                        $join->on([['po.' . Constant::DB_TABLE_UNIQUE_ID, '=', 'poi.' . Constant::DB_TABLE_ORDER_UNIQUE_ID]]);
                    }),
        ];

        $with = [];
        $unset = [];
        $exePlan = FunctionHelper::getExePlan($storeId, null, OrderService::getNamespaceClass(), 'platform_orders as po', $select, $where, $order, $limit, $offset, $isPage, $pagination, $isOnlyGetCount, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);

        $itemHandleDataCallback = [
            Constant::DB_TABLE_EMAIL => function ($item) {
                return FunctionHelper::handleAccount(data_get($item, Constant::DB_TABLE_EMAIL, ''));
            }
        ];

        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            Constant::DB_EXECUTION_PLAN_WITH => $with,
            Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
        ];

        $flatten = false;
        $isGetQuery = false;
        $dataStructure = 'list';
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

    public static function pointProducts($storeId, $actId = 0) {
        $configs = ActivityService::getActivityConfigData($storeId, $actId, 'point', 'products');
        $productIds = json_decode(data_get($configs, 'point_products.value', Constant::PARAMETER_STRING_DEFAULT), true);
        if (empty($productIds)) {
            return [];
        }

        $result = [];
        foreach ($productIds as $productId => $country) {
            $product = static::existsOrFirst($storeId, '', [Constant::STORE_PRODUCT_ID => $productId], true, [Constant::EXCHANGED_NUMS, Constant::DB_TABLE_OLD_UPDATED_AT, Constant::DB_TABLE_IMG_URL]);
            if (empty($product)) {
                continue;
            }
            $exchangedNums = data_get($product, Constant::EXCHANGED_NUMS);
            $mtime = Carbon::parse(data_get($product, Constant::DB_TABLE_OLD_UPDATED_AT))->format('Y-m-d H:i:s');
            $imgUrl = data_get($product, Constant::DB_TABLE_IMG_URL);

//            $customizeWhere = MetafieldService::buildCustomizeWhere($storeId, '', [
//                        [
//                            Constant::OWNER_RESOURCE => static::getModelAlias(),
//                            Constant::DB_TABLE_KEY => Constant::DB_TABLE_COUNTRY,
//                            Constant::DB_TABLE_VALUE => $country,
//                        ]
//            ]);
//            dump($customizeWhere);

            $count = static::getModel($storeId)
                    ->from(static::PRODUCTS_TABLE)
                    ->leftJoin(DB::raw(static::METAFIELDS_TABLE), 'm.' . Constant::OWNER_ID, '=', 'p.' . Constant::DB_TABLE_UNIQUE_ID)
                    ->where([
                        ['m.' . Constant::OWNER_RESOURCE, '=', static::getModelAlias()],
                        ['m.' . Constant::DB_TABLE_KEY, '=', Constant::DB_TABLE_COUNTRY],
                        ['m.' . Constant::DB_TABLE_VALUE, '=', $country],
                        ['m.' . Constant::DB_TABLE_STATUS, '=', 1],
                        ['p.' . Constant::DB_TABLE_PRODUCT_STATUS, '=', 1],
                        ['p.' . Constant::EXCHANGED_NUMS, '>=', $exchangedNums],
                        ['p.' . Constant::DB_TABLE_OLD_UPDATED_AT, '>=', $mtime]
                    ])
//                    ->buildWhere([
//                        [
//                            ['p.' . Constant::DB_TABLE_PRODUCT_STATUS, '=', 1],
//                            ['p.' . Constant::EXCHANGED_NUMS, '>=', $exchangedNums],
//                            ['p.' . Constant::DB_TABLE_OLD_UPDATED_AT, '>=', $mtime],
//                        ],
//                        '{customizeWhere}' => $customizeWhere,
//                    ])
                    ->count();

            $result[] = [
                Constant::REQUEST_PAGE => intval($count / 12) + 1,
                Constant::DB_TABLE_COUNTRY => $country,
                Constant::DB_TABLE_IMG_URL => $imgUrl,
            ];
        }

        return $result;
    }

}
