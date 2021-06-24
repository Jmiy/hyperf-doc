<?php

/**
 * 公司内部api服务
 * User: Jmiy
 * Date: 2019-05-17
 * Time: 19:50
 */

namespace App\Services;

use App\Services\Erp\ErpAmazonService;
use App\Utils\Response;
use App\Constants\Constant;

class CompanyApiService {

    /** 获取单个产品价格
     * @param $productAsin 产品asin
     * @param $productSku
     * @param$productCountry 产品国家
     * @param string $platform 标识
     * @return array
     */
    public static function getPrice($productAsin, $productSku, $productCountry, $platform) {

        $retult = Response::getDefaultResponseData(1);
        if ($platform == Constant::PLATFORM_AMAZON) {
            $priceinfo = ErpAmazonService::getProductPriceInfo($productAsin, $productSku, $productCountry);
            data_set($retult, Constant::RESPONSE_DATA_KEY, $priceinfo);
        }

        if (!$retult[Constant::RESPONSE_DATA_KEY]) {
            $retult[Constant::RESPONSE_CODE_KEY] = 0;
            $retult[Constant::RESPONSE_MSG_KEY] = 'can not find this product price from system';
        }

        return $retult;
    }

}
