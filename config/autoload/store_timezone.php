<?php

return [
    /*
      |--------------------------------------------------------------------------
      | Store Timezone Configuration
      |--------------------------------------------------------------------------
     */
    //1:mpow：'America/Denver', //太平洋(美国/加拿大)
    //2:vt：'Asia/Shanghai', //太平洋(美国/加拿大)
    //3:holife：'Asia/Shanghai', //太平洋(美国/加拿大)
    //5:ikich：'America/Vancouver', //太平洋(美国/加拿大)
    //6:homasy：'America/Denver', //太平洋(美国/加拿大)
    //8:litom:'Asia/Hong_Kong', //太平洋(美国/加拿大)   202008131633 业务端shopfiy全部统一，系统相应的时区对应都统一，注册登录，过滤查询等等 ，不在列的官网暂时先不用处理了
    'cn' => [
        'timezone' => 'Asia/Shanghai', //太平洋(美国/加拿大)
        'db_timezone' => '+08:00',
    ],
    0 => [
        'timezone' => 'America/Vancouver', //太平洋(美国/加拿大)
        'db_timezone' => '-08:00',
    ],
    1 => [
//            'timezone' => 'UTC',
//            'db_timezone' => '+00:00',
//        'timezone' => 'America/Denver', //山区(美国/加拿大) America/Los_Angeles
//        'db_timezone' => '-07:00',
        'timezone' => 'America/Vancouver', //太平洋(美国/加拿大)
        'db_timezone' => '-08:00',
    ],
    2 => [
//        'timezone' => 'Asia/Shanghai', //太平洋(美国/加拿大)
//        'db_timezone' => '+08:00',
        'timezone' => 'America/Vancouver', //太平洋(美国/加拿大)
        'db_timezone' => '-08:00',
    ],
    3 => [
//        'timezone' => 'Asia/Shanghai', //太平洋(美国/加拿大)
//        'db_timezone' => '+08:00',
        'timezone' => 'America/Vancouver', //太平洋(美国/加拿大)
        'db_timezone' => '-08:00',
    ],
    5 => [
        'timezone' => 'America/Vancouver', //太平洋(美国/加拿大)
        'db_timezone' => '-08:00',
    ],
    6 => [
//        'timezone' => 'America/Denver', //山区(美国/加拿大)
//        'db_timezone' => '-07:00',
        'timezone' => 'America/Vancouver', //太平洋(美国/加拿大)
        'db_timezone' => '-08:00',
    ],
    7 => [
        'timezone' => 'Asia/Tokyo', //日本((GMT+09:00) 大阪、札幌、东京)
        'db_timezone' => '+09:00',
    ],
    8 => [
//        'timezone' => 'Asia/Hong_Kong', //香港((GMT+08:00) 香港)
//        'db_timezone' => '+08:00',
        'timezone' => 'America/Vancouver', //太平洋(美国/加拿大)
        'db_timezone' => '-08:00',
    ],
    9 => [
        'timezone' => 'Asia/Hong_Kong', //香港((GMT+08:00) 香港)
        'db_timezone' => '+08:00',
    ],
    10 => [
        'timezone' => 'Asia/Hong_Kong', //香港((GMT+08:00) 香港)
        'db_timezone' => '+08:00',
    ],
//    11 => [
//        'timezone' => 'America/Vancouver', //太平洋(美国/加拿大)
//        'db_timezone' => '-08:00',
//    ],

    'sandbox_1' => [
//            'timezone' => 'UTC',
//            'db_timezone' => '+00:00',
//        'timezone' => 'America/Denver', //山区(美国/加拿大) America/Los_Angeles
//        'db_timezone' => '-07:00',
        'timezone' => 'America/Vancouver', //太平洋(美国/加拿大)
        'db_timezone' => '-08:00',
    ],
    'sandbox_2' => [
//        'timezone' => 'Asia/Shanghai', //太平洋(美国/加拿大)
//        'db_timezone' => '+08:00',
        'timezone' => 'America/Vancouver', //太平洋(美国/加拿大)
        'db_timezone' => '-08:00',
    ],
    'sandbox_3' => [
//        'timezone' => 'Asia/Shanghai', //太平洋(美国/加拿大)
//        'db_timezone' => '+08:00',
        'timezone' => 'America/Vancouver', //太平洋(美国/加拿大)
        'db_timezone' => '-08:00',
    ],
    'sandbox_5' => [
        'timezone' => 'America/Vancouver', //太平洋(美国/加拿大)
        'db_timezone' => '-08:00',
    ],
    'sandbox_6' => [
//        'timezone' => 'America/Denver', //山区(美国/加拿大)
//        'db_timezone' => '-07:00',
        'timezone' => 'America/Vancouver', //太平洋(美国/加拿大)
        'db_timezone' => '-08:00',
    ],
    'sandbox_7' => [
        'timezone' => 'Asia/Tokyo', //日本((GMT+09:00) 大阪、札幌、东京)
        'db_timezone' => '+09:00',
    ],
    'sandbox_8' => [
//        'timezone' => 'Asia/Hong_Kong', //香港((GMT+08:00) 香港)
//        'db_timezone' => '+08:00',
        'timezone' => 'America/Vancouver', //太平洋(美国/加拿大)
        'db_timezone' => '-08:00',
    ],
    'sandbox_9' => [
        'timezone' => 'Asia/Hong_Kong', //香港((GMT+08:00) 香港)
        'db_timezone' => '+08:00',
    ],
    'sandbox_10' => [
        'timezone' => 'Asia/Hong_Kong', //香港((GMT+08:00) 香港)
        'db_timezone' => '+08:00',
    ],
//    'sandbox_11' => [
//        'timezone' => 'America/Vancouver', //太平洋(美国/加拿大)
//        'db_timezone' => '-08:00',
//    ],
];
