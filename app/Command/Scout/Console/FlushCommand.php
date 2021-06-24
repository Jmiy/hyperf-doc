<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Command\Scout\Console;

use App\Services\BaseService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 */
class FlushCommand extends HyperfCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'scout:flush:store';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Flush all of the model's records from the index";

    /**
     * Execute the console command.
     */
    public function handle()
    {
        define('SCOUT_COMMAND', true);
        $class = $this->input->getArgument('model');
        $storeId = $this->input->getArgument('store_id');
        $model = BaseService::createModel($storeId, $class);//new $class();
        $model->removeAllFromSearch();

        $dbConfig = BaseService::getDbConfig($storeId, '', [], $class);
        $class = data_get($dbConfig, 'full_db_table', $model->searchableAs());

        $this->info('All [' . $class . '] records have been flushed.');
    }

    protected function getArguments()
    {
        return [
            ['model', InputArgument::REQUIRED, 'fully qualified class name of the model'],
            ['store_id', InputArgument::REQUIRED, 'fully qualified store id of the model'],
        ];
    }
}
