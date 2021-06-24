<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace App\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

/**
 * @Constants
 */
class Constant extends AbstractConstants
{
    const REQUEST_PAGE = 'page';
    const REQUEST_PAGE_SIZE = 'page_size';

    const SERVICE_KEY = 'service';
    const METHOD_KEY = 'method';
    const PARAMETERS_KEY = 'parameters';

    const RESPONSE_CODE_KEY = 'code';
    const RESPONSE_MSG_KEY = 'msg';
    const RESPONSE_DATA_KEY = 'data';
    const RESPONSE_EXE_TIME = 'exeTime';
    const REQUEST_DATA_KEY = 'requestData';

    const ACT_LIMIT_KEY = 'limit';
    const ACT_MONTH_LIMIT_KEY = 'month_limit';
    const ACT_WEEK_LIMIT_KEY = 'week_limit';
    const ACT_DAY_LIMIT_KEY = 'day_limit';

    const TIME_FRAME_SHOPIFY_CUSTOMER = '2019-10-29 00:00:00';
    const RULES_NOT_APPLY_STORE = [3, 5];//shopfiy账号同步不执行新规则的官网
    const RULES_APPLY_SOURCE = [5, 6];//shopfiy新规则执行的账号来源

    const DB_TABLE_OLD_CREATED_AT = 'ctime';
    const DB_TABLE_OLD_UPDATED_AT = 'mtime';
    const DB_TABLE_CREATED_AT = 'created_at';
    const DB_TABLE_UPDATED_AT = 'updated_at';
    //const DB_TABLE_STATUS_AT = 'status';
    const DB_TABLE_STATUS = 'status';
    const DB_TABLE_DELETED_AT = 'deleted_at';
    const DB_TABLE_CREATED_MARK = 'created_mark';
    const DB_TABLE_UPDATED_MARK = 'updated_mark';
    const DB_TABLE_STATE = 'state';
    const DB_TABLE_STATE_INVITED = 'invited';
    const DB_TABLE_STATE_DISABLED = 'disabled';//disabled/invited/enabled/declined
    const DB_TABLE_STATE_ENABLED = 'enabled';
    const DB_TABLE_STATE_DECLINED = 'declined';
    const DB_TABLE_ACCEPTS_MARKETING = 'accepts_marketing';
    const DB_TABLE_ACCEPTS_MARKETING_UPDATED_AT = 'accepts_marketing_updated_at';
    const DB_TABLE_PLATFORM_ACCEPTS_MARKETING_UPDATED_AT = 'platform_accepts_marketing_updated_at';
    const DB_TABLE_PLATFORM_CREATED_AT = 'platform_created_at';
    const DB_TABLE_PLATFORM_UPDATED_AT = 'platform_updated_at';
    const DB_TABLE_INVITE_CODE = 'invite_code';
    const DB_TABLE_NEW_INVITE_CODE = 'new_invite_code';
    const DB_TABLE_INVITE_ACCOUNT = 'invite_account';//被邀请者账号
    const DB_TABLE_INVITE_COMMISSION = 'commission';//邀请的奖励
    const DB_TABLE_INVITE_TYPE = 'invite_code_type';//邀请码类型

    const DB_TABLE_STORE_ID = 'store_id';
    const DB_TABLE_ACCOUNT = 'account';
    const DB_TABLE_CUSTOMER_PRIMARY = 'customer_id';
    const DB_TABLE_COUNTRY = 'country';
    const DB_TABLE_ACT_ID = 'act_id';
    const DB_TABLE_STORE_CUSTOMER_ID = 'store_customer_id';
    const DB_TABLE_FIRST_NAME = 'first_name';
    const DB_TABLE_LAST_NAME = 'last_name';
    const DB_TABLE_GENDER = 'gender';
    const DB_TABLE_BRITHDAY = 'brithday';
    const DB_TABLE_SOURCE = 'source';
    const DB_TABLE_LASTLOGIN = 'lastlogin';
    const DB_TABLE_LAST_SYS_AT = 'last_sys_at';
    const DB_TABLE_IP = 'ip';
    const DB_TABLE_ORDER_NO = 'orderno';
    const DB_TABLE_EMAIL = 'email';
    const DB_TABLE_PASSWORD = 'password';

    const DB_TABLE_ACTIVITY_ID = 'activity_id';
    const DB_TABLE_TYPE = 'type';
    const DB_TABLE_KEY = 'key';
    const DB_TABLE_VALUE = 'value';
    const DB_TABLE_TYPE_VALUE = 'type_value';

    const DB_TABLE_REGION = 'region';
    const DB_TABLE_CITY = 'city';
    const DB_TABLE_STREET = 'street';
    const DB_TABLE_ADDR = 'addr';
    const DB_TABLE_ADDRESS = 'address';
    const DB_TABLE_ADDRESSES = 'addresses';
    const DB_TABLE_PLATFORM_ADDRESSES = 'platform_addresses';

    const DB_TABLE_PRIMARY = 'id';
    const DB_TABLE_EXT_ID = 'ext_id';
    const DB_TABLE_EXT_TYPE = 'ext_type';
    const DB_TABLE_EXT_DATA = 'ext_data';

    const DB_TABLE_PRODUCT_ID = 'product_id';
    const DB_TABLE_NAME = 'name';
    const DB_TABLE_SKU = 'sku';
    const DB_TABLE_SHOP_SKU = 'shop_sku';
    const DB_TABLE_ASIN = 'asin';
    const DB_TABLE_PRODUCT_STATUS = 'product_status';
    const DB_TABLE_IMG_URL = 'img_url';
    const DB_TABLE_MB_IMG_URL = 'mb_img_url';
    const DB_TABLE_MB_TYPE = 'mb_type';
    const DB_TABLE_STAR = 'star';
    const DB_TABLE_DES = 'des';
    const DB_TABLE_OPERATOR = 'operator';
    const DB_TABLE_UPLOAD_USER = 'upload_user';
    const DB_TABLE_ACTIVITY_NAME = 'activity_name';
    const DB_TABLE_CLICK = 'click';
    const DB_TABLE_LISTING_PRICE = 'listing_price';
    const DB_TABLE_REGULAR_PRICE = 'regular_price';
    const DB_TABLE_QUERY_RESULTS = 'query_results';

    const DB_TABLE_ACT_UNIQUE = 'act_unique';
    const DB_TABLE_ACT_TYPE = 'act_type';
    const DB_TABLE_MARK = 'mark';
    const DB_TABLE_START_AT = 'start_at';
    const DB_TABLE_END_AT = 'end_at';
    const DB_TABLE_IS_PARTICIPATION_AWARD = 'is_participation_award';
    const DB_TABLE_PRIZE_ID = 'prize_id';
    const DB_TABLE_QTY = 'qty';
    const DB_TABLE_QTY_RECEIVE = 'qty_receive';
    const DB_TABLE_QTY_APPLY = 'qty_apply';
    const DB_TABLE_SORT = 'sort';
    const DB_TABLE_INTERNAL_NAME = 'internal_name';
    const DB_TABLE_SUB_NAME = 'sub_name';
    const DB_TABLE_REMARKS = 'remarks';
    const DB_TABLE_HELP_SUM = 'help_sum';
    const DB_TABLE_IS_PRIZE = 'is_prize';
    const DB_TABLE_MAX = 'max';
    const DB_TABLE_WINNING_VALUE = 'winning_value';
    const DB_TABLE_DISCOUNT = 'discount';
    const DB_TABLE_USE_TYPE = 'use_type';
    const DB_TABLE_AMOUNT = 'amount';
    const DB_TABLE_START_TIME = 'satrt_time';
    const DB_TABLE_END_TIME = 'end_time';
    const DB_TABLE_AMAZON_URL = 'amazon_url';
    const DB_TABLE_PLATFORM = 'platform';
    const DB_TABLE_CURRENCY_CODE = 'currency_code';
    const DB_TABLE_CONTENT = 'content';
    const DB_TABLE_ORDER_TIME = 'order_time';
    const DB_TABLE_ORDER_STATUS = 'order_status';
    const DB_TABLE_BRAND = 'brand';
    const DB_TABLE_ORDER_AT = 'order_at';
    const DB_TABLE_ORDER_ID = 'order_id';
    const DB_TABLE_ORDER_ITEM_ID = 'order_item_id';
    const DB_TABLE_AMAZON_ORDER_ID = 'amazon_order_id';
    const DB_TABLE_LISITING_PRICE = 'lisiting_price';
    const DB_TABLE_PROMOTION_DISCOUNT_AMOUNT = 'promotion_discount_amount';
    const DB_TABLE_PRICE = 'price';
    const DB_TABLE_TTEM_PRICE_AMOUNT = 'item_price_amount';
    const DB_TABLE_QUANTITY_ORDERED = 'quantity_ordered';
    const DB_TABLE_QUANTITY_SHIPPED = 'quantity_shipped';
    const DB_TABLE_IS_GIFT = 'is_gift';
    const DB_TABLE_SERIAL_NUMBER_REQUIRED = 'serial_number_required';
    const DB_TABLE_IS_TRANSPARENCY = 'is_transparency';
    const DB_TABLE_IMG = 'img';
    const DB_TABLE_PURCHASE_DATE_ORIGIN = 'purchase_date_origin';
    const DB_TABLE_SELLER_SKU = 'seller_sku';
    const DB_TABLE_COUNTRY_CODE = 'country_code';
    const DB_TABLE_PURCHASE_DATE = 'purchase_date';

    const DB_TABLE_RATE = 'rate';
    const DB_TABLE_RATE_AMOUNT = 'rate_amount';
    const DB_TABLE_IS_REPLACEMENT_ORDER = 'is_replacement_order';
    const DB_TABLE_IS_PREMIUM_ORDER = 'is_premium_order';
    const DB_TABLE_SHIPMENT_SERVICE_LEVEL_CATEGORY = 'shipment_service_level_category';
    const DB_TABLE_LATEST_SHIP_DATE = 'latest_ship_date';
    const DB_TABLE_EARLIEST_SHIP_DATE = 'earliest_ship_date';
    const DB_TABLE_SALES_CHANNEL = 'sales_channel';
    const DB_TABLE_IS_BUSINESS_ORDER = 'is_business_order';
    const DB_TABLE_FULFILLMENT_CHANNEL = 'fulfillment_channel';
    const DB_TABLE_PAYMENT_METHOD = 'payment_method';
    const DB_TABLE_IS_HAND = 'is_hand';
    const DB_TABLE_ORDER_TYPE = 'order_type';
    const DB_TABLE_SHIP_SERVICE_LEVEL = 'ship_service_level';
    const DB_TABLE_MODFIY_AT_TIME = 'modfiy_at_time';
    const DB_TABLE_LAST_UPDATE_DATE = 'last_update_date';
    const DB_TABLE_PULL_MODE = 'pull_mode';

    const DB_TABLE_IS_PRIME = 'is_prime';
    const DB_TABLE_BUYER_EMAIL = 'buyer_email';
    const DB_TABLE_BUYER_NAME = 'buyer_name';
    const DB_TABLE_SHIPPING_ADDRESS_NAME = 'shipping_address_name';
    const DB_TABLE_STATE_OR_REGION = 'state_or_region';
    const DB_TABLE_POSTAL_CODE = 'postal_code';
    const DB_TABLE_ADDRESS_LINE_1 = 'address_line_1';
    const DB_TABLE_ADDRESS_LINE_2 = 'address_line_2';
    const DB_TABLE_ADDRESS_LINE_3 = 'address_line_3';

    const PARAMETER_INT_DEFAULT = 0;
    const PARAMETER_STRING_DEFAULT = '';
    const PARAMETER_ARRAY_DEFAULT = [];

    const DB_EXECUTION_PLAN_PARENT = 'parent';
    const DB_EXECUTION_PLAN_SETCONNECTION = 'setConnection';
    const DB_EXECUTION_PLAN_STOREID = 'storeId';
    const DB_EXECUTION_PLAN_RELATION = 'relation';
    const DB_EXECUTION_PLAN_BUILDER = 'builder';
    const DB_EXECUTION_PLAN_MAKE = 'make';
    const DB_EXECUTION_PLAN_FROM = 'from';
    const DB_EXECUTION_PLAN_SELECT = 'select';
    const DB_EXECUTION_PLAN_WHERE = 'where';
    const DB_EXECUTION_PLAN_LIMIT = 'limit';
    const DB_EXECUTION_PLAN_OFFSET = 'offset';
    const DB_EXECUTION_PLAN_ORDERS = 'orders';
    const DB_EXECUTION_PLAN_GROUP = 'group';
    const DB_EXECUTION_PLAN_IS_PAGE = 'isPage';
    const DB_EXECUTION_PLAN_PAGINATION = 'pagination';
    const DB_EXECUTION_PLAN_IS_ONLY_GET_COUNT = 'isOnlyGetCount';
    const DB_EXECUTION_PLAN_HANDLE_DATA = 'handleData';
    const DB_EXECUTION_PLAN_FIELD = 'field';
    const DB_EXECUTION_PLAN_DATATYPE = 'dataType';
    const DB_EXECUTION_PLAN_DATA_FORMAT = 'dateFormat';
    const DB_EXECUTION_PLAN_GLUE = 'glue';
    const DB_EXECUTION_PLAN_DEFAULT = 'default';
    const DB_EXECUTION_PLAN_TIME = 'time';
    const DB_EXECUTION_PLAN_IS_ALLOW_EMPTY = 'is_allow_empty';
    const DB_EXECUTION_PLAN_UNSET = 'unset';
    const DB_EXECUTION_PLAN_WITH = 'with';
    const DB_EXECUTION_PLAN_ITEM_HANDLE_DATA = 'itemHandleData';
    const DB_EXECUTION_PLAN_CALLBACK = 'callback';
    const DB_EXECUTION_PLAN_DEBUG = 'sqlDebug';
    const DB_EXECUTION_PLAN_JOIN_DATA = 'joinData';
    const DB_EXECUTION_PLAN_TABLE = 'table';
    const DB_EXECUTION_PLAN_FIRST = 'first';
    const DB_EXECUTION_PLAN_SECOND = 'second';
    const DB_EXECUTION_PLAN_ONLY = 'only';
    const DB_EXECUTION_PLAN_IS_ONLY_GET_PRIMARY = 'isOnlyGetPrimary';
    const DB_EXECUTION_PLAN_DEFAULT_CONNECTION = 'default_connection_';
    const DB_EXECUTION_PLAN_ORDER_DESC = 'DESC';
    const DB_EXECUTION_PLAN_ORDER_ASC = 'ASC';

    const DB_OPERATION = 'dbOperation';
    const DB_OPERATION_SELECT = 'select';
    const DB_OPERATION_INSERT = 'insert';
    const DB_OPERATION_UPDATE = 'update';
    const DB_OPERATION_DELETE = 'delete';
    const DB_OPERATION_DEFAULT = 'no';

    const UPLOAD_FILE_KEY = 'file';
    const FILE_URL = 'url';
    const FILE_TITLE = 'title';
    const FILE_FULL_PATH = 'fileFullPath';
    const RESOURCE_TYPE = 'resourceType';

    const WHETHER_YES_VALUE = 1;
    const WHETHER_YES_VALUE_CN = '是';
    const WHETHER_NO_VALUE = 0;
    const WHETHER_NO_VALUE_CN = '否';

    const EXPORT_DISTINCT_FIELD = 'distinctField';
    const EXPORT_PRIMARY_KEY = 'primaryKey';
    const EXPORT_PRIMARY_VALUE_KEY = 'primaryValueKey';
    const ACT_ALIAS = 'act';
    const ACT_PRODUCT_ALIAS = 'ap';
    const LINKER = '.';

    const PLATFORM_AMAZON = 'amazon';
    const PLATFORM_SHOPIFY = 'shopify';
    const SHOPIFY_URL_PREFIX = 'pages';

    const REQUEST_MARK = 'request_mark';
    const ORDER_STATUS_DEFAULT = -1;
    const ORDER_STATUS_MATCHING = 'Matching';//-1:Matching 0:Pending 1:Shipped 2:Canceled 3:Failure
    const ORDER_STATUS_PENDING = 'Pending';
    const ORDER_STATUS_SHIPPED = 'Shipped';
    const ORDER_STATUS_CANCELED = 'Canceled';
    const ORDER_STATUS_FAILURE = 'Failure';
    const ORDER_STATUS_MATCHING_INT = -1;
    const ORDER_STATUS_PENDING_INT = 0;
    const ORDER_STATUS_SHIPPED_INT = 1;
    const ORDER_STATUS_CANCELED_INT = 2;
    const ORDER_STATUS_FAILURE_INT = 3;

    const AUDIT_STATUS = 'audit_status';
    const WARRANTY_AT = 'warranty_at';
    const BUSINESS_TYPE = 'business_type';
    const PRODUCT_TYPE = 'product_type';
    const ORDER_DESC = 'desc';
    const ORDER_BY = 'orderby';
    const ORDER = 'order';
    const TOTAL = 'total';
    const TOTAL_PAGE = 'total_page';
    const QUERY = 'query';

    const DB_TABLE_PRODUCT_COUNTRY = 'product_country';
    const DB_TABLE_ORDER_COUNTRY = 'order_country';
    const DB_TABLE_REVIEW_LINK = 'review_link';
    const DB_TABLE_REVIEW_IMG_URL = 'review_img_url';
    const DB_TABLE_REVIEW_TIME = 'review_time';
    const DB_TABLE_STAR_AT = 'star_at';
    const DB_TABLE_ADD_TYPE = 'add_type';
    const DB_TABLE_ACTION = 'action';
    const DB_TABLE_REWARD_NAME = 'reward_name';

    const EXCEPTION_CODE = 'exception_code';
    const EXCEPTION_MSG = 'message';

    const DB_TABLE_DICT_KEY = 'dict_key';
    const DB_TABLE_DICT_VALUE = 'dict_value';

    const RESPONSE_SUCCESS_CODE = 1;//响应成功状态码
    const RESPONSE_FAILURE_CODE = 0;//响应失败默认状态码
    const WARRANTY_DATE = 'warranty_date';//订单延保时间
    const WARRANTY_DES = 'warranty_des';//订单延保描述

    const AVATAR = 'avatar';//头像

    const DB_TABLE_CODE_TYPE = 'code_type';//code类型
    const DB_TABLE_IS_DEFAULT = 'is_default';//是否默认
    const DB_TABLE_PLATFORM_ORDER_ITEM_ID = 'platform_order_item_id';//平台订单item id
    const DB_TABLE_CONTACT_US_ID = 'contact_us_id';//联系我们id

    const DB_TABLE_VOTE_ID = 'vote_id';
    const DB_TABLE_VOTE_ITEM_ID = 'vote_item_id';
    const DB_TABLE_UNIQUE_STR = 'unique_str';

    const COUNTDOWN = 'countdown';//活动倒计时
    const END_DATE = 'end_date';//活动结束时间
    const START_DATE = 'start_date';//活动开始时间
    const REVIEW_STATUS = 'review_status';//审核状态
    const EXPIRE_TIME = 'expire_time';//到期时间
    const ACTIVITY_WINNING_ID = 'activity_winning_id';//申请id 或者 中奖id
    const SOCIAL_MEDIA = 'social_media';//社媒
    const IP_LIMIT_KEY = 'ip_limit';//IP限制字段key
    const SIGNUP_KEY = 'signup';//注册key
    const ACTION_INVITE = 'invite';//用户行为:邀请
    const REWARD_STATUS = 'reward_status';//礼品状态
    const ORDER_PLATFORM = 'order_platform';
    const ACTION_ACTIVATE = 'activate';//用户行为:激活
    const FREQUENCY = 'frequency';//用户引导:1 未引导 2+n 已引导
    const APP_ENV = 'app_env';//app 环境
    const LINE_ITEMS = 'line_items';//shopify order items
    const USERNAME = 'username';//用户名
    const CLIENT_ACCESS_URL = 'client_access_url';//用户访问页面地址
    const REVIEW_CREDIT = 'review_credit';//review 积分
    const REWARD_STATUS_NO = 'reward_status_no';//最近asin状态变更标识
    const DEL_ASIN = 'del_asin';//删除asin

    const ORDER_BIND = 'order_bind';
    const CREATE = 'create';
    const DB_TABLE_TRANSACTION_ID = 'transaction_id'; //交易id
    const DB_TABLE_PROCESSED_AT = 'processed_at'; //处理时间
    const DB_TABLE_NOTE = 'note'; //备注
    const DB_TABLE_ITEM_ID = 'item_id'; //备注
    const DB_TABLE_ADDRESS_TYPE = 'address_type'; //地址类型
    const DB_TABLE_FULFILLMENT_ID = 'fulfillment_id'; //物流id
    const DB_TABLE_REFUND_ID = 'refund_id'; //退款id
    const DB_TABLE_REFUND_ITEM_ID = 'refund_item_id'; //退款item_id
    const DB_TABLE_TOTAL_TAX = 'total_tax'; //税
    const DB_TABLE_CURRENCY = 'currency'; //货币
    const DB_TABLE_PHONE = 'phone'; //电话
    const DB_TABLE_LOCATION_ID = 'location_id'; //位置id
    const DB_TABLE_FULFILLMENT_STATUS = 'fulfillment_status'; //物流状态
    const DB_TABLE_GATEWAY = 'gateway'; //支付网关
    const DB_TABLE_TEST = 'test'; //是否测试
    const DB_TABLE_FULFILLMENT_SERVICE = 'fulfillment_service';
    const DB_TABLE_QUANTITY = 'quantity'; //数量
    const DB_TABLE_REQUIRES_SHIPPING = 'requires_shipping';
    const DB_TABLE_ADMIN_GRAPHQL_API_ID = 'admin_graphql_api_id';
    const DB_TABLE_ADDRESS1 = 'address1'; //地址
    const DB_TABLE_ADDRESS2 = 'address2'; //可选地址
    const DB_TABLE_ZIP = 'zip'; //邮编
    const DB_TABLE_PROVINCE = 'province';
    const DB_TABLE_COMPANY = 'company'; //公司
    const DB_TABLE_LATITUDE = 'latitude'; //
    const DB_TABLE_LONGITUDE = 'longitude';
    const DB_TABLE_PROVINCE_CODE = 'province_code';
    const DB_TABLE_TRACKING_NUMBERS = 'tracking_numbers';
    const DB_TABLE_TRACKING_URLS = 'tracking_urls';
    const DB_TABLE_RECEIPT = 'receipt';
    const DB_TABLE_TOTAL_PRICE = 'total_price'; //总金额
    const DB_TABLE_PRESENTMENT_CURRENCY = 'presentment_currency';
    const RESPONSE_TEXT = 'responseText'; //响应数据

    const IS_HAS_APPLY_INFO = 'is_has_apply_info';//是否提交了申请资料
    const HAS_ONE = 'hasOne';//关联关系 一对一
    const REVIEW_AT = 'review_at';//审核时间
    const ACT_ID = 'actId';//审核时间
    const BANNER_NAME = 'banner_name';//banner_name
    const CATEGORY_ID = 'category_id';//category_id
    const IN_STOCK = 'in_stock';//in_stock
    const OUT_STOCK = 'out_stock';//out_stock
    const HELP_ACCOUNT = 'help_account';//help_account
    const PREFIX = 'prefix';//prefix
    const AMAZON_HOST = 'amazon_host';//amazon_host
    const DB_EXECUTION_PLAN_CONNECTION = '{connection}';
    const DB_EXECUTION_PLAN_OR = '{or}';
    const DB_EXECUTION_PLAN_DATATYPE_STRING = 'string';
    const DB_EXECUTION_PLAN_ORDER_BY = 'orderBy';
    const DB_EXECUTION_PLAN_CUSTOMIZE_WHERE = '{customizeWhere}';
    const DB_TABLE_DISCOUNT_PRICE = 'discount_price';
    const DB_TABLE_EXTINFO = 'extinfo';
    const ACTIVITY_COUPON = 'activity_coupon';
    const DB_EXECUTION_PLAN_GROUP_COMMON = 'common';
    const DB_TABLE_PRIZE_ITEM_ID = 'prize_item_id';
    const LOTTERY_NUM = 'lotteryNum';
    const LOTTERY_TOTAL = 'lotteryTotal';
    const ACT_TOTAL = 'actTotal';
    const CLICK_SHARE = 'click_share';
    const SOCIAL_MEDIA_URL = 'social_media_url';//社媒url
    const CLICK_VIP_CLUB = 'click_vip_club';
    const DB_TABLE_APPLY_ID = 'apply_id';
    const DB_DATABASE = 'database';
    const SUBJECT = 'subject';
    const DB_TABLE_COUNTRY_NAME = 'country_name';
    const DB_TABLE_RECEIVE = 'receive';
    const COUPON = 'coupon';
    const ENV_PRODUCTION = 'production';
    const DB_TABLE_CREDIT = 'credit';
    const START_TIME = 'start_time';
    const DB_TABLE_REMARK= 'remark';
    const PLATFORM_SERVICE_SHOPIFY = 'Shopify';
    const DB_TABLE_EDIT_AT = 'edit_at';
    const DB_TABLE_PROFILE_URL = 'profile_url';
    const DB_TABLE_INTERESTS = 'interests';
    const DB_TABLE_IS_ORDER = 'isorder';
    const CUSTOMER = 'customer';
    const CUSTOMER_ID = 'customerId';
    const DB_EXECUTION_PLAN_DATATYPE_DATETIME = 'datetime';
    const RESPONSE_DATA = 'responseData';
    const SUCCESS_COUNT = 'success_count';
    const EXISTS_COUNT = 'exists_count';
    const FAIL_COUNT = 'fail_count';
    const STORE_DICT_TYPE = 'storeDictType';
    const ENCRYPTION = 'encryption';
    const LEVEL_ERROR = 'error';
    const TO_EMAIL = 'to_email';
    const STORE_DICT_TYPE_EMAIL_COUPON = 'email_coupon';
    const ADDRESS_HOME = 'address_home';
    const DB_TABLE_INTEREST = 'interest';
    const DB_TABLE_TOTAL_CREDIT = 'total_credit';
    const DB_TABLE_EXP = 'exp';
    const DB_TABLE_VIP = 'vip';
    const DB_TABLE_IS_ACTIVATE = 'isactivate';
    const DB_TABLE_FROM_EMAIL = 'from_email';
    const DB_TABLE_ROW_STATUS = 'row_status';
    const LOG_TYPE_EMAIL_DEBUG = 'email_debug';
    const DB_TABLE_TOPIC = 'topic';
    const SEND_NUMS = 'send_nums';
    const APP = 'app';
    const EXPORT_PATH = 'export_path';
    const RESPONSE_CACHE = 'cache';
    const RESPONSE_COUNT = 'count';
    const DEFAULT_WARRANTY_DATE = '2 years';//默认订单延保时间
    const DB_TABLE_PULL_NUM = 'pull_num';
    const CUSTOMER_ORDER = 'customer_order';
    const RESPONSE_WARRANTY = 'warranty';
    const CONFIG_KEY_WARRANTY_DATE_FORMAT = 'warranty_date_format';
    const IKICH_WARRANTY_DATE = '1-Year Extended';
    const DB_TABLE_PLATFORM_ORDER_ID = 'platform_order_id';
    const DB_TABLE_PLATFORM_CUSTOMER_ID = 'platform_customer_id';
    const DB_TABLE_PLATFORM_CLOSED_AT = 'platform_closed_at';
    const DB_TABLE_PLATFORM_CANCELLED_AT = 'platform_cancelled_at';
    const DB_TABLE_PLATFORM_CANCELLED_REASON = 'platform_cancel_reason';

    const ACT_FORM_SLOT_MACHINE = 'slot_machine';
    const WINNING_LOGS = 'winning_logs';
    const SCORE = 'score';
    const TOTAL_SCORE = 'total_score';

    const BUSINESS_TYPE_ORDER = 'order';//订单
    const BUSINESS_TYPE_FULFILLMENT = 'fulfillment';//物流
    const BUSINESS_TYPE_REFUND = 'refund';//退款
    const BUSINESS_TYPE_TRANSACTION = 'transaction';//交易

    const DB_TABLE_UNIQUE_ID = 'unique_id';//唯一id
    const DB_TABLE_ORDER_UNIQUE_ID = 'order_unique_id';//订单唯一id
    const DB_TABLE_ORDER_ITEM_UNIQUE_ID = 'order_item_unique_id';//订单 item 唯一id
    const DB_TABLE_FULFILLMENT_UNIQUE_ID = 'fulfillment_unique_id';//物流唯一id
    const DB_TABLE_REFUND_UNIQUE_ID = 'refund_unique_id';//退款唯一id
    const DB_TABLE_PRODUCT_UNIQUE_ID = 'product_unique_id';//产品唯一id
    const DB_TABLE_PRODUCT_VARIANT_UNIQUE_ID = 'product_variant_unique_id';//产品变种唯一id
    const DB_TABLE_PRODUCT_IMAGE_UNIQUE_ID = 'product_image_unique_id';//产品图片唯一id

    const CUSTOMERS = 'customers';
    const PRODUCTS = 'products';
    const CUSTOMER_SOUTCE = 'customer_source';
    const CURRENCY_SYMBOL = 'currency_symbol'; //货币符号
    const DEFAULT_CUSTOMER_PRIMARY_VALUE = -1;//默认账号id

    const FIELD = 'field';
    const ORDER_REVIEW = 'order_review'; //

    const DICT = 'dict'; //
    const DICT_STORE = 'dictStore'; //

    const HOLIFE_WARRANTY_DATE = '1-year Extended';
    const ACTIVITY_CONFIG_TYPE = 'activityConfigType';

    const PLATFORM_SERVICE_AMAZON = 'Amazon';
    const DRIVER = 'driver';
    const ACTION_TYPE = 'action_type';
    const SUB_TYPE = 'sub_type';
    const CLIENT_ACCESS_API_URI = 'client_access_api_uri';
    const CLIENT_DATA = 'clientData';
    const REQUEST_HEADER_DATA = 'headerData';

    const DEVICE = 'device';//设备信息
    const DEVICE_TYPE = 'device_type';// 设备类型 1:手机 2：平板 3：桌面
    const PLATFORM_VERSION = 'platform_version';//系统版本
    const BROWSER = 'browser';// 浏览器信息  (Chrome, IE, Safari, Firefox, ...)
    const BROWSER_VERSION = 'browser_version';// 浏览器版本
    const LANGUAGES = 'languages';// 语言 ['nl-nl', 'nl', 'en-us', 'en']
    const IS_ROBOT = 'is_robot';//是否是机器人

    const TOKEN = 'token';//token

    const VARIANT_ID = 'variant_id';
    const STORE_PRODUCT_ID = 'store_product_id';

    const OWNER_RESOURCE = 'owner_resource';
    const NAME_SPACE = 'namespace';
    const OP_ACTION = 'op_action';
    const METAFIELDS = 'metafields';
    const OWNER_ID = 'owner_id';
    const DESCRIPTION = 'description';
    const VALUE_TYPE = 'value_type';
    const METAFIELD_ID = 'metafield_id';
    const EXCHANGED_NUMS = 'exchanged_nums';
    const SORTS = 'sorts';
    const VALID = 'valid';
    const REWARD = 'reward';

    const RELATED_DATA = 'relatedData';//关联数据
    const SERIAL_HANDLE = 'serialHandle';//联系执行
    const DB_TABLE_STORE_DICT_KEY = 'conf_key';
    const DB_TABLE_STORE_DICT_VALUE = 'conf_value';
    const ACTION_FOLLOW = 'follow';//用户行为:关注
    const ACTION_LOGIN = 'login';//用户行为:登录
    const REGISTER_RESPONSE = 'registerResponse';//注册响应数据
    const ACTIVATE_EMAIL_HANDLE = 'activateEmailHandle';//激活邮件响应数据
    const ORDER_DATA = 'orderData';//订单id
    const REGISTERED = 'registered';//注册key
    const IS_IP_LIMIT_WHITE_LIST = 'isIpLimitWhitelist';//是否是白名单
    const REGISTERED_IP_LIMIT = 'registeredIpLimit';//注册时同一个ip限制注册的账号个数
    const POINT_STORE_NAME_SPACE = 'point_store';//积分商城命名空间

    const DB_TABLE_USE_CHANNEL = 'use_channel';//使用场景 亚马逊 官网
    const DB_TABLE_PRODUCT_CODE = 'product_code';//产品 折扣码
    const DB_TABLE_PRODUCT_URL = 'product_url';//产品 链接

    const DB_TABLE_REVIEWER = 'reviewer';//审核人

    const PLATFORM_SERVICE_LOCALHOST = 'Localhost';
    const DB_TABLE_CREDIT_LOG_ID = 'credit_log_id';//积分流水id
    const PLATFORM_SERVICE_PATOZON = 'Patozon';

    const PROCESS_PLATFORM = 'Hhxsv5';//进程平台
    const PROCESS_PLATFORM_ILLUMINATE = 'Illuminate';//默认进程平台
    const TASK_PLATFORM = 'Hhxsv5';//任务平台
    const TASK_PLATFORM_ILLUMINATE = 'Illuminate';//默认任务平台

    const DB_TABLE_INVITE_CODE_TYPE = 'invite_code_type'; //邀请码类型

    const PRODUCT_NAME = 'product_name';
    const ONE_CATEGORY_NAME = 'one_category_name';
    const TWO_CATEGORY_NAME = 'two_category_name';
    const THREE_CATEGORY_NAME = 'three_category_name';
    const FILE_NAME = 'file_name';
    const NEW_FILE_URL = 'file_url';

    const CONTEXT_REQUEST_DATA = 'contextRequestData';
}
