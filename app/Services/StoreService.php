<?php

/**
 * 商店服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use Carbon\Carbon;
use App\Utils\Support\Facades\Cache;
use App\Models\Store;
use App\Models\DictStore;
use App\Services\Traits\GetDefaultConnectionModel;

class StoreService extends BaseService {

    use GetDefaultConnectionModel;

    public static function getStore($host, $country) {
        $tags = config('cache.tags.store', ['{store}']);
        $ttl = config('cache.ttl'); //缓存时间 单位秒

        $dictValue = str_replace('.', '_', $host);
        $storeData = Cache::tags($tags)->remember($host, $ttl, function () use($host, $dictValue) {

            if (empty($host)) {
                return null;
            }

            $storeData = Store::where(['host' => $host])->first();
            if (empty($storeData)) {
                $nowTime = Carbon::now()->toDateTimeString();

                //添加商城
                $storeData = [
                    'host' => $host,
                    'name' => $host,
                    'created_at' => $nowTime,
                    'updated_at' => $nowTime,
                ];
                $id = Store::insertGetId($storeData);
                $storeData = Store::where(['id' => $id])->first();

                //添加商城邮件模板文件夹数据
//                $where = [
//                    'type' => 'store_id',
//                    'dict_key' => $id,
//                ];
//                Dict::where($where)->withTrashed()->forceDelete();
//                $dictData = [
//                    'type' => 'store_id',
//                    'dict_key' => $id,
//                    'dict_value' => $dictValue,
//                    'ctime' => $nowTime,
//                    'mtime' => $nowTime,
//                ];
//                Dict::insert($dictData);
                //添加商城配置数据
                $dictStoreData = [
                    [
                        'store_id' => $id,
                        'type' => 'db',
                        'conf_key' => 'database',
                        'conf_value' => $host,
                        'remark' => $host . ' 数据库名',
                        'ctime' => $nowTime,
                        'mtime' => $nowTime,
                    ],
                    [
                        'store_id' => $id,
                        'type' => 'signup',
                        'conf_key' => 'credit',
                        'conf_value' => 0,
                        'remark' => $host . ' 注册积分',
                        'ctime' => $nowTime,
                        'mtime' => $nowTime,
                    ],
                    [
                        'store_id' => $id,
                        'type' => 'signup',
                        'conf_key' => 'exp',
                        'conf_value' => 0,
                        'remark' => $host . ' 注册经验',
                        'ctime' => $nowTime,
                        'mtime' => $nowTime,
                    ],
                    [
                        'store_id' => $id,
                        'type' => 'subcribe',
                        'conf_key' => 'support',
                        'conf_value' => 0,
                        'remark' => $host . ' 是否支持订阅 0:否 1:是',
                        'ctime' => $nowTime,
                        'mtime' => $nowTime,
                    ],
                    [
                        'store_id' => $id,
                        'type' => 'email',
                        'conf_key' => 'from',
                        'conf_value' => 'service@xmpow.com',
                        'remark' => $host . ' 邮件发送者',
                        'ctime' => $nowTime,
                        'mtime' => $nowTime,
                    ],
                    [
                        'store_id' => $id,
                        'type' => 'email',
                        'conf_key' => 'coupon',
                        'conf_value' => 0,
                        'remark' => $host . ' 是否发送coupon邮件  0:不发coupon邮件 1:发coupon邮件',
                        'ctime' => $nowTime,
                        'mtime' => $nowTime,
                    ],
                    [
                        'store_id' => $id,
                        'type' => 'email',
                        'conf_key' => 'coupon_subject',
                        'conf_value' => 'Welcome ! You have gotten a gift for our first meeting!',
                        'remark' => $host . ' coupon邮件主题',
                        'ctime' => $nowTime,
                        'mtime' => $nowTime,
                    ],
                    [
                        'store_id' => $id,
                        'type' => 'email',
                        'conf_key' => 'activate',
                        'conf_value' => 0,
                        'remark' => $host . ' 是否发送激活邮件 0:不发coupon邮件 1:发coupon邮件',
                        'ctime' => $nowTime,
                        'mtime' => $nowTime,
                    ],
                    [
                        'store_id' => $id,
                        'type' => 'email',
                        'conf_key' => 'activate_subject',
                        'conf_value' => 'Verify Your Email Before You Start',
                        'remark' => $host . ' 账号激活邮件主题',
                        'ctime' => $nowTime,
                        'mtime' => $nowTime,
                    ],
                    [
                        'store_id' => $id,
                        'type' => 'activate',
                        'conf_key' => 'credit',
                        'conf_value' => 0,
                        'remark' => $host . ' 激活积分',
                        'ctime' => $nowTime,
                        'mtime' => $nowTime,
                    ],
                    [
                        'store_id' => $id,
                        'type' => 'activate',
                        'conf_key' => 'exp',
                        'conf_value' => 0,
                        'remark' => $host . ' 激活经验',
                        'ctime' => $nowTime,
                        'mtime' => $nowTime,
                    ],
                    [
                        'store_id' => $id,
                        'type' => 'activate',
                        'conf_key' => 'back_url',
                        'conf_value' => 0,
                        'remark' => $host . ' 激活落地页',
                        'ctime' => $nowTime,
                        'mtime' => $nowTime,
                    ],
                    [
                        'store_id' => $id,
                        'type' => 'invite',
                        'conf_key' => 'rank_score',
                        'conf_value' => 0,
                        'remark' => $host . ' 邀请排行榜积分',
                        'ctime' => $nowTime,
                        'mtime' => $nowTime,
                    ],
                    [
                        'store_id' => $id,
                        'type' => 'share',
                        'conf_key' => 'rank_score',
                        'conf_value' => 0,
                        'remark' => $host . ' 分享排行榜积分',
                        'ctime' => $nowTime,
                        'mtime' => $nowTime,
                    ],
                    [
                        'store_id' => $id,
                        'type' => 'order_bind',
                        'conf_key' => 'support_exp',
                        'conf_value' => 1,
                        'remark' => $host . ' 订单绑定支持经验获取',
                        'ctime' => $nowTime,
                        'mtime' => $nowTime,
                    ],
                    [
                        'store_id' => $id,
                        'type' => 'customer',
                        'conf_key' => 'support_vip',
                        'conf_value' => 1,
                        'remark' => $host . ' 会员是否支持等级 1:支持 0:不支持',
                        'ctime' => $nowTime,
                        'mtime' => $nowTime,
                    ],
                ];

//                $countryData = Dict::where('type', 'country')->withTrashed()->value('dict_key');
//                foreach ($countryData as $country) {
//                    $country = strtolower($country);
//                    $dictStoreData[] = [
//                        'store_id' => $id,
//                        'type' => 'email',
//                        'conf_key' => 'view_coupon',
//                        'conf_value' => '',
//                        'remark' => $host . ' coupon邮件模板',
//                        'ctime' => $nowTime,
//                        'mtime' => $nowTime,
//                        'country' => $country,
//                    ];
//
//                    $dictStoreData[] = [
//                        'store_id' => $id,
//                        'type' => 'email',
//                        'conf_key' => 'link',
//                        'conf_value' => '',
//                        'remark' => $host . ' 门店amazon地址',
//                        'ctime' => $nowTime,
//                        'mtime' => $nowTime,
//                        'country' => $country,
//                    ];
//
//                    $dictStoreData[] = [
//                        'store_id' => $id,
//                        'type' => 'exchange',
//                        'conf_key' => $country,
//                        'conf_value' => 0,
//                        'remark' => $host . ' 货币比率(相对美金)',
//                        'ctime' => $nowTime,
//                        'mtime' => $nowTime,
//                        'country' => $country,
//                    ];
//                }
                DictStore::insert($dictStoreData);
            }

            return $storeData;
        });

        //创建邮件模板文件
//        $fileName = 'views/emails/' . $dictValue . '/coupon/' . strtolower($country) . '.blade.php';
//        if (!file_exists(resource_path($fileName))) {
//            Storage::disk('app_resources')->put($fileName, '');
//        }

        return $storeData;
    }

    /**
     * 获取官网的id集合
     * @return mixed
     */
    public static function getStoreIds() {
        $tags = config('cache.tags.store', ['{store}']);
        $key = 'store_ids';
        $ttl = config('cache.ttl', 86400);
        $data = Cache::tags($tags)->remember($key, $ttl, function () {
            return $data = Store::select(['id'])->get();
        });
        return $data;
    }
}
