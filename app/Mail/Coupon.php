<?php

namespace App\Mail;

use Carbon\Carbon;
use Hyperf\Utils\Arr;
use App\Utils\FunctionHelper;
use App\Services\CouponService;
use App\Services\DictStoreService;
use App\Services\EmailService;
use App\Constants\Constant;

class Coupon extends BaseMail {

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

        $data = [
            'CA57BN' => '',
            'GEPC034AB' => '',
            'GEPC049ABIT' => '',
            'GEPC066BB' => '',
            'GEPC066BR' => '',
            'GEPC173ABUS' => '',
            'GEPC217AB' => '',
            'HMHM235BWEU' => '',
            'HMHM235BWUK' => '',
            'VTBH267AB' => '',
            'VTCA004B' => '',
            'VTGEHM057ABUS' => '',
            'VTHM004YEU' => '',
            'VTHM004YUK' => '',
            'VTHM024ABEU' => '',
            'VTHM057AYEU' => '',
            'VTHM057BBUS' => '',
            'VTHM129BYUS' => '',
            'VTHM196AWEU' => '',
            'VTHM196BWEU' => '',
            'VTPC109AB' => '',
            'VTPC120AD' => '',
            'VTPC132AB' => '',
            'VTPC132ABES' => '',
            'VTPC149ABDE' => '',
            'VTPC149ABES' => '',
            'VTPC149ABIT' => '',
            'VTPC149ABUK' => '',
            'VTPC149ABUS' => '',
            'VTPC174ABUS' => '',
            'VTPC175ABDE' => '',
            'VTPC175ABFR' => '',
            'VTPC206ABFR' => '',
            'VTPC22BABUS' => '',
            'HM235BWEU' => '',
            'type_A' => '',
            'type_B' => '',
            'type_C' => '',
            'type_D' => '',
            'type_E' => '',
            'type_F' => '',
            'name' => '',
            'start_date' => '',
            'end_date' => '',
            'link' => '',
        ];

        $storeId = data_get($this->data, 'store_id', 0);
        $country = strtolower(data_get($this->data, 'country', ''));
        $timeFormatKey = $storeId . '.' . strtoupper($country);
        $timeFormatData = CouponService::$timeFormat;
        $timeFormat = data_get($timeFormatData, $timeFormatKey, data_get($timeFormatData, ($storeId . '.ALL'), data_get($timeFormatData, 'ALL', 'Y-m-d')));

        foreach ($this->data['coupons'] as $type => $coupon) {
            $data['type_' . $type] = data_get($coupon, 'code', '');
            $data[$type] = data_get($coupon, 'code', '');
            $data[$type . '_start_date'] = Carbon::parse(data_get($coupon, 'satrt_time', ''))->rawFormat($timeFormat);
            $data[$type . '_end_date'] = Carbon::parse(data_get($coupon, 'end_time', ''))->rawFormat($timeFormat);
        }

        $data["name"] = implode(' ', Arr::only($this->data, ['first_name', 'last_name']));
        if ($storeId == 5) {
            $data["name"] = data_get($this->data, 'first_name', '');
        }

        $startTime = Carbon::now()->rawFormat($timeFormat);
        $endTime = DictStoreService::getByTypeAndKey($storeId, 'coupon', 'end_time', true); //获取coupon到期时间
        $endTime = $endTime ? $endTime : '+30day';
        $time = strtotime($endTime, Carbon::now()->timestamp); //holif订单延保时间为3年
        $endTime = Carbon::createFromTimestamp($time)->rawFormat($timeFormat);

        $data['start_date'] = $startTime;
        $data['end_date'] = $endTime;

        $link = DictStoreService::getByTypeAndKey($storeId, Constant::DB_TABLE_EMAIL, 'link', true, true, $country);
        $data['link'] = $link ? $link : '';

        //获取邮件模板
        $replacePairs = [];
        foreach ($data as $key => $value) {
            $replacePairs['{{$' . $key . '}}'] = $value;
        }
        $isCommon = DictStoreService::getByTypeAndKey($storeId, 'view_coupon', 'is_common', true); //coupon邮件模板是否通用 1:是 0:否  默认:0
        if ($isCommon) {//如果是通用 就获取通用coupon邮件模板
            $country = '';
        }
        $emailView = DictStoreService::getByTypeAndKey($storeId, Constant::DB_TABLE_EMAIL, 'view_coupon', true, true, $country); //获取coupon邮件模板

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

        $storeId = data_get($this->data, 'store_id', 0);
        FunctionHelper::setTimezone($storeId); //设置时区

        $data = $this->getViewData();
        $from = '';
        $fromName = '';
        $subject = DictStoreService::getByTypeAndKey($storeId, Constant::DB_TABLE_EMAIL, 'coupon_subject', true);
        $view = $data['view_file'];
        $viewData = $data;
        $extData = $this->data;
        EmailService::init($storeId, $this, $from, $fromName, $subject, $view, $viewData, $extData);

        return $this;
    }

}
