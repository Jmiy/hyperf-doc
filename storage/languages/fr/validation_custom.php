<?php

$validationCustom = [
    /*
      |--------------------------------------------------------------------------
      | Custom Validation Language Lines https://learnku.com/docs/laravel/5.8/validation/3899
      |--------------------------------------------------------------------------
      |
      | Here you may specify custom validation messages for attributes using the
      | convention "attribute.rule" to name the lines. This makes it quick to
      | specify a specific custom language line for a given attribute rule.
      |
     */

    'test' => [
        1 => [
            'attribute-name' => 'TEST',
            'custom' => [
                'accepted' => 'The :attribute must be accepted.',
                'active_url' => 'The :attribute is not a valid URL.',
            ],
        ],
    ],
    '1' => 'success', //成功
    '0' => 'failure', //失败
    '10000' => [
        1 => [
            'custom' => [
                'api_code_msg' => 'Account has been activated',
            ],
        ],
        'default' => [
            'api_code_msg' => 'Account has been activated',
        ],
    ], //账号已激活
    '10001' => 'Messages are limited,please wait for about 10 minutes before you try again.', //激活邮件超过频次限制
    '10002' => 'Multiple registrations of the same account at the same time', //同一时间同一账号多次注册
    '10003' => 'The same account is bound multiple times at the same time', //同一时间同一账号多次导入shopify
    '10016' => 'System error, input again 60 seconds later.', //注册失败
    '10028' => [
        1 => [
            'custom' => [
                'api_code_msg' => 'Error: Slow down, too many requests from this IP address.',
            ],
        ],
        'default' => [
            'api_code_msg' => 'Your IP request is too many times, please contact our customer service.',
        ],
    ], //同一个ip注册账号超过限制
    '10029' => 'Account already exists', //账号已经存在
    '20000' => '', //积分相关.
    '20001' => 'Insufficient points', //积分不足.
    '30000' => [
        3 => [
            'custom' => [
                'api_code_msg' => 'Your order has been successful extended, Please try another one.',
            ],
        ],
        1 => [
            'custom' => [
                'api_code_msg' => 'your order submit successfully.Please do not repeat',
            ]
        ],
        'default' => [
            'api_code_msg' => 'The order number you submitted is duplicate,please try another one.',
        ],
    ], //订单重复绑定.  Order already exists, please fill in a new one.
    '30001' => 'The same order is bound multiple times at the same time', //同一订单同一时间多次绑定
    '39000' => 'Order number must be passed', //订单号必传
    '39001' => 'Order number does not exist.', //订单不存在
    '39002' => 'No order email triggered', //没有触发订单邮件
    '39003' => 'Email exist.', //订单邮件已经存在
    '39005' => 'Email processing', //订单邮件处理中
    '39006' => 'Order Incorrect', //订单号不正确
    '39007' => 'Error! Order is pending, please register later.', //订单状态pending
    '39008' => "Error! Failed order can't be registered.", //订单状态cancel
    '50000' => '', //分享相关.
    '60000' => '', //活动相关.
    '60001' => 'Participation must be activated', //参加活动必须激活
    '60002' => 'Application information has been filled in, please do not submit again',
    '60003' => 'Your application is still in progress, please wait for the result.',
    '60005' => "Successful application, don't repeat application", //申请众测成功，不要重复申请
    '60006' => 'The requested product does not exist', //申请的商品不存在
    '60007' => [
        3 => [
            'custom' => [
                'api_code_msg' => 'Sorry ! you are late,the prize out of stock. ',
            ],
        ],
        5 => [
            'custom' => [
                'api_code_msg' => 'Sorry ! you are late,the prize out of stock. ',
            ],
        ],
        'default' => [
            'api_code_msg' => 'Sorry ! you are late,the prize out of stock. ',
        ],

    ], //库存不足
    '60008' => 'Multiple applications for the same product at the same time', //同一时间同一产品多次申请
    '60009' => 'The requested quantity of products is illega', //申请的商品数量不合法
    '60010' => 'Submit failed, please submit later', //添加申请记录失败
    '60011' => 'Only one user can apply for the same IP or account',
    '60012' => 'Multiple submissions',//用户已经参与过，不要重复参与
    '60100' => 'Your apply has reached the maximum, please contact customer service manually <support@holife.com>',
    '60101' => 'Your application has been submitted',
    '61000' => 'Already voted', //已经投票
    '61001' => 'Voting item does not exist', //投票项不存在
    '61002' => 'Submit failed, please submit later', //投票失败
    '61003' => 'Failed to update voting leaderboard', //更新投票排行榜失败
    '61004' => 'Request processing, please do not repeat the request', //高并发投票失败
    '61100' => 'Unlock inviter account does not exist', //解锁邀请者账号不存在
    '61101' => 'You has helped your friends unlock it once. ', //被邀请者已经助力过
    '61102' => 'System request failed. Please operate later', //用户未申请商品
    '61103' => 'Power Success', //用户未申请商品
    '61104' => 'The product has been unlocked successfully', //商品已经解锁成功,不需要重复解锁
    '61105' => 'The account of the player and the invitee are the same', //解锁者和邀请者是同一个账号
    '61106' => 'System request failed. Please operate later', //添加解锁流水失败
    '61107' => 'System request failed. Please operate later', //更新库存失败
    '61108' => 'You has helped your friends unlock it once. ', //解锁过程中已经被别人优先解锁
    '61109' => [
        3 => [
            'custom' => [
                'api_code_msg' => 'Sorry ! you are late,the prize out of stock. ',
            ],
        ],
        5 => [
            'custom' => [
                'api_code_msg' => 'Sorry ! you are late,the prize out of stock. ',
            ],
        ],
        'default' => [
            'api_code_msg' => 'Sorry ! you are late,the prize out of stock. ',
        ],

    ], //总库存或者分库存不足
    '61110' => 'The invitation fails, please operate later', //更新解锁状态失败
    '61111' => [
        3 => [
            'custom' => [
                'api_code_msg' => 'Help unlock multiple times at the same time.',
            ],
        ],
        5 => [
            'custom' => [
                'api_code_msg' => 'Help unlock multiple times at the same time.',
            ],
        ],
        'default' => [
            'api_code_msg' => 'Request processing, please do not repeat the request',
        ],

    ], //同一时间同一ip多次助力解锁
    '61112' => 'You has helped your friends unlock it once. ', //被邀请者已经助力过
    '61113' => 'Submit failed, please submit later', //更新商品申请数量失败
    '61114' => 'The task is not completed.', //任务没完成
    '61115' => 'File is illegal', //文件不合法
    '61116' => 'Image uploaded', //已上传过图片
    '61117' => 'IP address is invalid', //IP地址不合法
    '61118' => 'Not within operable time period', //不在可操作时间段内
    '62000' => [
        3 => [
            'custom' => [
                'api_code_msg' => 'Run out of game',
            ],
        ],
        'default' => [
            'api_code_msg' => 'The opportunity to participate in the event has been used up',
        ],

    ], //参与活动的机会已经使用完了
    '62001' => 'Exceeded the number of winnings', //超过了中奖次数
    '62002' => 'No prizes', //已经没有奖品
    '62003' => 'Failure to obtain winning data', //中奖数据获取失败
    '62004' => 'Not winning', //如果已经中过实物奖，就不可以中其他奖品了
    '62005' => 'The lucky draw has been used up', //抽奖机会已经使用完了
    '62006' => 'Invalid invitation code', //邀请码无效
    '62007' => 'Request processing, please do not repeat the request', //同一时间同一用户多次抽奖
    '62008' => 'Not winning', //已经中过安慰奖，不能再中安慰奖
    '62009' => 'Duplicate IP address', //ip重复
    '62010' => "One person can only test one product per month. Welcome to apply again next month. ", //
    '63000' => 'The product is off the shelf', //商品已下架
    '69998' => 'Activity does not exist', //活动不存在
    '69999' => 'Activity has ended', //活动结束
    '70000' => 'No Support Subcribe', //该官网不支持订阅
    '70001' => 'You have subscribed', //邮箱已经订阅
    '80000' => 'Mail failed to send',
    '80001' => 'Mail failed to send', //发送邮件异常
    '90000' => '', //会员通讯录相关
    '100000' => 'Request processing, please do not repeat the request', //高并发提交调查问券，提交失败
    '100001' => 'Submitted, no need to repeat', //同一个ip调查问券已经提交，不需要重复提交
    '100002' => 'Request processing, please do not repeat the request', //并发发送调查问券邮件
    '100003' => 'Mail already exists', //调查问券邮件已存在
    '100004' => 'Submitted, no need to repeat', //同一个邮箱调查问券已经提交，不需要重复提交
    '110000' => 'Submit failed, please submit later', //添加联系我们记录失败
    '110001' => 'Request processing, please do not repeat the request', //高并发签到失败
    '110002' => 'The day has to sign', //当天已经签到
    '110003' => 'Followed', //关注成功
    '110004' => 'Followed', //已关注
    '110005' => 'This type of follow is not supported', //不支持此类型关注
    '110006' => 'login successful', //登录成功，配置没有积分
    '110007' => 'login successful', //登录成功，当天已经给过积分
    '110008' => 'login successful', //登录成功，添加积分
    '9800000000' => 'Shopify interface request frequency is too high, please request later.', //shopify接口请求频率过高，请稍后请求。
    '9900000000' => 'System request failed. Please operate later',//Call to undefined function
    '9900000001' => 'System request failed. Please operate later',//return empty.
    '9900000002' => 'System request failed. Please operate later', //方法执行失败
    '9900000003' => 'System request failed. Please operate later', //更新记录失败
    '9999999998' => 'System request failed. Please operate later', //必传参数未传
    '9999999999' => 'System request failed. Please operate later', //请求参数异常
    '30002' => 'Order does not exist', //订单不存在
    '30003' => 'The order does not belong to you', //订单不属于当前用户
    '30004' => 'Order request reward does not exist', //订单索评奖励不存在
    '30005' => 'Order request reward does not exist', //订单索评奖励不存在
    '30006' => 'Order review has been rated', //订单索评已经评星级
    '30007' => 'The value of the order request rewards is empty', //订单索评奖励礼品值为空
    '30008' => 'Amazon order pull failed', //亚马逊订单拉取失败
    '30009' => 'Order creation failed', //订单创建失败
    '200000' => 'Wrong password', //口令不对
    '200001' => 'Password has been submitted', //已经提交过口令
    '200002' => 'Repeat activation email', //重复发送激活邮件
    '200003' => 'Page push failed. Failed to request shopify interface', //Shopify 页面推送失败，接口请求失败
    '200004' => 'Page push failed. Shopify Page getList interface request exception', //Shopify 页面推送失败，获取Shopify页面数据接口请求异常
    '200005' => 'Shopify page push failed, page creation failed', //Shopify 页面推送失败，创建页面失败
    '200006' => 'Shopify page push failed, page update failed', //Shopify 页面推送失败，更新页面失败
    '200007' => 'Shopify page push failed, requesting the theme list interface failed', //Shopify 页面推送失败，请求主题列表接口失败
    '200008' => 'Shopify page push failed, requesting the theme list interface exception', //Shopify 页面推送失败，请求主题列表接口异常
    '200009' => 'Shopify page push failed, failed to get theme', //Shopify 页面推送失败，获取主题失败
    '200010' => 'Shopify page push failed, and the request to update the theme template interface failed', //Shopify 页面推送失败，更新主题模板接口请求失败
    '200011' => 'Shopify page push failed, failed to update theme template', //Shopify 页面推送失败，更新主题模板失败
    '200012' => 'Shopify page push failed, request to get online theme resource interface failed', //Shopify 页面推送失败，请求 获取线上主题资源接口 失败
    '200013' => 'Shopify page push failed, failed to obtain online theme resources', //Shopify 页面推送失败，获取线上主题资源失败
    '200014' => 'Shopify page push failed, request to update the online theme resource interface failed', //Shopify 页面推送失败，请求 更新线上主题资源接口 失败
    '200015' => 'Shopify page push failed, failed to update online theme resources', //Shopify 页面推送失败，更新线上主题资源失败
    '200016' => 'Shopify page push failed, request to create page interface failed', //Shopify 页面推送失败，请求 创建页面接口 失败
    '200017' => 'Shopify page push failed, request to update page interface failed', //Shopify 页面推送失败，请求 更新页面接口 失败

    '30010' => 'The current product can only be redeemed once a month, please redeem next month', //当前商品每个月只能兑换一次，请下个月在兑换
    '30011' => 'The current product exchange has ended', //当前商品兑换已经结束
    '30012' => 'Insufficient coupon', //coupon不足

    '50001' => 'Oups! Lien invalide. Vous venez de quitter la commentaire? Vous pouvez partager le lien de révision valide plus tard dans Compte - Garantie du produit.', //用户等72小时以后重新提交,
    '50002' => "La commande a été examinée et des cadeaux ont été envoyés. Appréciez votre partage!", //存在返现,不能评星/索评
    '50003' => "Vous avez déjà évalué ce produit. Très appréciée!", //已经提交过反馈问题，或者已经提交过Rv，不能再次评星
    '50004' => 'The order has been cashed and cannot be extended', //订单已返现，不能延保
    '50005' => 'Order has not been paid, can not comment star',
    '50006' => 'The order has not been paid and cannot be submitted for review',
    '50007' => "La commande n'est pas encore terminée, veuillez d'abord la terminer." //非shipped状态的订单
];

return $validationCustom;
