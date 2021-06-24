<?php

namespace App\Services\Store\Shopify\Products;

use App\Services\Store\Shopify\BaseService;
use Carbon\Carbon;
use App\Constants\Constant;
use App\Utils\FunctionHelper;
use Hyperf\Utils\Arr;

class Product extends BaseService {

    /**
     * 获取统一的产品数据
     * @param array $data
     * @return array 产品数据
     */
    public static function getProductData($storeId, $platform, $data, $source = 5) {
        $storeId = static::castToString($storeId);

        $result = [];

        if (empty($data)) {
            return $result;
        }

        $nowTime=Carbon::now()->toDateTimeString();
        foreach ($data as $k => $row) {

            if (empty($row)) {
                continue;
            }

            $productId = data_get($row, Constant::DB_TABLE_PRIMARY) ?? 0; //产品id

            $result[] = [
                Constant::DB_TABLE_PRODUCT_UNIQUE_ID => FunctionHelper::getUniqueId($storeId, $platform, $productId, static::getCustomClassName()), //平台产品唯一id
                'store_product_id' => $productId,
                'name' => $row['title'],
                'url' => $row['handle'],
                'ctime' => FunctionHelper::handleTime(data_get($row, Constant::DB_TABLE_CREATED_AT)) ?? $nowTime,
                'mtime' => FunctionHelper::handleTime(data_get($row, Constant::DB_TABLE_UPDATED_AT)) ?? $nowTime,
                'sku' => isset($row['variants'][0]) && $row['variants'][0]['sku'] ? $row['variants'][0]['sku'] : '',
                //'credit' => isset($row['variants'][0]) && $row['variants'][0]['price'] ? $row['variants'][0]['price'] * 10 : '',
                'img_url' => isset($row['image']) && $row['image'] ? $row['image']['src'] : '',
                //'expire_time' => '',
                'product_status' => 0, //状态：1上架 0下架 同步过来的产品默认下架状态
                'last_sys_at' => $nowTime, //产品最新同步时间
            ];
        }
        return $result;
    }

    /**
     * 产品获取 https://shopify.dev/docs/admin-api/rest/reference/products/product?api[version]=2020-04#index-2020-04
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
    public static function getProduct($storeId = 2, $parameters = []) {
        $storeId = static::castToString($storeId);

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . "products.json";

        $fields = data_get($parameters, 'fields', []); //Retrieve only certain fields, specified by a comma-separated list of fields names.  'id', 'title', 'handle', 'created_at', 'updated_at', 'variants', 'image'
        $fields = $fields ? implode(',', $fields) : '';

        $ids = array_unique(array_filter(data_get($parameters, 'ids', []))); //Retrieve only orders specified by a comma-separated list of order IDs.
        $sinceId = data_get($parameters, 'sinceId', ''); //Show orders after the specified ID.
        $limit = data_get($parameters, 'limit', 250);
        $limit = $limit > 250 ? 250 : $limit;

        $createdAtMin = data_get($parameters, 'created_at_min', '');
        $createdAtMax = data_get($parameters, 'created_at_max', '');

        $updatedAtMin = data_get($parameters, 'updated_at_min', '');
        $updatedAtMax = data_get($parameters, 'updated_at_max', '');

        $publishedAtMin = data_get($parameters, 'published_at_min', '');
        $publishedAtMax = data_get($parameters, 'published_at_max', '');

        /**
         * Return products by their published status
          (default: any)
          published: Show only published products.
          unpublished: Show only unpublished products.
          any: Show all products.
         */
        $publishedStatus = data_get($parameters, 'published_status', '');
        $presentmentCurrencies = data_get($parameters, 'presentment_currencies', ''); //Return presentment prices in only certain currencies, specified by a comma-separated list of ISO 4217 currency codes.

        $title = data_get($parameters, 'title', '');
        $vendor = data_get($parameters, 'vendor', '');
        $handle = data_get($parameters, 'handle', '');
        $productType = data_get($parameters, 'product_type', '');
        $collectionId = data_get($parameters, 'collection_id', '');

        $requestData = array_filter([
            'ids' => $ids ? implode(',', $ids) : '', //207119551,1073339460
            'since_id' => $sinceId ? $sinceId : '', //925376970775
            'created_at_min' => $createdAtMin ? Carbon::parse($createdAtMin)->toIso8601String() : '', //2019-02-25T16:15:47-04:00
            'created_at_max' => $createdAtMax ? Carbon::parse($createdAtMax)->toIso8601String() : '',
            'updated_at_min' => $updatedAtMin ? Carbon::parse($updatedAtMin)->toIso8601String() : '', //2019-02-25T16:15:47+08:00
            'updated_at_max' => $updatedAtMax ? Carbon::parse($updatedAtMax)->toIso8601String() : '',
            'published_at_min' => $publishedAtMin ? Carbon::parse($publishedAtMin)->toIso8601String() : '', //2019-02-25T16:15:47-04:00
            'published_at_max' => $publishedAtMax ? Carbon::parse($publishedAtMax)->toIso8601String() : '',
            'published_status' => $publishedStatus,
            'fields' => $fields,
            'limit' => $limit,
            'presentment_currencies' => $presentmentCurrencies,
            'title' => $title,
            'vendor' => $vendor,
            'handle' => $handle,
            'product_type' => $productType,
            'collection_id' => $collectionId,
        ]);
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'GET';
        $headers = [];
        $dataKey = Constant::PRODUCTS;
        $curlExtData = [
            'dataKey' => $dataKey,
            'keyInfo' => implode('_', array_filter([static::getAttribute($storeId, 'storeId'), data_get($parameters, 'operator', '')])),
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        $data = data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . Constant::PRODUCTS);
        $count = $data !== null ? count($data) : 0;
        if ($count >= 250) {
            $_updatedAtMax = data_get($data, (($count - 1) . '.updated_at'), '');

            if ($updatedAtMax) {
                data_set($parameters, 'updated_at_max', $_updatedAtMax);
            }

            if ($ids) {
                $_ids = collect($data)->keyBy(Constant::DB_TABLE_PRIMARY)->keys()->toArray();
                $ids = array_diff($ids, $_ids);
            }

            sleep(1);
            $_data = static::getProduct($storeId, $parameters);

            return $_data !== null ? Arr::collapse([$data, $_data]) : $data;
        }

        return $data;
    }

    /**
     * Creating an access token https://help.shopify.com/en/api/storefront-api/guides/updating-customers#creating-an-access-token
     * @param int $storeId 商城id
     * @param string $account 会员账号
     * @param string $password 会员密码
     * @param string $accessToken X-Shopify-Storefront-Access-Token
     * @return array|boolean
     */
//    public static function productImageUploade($storeId = 3, $resource = '', $filename = '', $mimetype = '', $accessToken = '') {
//
//        //static::setConf($storeId);
//        $accessToken = $accessToken ? $accessToken : static::getAttribute($storeId, 'accessToken');
//
//        $requestData = '
//mutation {
//  stagedUploadTargetsGenerate(input:
//  {
//  resource: "' . $resource . '",
//  filename: "' . $filename . '",
//  mimeType: "' . $mimetype . '"
//  }) {
//    urls {
//      parameters {
//        name
//        value
//      }
//      url
//    }
//    userErrors {
//      field
//      message
//    }
//  }
//}';
//        $resource = '';
//        $filename = '';
//        $mimetype = '';
//        $requestMethod = 'POST';
//        $headers = [
//            'Content-Type: application/graphql',
//            'X-Shopify-Storefront-Access-Token: ' . $accessToken,
//        ];
//        $res = static::imageRequest(static::getAttribute($storeId, 'graphqlUrl'), $requestData, $resource, $filename, $mimetype, $requestMethod, $headers);
//        return $res;
//        if ($res['responseText'] === false) {
//            return [];
//        }
//
//        if (empty($res['responseText']) || !isset($res['responseText']['data']) || empty($res['responseText']['data'])) {
//            return [];
//        }
//
////        //响应报文
////        {
////            "data": {
////              "customerAccessTokenCreate": {
////                "userErrors": [],
////                "customerAccessToken": {
////                  "accessToken": "003a87ac3aeaf1a219f38cc0e4eba38d",
////                  "expiresAt": "2019-08-06T09:10:10Z"
////                }
////              }
////            }
////        }
//
//        return $res['responseText'];
//    }

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

        $createdAt = FunctionHelper::handleTime(data_get($data, Constant::DB_TABLE_CREATED_AT)); //创建时间
        $parameters = [$storeId, $platform, $productId, static::getCustomClassName()];

        return [
            Constant::DB_TABLE_UNIQUE_ID => FunctionHelper::getUniqueId(...$parameters), //平台产品唯一id
            Constant::DB_TABLE_PLATFORM => FunctionHelper::getUniqueId($platform), //平台
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_TABLE_PLATFORM_CREATED_AT => $createdAt, //创建时间
            Constant::DB_TABLE_PLATFORM_UPDATED_AT => FunctionHelper::handleTime(data_get($data, Constant::DB_TABLE_UPDATED_AT)), //更新时间
            Constant::DB_TABLE_PRODUCT_ID => $productId, //订单号
            'title' => data_get($data, 'title') ?? '', //订单编号
            'body_html' => data_get($data, 'body_html') ?? '', //订单编号
            'vendor' => data_get($data, 'vendor') ?? '',
            'product_type' => data_get($data, 'product_type') ?? '',
            'handle' => data_get($data, 'handle') ?? '',
            'platform_published_at' => FunctionHelper::handleTime(data_get($data, 'published_at')),
            'template_suffix' => data_get($data, 'template_suffix') ?? '',
            'published_scope' => data_get($data, 'published_scope') ?? '',
            'tags' => data_get($data, 'tags') ?? '',
            'admin_graphql_api_id' => data_get($data, 'admin_graphql_api_id') ?? '',
            'image_src' => data_get($data, 'image' . Constant::LINKER . 'src', ''),
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
            $imageId = data_get($image, Constant::DB_TABLE_PRIMARY) ?? 0; //image id
            if (empty($imageId)) {
                continue;
            }
            $createdAt = FunctionHelper::handleTime(data_get($image, Constant::DB_TABLE_CREATED_AT)); //创建时间

            $parameters = [$storeId, $platform, $imageId, (static::getCustomClassName() . 'Image')];
            $productParameters = [$storeId, $platform, $productId, static::getCustomClassName()];

            $item = [
                Constant::DB_TABLE_UNIQUE_ID => FunctionHelper::getUniqueId(...$parameters), //唯一id
                Constant::DB_TABLE_PRODUCT_UNIQUE_ID => FunctionHelper::getUniqueId(...$productParameters), //产品 唯一id
                Constant::DB_TABLE_PLATFORM_CREATED_AT => $createdAt, //创建时间
                Constant::DB_TABLE_PLATFORM_UPDATED_AT => FunctionHelper::handleTime(data_get($image, Constant::DB_TABLE_UPDATED_AT)), //更新时间
                'image_id' => $imageId,
                'product_id' => data_get($image, 'product_id', 0),
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
            $variantId = data_get($variant, Constant::DB_TABLE_PRIMARY) ?? 0; //item id

            if (empty($variantId)) {
                continue;
            }

            $imageId = data_get($variant, 'image_id') ?? 0; //产品变体图片 唯一id
            $createdAt = FunctionHelper::handleTime(data_get($variant, Constant::DB_TABLE_CREATED_AT)); //创建时间

            $parameters = [$storeId, $platform, $variantId, (static::getCustomClassName() . 'Variant')];
            $imageParameters = [$storeId, $platform, $imageId, (static::getCustomClassName() . 'Image')];
            $productParameters = [$storeId, $platform, $productId, static::getCustomClassName()];

            $item = [
                Constant::DB_TABLE_UNIQUE_ID => FunctionHelper::getUniqueId(...$parameters), //唯一id
                Constant::DB_TABLE_PRODUCT_UNIQUE_ID => FunctionHelper::getUniqueId(...$productParameters), //产品 唯一id
                Constant::DB_TABLE_PRODUCT_IMAGE_UNIQUE_ID => FunctionHelper::getUniqueId(...$imageParameters), //产品变体图片 唯一id
                Constant::DB_TABLE_PLATFORM_CREATED_AT => $createdAt, //创建时间
                Constant::DB_TABLE_PLATFORM_UPDATED_AT => FunctionHelper::handleTime(data_get($variant, Constant::DB_TABLE_UPDATED_AT)), //更新时间
                'variant_id' => $variantId,
                'product_id' => data_get($variant, 'product_id', 0),
                'title' => data_get($variant, 'title') ?? '',
                Constant::DB_TABLE_PRICE => FunctionHelper::handleNumber(data_get($variant, Constant::DB_TABLE_PRICE)),
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
                'image_id' => $imageId,
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
     * 产品获取 https://shopify.dev/docs/admin-api/rest/reference/products/product?api[version]=2020-04#index-2020-04
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
    public static function count($storeId = 1, $parameters = []) {
        $storeId = static::castToString($storeId);

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . "products/count.json";

        $createdAtMin = data_get($parameters, 'created_at_min', '');
        $createdAtMax = data_get($parameters, 'created_at_max', '');

        $updatedAtMin = data_get($parameters, 'updated_at_min', '');
        $updatedAtMax = data_get($parameters, 'updated_at_max', '');

        $publishedAtMin = data_get($parameters, 'published_at_min', '');
        $publishedAtMax = data_get($parameters, 'published_at_max', '');

        /**
         * Return products by their published status
          (default: any)
          published: Show only published products.
          unpublished: Show only unpublished products.
          any: Show all products.
         */
        $publishedStatus = data_get($parameters, 'published_status', '');
        $vendor = data_get($parameters, 'vendor', '');
        $productType = data_get($parameters, 'product_type', '');
        $collectionId = data_get($parameters, 'collection_id', '');

        $requestData = array_filter([
            'vendor' => $vendor,
            'product_type' => $productType,
            'collection_id' => $collectionId,
            'created_at_min' => $createdAtMin ? Carbon::parse($createdAtMin)->toIso8601String() : '', //2019-02-25T16:15:47-04:00
            'created_at_max' => $createdAtMax ? Carbon::parse($createdAtMax)->toIso8601String() : '',
            'updated_at_min' => $updatedAtMin ? Carbon::parse($updatedAtMin)->toIso8601String() : '', //2019-02-25T16:15:47+08:00
            'updated_at_max' => $updatedAtMax ? Carbon::parse($updatedAtMax)->toIso8601String() : '',
            'published_at_min' => $publishedAtMin ? Carbon::parse($publishedAtMin)->toIso8601String() : '', //2019-02-25T16:15:47-04:00
            'published_at_max' => $publishedAtMax ? Carbon::parse($publishedAtMax)->toIso8601String() : '',
            'published_status' => $publishedStatus,
        ]);
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'GET';
        $headers = [];
        $dataKey = 'count';
        $curlExtData = [
            'dataKey' => null,
            'keyInfo' => implode('_', array_filter([static::getAttribute($storeId, 'storeId'), data_get($parameters, 'operator', '')])),
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        return data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . $dataKey);
    }

}
