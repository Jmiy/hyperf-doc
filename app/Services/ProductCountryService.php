<?php

/**
 * Created by Patazon.
 * @desc   :
 * @author : Roy_qiu
 * @email  : Roy_qiu@patazon.net
 * @date   : 2020/8/27 17:52
 */

namespace App\Services;

use App\Constants\Constant;

class ProductCountryService extends BaseService {

    /**
     * 编辑
     * @param $storeId
     * @param $productIds
     * @param $countries
     * @return boolean
     */
    public static function edit($storeId, $productIds, $countries) {
        $where = [
            Constant::DB_TABLE_PRODUCT_ID => $productIds
        ];
        static::delete($storeId, $where);

        $products = ProductService::getModel($storeId)->select('store_product_id', 'id')->buildWhere(['id' => $productIds])->get();
        if ($products->IsEmpty()) {
            return false;
        }

        $products = array_column($products->toArray(), 'store_product_id', 'id');

        foreach ($productIds as $productId) {
            $data = [];
            foreach ($countries as $country) {
                $data[] = [
                    Constant::DB_TABLE_COUNTRY => $country,
                    Constant::DB_TABLE_PRODUCT_ID => $productId,
                    'store_product_id' => data_get($products, "$productId", Constant::PARAMETER_INT_DEFAULT)
                ];
            }
            static::getModel($storeId)->insert($data);
        }

        return true;
    }


}
