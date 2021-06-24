<?php

declare(strict_types=1);
/**
 * Job
 */

namespace App\Jobs;

use Carbon\Carbon;
use App\Exception\Handler\AppExceptionHandler as ExceptionHandler;

class DingDingJob extends Job {

    /**
     * @var
     */
    private $message;

    /**
     * @var
     */
    private $code;

    /**
     * @var
     */
    private $file;

    /**
     * @var
     */
    private $line;

    /**
     * @var
     */
    private $url;

    /**
     * @var
     */
    private $trace;

    /**
     * @var
     */
    private $exception;

    /**
     * @var
     */
    private $simple;

    /**
     * Create a new job instance.
     *
     * @param $url
     * @param $exception
     * @param $message
     * @param $code
     * @param $file
     * @param $line
     * @param $trace
     * @param $simple
     */
    public function __construct($url, $exception, $message, $code, $file, $line, $trace, $simple = false) {
        $this->message = $message;
        $this->code = $code;
        $this->file = $file;
        $this->line = $line;
        $this->url = $url;
        $this->trace = $trace;
        $this->exception = $exception;
        $this->simple = $simple;
    }

    /**
     * Execute the job.
     * ding()->at([],true)->text(implode(PHP_EOL, $message));//@所有人    
     * @return void
     */
    public function handle() {

        $messages = [
            'Time:' . Carbon::now()->toDateTimeString(),
            'Url:' . $this->url,
            'Exception:' . $this->exception,
            'Message:' . $this->message,
        ];

        if ($this->code) {
            $messages = [
                'Time:' . Carbon::now()->toDateTimeString(),
                'Url:' . $this->url,
                'Exception:' . $this->exception,
                'File：' . $this->file,
                'Code：' . $this->code,
                'Message:' . $this->message,
                $this->simple ? '' : ('Exception Trace:' . (is_array($this->trace) ? json_encode($this->trace, JSON_UNESCAPED_UNICODE) : $this->trace)),
            ];
        }

        ding()->text(implode(PHP_EOL, $messages));
    }

}
