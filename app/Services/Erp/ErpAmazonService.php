<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/1
 * Time: 19:38
 */

namespace App\Services\Erp;

use Exception;
use App\Models\Erp\Amazon\AmazonOrder;
use App\Models\Erp\Amazon\AmazonOrderItem;
use App\Models\Erp\Amazon\AmazonProductPrice;
use App\Services\LogService;
use App\Utils\FunctionHelper;
use Carbon\Carbon;
use App\Models\Erp\Amazon\Xc\Cleanout\CleanoutListing;
use App\Constants\Constant;
use App\Services\OrdersService;
use App\Services\CompanyApiService;
use App\Exception\Handler\AppExceptionHandler as ExceptionHandler;

class ErpAmazonService {

    /**
     * 获取产品价格数据
     * @param string $productAsin
     * @param string $productSku
     * @param string $productCountry 国家缩写
     * @return array $orderInfo 价格数据
     */
    public static function getProductPriceInfo($productAsin, $productSku, $productCountry) {
        return static::getProductPriceData($productAsin, $productSku, $productCountry);
    }

    /**
     * 获取销参产品价格数据
     * @param string $productAsin
     * @param string $productSku
     * @param string $productCountry 国家缩写
     * @param boolean $isArray 是否转换为数组 true:是  false:否
     * @return array $result 销参价格数据
     */
    public static function getProductPriceData($productAsin, $productSku, $productCountry) {

        $priceData = [];
        $isQuerySuccessful = true;

        FunctionHelper::setTimezone('cn'); //设置时区
        $pullStart = '-4 hour';
        $nowTime = Carbon::now()->toDateTimeString();
        $time = strtotime($pullStart, strtotime($nowTime));
        $updatedAt = Carbon::createFromTimestamp($time)->rawFormat('Y-m-d H:i:00');

        $nowDay = Carbon::now()->rawFormat('Y-m-d 00:00:00'); //当天时间精确到天
        try {
            $productAsin = trim($productAsin);
            $productSku = trim($productSku);
            $productCountry = strtolower(trim($productCountry));
            $productCountry = !empty($productCountry) && !in_array($productCountry, ["other", 'all']) ? $productCountry : 'us';

            //获取 aws API 192.168.5.239 amzapi.sku_price_us产品价格数据 
            $from = AmazonProductPrice::$tablePrefix . '_' . $productCountry;
            $select = [
                Constant::DB_TABLE_ASIN,
                'sku',
                Constant::DB_TABLE_LISTING_PRICE, //销售价
                'shipping', //运费
                'landed_price', //ListingPrice + Shipping
                'regular_price', //原价
                'fulfillment_channel', //有效值：Amazon - 亚马逊物流 | Merchant - 卖家自行配送
                'item_condition', //商品的状况。有效值：New、Used、Collectible、Refurbished、Club
                'item_sub_condition', //商品的子状况。有效值：New、Mint、Very Good、Good、Acceptable、Poor、Club、OEM、Warranty、Refurbished Warranty、Refurbished、Open Box 或 Other
                Constant::DB_TABLE_UPDATED_AT, //亚马逊产品价格更新时间
                'latest_price', //最新一次的价格
            ];

            $where = [
                Constant::DB_TABLE_ASIN => $productAsin,
                Constant::DB_TABLE_SKU => $productSku,
                [[Constant::DB_TABLE_UPDATED_AT, '>=', $updatedAt]],
            ];

            $field = '88';
            $data = Constant::PARAMETER_ARRAY_DEFAULT;
            $dataType = Constant::PARAMETER_STRING_DEFAULT;
            $dateFormat = Constant::PARAMETER_STRING_DEFAULT;
            $time = Constant::PARAMETER_STRING_DEFAULT;
            $glue = Constant::PARAMETER_STRING_DEFAULT;
            $isAllowEmpty = true;
            $default = $from;
            $callback = Constant::PARAMETER_ARRAY_DEFAULT;
            $only = Constant::PARAMETER_ARRAY_DEFAULT;

            $parameters = [$field, $default, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only];
            $handleData = [
                'price_source' => FunctionHelper::getExePlanHandleData(...$parameters),
            ];

            $joinData = Constant::PARAMETER_ARRAY_DEFAULT;
            $with = Constant::PARAMETER_ARRAY_DEFAULT;
            $unset = Constant::PARAMETER_ARRAY_DEFAULT;
            $exePlan = FunctionHelper::getExePlan('default_connection_', null, 'AmazonProductPrice', $from, $select, $where, [], 1, null, false, [], false, $joinData, $with, $handleData, $unset);
            $dbExecutionPlan = [
                Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
            ];
            $dataStructure = 'one';
            $flatten = false;
            $priceDataApi = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);

            //获取爬虫价格数据 192.168.5.223 cleanout_xc.cleanout_listing_us
            $from = CleanoutListing::$tablePrefix . '_' . $productCountry;
            $select = [
                Constant::DB_TABLE_ASIN,
                'list_price', //吊牌价
                'sale_price', //销售价1
                'price', //销售价2
                'deal_price', //折扣价
                'price_mc', //最低价格
                'add_date_time as updated_at', //亚马逊产品价格更新时间
            ];
            $where = [
                Constant::DB_TABLE_ASIN => $productAsin,
            ];
            $orders = [['local_date_time', 'DESC']]; //[['add_date_time', '>=', $nowDay]],

            $handleData = [
                'price_source' => FunctionHelper::getExePlanHandleData($field, $from, $data, $dataType, $dateFormat, $time, $glue, $isAllowEmpty, $callback, $only),
            ];
            $exePlan = FunctionHelper::getExePlan('default_connection_', null, 'CleanoutListing', $from, $select, $where, $orders, 1, null, false, [], false, $joinData, Constant::PARAMETER_ARRAY_DEFAULT, $handleData, $unset);
            $itemHandleDataCallback = [
                Constant::DB_TABLE_LISTING_PRICE => function ($item) {
                    $field = FunctionHelper::getExePlanHandleData('price', 0);
                    return number_format((FunctionHelper::handleData($item, $field) / 100), 2, '.', '') + 0;
                },
                'regular_price' => function ($item) {
                    $field = FunctionHelper::getExePlanHandleData('list_price{or}sale_price{or}price', 0);
                    return number_format((FunctionHelper::handleData($item, $field) / 100), 2, '.', '') + 0;
                }
            ];
            $dbExecutionPlan = [
                Constant::DB_EXECUTION_PLAN_PARENT => $exePlan,
                Constant::DB_EXECUTION_PLAN_ITEM_HANDLE_DATA => FunctionHelper::getExePlanHandleData(null, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_ARRAY_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, Constant::PARAMETER_STRING_DEFAULT, true, $itemHandleDataCallback, $only),
            ];
            $priceDataCleanout = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);

            if (data_get($priceDataCleanout, Constant::DB_TABLE_UPDATED_AT, '') < $nowDay) {
                $priceDataCleanout = [];
            }

            if (empty($priceDataApi)) {//如果 aws API 最近 4 个小时没有更新数据，就直接使用爬虫数据即可
                $priceData = $priceDataCleanout;
            } else {
                $priceData = $priceDataApi;
                $priceCleanout = data_get($priceDataCleanout, Constant::DB_TABLE_LISTING_PRICE, 0);
                if (
                        !empty($priceDataCleanout) && data_get($priceDataCleanout, Constant::DB_TABLE_UPDATED_AT, '') >= data_get($priceData, Constant::DB_TABLE_UPDATED_AT, '') && $priceCleanout > 0 && data_get($priceData, Constant::DB_TABLE_LISTING_PRICE, 0) != $priceCleanout
                ) {//如果爬虫数据存在, 并且爬虫价格大于0 并且 爬虫价格不等于api价格，就直接使用爬虫价格
                    $priceData = $priceDataCleanout;
                }
            }
        } catch (Exception $exc) {
            $isQuerySuccessful = false;
            LogService::addSystemLog(Constant::LEVEL_ERROR, 'product_price', 'get_product_price', '查询销参产品价格出错', [Constant::DB_TABLE_ASIN => $productAsin, 'sku' => $productSku, Constant::DB_TABLE_COUNTRY => $productCountry, 'exc' => ExceptionHandler::getMessage($exc)]); //添加系统日志
        }

        $queryResults = 1;
        if ($isQuerySuccessful) {//如果查询数据库成功，就执行以下判断
            if (empty($priceData)) {//如果 aws和爬虫都没有  价格数据，就返回没有价格数据  前端界面显示  The price is updating
                $queryResults = 0;
            } else {//如果 有价格数据，就判断  销售价是否为0
                $listingPrice = data_get($priceData, Constant::DB_TABLE_LISTING_PRICE, 0);
                if (empty($listingPrice)) {//如果销售价为0，前端界面显示  The price is updating
                    $queryResults = 0;
                }
            }
        }

        return [
            'isQuerySuccessful' => $isQuerySuccessful,
            'queryResults' => $queryResults,
            'queryAt' => $nowTime,
            'priceData' => $priceData,
        ];
    }

}
