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

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;

use Hyperf\Scout\Event\ModelsImported;
use Hyperf\Utils\ApplicationContext;
use Psr\EventDispatcher\ListenerProviderInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use App\Services\BaseService;

/**
 * @Command
 */
class ImportCommand extends HyperfCommand
{
    /**
     * The name and signature of the console command.
     * 执行：php bin/hyperf.php scout:import:store "Rank"
     * @var string
     */
    protected $name = 'scout:import:store';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import the given model into the search index';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        define('SCOUT_COMMAND', true);
        $class = $this->input->getArgument('model');
        $storeId = $this->input->getArgument('store_id');
        $chunk = (int)$this->input->getOption('chunk');
        $column = (string)$this->input->getOption('column');
        $model = BaseService::getModel($storeId, '', [], $class);//new $class();

        $dbConfig = BaseService::getDbConfig($storeId, '', [], $class);
        $class = data_get($dbConfig, 'full_db_table', $model->searchableAs());

        $provider = ApplicationContext::getContainer()->get(ListenerProviderInterface::class);
        $provider->on(ModelsImported::class, function ($event) use ($class) {
            /** @var ModelsImported $event */
            $key = $event->models->last()->getScoutKey();
            $this->line('<comment>Imported [' . $class . '] models up to ID:</comment> ' . $key);
        });
        $model->makeAllSearchable($chunk ?: null, $column ?: null, $model);

        $this->info('All [' . $class . '] records have been imported.');
    }

    protected function getOptions()
    {
        return [
            ['column', 'c', InputOption::VALUE_OPTIONAL, 'Column used in chunking. (Default use primary key)'],
            ['chunk', '', InputOption::VALUE_OPTIONAL, 'The number of records to import at a time (Defaults to configuration value: `scout.chunk.searchable`)'],
        ];
    }

    protected function getArguments()
    {
        return [
            ['model', InputArgument::REQUIRED, 'fully qualified class name of the model'],
            ['store_id', InputArgument::REQUIRED, 'fully qualified store id of the model'],
        ];
    }
}
