<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Support\Traits\Queueable;

class OrderShipped extends Mailable {// implements ShouldQueue

    use Queueable;

    /**
     * https://learnku.com/docs/laravel/5.8/mail/3920
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct() {
        //
    }

    /**
     * Build the message.
     * @param  \Illuminate\Mail\Contracts\FactoryInterface|\Illuminate\Mail\Contracts\Mailer  $mailer
     * @return $this
     */
    public function build() {

        $this->from('warranty@victsing.com')//service@xmpow.com  warranty@hommakstore.com  warranty@victsing.com support@victsing.com
                //->text('emails.test')
                ->view('emails.coupon.default')
                ->with([
                    'content' => 'test=====',
                    'start_date' => date('Y-m-d H:i:s'),
                    'end_date' => date('Y-m-d H:i:s'),
                    'type_A' => 'type_A',
                    'link' => 'link',
                    'type_B' => 'type_B',
                    'type_C' => 'type_C',
                    'type_D' => 'type_D',
        ]);

//                        ->attach('/path/to/file', [
//                            'as' => 'name.pdf', //显示名称
//                            'mime' => 'application/pdf', //MIME 类型
//                        ])//要在邮件中加入附件，在 build 方法中使用 attach 方法。attach 方法接受文件的绝对路径作为它的第一个参数：
//                        ->attachData($this->pdf, 'name.pdf', [
//                            'mime' => 'application/pdf',
//                        ])//原始数据附件
        //Mailable 基类的 withSwiftMessage 方法允许你注册一个回调，它将在发送消息之前被调用，原始的 SwiftMailer 消息将作为该回调的参数
//        $this->withSwiftMessage(function ($message) {
//            $message->getHeaders()
//                    ->addTextHeader('Custom-Header', 'HeaderValue');
//        });

        $swiftMailer = $this->getMailService()->getSwiftMailer();
        $transport = $swiftMailer->getTransport()
            ->setHost(config('mail.host'))
            ->setPort(config('mail.port'))
            ->setEncryption(config('mail.encryption'))
            ->setUsername(config('mail.username'))
            ->setPassword(config('mail.password'))
        ;

        $host = 'email-smtp.us-west-2.amazonaws.com';
        $port = 465;
        $encryption = 'ssl';
//        $username = 'AKIAYI4LTW7Z2FAXAAU4';
//        $password = 'BJwgU/oAg+eJEpAonYwIXKDNLVLfn6NuR8GVdSZZpSqu';
//
//        $username = 'AKIAWMYDQ3ESV5U3I7G5';
//        $password = 'BGEHjEYnfgSxNSUsj/VLfahyjFtwSLg3a9rJ2JL0lrHx';

        $username = 'AKIAJAMVGWAVFLXGRHJA';
        $password = 'AgXYkHLD6Yz5hTMyQDRtGvsL91xeXjsmaVq4HIs5DinS';

        if ($host) {//发送邮件服务器host
            $transport->setHost($host);
        }

        if ($port) {//发送邮件服务器port
            $transport->setPort($port);
        }

        if ($encryption) {//发送邮件加密方式
            $transport->setEncryption($encryption);
        }

        if ($username) {//发送邮件服务器账号
            $transport->setUsername($username);
        }

        if ($password) {//发送邮件服务器密码
            $transport->setPassword($password);
        }

        return $this;
    }

}
