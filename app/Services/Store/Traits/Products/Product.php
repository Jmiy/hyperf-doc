<?php

namespace App\Services\Store\Traits\Products;

use App\Constants\Constant;
use App\Utils\FunctionHelper;

trait Product {

    /**
     * 获取 产品 唯一id
     * @param int $storeId 商城id
     * @param string $platform 平台
     * @param string $asin 产品asin
     * @return string 平台产品id
     */
    public static function getProductUniqueId($storeId, $platform, $country, $asin) {
        $storeId = static::castToString($storeId);
        return FunctionHelper::getUniqueId($platform, $country, $asin, (static::getCustomClassName()));
    }

    /**
     * 获取 产品Variant 唯一id
     * @param int $storeId 商城id
     * @param string $platform 平台
     * @param string $country  平台产品国家
     * @param string $asin 平台产品asin
     * @param string $sku 平台产品sku
     * @return string 平台产品VariantId
     */
    public static function getProductVariantUniqueId($storeId, $platform, $country, $asin, $sku) {
        $storeId = static::castToString($storeId);
        return FunctionHelper::getUniqueId($platform, $country, $asin, $sku, (static::getCustomClassName() . 'Variant'));
    }

    /**
     * 获取 产品图片 唯一id
     * @param int|string $storeId 商城id
     * @param int $productId 产品唯一id
     * @param string $imageSrc 产品图片地址
     * @return int 平台产品图片id
     */
    public static function getImageUniqueId($storeId, $productId, $imageSrc) {
        $storeId = static::castToString($storeId);
        return FunctionHelper::getUniqueId($productId, $imageSrc, (static::getCustomClassName() . 'Image'));
    }

    /**
     * 产品获取
     * @param int $storeId 商城id
     * @param array $parameters    参数
     * @return array
     */
    public static function getProduct($storeId = 0, $parameters = []) {
        return [];
    }

    /**
     * 获取统一平台产品数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条产品数据
     * @return array
     */
    public static function getPlatformProductData($storeId, $platform, $data) {

        $storeId = static::castToString($storeId);

        if (empty($data)) {
            return [];
        }

        $productId = data_get($data, Constant::DB_TABLE_PRIMARY) ?? 0; //产品id

        if (empty($productId)) {
            return [];
        }

        return [
            Constant::DB_TABLE_UNIQUE_ID => data_get($data, Constant::DB_TABLE_UNIQUE_ID) ?? '', //平台产品唯一id
            Constant::DB_TABLE_PLATFORM => data_get($data, Constant::DB_TABLE_PLATFORM) ?? '', //平台
            Constant::DB_TABLE_STORE_ID => data_get($data, Constant::DB_TABLE_STORE_ID) ?? 0,
            Constant::DB_TABLE_PLATFORM_CREATED_AT => FunctionHelper::handleTime(data_get($data, Constant::DB_TABLE_PLATFORM_CREATED_AT)), //创建时间
            Constant::DB_TABLE_PLATFORM_UPDATED_AT => FunctionHelper::handleTime(data_get($data, Constant::DB_TABLE_PLATFORM_UPDATED_AT)), //更新时间
            Constant::DB_TABLE_PRODUCT_ID => data_get($data, Constant::DB_TABLE_PRODUCT_ID) ?? '', //订单号
            'title' => data_get($data, 'title') ?? '', //订单编号
            'body_html' => data_get($data, 'body_html') ?? '', //订单编号
            'vendor' => data_get($data, 'vendor') ?? '',
            'product_type' => data_get($data, 'product_type') ?? '',
            'handle' => data_get($data, 'handle') ?? '',
            'platform_published_at' => FunctionHelper::handleTime(data_get($data, 'platform_published_at')),
            'template_suffix' => data_get($data, 'template_suffix') ?? '',
            'published_scope' => data_get($data, 'published_scope') ?? '',
            'tags' => data_get($data, 'tags') ?? '',
            'admin_graphql_api_id' => data_get($data, 'admin_graphql_api_id') ?? '',
            'image_src' => data_get($data, 'image_src', '') ?? '',
            Constant::DB_TABLE_ASIN => data_get($data, Constant::DB_TABLE_ASIN, '') ?? '',
            Constant::DB_TABLE_COUNTRY => data_get($data, Constant::DB_TABLE_COUNTRY, '') ?? '',
        ];
    }

    /**
     * 获取统一平台产品图片数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条产品数据
     * @return array
     */
    public static function getProductImages($storeId, $platform, $data) {

        $storeId = static::castToString($storeId);

        $items = [];
        $images = data_get($data, 'images', []);
        if (empty($images)) {
            return $items;
        }

        $productId = data_get($data, Constant::DB_TABLE_PRIMARY) ?? 0; //产品id
        if (empty($productId)) {
            return $items;
        }

        foreach ($images as $image) {
            $imageId = data_get($image, Constant::DB_TABLE_UNIQUE_ID) ?? 0; //image id
            if (empty($imageId)) {
                continue;
            }

            $item = [
                Constant::DB_TABLE_UNIQUE_ID => $imageId, //唯一id
                Constant::DB_TABLE_PRODUCT_UNIQUE_ID => data_get($image, Constant::DB_TABLE_PRODUCT_UNIQUE_ID) ?? 0, //产品 唯一id
                Constant::DB_TABLE_PLATFORM_CREATED_AT => FunctionHelper::handleTime(data_get($image, Constant::DB_TABLE_PLATFORM_CREATED_AT)), //创建时间
                Constant::DB_TABLE_PLATFORM_UPDATED_AT => FunctionHelper::handleTime(data_get($image, Constant::DB_TABLE_PLATFORM_UPDATED_AT)), //更新时间
                'image_id' => $imageId,
                Constant::DB_TABLE_PRODUCT_ID => data_get($image, Constant::DB_TABLE_PRODUCT_ID, 0),
                'position' => data_get($image, 'position') ?? '',
                'alt' => data_get($image, 'alt') ?? '',
                'width' => data_get($image, 'width') ?? 0,
                'height' => data_get($image, 'height') ?? 0,
                'src' => data_get($image, 'src') ?? '',
                'admin_graphql_api_id' => data_get($image, 'admin_graphql_api_id') ?? '',
            ];

            $items[] = $item;
        }

        return $items;
    }

    /**
     * 获取统一平台产品变种数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条产品数据
     * @return array
     */
    public static function getProductVariants($storeId, $platform, $data) {

        $storeId = static::castToString($storeId);

        $items = [];
        $variants = data_get($data, 'variants', []);
        if (empty($variants)) {
            return $items;
        }

        $productId = data_get($data, Constant::DB_TABLE_PRIMARY) ?? 0; //产品id
        if (empty($productId)) {
            return $items;
        }

        foreach ($variants as $variant) {
            $variantId = data_get($variant, Constant::DB_TABLE_UNIQUE_ID) ?? 0; //产品变种唯一id

            if (empty($variantId)) {
                continue;
            }

            $item = [
                Constant::DB_TABLE_UNIQUE_ID => $variantId, //唯一id
                Constant::DB_TABLE_PRODUCT_UNIQUE_ID => data_get($variant, Constant::DB_TABLE_PRODUCT_UNIQUE_ID) ?? 0, //产品 唯一id
                Constant::DB_TABLE_PRODUCT_IMAGE_UNIQUE_ID => data_get($variant, Constant::DB_TABLE_PRODUCT_UNIQUE_ID) ?? 0, //产品变体图片 唯一id
                Constant::DB_TABLE_PLATFORM_CREATED_AT => FunctionHelper::handleTime(data_get($variant, Constant::DB_TABLE_PLATFORM_CREATED_AT)), //创建时间
                Constant::DB_TABLE_PLATFORM_UPDATED_AT => FunctionHelper::handleTime(data_get($variant, Constant::DB_TABLE_PLATFORM_UPDATED_AT)), //更新时间
                'variant_id' => data_get($variant, 'variant_id') ?? 0,
                Constant::DB_TABLE_PRODUCT_ID => data_get($variant, Constant::DB_TABLE_PRODUCT_ID) ?? 0,
                Constant::FILE_TITLE => data_get($variant, Constant::FILE_TITLE) ?? '',
                Constant::DB_TABLE_PRICE => FunctionHelper::handleNumber(data_get($variant, Constant::DB_TABLE_PRICE)),
                Constant::DB_TABLE_CURRENCY => data_get($variant, Constant::DB_TABLE_CURRENCY) ?? '',
                'sku' => data_get($variant, 'sku') ?? '',
                'position' => data_get($variant, 'position') ?? 1,
                'inventory_policy' => data_get($variant, 'inventory_policy') ?? '',
                'compare_at_price' => FunctionHelper::handleNumber(data_get($variant, 'compare_at_price')),
                'fulfillment_service' => data_get($variant, 'fulfillment_service') ?? '',
                'inventory_management' => data_get($variant, 'inventory_management') ?? '',
                'option1' => data_get($variant, 'option1') ?? '',
                'option2' => data_get($variant, 'option2') ?? '',
                'option3' => data_get($variant, 'option3') ?? '',
                'taxable' => data_get($variant, 'taxable') ? 1 : 0,
                'barcode' => data_get($variant, 'barcode') ?? '',
                'grams' => data_get($variant, 'grams') ?? 0,
                'image_id' => data_get($variant, 'image_id') ?? '',
                'weight' => data_get($variant, 'weight') ?? 0,
                'weight_unit' => data_get($variant, 'weight_unit') ?? '',
                'inventory_item_id' => data_get($variant, 'inventory_item_id') ?? 0,
                'inventory_quantity' => data_get($variant, 'inventory_quantity') ?? 0,
                'old_inventory_quantity' => data_get($variant, 'old_inventory_quantity') ?? 0,
                'requires_shipping' => data_get($variant, 'requires_shipping') ? 1 : 0,
                'admin_graphql_api_id' => data_get($variant, 'admin_graphql_api_id') ?? '',
            ];

            $items[] = $item;
        }

        return $items;
    }

    /**
     * 获取统一平台产品类目数据
     * @param int $storeId 品牌商店id
     * @param string $platform 平台标识
     * @param array $data 单条产品数据
     * @return array
     */
    public static function getProductCategory($storeId, $platform, $data) {
        $storeId = static::castToString($storeId);

        $items = [];
        $_data = data_get($data, 'categorys', []);
        if (empty($_data)) {
            return $items;
        }

        $productId = data_get($data, Constant::DB_TABLE_PRIMARY) ?? 0; //产品id
        if (empty($productId)) {
            return $items;
        }

        foreach ($_data as $row) {
            $productUniqueId = data_get($row, Constant::DB_TABLE_PRODUCT_UNIQUE_ID) ?? 0; //产品唯一id

            if (empty($productUniqueId)) {
                continue;
            }

            $item = [
                Constant::DB_TABLE_PRODUCT_UNIQUE_ID => $productUniqueId, //产品 唯一id
                'one_category_code' => data_get($row, 'one_category_code') ?? '',
                'one_category_name' => data_get($row, 'one_category_name') ?? '',
                'two_category_code' => data_get($row, 'two_category_code') ?? '',
                'two_category_name' => data_get($row, 'two_category_name') ?? '',
                'three_category_code' => data_get($row, 'three_category_code') ?? '',
                'three_category_name' => data_get($row, 'three_category_name') ?? '',
            ];

            $items[] = $item;
        }

        return $items;
    }

}
