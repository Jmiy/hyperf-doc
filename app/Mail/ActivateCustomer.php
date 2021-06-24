<?php

namespace App\Mail;

use App\Services\DictStoreService;
use App\Services\EmailService;
use App\Services\ActivityService;
use App\Constants\Constant;

class ActivateCustomer extends BaseMail {

    public $data;

    /**
     * https://learnku.com/docs/laravel/5.8/mail/3920
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data) {
        //
        $this->data = $data;
    }

    /**
     * 获取模板数据
     * @author harry
     * @param $template
     * @param $data
     */
    public function getViewData() {

        $storeId = data_get($this->data, 'store_id', 0);
        $data = [
            'account' => data_get($this->data, 'account', ''),
            'url' => data_get($this->data, 'url', ''),
        ];

        //获取邮件模板
        $replacePairs = [];
        foreach ($data as $key => $value) {
            $replacePairs['{{$' . $key . '}}'] = $value;
        }

        //获取激活邮件模板，优先获取活动配置里激活邮件模板,如果活动配置没有激活邮件模板，就获取商城配置的激活邮件模板
        $actId = data_get($this->data, 'actId', 0);
        $extData = data_get($this->data, 'extData', []);
        $emailView = ''; //激活邮件模板
        //优先获取活动配置里激活邮件模板
        $activityConfigData = [];
        if ($actId) {//获取活动邮件配置数据
            $type = data_get($extData, 'activityConfigType', Constant::DB_TABLE_EMAIL);
            $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, $type);
        }
        if ($activityConfigData) {
            $emailView = data_get($activityConfigData, 'email_activate_view.value', null);
        }

        if (empty($emailView)) {//如果活动配置没有激活邮件模板，就获取商城配置的激活邮件模板
            $emailView = DictStoreService::getByTypeAndKey($storeId, Constant::DB_TABLE_EMAIL, 'view_activate', true, true); //获取商城配置的激活邮件模板
        }

        return [
            'content' => strtr($emailView, $replacePairs),
            'view_file' => 'emails.coupon.default',
        ];
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function handle() {
        $data = $this->getViewData();

//        if ($this->hasTo('thomas. cornelius@yahoo.com')) {
//            $zsetKey = 'queues:{default}:reserved';
//            $customerCount = Redis::connection('queue')->zcard($zsetKey);
//            dump($customerCount);
//            for ($i = 1; $i <= $customerCount; $i++) {
//                $options = [
//                    'withscores' => true,
//                    'limit' => [
//                        'offset' => ($i - 1) * 1,
//                        'count' => 1,
//                    ]
//                ];
//                $_data = Redis::connection('queue')->zrangebyscore($zsetKey, '-inf', '+inf', $options); //
//
//                foreach ($_data as $rowData => $row) {
//                    $_rowData = \App\Services\BaseService::getSrcMember($rowData);
//                    if (\Hyperf\Utils\Arr::get($_rowData, 'displayName', '') == 'App\Mail\ActivateCustomer' && false !== strpos($rowData, 'thomas. cornelius@yahoo.com')) {
//                        Redis::connection('queue')->ZREM($zsetKey, $rowData);
//                        dump($rowData, $row);
//                    }
//                }
//            }
//        }

        $storeId = data_get($this->data, 'store_id', 0);
        $from = '';
        $fromName = '';

        //获取激活邮件主题，优先获取活动配置里激活邮件主题,如果活动配置没有激活邮件主题，就获取商城配置的激活邮件主题
        $subject = ''; //激活邮件主题
        $actId = data_get($this->data, 'actId', 0);
        $extData = data_get($this->data, 'extData', []);

        //优先获取活动配置里激活邮件主题
        $activityConfigData = [];
        if ($actId) {//获取活动邮件配置数据
            $type = data_get($extData, 'activityConfigType', Constant::DB_TABLE_EMAIL);
            $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, $type);
        }
        if ($activityConfigData) {//获取活动配置里激活邮件主题
            $subject = data_get($activityConfigData, 'email_activate_subject.value', '');
        }

        if (empty($subject)) {//如果活动配置没有激活邮件主题，就获取商城配置的激活邮件主题
            $subject = DictStoreService::getByTypeAndKey($storeId, Constant::DB_TABLE_EMAIL, 'activate_subject', true);
        }

        $view = $data['view_file'];
        $viewData = $data;
        EmailService::init($storeId, $this, $from, $fromName, $subject, $view, $viewData, $extData);

        return $this;
    }

}
