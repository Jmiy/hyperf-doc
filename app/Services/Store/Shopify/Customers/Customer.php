<?php

namespace App\Services\Store\Shopify\Customers;

use App\Services\Store\Shopify\BaseService;
use Carbon\Carbon;
use Hyperf\Utils\Arr;
use App\Constants\Constant;
use App\Services\Store\Shopify\Metafield\Metafield;

class Customer extends BaseService {

    /**
     * https://shopify.dev/docs/admin-api/graphql/getting-started
     * @param type $storeId
     * @return type
     */
    public static function testAdminGraphql($storeId = 2) {
        $storeId = static::castToString($storeId);
        //static::setConf($storeId);
        $accessToken = static::getAttribute($storeId, 'password');

        $requestData = '
{
  shop {
    products(first: 5) {
      edges {
        node {
          id
          handle
        }
      }
      pageInfo {
        hasNextPage
      }
    }
  }
}';
        //查询
        $requestData = '
query {
  productVariant(id: "gid://shopify/ProductVariant/3619123134564") {
    inventoryItem {
      inventoryLevels (first:10) {
        edges {
          node {
            location {
              name
            }
            available
          }
        }
      }
    }
  }
}';

        //更新
        $requestData = '
mutation {
  inventoryAdjustQuantity(
    input:{
      inventoryLevelId: "gid://shopify/InventoryLevel/13570506808?inventory_item_id=10820777115690"
      availableDelta: 1
    }
  )
  {
    inventoryLevel {
      available
    }
  }
}';

        $requestData = 'mutation {
  webhookSubscriptionCreate(topic: APP_UNINSTALLED, webhookSubscription: {callbackUrl: "", format: JSON, includeFields: "", metafieldNamespaces: ""}) {
    userErrors {
      field
      message
    }
    webhookSubscription {
      callbackUrl
      createdAt
      format
      includeFields
      id
      legacyResourceId
      metafieldNamespaces
      topic
      updatedAt
    }
  }
}
';
        $username = '';
        $password = '';
        $requestMethod = 'POST';
        $headers = [
            'Content-Type: application/graphql',
            'X-Shopify-Access-Token: ' . $accessToken,
        ];
        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . "graphql.json";
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers);

        if ($res['responseText'] === false) {
            return [];
        }

        if (empty($res['responseText']) || !isset($res['responseText']['data']) || empty($res['responseText']['data'])) {
            return [];
        }

//        //响应报文
//        {
//            "data": {
//              "customerReset": {
//                "userErrors": [],
//                "customer": {
//                  "id": "Z2lkOi8vc2hvcGlmeS9DdXN0b21lci8xNzk4OTQ5MTA5ODI="
//                }
//              }
//            }
//        }
        return $res['responseText'];
    }

    /**
     * 获取统一的会员数据
     * @param array $data shopify会员数据
     * @param int $storeId 商店id
     * @param int $source 会员来源
     * @return array 统一的会员数据
     */
    public static function getCustomerData($data, $storeId = 2, $source = 5) {
        $result = [];
        foreach ($data as $k => $row) {

            if (empty($row)) {
                continue;
            }

            $result[$k] = [
                'store_id' => $storeId,
                'store_customer_id' => $row['id'] ?? 0,
                'account' => $row['email'] ?? '',
                'first_name' => $row['first_name'] ?? '',
                'last_name' => $row['last_name'] ?? '',
                'currency' => $row['currency'] ?? '',
                'phone' => $row['phone'] ?? '',
                'address' => [],
                'source' => $source,
                'accepts_marketing' => data_get($row, 'accepts_marketing', 0) ? 1 : 0, //是否订阅 true：订阅  false：不订阅
                'accepts_marketing_updated_at' => Carbon::parse($row['accepts_marketing_updated_at'])->toDateTimeString(), //订阅时间
                'state' => data_get($row, 'state', 'disabled'), //账号状态 disabled/invited/enabled/declined
                'status' => 1, //账号状态 disabled/invited/enabled/declined
                'platformData' => $row,
                Constant::DB_TABLE_IP => data_get($row, Constant::DB_TABLE_IP, ''),
                Constant::DB_TABLE_COUNTRY => data_get($row, Constant::DB_TABLE_COUNTRY, ''),
            ];

            if (isset($row['created_at']) && $row['created_at']) {//注册时间
                $createdAt = Carbon::parse($row['created_at'])->toDateTimeString();
                data_set($result, $k . '.ctime', $createdAt);
                data_set($result, $k . '.platform_created_at', $createdAt);
            }

            if (isset($row['updated_at']) && $row['updated_at']) {//用户信息更新时间
                $updatedAt = Carbon::parse($row['updated_at'])->toDateTimeString();
                data_set($result, $k . '.mtime', $updatedAt);
                data_set($result, $k . '.lastlogin', $updatedAt);
                data_set($result, $k . '.platform_updated_at', $updatedAt);
            }

            $defaultAddress = data_get($row, 'default_address', []);
            if ($defaultAddress) {

                if (empty($result[$k][Constant::DB_TABLE_COUNTRY]) && $defaultAddress['country_code']) {
                    $result[$k][Constant::DB_TABLE_COUNTRY] = $defaultAddress['country_code'];
                }

                if (empty($result[$k]['phone'])) {
                    $result[$k]['phone'] = $defaultAddress['phone'] ?? $result[$k]['phone'];
                }

                $result[$k]['address'] = [
                    'type' => 'home',
                    'city' => $defaultAddress['city'] ?? '',
                    'region' => $defaultAddress['province_code'] ?? '',
                    'street' => $defaultAddress['address1'] . $defaultAddress['address2'],
                    'addr' => json_encode($defaultAddress),
                    'addresses' => json_encode(data_get($row, 'addresses', [])),
                ];
            }
        }

        return $result;
    }

    /**
     * 获取会员数据 https://help.shopify.com/en/api/reference/customers/customer#index-2019-07
     * @param int $storeId 商城id
     * @param string $createdAtMin 最小创建时间
     * @param string $createdAtMax 最大创建时间
     * @param array $ids shopify会员id
     * @param string $sinceId shopify会员id
     * @param int $limit 记录条数
     * @param int $source 会员来源
     * @param array $extData 扩展数据
     * @return array
     */
    public static function getCustomer($storeId = 2, $createdAtMin = '', $createdAtMax = '', $ids = [], $sinceId = '', $limit = 250, $source = 5, $extData = []) {

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . "customers.json";
        $limit = $limit ? $limit : 10;
        $limit = $limit > 250 ? 250 : $limit;
        $updatedAtMin = data_get($extData, 'updated_at_min', '');
        $updatedAtMax = data_get($extData, 'updated_at_max', '');

        $requestData = array_filter([
            'ids' => $ids ? implode(',', $ids) : '', //207119551,1073339460
            'since_id' => $sinceId ? $sinceId : '', //925376970775
            'created_at_min' => $createdAtMin ? Carbon::parse($createdAtMin)->toIso8601String() : '', //2019-02-25T16:15:47+08:00
            'created_at_max' => $createdAtMax ? Carbon::parse($createdAtMax)->toIso8601String() : '',
            'updated_at_min' => $updatedAtMin ? Carbon::parse($updatedAtMin)->toIso8601String() : '', //2019-02-25T16:15:47+08:00
            'updated_at_max' => $updatedAtMax ? Carbon::parse($updatedAtMax)->toIso8601String() : '',
            'limit' => $limit,
        ]);
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'GET';
        $headers = [];
        $dataKey = Constant::CUSTOMERS;
        $curlExtData = [
            'dataKey' => $dataKey,
        ];
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod, $headers, $curlExtData);

        $data = data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . Constant::CUSTOMERS, []);
        $count = count($data);
        if ($count >= 250) {
            $_updatedAtMax = data_get($data, (($count - 1) . '.updated_at'), '');

            if ($createdAtMax) {
                $createdAtMax = $_updatedAtMax;
            }

            if ($updatedAtMax) {
                data_set($extData, 'updated_at_max', $_updatedAtMax);
            }

            if ($ids) {
                $_ids = collect($data)->keyBy(Constant::DB_TABLE_PRIMARY)->keys()->toArray();
                $ids = array_diff($ids, $_ids);
            }

            sleep(1);
            $_data = static::getCustomer($storeId, $createdAtMin, $createdAtMax, $ids, $sinceId, $limit, $source, $extData);

            return $data = Arr::collapse([$data, $_data]);
        }

        return $data;
    }

    /**
     * 会员查询 https://help.shopify.com/en/api/reference/customers/customer#search-2019-07
     * @param int $storeId 商城id
     * @param string $order 排序
     * @param string $query 查询
     * @param array $fields 字段数据
     * @param int $limit 记录条数
     * @param int $source 会员来源
     * @return array
     */
    public static function customerQuery($storeId = 2, $order = '', $query = '', $fields = [], $limit = 1, $source = 5) {

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . "customers/search.json";
        $limit = $limit ? $limit : 1;
        $limit = $limit > 250 ? 250 : $limit;
        $requestData = array_filter([
            'order' => $order ? $order : '',
            'query' => $query ? $query : '',
            'fields' => implode(',', $fields),
            'limit' => $limit,
        ]);
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'GET';
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod);

        $data = data_get($res, Constant::RESPONSE_TEXT . Constant::LINKER . Constant::CUSTOMERS, []);

        if (empty($data)) {
            return [];
        }

        return static::getCustomerData($data, $storeId, $source);
    }

    /**
     * 删除会员：https://help.shopify.com/en/api/reference/customers/customer#destroy-2019-07
     * @param int $storeId 商城id
     * @param string $customerId 商城平台会员id
     * @return array|string|boolean 删除结果 true:删除成功  array:请求接口异常 string:删除错误提示
     */
    public static function delete($storeId = 1, $customerId = '') {
        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . "customers/$customerId.json";

        $requestData = [];
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'DELETE';
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod);

        if ($res['responseText'] === false) {
            return [];
        }

        if (empty($res['responseText'])) {
            return [];
        }

        return isset($res['responseText']['errors']) ? $res['responseText']['errors'] : true;
    }

    /**
     * 统计会员总数：https://help.shopify.com/en/api/reference/customers/customer#count-2019-07
     * @param int $storeId 商城id
     * @return array|int 会员总数
     */
    public static function getCount($storeId = 1) {
        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . "customers/count.json";

        $requestData = [];
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'GET';
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod);

        if ($res['responseText'] === false) {
            return [];
        }

        if (empty($res['responseText'])) {
            return [];
        }

        return isset($res['responseText']['count']) ? $res['responseText']['count'] : $res['responseText'];
    }

    /**
     * 创建会员：https://help.shopify.com/en/api/storefront-api/reference/mutation/customercreate  https://shopify.dev/docs/storefront-api/reference/mutation/customercreate?api[version]=2020-04
     * @param int $storeId 商城id
     * @param string $account 会员账号
     * @param string $password 会员密码
     * @param boolean $acceptsMarketing 是否接收营销邮件
     * @param string $firstName The customer’s first name.
     * @param string $lastName
     * @param string $phone
     * @param string $accessToken X-Shopify-Storefront-Access-Token
     * @return array 创建结果
     */
    public static function createCustomer($storeId = 1, $account = '', $password = '', $acceptsMarketing = true, $firstName = '', $lastName = '', $phone = '', $accessToken = '') {

        //static::setConf($storeId);
        $accessToken = $accessToken ? $accessToken : static::getAttribute($storeId, 'accessToken');

        //test@example.com:Z2lkOi8vc2hvcGlmeS9DdXN0b21lci8xOTU5MTM2MzYyNTU2
        //test@qq.com  123456 Z2lkOi8vc2hvcGlmeS9DdXN0b21lci8xOTU5MTUxNjY1MjEy

        $requestData = '
mutation {
  customerCreate(input: {
    email: "' . $account . '",
    password: "' . $password . '",
    acceptsMarketing : ' . ($acceptsMarketing ? 'true' : 'false') . ',
    firstName: "' . $firstName . '",
    lastName: "' . $lastName . '"
  }) {
    userErrors {
      field
      message
    }
    customer {
      id
    }
  }
}';
        //Jmiy_cen@patazon.net  123456  Z2lkOi8vc2hvcGlmeS9DdXN0b21lci8xOTU5MTc1ODE1MjI4
//        $email = 'Jmiy_cen@patazon.net';
//        $requestData = '
//mutation {
//  customerRecover(email: "' . $email . '") {
//    customerUserErrors {
//      code
//      field
//      message
//    }
//  }
//}';
//        $id = 'Z2lkOi8vc2hvcGlmeS9DdXN0b21lci8xOTU5MTc1ODE1MjI4';
//        $requestData = '
//mutation {
//  customerReset(id: "' . $id . '", input: {
//    resetToken: "ae0f1d2e179c9571122a0595a6ac8125",
//    password: "20316"
//  }) {
//    customer {
//      id
//    }
//    customerAccessToken {
//      accessToken
//      expiresAt
//    }
//    customerUserErrors {
//      code
//      field
//      message
//    }
//  }
//}';
//        $requestData = '{
//  collections(first: 5) {
//    edges {
//      node {
//        id
//        handle
//      }
//    }
//    pageInfo {
//      hasNextPage
//    }
//  }
//}';
//
//        $requestData = '{
//  productByHandle(handle: "mpow") {
//    id
//  }
//}';
        $username = '';
        $password = '';
        $requestMethod = 'POST';
        $headers = [
            'Content-Type: application/graphql',
            'X-Shopify-Storefront-Access-Token: ' . $accessToken,
        ];
        $res = static::request($storeId, static::getAttribute($storeId, 'graphqlUrl'), $requestData, $username, $password, $requestMethod, $headers);

        if ($res['responseText'] === false) {
            return [];
        }

        if (empty($res['responseText']) || !isset($res['responseText']['data']) || empty($res['responseText']['data'])) {
            return [];
        }

//        {
//            "data": {
//              "customerCreate": {
//                "userErrors": [
//                  {
//                    "field": [
//                      "input",
//                      "email"
//                    ],
//                    "message": "Email is invalid"
//                  }
//                ],
//                "customer": "Z2lkOi8vc2hvcGlmeS9DdXN0b21lci8xOTU5MjE2MjE4MTcy"|null//Jmiy_cen1@patazon.net
//              }
//            }
//        }

        return $res['responseText'];
    }

    /**
     * Creating an access token https://help.shopify.com/en/api/storefront-api/guides/updating-customers#creating-an-access-token
     * @param int $storeId 商城id
     * @param string $account 会员账号
     * @param string $password 会员密码
     * @param string $accessToken X-Shopify-Storefront-Access-Token
     * @return array|boolean
     */
    public static function customerAccessTokenCreate($storeId = 1, $account = '', $password = '', $accessToken = '') {

        //static::setConf($storeId);
        $accessToken = $accessToken ? $accessToken : static::getAttribute($storeId, 'accessToken');

        $requestData = '
mutation {
  customerAccessTokenCreate(input: {
    email: "' . $account . '",
    password: "' . $password . '"
  }) {
    userErrors {
      field
      message
    }
    customerAccessToken {
      accessToken
      expiresAt
    }
  }
}';
        $username = '';
        $password = '';
        $requestMethod = 'POST';
        $headers = [
            'Content-Type: application/graphql',
            'X-Shopify-Storefront-Access-Token: ' . $accessToken,
        ];
        $res = static::request($storeId, static::getAttribute($storeId, 'graphqlUrl'), $requestData, $username, $password, $requestMethod, $headers);

        if ($res['responseText'] === false) {
            return [];
        }

        if (empty($res['responseText']) || !isset($res['responseText']['data']) || empty($res['responseText']['data'])) {
            return [];
        }

//        //响应报文
//        {
//            "data": {
//              "customerAccessTokenCreate": {
//                "userErrors": [],
//                "customerAccessToken": {
//                  "accessToken": "003a87ac3aeaf1a219f38cc0e4eba38d",
//                  "expiresAt": "2019-08-06T09:10:10Z"
//                }
//              }
//            }
//        }

        return $res['responseText'];
    }

    /**
     * Updating the address https://help.shopify.com/en/api/storefront-api/guides/updating-customers#Updating%20the%20address
     * @param int $storeId 商城id
     * @param string $account 会员账号
     * @param string $password 会员密码
     * @param string $accessToken X-Shopify-Storefront-Access-Token
     * @return array|boolean
     */
    public static function customerAddressCreate($storeId = 1, $account = '', $password = '', $accessToken = '') {
        //static::setConf($storeId);
        $accessToken = $accessToken ? $accessToken : static::getAttribute($storeId, 'accessToken');

        $customerAccessToken = static::customerAccessTokenCreate($storeId, $account, $password, $accessToken);
        $customerAccessToken = $customerAccessToken['data']['customerAccessTokenCreate']['customerAccessToken']['accessToken'];

        $address = '{
    "lastName": "Doe",
    "firstName": "John",
    "address1": "123 Test Street",
    "province": "QC",
    "country": "Canada",
    "zip": "H3K0X2",
    "city": "Montreal"
  }';
        $requestData = '
mutation {
  customerAddressCreate(customerAccessToken: ' . $customerAccessToken . ', address: ' . $address . ') {
    userErrors {
      field
      message
    }
    customerAddress {
      id
    }
  }
}';
        $username = '';
        $password = '';
        $requestMethod = 'POST';
        $headers = [
            'Content-Type: application/graphql',
            'X-Shopify-Storefront-Access-Token: ' . $accessToken,
        ];
        $res = static::request($storeId, static::getAttribute($storeId, 'graphqlUrl'), $requestData, $username, $password, $requestMethod, $headers);

        if ($res['responseText'] === false) {
            return [];
        }

        if (empty($res['responseText']) || !isset($res['responseText']['data']) || empty($res['responseText']['data'])) {
            return [];
        }

//        //响应报文
//        {
//            "data": {
//              "customerAddressCreate": {
//                "userErrors": [],
//                "customerAddress": {
//                  "id": "Z2lkOi8vc2hvcGlmeS9NYWlsaW5nQWRkcmVzcy8yMTg3MDUxOTkxMTA/bW9kZWxfbmFtZT1DdXN0b21lckFkZHJlc3MmY3VzdG9tZXJfYWNjZXNzX3Rva2VuPWU0MGU5MDE3OTk3YTYwZWQ4YTZkN2JmMWY0NDFkNzQz"
//                }
//              }
//            }
//        }

        return $res['responseText'];
    }

    /**
     * 发送找回密码邮件 Recovering https://help.shopify.com/en/api/storefront-api/guides/updating-customers#Recovering%20and%20resetting%20passwords
     * @param int $storeId 商城id
     * @param string $account 会员账号
     * @param string $accessToken X-Shopify-Storefront-Access-Token
     * @return array|boolean
     */
    public static function customerRecover($storeId = 1, $account = '', $accessToken = '') {
        //static::setConf($storeId);
        $accessToken = $accessToken ? $accessToken : static::getAttribute($storeId, 'accessToken');

        $requestData = '
mutation {
  customerRecover(email: ' . $account . ') {
    userErrors {
      field
      message
    }
  }
}';
        $username = '';
        $password = '';
        $requestMethod = 'POST';
        $headers = [
            'Content-Type: application/graphql',
            'X-Shopify-Storefront-Access-Token: ' . $accessToken,
        ];
        $res = static::request($storeId, static::getAttribute($storeId, 'graphqlUrl'), $requestData, $username, $password, $requestMethod, $headers);
        if ($res['responseText'] === false) {
            return [];
        }

        if (empty($res['responseText']) || !isset($res['responseText']['data']) || empty($res['responseText']['data'])) {
            return [];
        }

        //In response to a successful mutation, an email is sent with a reset password link. Clicking the link directs the customer to the Shopify account reset URL.
        //The resetToken is included in the account reset redirect URL.
        return $res['responseText'];
    }

    /**
     * 重置密码 https://help.shopify.com/en/api/storefront-api/guides/updating-customers#Recovering%20and%20resetting%20passwords
     * @param int $storeId 商城id
     * @param string $id 会员id
     * @param string $resetToken 重置密码令牌  在找回密码邮件的链接中带有 resetToken 重置密码令牌
     * @param string $password 会员密码
     * @param string $accessToken X-Shopify-Storefront-Access-Token
     * @return array|boolean
     */
    public static function customerReset($storeId = 1, $id = '', $resetToken = '', $password = '', $accessToken = '') {
        //static::setConf($storeId);
        $accessToken = $accessToken ? $accessToken : static::getAttribute($storeId, 'accessToken');

        $requestData = '
mutation {
  customerReset(id: ' . $id . ', input: {
    "resetToken": "' . $resetToken . '",
    "password": "' . $password . '"
  }) {
    userErrors {
      field
      message
    }
    customer {
      id
    }
  }
}';
        $username = '';
        $password = '';
        $requestMethod = 'POST';
        $headers = [
            'Content-Type: application/graphql',
            'X-Shopify-Storefront-Access-Token: ' . $accessToken,
        ];
        $res = static::request($storeId, static::getAttribute($storeId, 'graphqlUrl'), $requestData, $username, $password, $requestMethod, $headers);
        if ($res['responseText'] === false) {
            return [];
        }

        if (empty($res['responseText']) || !isset($res['responseText']['data']) || empty($res['responseText']['data'])) {
            return [];
        }

//        //响应报文
//        {
//            "data": {
//              "customerReset": {
//                "userErrors": [],
//                "customer": {
//                  "id": "Z2lkOi8vc2hvcGlmeS9DdXN0b21lci8xNzk4OTQ5MTA5ODI="
//                }
//              }
//            }
//        }
        return $res['responseText'];
    }

    /**
     * 获取激活会员链接：https://help.shopify.com/en/api/reference/customers/customer#account_activation_url-2019-07
     * Create an account activation URL for an invited or disabled customer
     * @param int $storeId 商城id
     * @param string $customerId 商城平台会员id
     * @return string 激活用户链接
     */
    public static function getAccountActivationUrl($storeId = 1, $customerId = '') {

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . 'customers/' . $customerId . '/account_activation_url.json';

        $requestData = [];
        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'POST';
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod);
//        var_dump($res);
//        exit;
        if ($res['responseText'] === false) {
            return [];
        }

        if (empty($res['responseText'])) {
            return [];
        }

        return isset($res['responseText']['account_activation_url']) ? $res['responseText']['account_activation_url'] : '';
    }

    /**
     * 激活会员 https://help.shopify.com/en/api/storefront-api/guides/updating-customers#creating-the-customer
     * @param int $storeId 商城id
     * @param string $id 会员id
     * @param string $resetToken 重置密码令牌  在找回密码邮件的链接中带有 resetToken 重置密码令牌
     * @param string $password 会员密码
     * @param string $accessToken X-Shopify-Storefront-Access-Token
     * @return array|boolean
     */
    public static function customerActivate($storeId = 1, $id = '', $activationToken = '53a6dd57f06110bd70e044297876320d-1566030445', $password = '', $accessToken = '') {
        //static::setConf($storeId);
        $accessToken = $accessToken ? $accessToken : static::getAttribute($storeId, 'accessToken');

        $requestData = '
mutation {
  customerActivate(id: ' . $id . ', input: {
    "activationToken": "' . $activationToken . '",
    "password": "' . $password . '"
  }) {
    userErrors {
      field
      message
    }
    customer {
      id
    }
  }
}';
        $username = '';
        $password = '';
        $requestMethod = 'POST';
        $headers = [
            'Content-Type: application/graphql',
            'X-Shopify-Storefront-Access-Token: ' . $accessToken,
        ];
        $res = static::request($storeId, static::getAttribute($storeId, 'graphqlUrl'), $requestData, $username, $password, $requestMethod, $headers);
        if ($res['responseText'] === false) {
            return [];
        }

        if (empty($res['responseText']) || !isset($res['responseText']['data']) || empty($res['responseText']['data'])) {
            return [];
        }

//        //响应报文
//        {
//            "data": {
//              "customerReset": {
//                "userErrors": [],
//                "customer": {
//                  "id": "Z2lkOi8vc2hvcGlmeS9DdXN0b21lci8xNzk4OTQ5MTA5ODI="
//                }
//              }
//            }
//        }
        return $res['responseText'];
    }

    /**
     * 平台用户信息修改：https://shopify.dev/docs/admin-api/rest/reference/customers/customer?api[version]=2020-04#update-2020-04
     * @param int $storeId 官网id
     * @param int $storeCustomerId 平台会员id
     * @param string $account 账号
     * @param string $firstName 名字
     * @param string $lastName 名字
     * @param string $phone 电话
     * @param string $note 修改备注
     * @return array
     */
    public static function updateCustomerDetails($storeId, $storeCustomerId, $account, $firstName, $lastName, $phone, $note) {

        //static::setConf($storeId);

        $url = static::getAttribute($storeId, 'schema') . static::getAttribute($storeId, 'storeUrl') . "customers/$storeCustomerId.json";
        $requestData = [
            Constant::CUSTOMER => array_filter([
                'id' => $storeCustomerId,
                Constant::DB_TABLE_EMAIL => $account,
                Constant::DB_TABLE_FIRST_NAME => $firstName,
                Constant::DB_TABLE_LAST_NAME => $lastName,
                Constant::DB_TABLE_PHONE => $phone,
                'note' => $note,
            ])
        ];
        $requestData = json_encode($requestData);

        $username = static::getAttribute($storeId, 'apiKey');
        $password = static::getAttribute($storeId, 'password');
        $requestMethod = 'PUT';
        $res = static::request($storeId, $url, $requestData, $username, $password, $requestMethod);

        if ($res['responseText'] === false) {
            return [];
        }

        if (empty($res['responseText'])) {
            return [];
        }

        return $res;
    }

    /**
     * Retrieves a list of metafields that belong to a $ownerResource. https://shopify.dev/docs/admin-api/rest/reference/metafield?api[version]=2020-04#index-2020-04
     * @param int $storeId 品牌商店id
     * @param int $ownerId 平台账号id
     * @return array metafields
     * array:1 [▼
      0 => array:11 [▼
      "id" => 12117918974004
      "namespace" => "global"
      "key" => "page_key"
      "value" => "page_value"
      "value_type" => "string"
      "description" => null
      "owner_id" => 50703007796
      "created_at" => "2020-07-23T15:31:36+08:00"
      "updated_at" => "2020-07-23T15:31:36+08:00"
      "owner_resource" => "page"
      "admin_graphql_api_id" => "gid://shopify/Metafield/12117918974004"
      ]
      ]
     */
    public static function getMetafield($storeId = 1, $ownerId = '') {
        return Metafield::getMetafield($storeId, $ownerId, 'customer');
    }

    /**
     * 创建属性
     * @param int $storeId 品牌商店id
     * @param int $ownerId id
     * @param string $key  属性key
     * @param string|int $value 属性值
     * @param string $valueType 属性类型 The metafield's information type. Valid values: string, integer, json_string.
     * @param string $description 属性描述
     * @param string $namespace A container for a set of metafields. You need to define a custom namespace for your metafields to distinguish them from the metafields used by other apps. Maximum length: 20 characters.
     * @return array metafield
     * array:11 [▼
      "id" => 12162841280564
      "namespace" => "patazon"
      "key" => "test_key"
      "value" => 98
      "value_type" => "integer"
      "description" => null
      "owner_id" => 3047836876852
      "created_at" => "2020-08-31T02:12:23-07:00"
      "updated_at" => "2020-08-31T02:12:23-07:00"
      "owner_resource" => "customer"
      "admin_graphql_api_id" => "gid://shopify/Metafield/12162841280564"
      ]
     */
    public static function createMetafield($storeId = 1, $ownerId = '', $key = '', $value = '', $valueType = 'integer', $description = '', $namespace = 'patazon') {
        return Metafield::createMetafield($storeId, $ownerId, 'customer', $key, $value, $valueType, $description, $namespace);
    }

}
