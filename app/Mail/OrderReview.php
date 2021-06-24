<?php

namespace App\Mail;

use App\Services\EmailService;
use App\Services\DictStoreService;
use App\Constants\Constant;

class OrderReview extends BaseMail {

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

        $storeId = $this->data['store_id'];
        $reviewStatus = $this->data['review_status'];

        $data = [//邮件内容里需要显示的数据
            'account' => $this->data['account'],
            'first_name' => $this->data['first_name'],
            'last_name' => $this->data['last_name'],
            Constant::REVIEW_CREDIT => $this->data[Constant::REVIEW_CREDIT]
        ];
        //获取邮件模板
        $replacePairs = [];
        foreach ($data as $key => $value) {
            $replacePairs['{{$' . $key . '}}'] = $value; //在邮件模板使用时，要用{{变量}}的格式
        }
        if ($reviewStatus == 1) {
            $emailView = DictStoreService::getByTypeAndKey($storeId, 'email', 'review_check_yes', true, true);
        }
        if ($reviewStatus == 2) {
            $emailView = DictStoreService::getByTypeAndKey($storeId, 'email', 'review_check_no', true, true);
        }

        return [
            'content' => strtr($emailView, $replacePairs), //把变量合并到模板里面去
            'view_file' => 'emails.review.default', //默认的邮件的模板位置（resources/views/emails/review/default.blade.php）
        ];
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function handle() {
        $data = $this->getViewData();

        $storeId = $this->data['store_id'];
        $reviewStatus = $this->data['review_status'];
        $reviewCredit = $this->data[Constant::REVIEW_CREDIT];
        $from = $this->data['from_email'];
        if ($reviewStatus == 1) {
            $subject = "[Attention] You got Holife " . $reviewCredit . " Points!";
        }
        if ($reviewStatus == 2) {
            $subject = "[Attention] You almost get your Holife " . $reviewCredit . " Points!";
        }

        EmailService::init($storeId, $this, $from, $from, $subject, 'emails.coupon.default', $data);

//        $this->from($from, $from)
//                ->subject($subject)//邮件的标题
//                ->view($data['view_file'])//邮件的模板
//                ->with($data);

        return $this;
    }

}
