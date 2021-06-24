<?php

declare(strict_types=1);
/**
 * Job
 */

namespace App\Jobs;

use App\Services\ExcelService;
use App\Services\LogService;

class CreateExcelJob extends Job {

    public $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        // 这里最好是普通数据，不要使用携带 IO 的对象，比如 PDO 对象
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() {

        try {
            $parameters = $this->data;
            ExcelService::handleExcel(...$parameters);
        } catch (\Exception $exc) {
            LogService::addSystemLog('error', 'CreateExcel_job', 'handle', '生成excel出错', ['data' => $this->data, 'exc' => $exc->getTraceAsString()]); //添加系统日志
        }
    }

}
