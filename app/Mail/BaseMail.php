<?php

namespace App\Mail;

use Illuminate\Mail\Support\Traits\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Contracts\Queue\ShouldQueue;
use App\Services\LogService;
use Exception;

class BaseMail extends Mailable implements ShouldQueue {

    use Queueable;

    /**
     * link：https://learnku.com/docs/laravel/5.8/mail/3920
     */

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build() {

        try {
            $this->handle();
        } catch (Exception $exc) {
            LogService::addSystemLog('error', 'build_email', get_called_class(), '构建邮件失败', json_encode($this->data, JSON_UNESCAPED_UNICODE) . '==>' . $exc->getTraceAsString()); //添加系统日志
        }

        return $this;
    }

    /**
     * 任务失败的处理过程
     *
     * @param  Exception  $exception
     * @return void
     */
    public function failed(\Exception $exception) {
        // 给用户发送任务失败的通知，等等……
        //dump(__METHOD__);
    }

}
