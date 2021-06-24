<?php

$apiVersoin = '2020-04';

return [
    /*
      |--------------------------------------------------------------------------
      | Store Sync Configuration
      |--------------------------------------------------------------------------
     */

    1 => [//mpow
        'syncCustomerUrl' => 'https://www.xmpow.com/ajaxlogin/ajax/sync',
        'shopify' => [
            'appSecret' => '243d05792ad42b0e853b0222b6966042c3c0f150c614b190a77cd7d2ee09aae3',
            'apiKey' => 'ba00c9f3c232674e1790c75f66cb059a',
            'password' => 'a9b4a846bf6624db4f8fed06653a7e29',
            'sharedSecret' => '469fa73ad59d64afaf4b52dac95dbd46',
            'storeUrl' => 'xmpow.myshopify.com/admin/api/' . $apiVersoin . '/',
            'schema' => 'https://',
            'graphqlUrl' => 'https://xmpow.myshopify.com/api/' . $apiVersoin . '/graphql.json', //Storefront API URL
            'accessToken' => 'ec46cdeaf62382c65dee8cade9ee717b',
        ],
    ],
    2 => [//victsing
        'shopify' => [
            'appSecret' => 'a330bfb9c584aba17ca78e5fa3033488d667015058f322b93afc3ec1bca404a5',
            'apiKey' => 'a671caa5fd35a0af31d890ededb2cac8',
            'password' => '7200adcb5f773030bb4a3e0327c259d1',
            'sharedSecret' => 'ad873eca78880d16fcd393a24ccfc0bb',
            'storeUrl' => 'victsing.myshopify.com/admin/api/' . $apiVersoin . '/',
            'schema' => 'https://',
            'graphqlUrl' => 'https://victsing.myshopify.com/api/graphql.json', //Storefront API URL
            'accessToken' => '3f181f8fed70eef93811ffe1c67b8d68',
        ],
    ],
    3 => [//holife
        'shopify' => [
            'appSecret' => '60fe8128024577ac20e604a04560cbea863ec46baed9ed07bf4e06db4560f60a',
            'apiKey' => '9a4d5f49f615e7d0952b674947fa26e4',
            'password' => '09fa78a95fd90347fe92b40b803acb7c',
            'sharedSecret' => '8c950caaccc6c1f1f606b9fc5318130a',
            'storeUrl' => 'deholife.myshopify.com/admin/api/' . $apiVersoin . '/',
            'schema' => 'https://',
            'graphqlUrl' => 'https://deholife.myshopify.com/api/graphql.json', //Storefront API URL
            'accessToken' => '33ad1e44e220341662a1f866ed086b82',
        ],
    ],
    5 => [//ikich
        'shopify' => [
            'appSecret' => '0b00a553b3c678ec703b06f1f2b412cd453e3e1eb4c264eb241b3c59c00b5d0d', //All your webhooks will be signed with 订单签名验证
            'apiKey' => '5b0e5ccab8719c99a34e6098cfb8a2f4',
            'password' => 'e40d3ad9b0ab3ff51399b06913f29d24',
            'sharedSecret' => '752dcffb10d64d71b0e2e45ae54fa265',
            'storeUrl' => 'omorc.myshopify.com/admin/api/' . $apiVersoin . '/', //
            'schema' => 'https://',
            'graphqlUrl' => 'https://omorc.myshopify.com/api/graphql.json', //Storefront API URL
            'accessToken' => '21c2fdf22d657af66e9c7ff300174d2e',
        ],
    ],
    6 => [//homasy
        'shopify' => [
            'appSecret' => '6b08d8234efaffc2d06d818b753a5225d0df5e4c2b7a3e5b095a56f0d86b2431', //All your webhooks will be signed with 订单签名验证
            'apiKey' => '59494a19f22ba36a40ae7b8a3e414b44',
            'password' => 'ba38f62d76352d13e931baabcdeb4f4c',
            'sharedSecret' => '208cb874ee8ee3c12c0a2d33498f0105',
            'schema' => 'https://', //https://59494a19f22ba36a40ae7b8a3e414b44:ba38f62d76352d13e931baabcdeb4f4c@homasy.myshopify.com/admin/api/' . $apiVersoin . '/orders.json
            'storeUrl' => 'homasy.myshopify.com/admin/api/' . $apiVersoin . '/',
            'graphqlUrl' => 'https://homasy.myshopify.com/api/' . $apiVersoin . '/graphql.json', //Storefront API URL
            'accessToken' => 'c7537a478b416dc6246cb479d7e3623d',
        ],
    ],
    7 => [//mpow.jp
        'shopify' => [
            'appSecret' => '2858f9cdaf628d97621a6230db98f97ab6ce54ce370c3ca2f98754bf1d0bfaf1', //All your webhooks will be signed with 订单签名验证
            'apiKey' => 'b7054fb2e936b98aedc1b2b6fa22d508',
            'password' => 'f17478e2365a41000f76902526efd057',
            'sharedSecret' => 'eca01f8a4182c83ce9657a2907d2f31c',
            'schema' => 'https://', //https://b7054fb2e936b98aedc1b2b6fa22d508:f17478e2365a41000f76902526efd057@patozon.myshopify.com/admin/api/' . $apiVersoin . '/orders.json
            'storeUrl' => 'patozon.myshopify.com/admin/api/' . $apiVersoin . '/',
            'graphqlUrl' => 'https://patozon.myshopify.com/api/' . $apiVersoin . '/graphql.json', //Storefront API URL
            'accessToken' => '983983e7aa6a557475c8ff496fb1a131',
        ],
    ],
    8 => [//www.ilitom.com
        'shopify' => [
            'appSecret' => '59558be6aae978f394f2bf23fba53324d6a4998358638bd703af0cc2d585b549', //All your webhooks will be signed with 订单签名验证 您的所有 Webhook 都将使用 此字符串 进行签名，使您能够验证它们的完整程度。
            'apiKey' => '1ee80d68555244b283f5bee308098c26',
            'password' => '3fab5ec88cbbf65bf62efb9b348de544',
            'sharedSecret' => '9403c217cae4bd7ddee55f8195797586',
            'schema' => 'https://', //https://1ee80d68555244b283f5bee308098c26:3fab5ec88cbbf65bf62efb9b348de544@ilitom.myshopify.com/admin/api/' . $apiVersoin . '/orders.json
            'storeUrl' => 'ilitom.myshopify.com/admin/api/' . $apiVersoin . '/',
            'graphqlUrl' => 'https://ilitom.myshopify.com/api/' . $apiVersoin . '/graphql.json', //Storefront API URL
            'accessToken' => '4a397aee35f6a11f3d12da5e40b28b90', //店面访问令牌 Store accessToken
        ],
    ],
    9 => [//www.iseneo.com
        'shopify' => [
            'appSecret' => 'aea237b0be10fa2e547b97840ce7c7edead26484523f04a82119f6d6f24ec07d', //All your webhooks will be signed with 订单签名验证 您的所有 Webhook 都将使用 此字符串 进行签名，使您能够验证它们的完整程度。
            'apiKey' => 'f65a3fbd74055bdbae60fdf60d9e68d0',
            'password' => 'ef76cefe135ab3c5c1507a306bc48a6e',
            'sharedSecret' => 'e7f0b4bf7bca853018b20447906a83c9',
            'schema' => 'https://', //https://f65a3fbd74055bdbae60fdf60d9e68d0:ef76cefe135ab3c5c1507a306bc48a6e@ilseneo.myshopify.com/admin/api/' . $apiVersoin . '/orders.json
            'storeUrl' => 'ilseneo.myshopify.com/admin/api/' . $apiVersoin . '/',
            'graphqlUrl' => 'https://ilseneo.myshopify.com/api/' . $apiVersoin . '/graphql.json', //Storefront API URL
            'accessToken' => '183f3d70af19f056a87fbd326bf8c242', //店面访问令牌 Store accessToken
        ],
    ],
    10 => [//www.iatmoko.com
        'shopify' => [
            'appSecret' => 'b28215c71b3fcfff18e6e115f388b3482c69a623253d055f7696a3b3cb99ec63', //All your webhooks will be signed with 订单签名验证 您的所有 Webhook 都将使用 此字符串 进行签名，使您能够验证它们的完整程度。
            'apiKey' => '81fe623ab44b9880463be430e21d30bc',
            'password' => '9f724ed7b07a300468d900873b1d88c7',
            'sharedSecret' => '6796aa5761dc4654b02998519155e52b',
            'schema' => 'https://', //https://f65a3fbd74055bdbae60fdf60d9e68d0:ef76cefe135ab3c5c1507a306bc48a6e@ilseneo.myshopify.com/admin/api/' . $apiVersoin . '/orders.json
            'storeUrl' => 'atmoko.myshopify.com/admin/api/' . $apiVersoin . '/',
            'graphqlUrl' => 'https://atmoko.myshopify.com/api/' . $apiVersoin . '/graphql.json', //Storefront API URL
            'accessToken' => '9d75693157284d07d3768b865e820c4c', //店面访问令牌 Store accessToken
        ],
    ],
//    11 => [//iokmee.com
//        'shopify' => [
//            'appSecret' => 'da217f74a756f7a91950f4aa763e2b4e0e04c06843075d57b6b63e5a56fb6fab', //All your webhooks will be signed with 订单签名验证 您的所有 Webhook 都将使用 此字符串 进行签名，使您能够验证它们的完整程度。
//            'apiKey' => 'b2d7f4b4ed03720350da2d35c12f6af5',
//            'password' => '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',
//            'sharedSecret' => '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',
//            'schema' => 'https://', //https://b2d7f4b4ed03720350da2d35c12f6af5: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@okmee.myshopify.com/admin/api/' . $apiVersoin . '/orders.json
//            'storeUrl' => 'okmee.myshopify.com/admin/api/' . $apiVersoin . '/',
//            'graphqlUrl' => 'https://okmee.myshopify.com/api/' . $apiVersoin . '/graphql.json', //Storefront API URL
//            'accessToken' => '65fa09b23b0f9485b89d5b3ba41afb9e', //店面访问令牌 Store accessToken(Storefront access token)
//        ],
//    ],
    'sandbox_1' => [//mpow
        'shopify' => [
            'appSecret' => 'e44819271f0b65a3ca6f701ac84cfdf237fdbd3c5bd342204d9fef331c9e032a',
            'apiKey' => 'e73cbdcc39bd2f511589fe993299d70c',
            'password' => '7e99e11e449e26b1fa7bbcb627c391f0',
            'sharedSecret' => '27edc395749afdf9192b22b6773246c3',
            'schema' => 'https://',
            'storeUrl' => 'pro-mpow.myshopify.com/admin/api/' . $apiVersoin . '/', //https://e73cbdcc39bd2f511589fe993299d70c:7e99e11e449e26b1fa7bbcb627c391f0@pro-mpow.myshopify.com/admin/api/' . $apiVersoin . '/orders.json
            'graphqlUrl' => 'https://pro-mpow.myshopify.com/api/' . $apiVersoin . '/graphql.json', //Storefront API URL
            'accessToken' => '8fb8307e3dbfbb76599614a5a42e216a', //店面访问令牌 Store accessToken
            'host' => 'pro-mpow.myshopify.com',
        ],
    ],
    'sandbox_2' => [//victsing
        'shopify' => [
            'appSecret' => 'cb72dc17de4e73f8e2620798902574fccca0d7d23b3aad7f3170bd86572c0c58',
            'apiKey' => '04f9b19666851404991b1bb0bbd14a8a',
            'password' => 'e779d96796f0f311d7490ba12c007ad6',
            'sharedSecret' => 'ebc19d5d7278798ab8ecdae354d9ddf9',
            'schema' => 'https://',
            'storeUrl' => 'pro-victsing.myshopify.com/admin/api/' . $apiVersoin . '/', //https://04f9b19666851404991b1bb0bbd14a8a:e779d96796f0f311d7490ba12c007ad6@pro-victsing.myshopify.com/admin/api/' . $apiVersoin . '/orders.json
            'graphqlUrl' => 'https://pro-victsing.myshopify.com/api/' . $apiVersoin . '/graphql.json', //Storefront API URL
            'accessToken' => 'cd4906d0131464c21b4bf4824ff2d0c1', //店面访问令牌 Store accessToken
            'host' => 'pro-victsing.myshopify.com',
        ],
    ],
    'sandbox_3' => [//holife
        'shopify' => [
            'appSecret' => 'f4f3249005907d9bcf5477eade6c2b4a9fa9f9e4fc8c69f1fcd9640b73c532eb',
            'apiKey' => 'ab07b1dd3c946c1d260d269fa65b5d6a',
            'password' => '1d194332e9a3e9e0d2e723016f073353',
            'sharedSecret' => '5246f7e1931b17ead43999a2636388e3',
            'schema' => 'https://',
            'storeUrl' => 'pro-holife.myshopify.com/admin/api/' . $apiVersoin . '/', //https://ab07b1dd3c946c1d260d269fa65b5d6a:1d194332e9a3e9e0d2e723016f073353@pro-holife.myshopify.com/admin/api/' . $apiVersoin . '/orders.json
            'graphqlUrl' => 'https://pro-holife.myshopify.com/api/' . $apiVersoin . '/graphql.json', //Storefront API URL
            'accessToken' => '312c88b4bfe764f4b61401bb28a2aef8', //店面访问令牌 Store accessToken
            'host' => 'pro-holife.myshopify.com',
        ],
    ],
    'sandbox_5' => [//ikich
        'shopify' => [
            'appSecret' => '5cdda8c2afbd8dd875fa8fecbb5f627d64a20d9a227639cc5e0b0a1e3146b967', //All your webhooks will be signed with 订单签名验证
            'apiKey' => '5ed33ebd3f8b37af9f0b71b5067cf1bb',
            'password' => '03b3ff83c702293a75e4244ccd38cf78',
            'sharedSecret' => '279a6691ddf3a3aa0bbf3377e54c9099',
            'storeUrl' => 'pro-ikich.myshopify.com/admin/api/' . $apiVersoin . '/', //https://5ed33ebd3f8b37af9f0b71b5067cf1bb:03b3ff83c702293a75e4244ccd38cf78@pro-ikich.myshopify.com/admin/api/' . $apiVersoin . '/orders.json
            'schema' => 'https://',
            'graphqlUrl' => 'https://pro-ikich.myshopify.com/api/' . $apiVersoin . '/graphql.json', //Storefront API URL
            'accessToken' => 'f2dbe066eb218dba028e2dfc3ebc5d75', //店面访问令牌 Store accessToken
            'host' => 'pro-ikich.myshopify.com',
        ],
    ],
    'sandbox_6' => [//homasy
        'shopify' => [
            'appSecret' => '97f89310b427386ee8512332b720b33aa01b4249e1bbe6c2f5f1910a44c8e881', //All your webhooks will be signed with 订单签名验证
            'apiKey' => '2a741a0bf7a9fdd00db3b614a98a6a23',
            'password' => '5f93a44e6d24d69567445489871f5e5f',
            'sharedSecret' => 'fdf2b0f33e0c4c173d689982310edcc1',
            'schema' => 'https://', //https://2a741a0bf7a9fdd00db3b614a98a6a23:5f93a44e6d24d69567445489871f5e5f@pro-homasy.myshopify.com/admin/api/' . $apiVersoin . '/orders.json
            'storeUrl' => 'pro-homasy.myshopify.com/admin/api/' . $apiVersoin . '/',
            'graphqlUrl' => 'https://pro-homasy.myshopify.com/api/' . $apiVersoin . '/graphql.json', //Storefront API URL
            'accessToken' => '86facead8cf490a1d3c5225c3bae72ed', //店面访问令牌 Store accessToken
            'host' => 'pro-homasy.myshopify.com',
        ],
    ],
    'sandbox_7' => [//mpow.jp
        'shopify' => [
            'appSecret' => '', //All your webhooks will be signed with 订单签名验证
            'apiKey' => '',
            'password' => '',
            'sharedSecret' => '',
            'schema' => 'https://', //https://b7054fb2e936b98aedc1b2b6fa22d508:f17478e2365a41000f76902526efd057@patozon.myshopify.com/admin/api/' . $apiVersoin . '/orders.json
            'storeUrl' => 'patozon.myshopify.com/admin/api/' . $apiVersoin . '/',
            'graphqlUrl' => 'https://patozon.myshopify.com/api/' . $apiVersoin . '/graphql.json', //Storefront API URL
            'accessToken' => '', //店面访问令牌 Store accessToken
            'host' => 'patozon.myshopify.com',
        ],
    ],
    'sandbox_8' => [//沙盒 www.ilitom.com
//        'shopify' => [
//            'appSecret' => '59558be6aae978f394f2bf23fba53324d6a4998358638bd703af0cc2d585b549', //All your webhooks will be signed with 订单签名验证 您的所有 Webhook 都将使用 此字符串 进行签名，使您能够验证它们的完整程度。
//            'apiKey' => '37410798f7cee0ed0b906a5104ebdab9',
//            'password' => 'f4efd00e07214311357d17bd6064e350',
//            'sharedSecret' => '49bceed4b453c8531833a3054733c772',
//            'schema' => 'https://', //https://37410798f7cee0ed0b906a5104ebdab9:f4efd00e07214311357d17bd6064e350@pro-ilitom.myshopify.com/admin/api/' . $apiVersoin . '/orders.json
//            'storeUrl' => 'pro-ilitom.myshopify.com/admin/api/' . $apiVersoin . '/',
//            'graphqlUrl' => 'https://pro-ilitom.myshopify.com/api/' . $apiVersoin . '/graphql.json', //Storefront API URL
//            'accessToken' => '634561bb38866ee0e5d3988b11890ae0', //店面访问令牌 Store accessToken
//        ],
        'shopify' => [
            'appSecret' => '3778325bfdd407911ef725f4c937e5545b6dcd6f265b2dd249257e320cb30711', //All your webhooks will be signed with 订单签名验证 您的所有 Webhook 都将使用 此字符串 进行签名，使您能够验证它们的完整程度。
            'apiKey' => '4c0c2f9919de6179c4c0ddf5072bb0e9',
            'password' => '8f797414c6704801fbdd4d67d35c9777',
            'sharedSecret' => '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   ',
            'schema' => 'https://', //https://37410798f7cee0ed0b906a5104ebdab9:f4efd00e07214311357d17bd6064e350@pro-ilitom.myshopify.com/admin/api/' . $apiVersoin . '/orders.json
            'storeUrl' => 'demo-ilitom.myshopify.com/admin/api/' . $apiVersoin . '/',
            'graphqlUrl' => 'https://demo-ilitom.myshopify.com/api/' . $apiVersoin . '/graphql.json', //Storefront API URL
            'accessToken' => '3cdf789b590aff072ff5e91c0e7dd423', //店面访问令牌 Store accessToken
            'host' => 'demo-ilitom.myshopify.com',
        ],
    ],
    'sandbox_9' => [//沙盒 www.iseneo.com
        'shopify' => [
            'appSecret' => '6f3a285a9a1bec0cad671fb2e5ec79a7666a5f19625193a35babf6f4f4fb462', //All your webhooks will be signed with 订单签名验证 您的所有 Webhook 都将使用 此字符串 进行签名，使您能够验证它们的完整程度。
            'apiKey' => '654be3e1f5ead1b4789858978e0c1beb',
            'password' => '2a9ccf69ee6a98d4c0600d2f2c760b6a',
            'sharedSecret' => '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',
            'schema' => 'https://', //https://654be3e1f5ead1b4789858978e0c1beb:2a9ccf69ee6a98d4c0600d2f2c760b6a@pro-ilseneo.myshopify.com/admin/api/' . $apiVersoin . '/orders.json
            'storeUrl' => 'pro-ilseneo.myshopify.com/admin/api/' . $apiVersoin . '',
            'graphqlUrl' => 'https://pro-ilseneo.myshopify.com/api/' . $apiVersoin . '/graphql.json', //Storefront API URL
            'accessToken' => '051bed34cee6cb36633946b3d7b4b8d4', //店面访问令牌 Store accessToken
            'host' => 'pro-ilseneo.myshopify.com',
        ],
    ],
    'sandbox_10' => [//沙盒 www.iatmoko.com
        'shopify' => [
            'appSecret' => 'dbb1c5191d91e0403d115c96c2e1d2250c95c75c9f5fb725453e2ecf58ca5b12 ', //All your webhooks will be signed with 订单签名验证 您的所有 Webhook 都将使用 此字符串 进行签名，使您能够验证它们的完整程度。
            'apiKey' => 'f8b61a2b42cd49c40c58d8db385a0001',
            'password' => '76fbd337aba2ca92a7d6a7e5e1bf5f9a',
            'sharedSecret' => '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',
            'schema' => 'https://', //https://f65a3fbd74055bdbae60fdf60d9e68d0:ef76cefe135ab3c5c1507a306bc48a6e@ilseneo.myshopify.com/admin/api/' . $apiVersoin . '/orders.json
            'storeUrl' => 'pro-atmoko.myshopify.com/admin/api/' . $apiVersoin . '/',
            'graphqlUrl' => 'https://pro-atmoko.myshopify.com/api/' . $apiVersoin . '/graphql.json', //Storefront API URL
            'accessToken' => 'e1be59ad3df8ad909639f8631d50dfdd', //店面访问令牌 Store accessToken
            'host' => 'pro-atmoko.myshopify.com',
        ],
    ],
//    'sandbox_11' => [//iokmee.com
//        'shopify' => [
//            'appSecret' => 'da217f74a756f7a91950f4aa763e2b4e0e04c06843075d57b6b63e5a56fb6fab', //All your webhooks will be signed with 订单签名验证 您的所有 Webhook 都将使用 此字符串 进行签名，使您能够验证它们的完整程度。
//            'apiKey' => 'b2d7f4b4ed03720350da2d35c12f6af5',
//            'password' => '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',
//            'sharedSecret' => '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',
//            'schema' => 'https://', //https://b2d7f4b4ed03720350da2d35c12f6af5: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@okmee.myshopify.com/admin/api/' . $apiVersoin . '/orders.json
//            'storeUrl' => 'pro-okmee.myshopify.com/admin/api/' . $apiVersoin . '/',
//            'graphqlUrl' => 'https://pro-okmee.myshopify.com/api/' . $apiVersoin . '/graphql.json', //Storefront API URL
//            'accessToken' => '65fa09b23b0f9485b89d5b3ba41afb9e', //店面访问令牌 Store accessToken(Storefront access token)
//        ],
//    ],
];
