<?php

/**
 * Created by Patazon.
 * @desc   : 数据统计
 * @author : Roy_qiu
 * @email  : Roy_qiu@patazon.net
 * @date   : 2020/7/16 15:36
 */

namespace App\Services;

use App\Models\Store;
use App\Constants\Constant;
use Hyperf\Utils\Arr;
use Illuminate\Support\Facades\Cache;
use Hyperf\DbConnection\Db as DB;
use App\Utils\FunctionHelper;

class StatisticsService extends BaseService {

    public static $genders = [
        'unknown',
        'male',
        'female',
        'private',
    ];

    public static function getCacheTags() {
        return 'statistics';
    }

    /**
     * 统计数据剔除测试邮箱
     * @return array
     */
    public static function testEmail() {
        $notLike = 'not like';
        return [
            [Constant::DB_TABLE_ACCOUNT, $notLike, '%@chacuo.net%'],
            [Constant::DB_TABLE_ACCOUNT, $notLike, '%@qq.com%'],
            [Constant::DB_TABLE_ACCOUNT, $notLike, '%@patazon.net%'],
            [Constant::DB_TABLE_ACCOUNT, $notLike, '%@163.com%'],
        ];
    }

    public static $countStr = "count(*) as num";

    /**
     * 获取要统计的品牌id数据
     * @param int $storeId 品牌id
     * @return array $staStores
     */
    public static function getStaStore($storeId = 0)
    {
        $extWhere = [
            Constant::DICT => [
                Constant::DB_TABLE_DICT_KEY => Constant::DB_TABLE_VALUE,
            ],
            Constant::DICT_STORE => [],
        ];
        $staStores = static::getConfig($storeId, 'sta_store', $extWhere);
        return $staStores ? explode(',', data_get($staStores, Constant::DB_TABLE_VALUE)) : [];
    }

    /**
     * 获取要统计的品牌数据
     * @param array $staStores
     * @param int $storeId 品牌id
     * @return array|null
     */
    public static function getStaStoreData($staStores = null, $storeId = 0)
    {
        $staStores = $staStores ?? static::getStaStore($storeId);

        $key = md5(implode(':', ['getStaStoreData', $storeId, json_encode($staStores)]));
        $ttl = static::getCacheTtl();

        $parameters = [$key, $ttl, function () use ($staStores) {
            $storeWhere = [
                Constant::DB_TABLE_PRIMARY => $staStores,
            ];
            return StoreService::getModel()->buildWhere($storeWhere)->pluck(Constant::DB_TABLE_NAME, Constant::DB_TABLE_PRIMARY);
        }];

        return static::handleCache(static::getCacheTags(), FunctionHelper::getJobData(static::getNamespaceClass(), 'remember', $parameters));
    }

    /**
     * 按官网||国家||性别|年龄|来源 统计用户数
     * @param string $field 统计字段
     * @return mixed
     */
    public static function userNumsByField($field = Constant::DB_TABLE_STORE_ID, $requestData = []) {
        $ttl = 120;
        $statDate = static::getStatDate($requestData);

        $staStoreId = data_get($requestData, 'sta_store_id', 0);
        $key = implode(':', ['stat', $field, md5(json_encode($statDate)), $staStoreId]);

        $parameters = [$key, $ttl, function () use($field, $statDate, $requestData, $staStoreId) {
            if (!in_array($field, [Constant::DB_TABLE_STORE_ID, Constant::DB_TABLE_COUNTRY, Constant::DB_TABLE_GENDER, Constant::DB_TABLE_BRITHDAY, Constant::DB_TABLE_SOURCE])) {
                return [];
            }

            $staStores = static::getStaStore();
            $stores = static::getStaStoreData($staStores);

            $where = [
                static::testEmail(),
                Constant::DB_TABLE_STORE_ID=>$staStores,
            ];

            $select = [DB::raw(static::$countStr), DB::raw("$field as " . Constant::FIELD)];
            switch ($field) {
                case Constant::DB_TABLE_STORE_ID:
                    $userNums = CustomerService::getModel()
                        ->select($select)
                        ->whereBetween(Constant::DB_TABLE_OLD_CREATED_AT, [data_get($statDate, Constant::START_TIME), data_get($statDate, Constant::DB_TABLE_END_TIME)])
                        ->where(Constant::DB_TABLE_STATUS, 1)
                        ->buildWhere($where)
                        ->withTrashed()
                        ->groupBy($field)
                        ->get()
                        ->pluck('num',Constant::FIELD)
                    ;

                    $tmpRet = [];
                    foreach ($stores as $storeId => $storeName) {
                        $tmpRet[] = [
                            Constant::FIELD => $storeName,
                            'num' => data_get($userNums,$storeId,0),
                        ];
                    }
                    $userNums = $tmpRet;

                    break;

                case Constant::DB_TABLE_COUNTRY:
                case Constant::DB_TABLE_GENDER:

                    foreach ($stores as $storeId => $storeName) {
                        $select[] = DB::raw("sum(if(store_id={$storeId},1,0)) as {$storeName}");
                    }

                    $userNums = CustomerInfoService::getModel()
                        ->select($select)
                        ->buildWhere($where)
                        ->groupBy($field)
                        ->get();
                    $userNums = $userNums->isEmpty() ? [] : $userNums->toArray();
                    switch ($field) {
                        case Constant::DB_TABLE_GENDER://性别
                            foreach ($userNums as &$item) {
                                $item[Constant::FIELD] = static::$genders[$item[Constant::FIELD]];
                                $storeData = [];
                                foreach ($stores as $storeId => $storeName) {
                                    $storeData[] = [
                                        Constant::DB_TABLE_VALUE => data_get($item, $storeName, 0),
                                        Constant::DB_TABLE_NAME => $storeName,
                                    ];
                                }
                                $item['storeData'] = $storeData;
                            }

                            break;

                        case Constant::DB_TABLE_COUNTRY://国家
                            $userNums = array_values(Arr::sort($userNums, function ($value) {
                                return $value['num'];
                            }));

                            $tmpRet = [
                                'other' => [
                                    'num' => 0,
                                    Constant::FIELD => 'other',
                                ],
                            ];
                            $sum = count($userNums) - 1;
                            for ($i = $sum; $i >= 0; $i--) {
                                $item = $userNums[$i];
                                empty($item[Constant::FIELD]) && $item[Constant::FIELD] = 'unknown';

                                $country = data_get($item, Constant::FIELD, '');
                                $num = data_get($item, 'num', 0);

                                if (count($tmpRet) <= 10) {
                                    $tmpRet[$country] = [
                                        'num' => $num,
                                        Constant::FIELD => $country,
                                    ];

                                    $storeData = [];
                                    foreach ($stores as $storeId => $storeName) {
                                        $storeData[] = [
                                            Constant::DB_TABLE_VALUE => data_get($item, $storeName, 0),
                                            Constant::DB_TABLE_NAME => $storeName,
                                        ];
                                    }
                                    $tmpRet[$country]['storeData'] = $storeData;
                                } else {
                                    $tmpRet['other']['num'] += $item['num'];
                                    foreach ($stores as $storeId => $storeName) {

                                        if (!isset($tmpRet['other']['storeData'][$storeName][Constant::DB_TABLE_VALUE])) {
                                            $tmpRet['other']['storeData'][$storeName][Constant::DB_TABLE_VALUE] = 0;
                                        }

                                        $tmpRet['other']['storeData'][$storeName][Constant::DB_TABLE_VALUE] += data_get($item, $storeName, 0);
                                        $tmpRet['other']['storeData'][$storeName][Constant::DB_TABLE_NAME] = $storeName;
                                    }
                                }
                            }
                            //$userNums = array_values($tmpRet);
                            $userNums = $tmpRet;

                            break;

                        default:
                            break;
                    }
                    break;

                case Constant::DB_TABLE_BRITHDAY:

                    $format = 'Y-m-d';
                    $now = date($format);
                    $_18year = date($format, strtotime("-18 year"));
                    $_25year = date($format, strtotime("-25 year"));
                    $_35year = date($format, strtotime("-35 year"));
                    $_45year = date($format, strtotime("-45 year"));
                    $_55year = date($format, strtotime("-55 year"));
                    $_65year = date($format, strtotime("-65 year"));
                    $ages = [
                        '65+' => [
                            '0000-00-00',
                            $_65year,
                        ],
                        '55-64' => [
                            $_65year,
                            $_55year,
                        ],
                        '45-54' => [
                            $_55year,
                            $_45year,
                        ],
                        '35-44' => [
                            $_45year,
                            $_35year,
                        ],
                        '25-34' => [
                            $_35year,
                            $_25year,
                        ],
                        '18-24' => [
                            $_25year,
                            $_18year,
                        ],
                        '0-17' => [
                            $_18year,
                            $now,
                        ],
                    ];

                    $select = [];
                    foreach ($ages as $key => $age) {
                        $key = str_replace('-', '_', str_replace('+', '_', $key));
                        $select[] = DB::raw("sum(if(date_format(`" . Constant::DB_TABLE_BRITHDAY . "`, '%Y-%m-%d') between '" . $age[0] . "' and '" . $age[1] . "',1,0)) as {$key}");
                        foreach ($stores as $storeId => $storeName) {
                            $select[] = DB::raw("sum(if(store_id={$storeId} and (date_format(`" . Constant::DB_TABLE_BRITHDAY . "`, '%Y-%m-%d') between '" . $age[0] . "' and '" . $age[1] . "'),1,0)) as {$key}_{$storeName}");
                        }
                    }

                    $where = [
                        static::testEmail(),
                        Constant::DB_TABLE_STORE_ID=>$staStores,
                        [[Constant::DB_TABLE_BRITHDAY, '!=', ''], [Constant::DB_TABLE_BRITHDAY, '!=', '1000-01-01']]
                    ];
                    $userNums = CustomerInfoService::getModel()
                        ->select($select)
                        ->buildWhere($where)
                        ->get();

                    $tmpRet = [];
                    foreach ($userNums as $item) {

                        foreach ($ages as $key => $age) {
                            $_key = str_replace('-', '_', str_replace('+', '_', $key));

                            $tmpRet[$key] = [
                                'num' => data_get($item, $_key, 0),
                                Constant::FIELD => $key,
                            ];

                            foreach ($stores as $storeId => $storeName) {
                                $tmpRet[$key]['storeData'][] = [
                                    Constant::DB_TABLE_VALUE => data_get($item, "{$_key}_{$storeName}", 0),
                                    Constant::DB_TABLE_NAME => $storeName,
                                ];
                            }
                        }
                    }

                    $userNums = $tmpRet;
                    break;

                case Constant::DB_TABLE_SOURCE://来源

                    $select = [];

                    $configData = static::getConfig($staStoreId, 'customer_source');
                    foreach ($configData as $key => $value) {
                        $select[] = DB::raw("sum(if(ext1 in({$value}),1,0)) as {$key}");
                    }

                    $where = [];
                    if ($staStoreId) {
                        $where['c.' . Constant::DB_TABLE_STORE_ID] = $staStoreId;
                    }

                    $userNums = CustomerService::getModel()
                        ->select($select)
                        ->from('customer as c')
                        ->leftJoin('dict as d', function ($join) {
                            $join->where('d.type', 'source')->on('d.dict_key', '=', 'c.source');
                        })
                        ->whereBetween('c.' . Constant::DB_TABLE_OLD_CREATED_AT, [data_get($statDate, Constant::START_TIME), data_get($statDate, Constant::DB_TABLE_END_TIME)])
                        ->where('c.' . Constant::DB_TABLE_STATUS, 1)
                        ->where(static::testEmail())
                        ->withTrashed()
                        ->where($where)
                        ->get();

                    $tmpRet = [];
                    foreach ($userNums as $item) {

                        foreach ($configData as $key => $value) {
                            $tmpRet[] = [
                                'num' => data_get($item, $key, 0),
                                Constant::FIELD => $key,
                            ];
                        }
                    }
                    $userNums = $tmpRet;

                    break;

                default:
                    $userNums = [];
                    break;
            }

            $ret = [];
            foreach ($userNums as $userNumItem) {
                $ret[] = [
                    Constant::DB_TABLE_VALUE => $userNumItem['num'],
                    Constant::DB_TABLE_NAME => $userNumItem[Constant::FIELD],
                    'storeData' => array_values(data_get($userNumItem, 'storeData', []))
                ];
            }

            return $ret;
        }];

        return static::handleCache(static::getCacheTags(), FunctionHelper::getJobData(static::getNamespaceClass(), 'remember', $parameters));
    }

    /**
     * 按年龄段统计用户数
     * @return array
     */
    public static function userNumsByAge() {
        $ret = [];
        $format = 'Y-m-d';
        $now = date($format);
        $_18year = date($format, strtotime("-18 year"));
        $_25year = date($format, strtotime("-25 year"));
        $_35year = date($format, strtotime("-35 year"));
        $_45year = date($format, strtotime("-45 year"));
        $_55year = date($format, strtotime("-55 year"));
        $_65year = date($format, strtotime("-65 year"));
        $ages = [
            '65+' => [
                '0',
                $_65year,
            ],
            '55-64' => [
                $_65year,
                $_55year,
            ],
            '45-54' => [
                $_55year,
                $_45year,
            ],
            '35-44' => [
                $_45year,
                $_35year,
            ],
            '25-34' => [
                $_35year,
                $_25year,
            ],
            '18-24' => [
                $_25year,
                $_18year,
            ],
            '0-17' => [
                $_18year,
                $now,
            ],
        ];

        foreach ($ages as $key => $item) {
            $nums = intval(static::_userNumsByAge($item[0], $item[1]));
            $ret[] = [
                'num' => $nums,
                'field' => $key,
            ];
        }

        return $ret;
    }

    /**
     * 统计用户年龄段数量
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return mixed
     */
    public static function _userNumsByAge($startTime, $endTime) {
        return CustomerInfoService::getModel()
                        ->select([DB::raw(static::$countStr)])
                        ->where(Arr::collapse([static::testEmail(), [[Constant::DB_TABLE_BRITHDAY, '>', $startTime], [Constant::DB_TABLE_BRITHDAY, '<=', $endTime], [Constant::DB_TABLE_BRITHDAY, '!=', '']]]))
                        ->count();
    }

    /**
     * 获取统计时间段
     * @param array $requestData 请求参数
     * @param boolean $isHandleTime 是否要处理时间 true：是  false：否 默认：true
     * @return array $data
     */
    public static function getStatDate($requestData, $isHandleTime=true) {
        $format = 'Y-m-d';

        $endTime = data_get($requestData, Constant::DB_TABLE_END_TIME, date($format));
        $startTime = data_get($requestData, Constant::START_TIME, date($format, strtotime("-30 day")));

        empty($endTime) && $endTime = date($format);
        empty($startTime) && $startTime = date($format, strtotime("-30 day"));

        return [
            Constant::START_TIME => $startTime . ($isHandleTime ? ' 00:00:00':''),
            Constant::DB_TABLE_END_TIME => $endTime . ($isHandleTime ?  ' 23:59:59':''),
        ];
    }

    /**
     * 按时间统计各个官网注册人数|回访人数
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param string $type 按天，周，月，季度
     * @param int $statType 统计类型(1注册,2回访)
     * @return array
     */
    public static function userNumsByTime($startTime, $endTime, $type, $statType = 1) {

        $str = '';
        switch ($type) {
            case 'day'://天
                $str = $statType == 1 ? "date_format(ctime, '%Y-%m-%d') time" : "date_format(created_at, '%Y-%m-%d') time";
                break;

            case 'week'://周
                $statType == 1 && $str = "date_format(ctime, '%x%v') time";
                $statType == 2 && $str = "date_format(created_at, '%x%v') time";
                break;

            case 'month'://月
                $statType == 1 && $str = "date_format(ctime, '%Y-%m') time";
                $statType == 2 && $str = "date_format(created_at, '%Y-%m') time";
                break;

            case 'quarterly'://季度
                $statType == 1 && $str = 'CONCAT_WS("-",date_format(ctime, "%Y"),QUARTER(ctime)) time';
                $statType == 2 && $str = 'CONCAT_WS("-",DATE_FORMAT(created_at, "%Y"),QUARTER(created_at)) time';
                break;

            default:
                $str = "";
                break;
        }

        if (empty($str)) {
            return [];
        }

        $ttl = 120;
        $key = "statReg_{$type}_{$startTime}_{$endTime}";
        $statType == 2 && $key = "statLogin_{$type}_{$startTime}_{$endTime}";

        $parameters = [$key, $ttl, function () use($startTime, $endTime, $str, $statType, $type) {

            $staStores = static::getStaStore();

            $stores = static::getStaStoreData($staStores);

            $where = [
                static::testEmail(),
                Constant::DB_TABLE_STORE_ID=>$staStores,
            ];

            switch ($statType) {
                case 1://注册
                    $userNums = CustomerService::getModel()
                        ->select([DB::raw(static::$countStr), DB::raw($str), Constant::DB_TABLE_STORE_ID])
                        ->whereBetween(Constant::DB_TABLE_OLD_CREATED_AT, [$startTime, $endTime])
                        ->where(Constant::DB_TABLE_STATUS, 1)
                        ->buildWhere($where)
                        ->withTrashed()
                        ->groupBy("time", Constant::DB_TABLE_STORE_ID)
                        ->get();

                    break;

                default://回访
                    $userNums = ReportLogService::getModel()
                        ->from('report_logs as rl')
                        ->select([DB::raw(static::$countStr), DB::raw($str), 'rl' . Constant::LINKER .Constant::DB_TABLE_STORE_ID])
                        ->leftJoin(DB::raw('`ptxcrm`.`crm_customer` AS crm_c'), function ($join) {
                            $join->on('c.customer_id', '=', 'rl.customer_id')->where('c' . Constant::LINKER . Constant::DB_TABLE_STATUS, 1);
                        })
                        ->whereBetween('rl' . Constant::LINKER . Constant::DB_TABLE_CREATED_AT, [$startTime, $endTime])
                        ->where([[Constant::ACTION_TYPE, '<=', 4]])
                        ->buildWhere([
                            static::testEmail(),
                            'c.'.Constant::DB_TABLE_STORE_ID => $staStores,
                        ])
                        ->groupBy('rl' . Constant::LINKER .Constant::DB_TABLE_STORE_ID, "time")
                        ->get();
                    break;
            }

            $ret = [];
            foreach ($userNums as $userNumItem) {

                $time = data_get($userNumItem,'time');

                foreach ($stores as $storeName) {
                    !isset($ret[$time][$storeName]) && $ret[$time][$storeName] = 0;
                }

                $ret[$time]['time'] = $time;
                $ret[$time][data_get($stores, data_get($userNumItem,Constant::DB_TABLE_STORE_ID,''))] = data_get($userNumItem,'num', 0);

                if ($type == 'week') {
                    $dateParse = date_parse_from_format("Ym", $time);
                    $weekStartTime = strtotime($dateParse['year'] . '-01-01 00:00:00') + ($dateParse['month'] - 1) * 86400 * 7;
                    $weekDays = OrderReviewService::getWeekDays($weekStartTime, 1);
                    $ret[$time]['time'] = $weekDays['week_start'] . '_' . $weekDays['week_end'];
                }
            }

            return array_values(Arr::sort($ret, function ($value) {
                return $value['time'];
            }));
        }];

        return static::handleCache(static::getCacheTags(), FunctionHelper::getJobData(static::getNamespaceClass(), 'remember', $parameters));
    }

    /**
     * 按时间统计各个官网注册人数 环比 同比
     * @param string $startTime 开始时间
     * @return array
     */
    public static function userNumsByCompared($startTime) {

        $ttl = 120;
        $key = implode(':', [__FUNCTION__, $startTime]);
        $parameters = [$key, $ttl, function () use ($startTime) {

            //环比时间段
            $chainRatioStartAT = FunctionHelper::handleTime($startTime, '-1 day', 'Y-m-d');
            $chainRatioEndAT = $chainRatioStartAT . ' 23:59:59';
            $chainRatioStartAT .= ' 00:00:00';

            //当前查询时间段
            $queryStartAt = $startTime . ' 00:00:00';
            $queryEndAT = $startTime . ' 23:59:59';

            //同比时间段
            $yoYStartAT = FunctionHelper::handleTime($startTime, '-1 year', 'Y-m-d');
            $yoYEndAT = $yoYStartAT . ' 23:59:59';
            $yoYStartAT .= ' 00:00:00';

            $atData = [
                'chainRatio' => [$chainRatioStartAT, $chainRatioEndAT], //环比时间段
                'query' => [$queryStartAt, $queryEndAT], //当前查询时间段
                'yoY' => [$yoYStartAT, $yoYEndAT], //同比时间段
            ];

            $select = [Constant::DB_TABLE_STORE_ID];
            foreach ($atData as $key => $value) {
                $select[] = DB::raw("sum(if(" . Constant::DB_TABLE_OLD_CREATED_AT . " between '" . $value[0] . "' and '" . $value[1] . "',1,0)) as {$key}");
            }

            //获取要统计的品牌
            $staStores = static::getStaStore();
            $stores = static::getStaStoreData($staStores);

            $where = [
                static::testEmail(),
                Constant::DB_TABLE_STORE_ID => $staStores,
            ];

            $userNums = CustomerService::getModel()
                ->select($select)
                ->where(function ($query) use ($atData) {
                    foreach ($atData as $key => $value) {
                        $query->orWhere(function ($_query) use ($value) {
                            $_query->whereBetween(Constant::DB_TABLE_OLD_CREATED_AT, $value);
                        });
                    }
                })
                ->where(Constant::DB_TABLE_STATUS, 1)
                ->buildWhere($where)
                ->withTrashed()
                ->groupBy(Constant::DB_TABLE_STORE_ID)
                ->get()
                ->pluck(null, Constant::DB_TABLE_STORE_ID);

            $ret = [];
            foreach ($stores as $storeId => $storeName) {

                $_data = data_get($userNums, $storeId, []);

                $chainRatioNum = data_get($_data, 'chainRatio', 0);
                $queryNum = data_get($_data, 'query', 0);
                $yoYNum = data_get($_data, 'yoY', 0);

                $ret[] = [
                    'name' => $storeName,
                    'data' => [
                        'chainRatio' => [
                            'date' => FunctionHelper::handleTime(data_get($atData, 'chainRatio.0', ''), '', 'Y-m-d'),
                            'num' => $chainRatioNum,
                            'ratio' => $chainRatioNum ? FunctionHelper::handleNumber((($queryNum / $chainRatioNum) - 1) * 100) : 0,
                        ], //环比时间段
                        'query' => [
                            'date' => FunctionHelper::handleTime(data_get($atData, 'query.0', ''), '', 'Y-m-d'),
                            'num' => $queryNum,
                            'ratio' => 0,
                        ], //当前查询时间段
                        'yoY' => [
                            'date' => FunctionHelper::handleTime(data_get($atData, 'yoY.0', ''), '', 'Y-m-d'),
                            'num' => $yoYNum,
                            'ratio' => $yoYNum ? FunctionHelper::handleNumber((($queryNum / $yoYNum) - 1) * 100) : 0,
                        ], //同比时间段
                    ]
                ];
            }

            return $ret;
        }
        ];

        return static::handleCache(static::getCacheTags(), FunctionHelper::getJobData(static::getNamespaceClass(), 'remember', $parameters));
    }

    /**
     * 延保统计
     * @param array $requestData 请求参数
     * @return array
     */
    public static function orderWarraytySta($requestData) {

        $statDate = static::getStatDate($requestData);

        $ttl = 120;
        $key = implode(':', [__FUNCTION__, md5(json_encode($statDate))]);

        $parameters = [$key, $ttl, function () use($statDate, $requestData) {


            $startAt = data_get($statDate, Constant::START_TIME);
            $endAt = data_get($statDate, Constant::DB_TABLE_END_TIME);
            $ret = [];

            //获取要统计的品牌
            $staStores = static::getStaStore();
            $stores = static::getStaStoreData($staStores);

            foreach ($stores as $storeId => $storeName) {

                $userNums = OrderWarrantyService::getModel($storeId)
                    ->whereBetween(Constant::DB_TABLE_OLD_CREATED_AT, [$startAt, $endAt])
                    ->where(Constant::DB_TABLE_STATUS, 1)
                    ->where(static::testEmail())
                    ->withTrashed()
                    ->count(DB::raw('DISTINCT ' . Constant::DB_TABLE_CUSTOMER_PRIMARY));
//                                ->pluck(Constant::DB_TABLE_CUSTOMER_PRIMARY)
//                                ->unique()
//                                ->count();

                $reviewUserNums = OrderReviewService::getModel($storeId)
                    ->whereBetween(Constant::DB_TABLE_REVIEW_TIME, [$startAt, $endAt])
                    ->where(Constant::DB_TABLE_REVIEW_TIME, '>','2019-01-01 00:00:00')
                    ->where(Constant::DB_TABLE_STATUS, 1)
                    ->where(static::testEmail())
                    ->withTrashed()
                    ->count(DB::raw('DISTINCT ' . Constant::DB_TABLE_CUSTOMER_PRIMARY))
                ;
                $ret[] = [
                    'name' => $storeName,
                    'warrayty' => $userNums,
                    'review' => $reviewUserNums,
                ];
            }

            return $ret;
        }];

        return static::handleCache(static::getCacheTags(), FunctionHelper::getJobData(static::getNamespaceClass(), 'remember', $parameters));
    }

}
