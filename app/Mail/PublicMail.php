<?php

namespace App\Mail;

use Hyperf\Utils\Arr;
use App\Services\EmailService;

class PublicMail extends BaseMail {

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
     * Build the message.
     *
     * @return $this
     */
    public function handle() {

        $data = $this->data; //$service::{$method}(...$parameters);
        $storeId = Arr::get($data, 'storeId', 0);
        $from = '';
        $fromName = '';
        $subject = Arr::get($data, 'subject', '');
        $view = Arr::get($data, 'emailView', 'emails.coupon.default');
        $viewData = $data;
        $extData = $data;
        EmailService::init($storeId, $this, $from, $fromName, $subject, $view, $viewData, $extData);

        return $this;
    }

}
