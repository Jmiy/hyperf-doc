<?php

namespace App\Services\Store\Amazon\Products;

use App\Services\Store\Amazon\BaseService;
use App\Constants\Constant;
use App\Utils\FunctionHelper;
use App\Models\Erp\Amazon\ShopInfo;
use App\Models\Erp\Amazon\Xc\Product\DimCountryAsin;
use App\Services\Store\PlatformServiceManager;

class Product extends BaseService {

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
     * @param string $createdAtMin    最小创建时间
     * @param string $createdAtMax    最大创建时间
     * @param array $ids              shopify会员id
     * @param string $sinceId shopify 会员id
     * @param string $publishedAtMin  最小发布时间
     * @param string $publishedAtMax  最大发布时间
     * @param string $publishedStatus 发布状态
     * @param array $fields 字段数据
     * @param int $limit 记录条数
     * @param int $source 数据获取方式 1:定时任务拉取
     * @return array
     */
    public static function getProduct($storeId = 0, $parameters = []) {
        $storeId = static::castToString($storeId);

        static::setConf($storeId);
        FunctionHelper::setTimezone('cn'); //设置时区
        $where = [
            'status' => 0,
        ];

        if (data_get($parameters, Constant::DB_TABLE_PLATFORM_UPDATED_AT) && data_get($parameters, Constant::DB_TABLE_PRODUCT_ID)) {
            $where['or'] = [
                [
                    [Constant::DB_TABLE_UPDATED_AT, '=', data_get($parameters, Constant::DB_TABLE_PLATFORM_UPDATED_AT)],
                    [Constant::DB_TABLE_PRIMARY, '>', data_get($parameters, Constant::DB_TABLE_PRODUCT_ID)]
                ],
                [
                    [Constant::DB_TABLE_UPDATED_AT, '>', data_get($parameters, Constant::DB_TABLE_PLATFORM_UPDATED_AT)]
                ],
            ];
        } elseif (empty(data_get($parameters, Constant::DB_TABLE_PLATFORM_UPDATED_AT)) && data_get($parameters, Constant::DB_TABLE_PRODUCT_ID)) {
            $where['or'] = [
                [
                    Constant::DB_TABLE_UPDATED_AT => data_get($parameters, Constant::DB_TABLE_PLATFORM_UPDATED_AT),
                    [Constant::DB_TABLE_PRIMARY, '>', data_get($parameters, Constant::DB_TABLE_PRODUCT_ID)]
                ],
                [
                    [Constant::DB_TABLE_UPDATED_AT, '!=', data_get($parameters, Constant::DB_TABLE_PLATFORM_UPDATED_AT)]
                ],
            ];
        }

        $limit = data_get($parameters, Constant::ACT_LIMIT_KEY) ?? 100;
        $productData = [];
        ShopInfo::buildWhere($where)->select(
                        [
                            Constant::DB_TABLE_PRIMARY,
                            Constant::DB_TABLE_ASIN,
                            Constant::DB_TABLE_COUNTRY,
                            Constant::FILE_TITLE,
                            Constant::DB_TABLE_CREATED_AT,
                            Constant::DB_TABLE_UPDATED_AT,
                            Constant::DB_TABLE_IMG,
//                            'one_category_code',
//                            'one_category_name',
//                            'two_category_code',
//                            'two_category_name',
//                            'three_category_code',
//                            'three_category_name',
                        ]
                )
                ->with(['variants' => function($query) {
                        $query->select([
                            Constant::DB_TABLE_PRIMARY,
                            Constant::DB_TABLE_ASIN,
                            Constant::DB_TABLE_COUNTRY,
                            Constant::FILE_TITLE,
                            'shop_sku',
                            Constant::DB_TABLE_IMG,
                            Constant::DB_TABLE_PRICE,
                            Constant::DB_TABLE_CURRENCY,
                            'create_at_time',
                            Constant::DB_TABLE_MODFIY_AT_TIME,
                        ])
                        ->where(Constant::DB_TABLE_STATUS, '<=', 1)
                        ->orderBy(Constant::DB_TABLE_MODFIY_AT_TIME, 'ASC')
                        ->orderBy(Constant::DB_TABLE_PRIMARY, 'ASC')
                        ;
                    }])
                ->orderBy(Constant::DB_TABLE_UPDATED_AT, 'ASC')
                ->orderBy(Constant::DB_TABLE_PRIMARY, 'ASC')
                ->chunk($limit, function ($data) use($storeId, &$productData) {
                    foreach ($data as $item) {

                        $country = strtoupper(data_get($item, Constant::DB_TABLE_COUNTRY) ?? '');
                        $asin = data_get($item, Constant::DB_TABLE_ASIN) ?? '';
                        $productUniqueId = static::getProductUniqueId($storeId, Constant::PLATFORM_SERVICE_AMAZON, $country, $asin); //平台产品唯一id

                        $createdAt = data_get($item, Constant::DB_TABLE_CREATED_AT); //创建时间

                        $imageSrc = data_get($item, Constant::DB_TABLE_IMG, '') ?? ''; //产品图片
                        $productId = data_get($item, Constant::DB_TABLE_PRIMARY) ?? 0; //平台产品 主键id
                        $productItem = [
                            Constant::DB_TABLE_PRIMARY => $productId, //平台产品 主键id
                            Constant::DB_TABLE_UNIQUE_ID => $productUniqueId, //产品唯一id
                            Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId(Constant::PLATFORM_SERVICE_AMAZON), //平台
                            Constant::DB_TABLE_STORE_ID => $storeId,
                            Constant::DB_TABLE_PLATFORM_CREATED_AT => $createdAt, //创建时间
                            Constant::DB_TABLE_PLATFORM_UPDATED_AT => data_get($item, Constant::DB_TABLE_UPDATED_AT), //更新时间
                            Constant::DB_TABLE_PRODUCT_ID => $productId, //平台产品 主键id
                            Constant::FILE_TITLE => data_get($item, Constant::FILE_TITLE) ?? '', //订单编号
                            'body_html' => data_get($item, 'body_html') ?? '', //订单编号
                            'vendor' => data_get($item, 'vendor') ?? '',
                            'product_type' => data_get($item, 'product_type') ?? '',
                            'handle' => data_get($item, 'handle') ?? '',
                            'platform_published_at' => FunctionHelper::handleTime(data_get($item, 'published_at')),
                            'template_suffix' => data_get($item, 'template_suffix') ?? '',
                            'published_scope' => data_get($item, 'published_scope') ?? '',
                            'tags' => data_get($item, 'tags') ?? '',
                            'admin_graphql_api_id' => data_get($item, 'admin_graphql_api_id') ?? '',
                            'image_src' => $imageSrc,
                            Constant::DB_TABLE_ASIN => $asin,
                            Constant::DB_TABLE_COUNTRY => $country,
                            Constant::DB_TABLE_STATUS => data_get($item, Constant::DB_TABLE_STATUS) ? 0 : 1,
                        ];

//                        $productItem['categorys'][] = [
//                            Constant::DB_TABLE_PRODUCT_UNIQUE_ID => $productUniqueId, //产品 唯一id
//                            'one_category_code' => data_get($item, 'one_category_code') ?? '',
//                            'one_category_name' => data_get($item, 'one_category_name') ?? '',
//                            'two_category_code' => data_get($item, 'two_category_code') ?? '',
//                            'two_category_name' => data_get($item, 'two_category_name') ?? '',
//                            'three_category_code' => data_get($item, 'three_category_code') ?? '',
//                            'three_category_name' => data_get($item, 'three_category_name') ?? '',
//                        ];

                        $images = [];
                        $imageId = static::getImageUniqueId($storeId, $productUniqueId, $imageSrc);
                        $_images = [$imageId];
                        $images[] = [
                            Constant::DB_TABLE_UNIQUE_ID => $imageId, //唯一id
                            Constant::DB_TABLE_PRODUCT_UNIQUE_ID => $productUniqueId, //产品 唯一id
                            Constant::DB_TABLE_PLATFORM_CREATED_AT => $createdAt, //创建时间
                            Constant::DB_TABLE_PLATFORM_UPDATED_AT => FunctionHelper::handleTime(data_get($item, Constant::DB_TABLE_UPDATED_AT)), //更新时间
                            'image_id' => $imageId,
                            Constant::DB_TABLE_PRODUCT_ID => $productId, //平台产品 主键id
                            'position' => '',
                            'alt' => '',
                            'width' => 0,
                            'height' => 0,
                            'src' => $imageSrc,
                            'admin_graphql_api_id' => data_get($item, 'admin_graphql_api_id') ?? '',
                        ];

                        //$_variants = collect(data_get($item, 'variants', []))->where(Constant::DB_TABLE_COUNTRY, $country)->all();
                        $_variants = data_get($item, 'variants', []);
                        $variants = [];
                        foreach ($_variants as $variant) {
                            $skuCountry = strtoupper(data_get($variant, Constant::DB_TABLE_COUNTRY) ?? ''); //sku 国家
                            $productUniqueId = static::getProductUniqueId($storeId, Constant::PLATFORM_SERVICE_AMAZON, $skuCountry, $asin); //平台产品唯一id

                            $imageSrc = data_get($variant, Constant::DB_TABLE_IMG) ?? ''; //产品图片
                            $imageId = static::getImageUniqueId($storeId, $productUniqueId, $imageSrc);

                            if (!in_array($imageId, $_images)) {
                                $images[] = [
                                    Constant::DB_TABLE_UNIQUE_ID => $imageId, //唯一id
                                    Constant::DB_TABLE_PRODUCT_UNIQUE_ID => $productUniqueId, //产品 唯一id
                                    Constant::DB_TABLE_PLATFORM_CREATED_AT => FunctionHelper::handleTime(data_get($variant, 'create_at_time')), //创建时间
                                    Constant::DB_TABLE_PLATFORM_UPDATED_AT => FunctionHelper::handleTime(data_get($variant, 'modfiy_at_time')), //更新时间
                                    'image_id' => $imageId,
                                    Constant::DB_TABLE_PRODUCT_ID => $productId, //平台产品 主键id
                                    'position' => '',
                                    'alt' => '',
                                    'width' => 0,
                                    'height' => 0,
                                    'src' => $imageSrc,
                                    'admin_graphql_api_id' => data_get($variant, 'admin_graphql_api_id') ?? '',
                                ];
                                $_images[] = $imageId;
                            }

                            $sku = data_get($variant, 'shop_sku', '');
                            $productVariantUniqueId = static::getProductVariantUniqueId($storeId, Constant::PLATFORM_SERVICE_AMAZON, $skuCountry, $asin, $sku); //唯一id
                            $variants[] = [
                                Constant::DB_TABLE_PRIMARY => data_get($variant, Constant::DB_TABLE_PRIMARY) ?? 0, //平台产品变种 主键id
                                Constant::DB_TABLE_UNIQUE_ID => $productVariantUniqueId, //唯一id
                                Constant::DB_TABLE_PRODUCT_UNIQUE_ID => $productUniqueId, //产品 唯一id
                                Constant::DB_TABLE_PRODUCT_IMAGE_UNIQUE_ID => $imageId, //产品变体图片 唯一id
                                Constant::DB_TABLE_PLATFORM_CREATED_AT => FunctionHelper::handleTime(data_get($variant, 'create_at_time')), //创建时间
                                Constant::DB_TABLE_PLATFORM_UPDATED_AT => FunctionHelper::handleTime(data_get($variant, 'modfiy_at_time')), //更新时间
                                Constant::VARIANT_ID => data_get($variant, Constant::DB_TABLE_PRIMARY) ?? 0, //平台产品变种 主键id
                                Constant::DB_TABLE_PRODUCT_ID => $productId, //平台产品 主键id
                                Constant::FILE_TITLE => data_get($variant, Constant::FILE_TITLE) ?? '',
                                Constant::DB_TABLE_PRICE => FunctionHelper::handleNumber(data_get($variant, Constant::DB_TABLE_PRICE)),
                                Constant::DB_TABLE_CURRENCY => data_get($variant, Constant::DB_TABLE_CURRENCY) ?? '',
                                'sku' => $sku,
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
                                'image_id' => $imageId,
                                'weight' => data_get($variant, 'weight') ?? 0,
                                'weight_unit' => data_get($variant, 'weight_unit') ?? '',
                                'inventory_item_id' => data_get($variant, 'inventory_item_id') ?? 0,
                                'inventory_quantity' => data_get($variant, 'inventory_quantity') ?? 0,
                                'old_inventory_quantity' => data_get($variant, 'old_inventory_quantity') ?? 0,
                                'requires_shipping' => data_get($variant, 'requires_shipping') ? 1 : 0,
                                'admin_graphql_api_id' => data_get($variant, 'admin_graphql_api_id') ?? '',
                            ];
                        }

                        $productItem['variants'] = $variants;
                        $productItem['images'] = $images;

                        $productData[] = $productItem;
                    }

                    return false;
                });

        return $productData;
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

//        $productId = data_get($data, Constant::DB_TABLE_PRIMARY) ?? 0; //产品id
//        if (empty($productId)) {
//            return $items;
//        }

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

    /**
     * 产品获取
     * @param int $storeId 商城id
     * @param string $createdAtMin    最小创建时间
     * @param string $createdAtMax    最大创建时间
     * @param array $ids              shopify会员id
     * @param string $sinceId shopify 会员id
     * @param string $publishedAtMin  最小发布时间
     * @param string $publishedAtMax  最大发布时间
     * @param string $publishedStatus 发布状态
     * @param array $fields 字段数据
     * @param int $limit 记录条数
     * @param int $source 数据获取方式 1:定时任务拉取
     * @return array
     */
    public static function getProductCategoryData($storeId = 0, $parameters = []) {
        $storeId = static::castToString($storeId);

        static::setConf($storeId);
        FunctionHelper::setTimezone('cn'); //设置时区
        $where = [];

        $limit = data_get($parameters, Constant::ACT_LIMIT_KEY) ?? 100;
        $platform = data_get($parameters, Constant::DB_TABLE_PLATFORM) ?? Constant::PLATFORM_SERVICE_AMAZON;
        $id = data_get($parameters, Constant::DB_TABLE_PRIMARY) ?? 0;


        if ($id) {
            $where[] = [
                [Constant::DB_TABLE_PRIMARY, '>', $id],
            ];
        }

        $productCategoryData = [];
        DimCountryAsin::buildWhere($where)->select(
                        [
                            Constant::DB_TABLE_PRIMARY,
                            Constant::DB_TABLE_ASIN,
                            Constant::DB_TABLE_COUNTRY,
                            'fcate1code as one_category_code',
                            'fcate1name as one_category_name',
                            'fcate2code as two_category_code',
                            'fcate2name as two_category_name',
                            'fcate3code as three_category_code',
                            'fcate3name as three_category_name',
                        ]
                )
                ->orderBy(Constant::DB_TABLE_PRIMARY, Constant::DB_EXECUTION_PLAN_ORDER_ASC)
                ->chunk($limit, function ($data) use($storeId, &$productCategoryData, $platform) {
                    foreach ($data as $item) {

                        $country = strtoupper(data_get($item, Constant::DB_TABLE_COUNTRY) ?? '');
                        $asin = data_get($item, Constant::DB_TABLE_ASIN) ?? '';

                        $productUniqueId = PlatformServiceManager::handle($platform, 'Product', 'getProductUniqueId', [$storeId, $platform, $country, $asin]); //平台产品唯一id

                        $productCategoryData[] = [
                            Constant::DB_TABLE_PRIMARY => data_get($item, Constant::DB_TABLE_PRIMARY), //id
                            Constant::DB_TABLE_PRODUCT_UNIQUE_ID => $productUniqueId, //产品 唯一id
                            'one_category_code' => data_get($item, 'one_category_code') ?? '',
                            'one_category_name' => data_get($item, 'one_category_name') ?? '',
                            'two_category_code' => data_get($item, 'two_category_code') ?? '',
                            'two_category_name' => data_get($item, 'two_category_name') ?? '',
                            'three_category_code' => data_get($item, 'three_category_code') ?? '',
                            'three_category_name' => data_get($item, 'three_category_name') ?? '',
                        ];
                    }

                    return false;
                });

        return [
            [
                'categorys' => $productCategoryData,
            ]
        ];
    }

}
