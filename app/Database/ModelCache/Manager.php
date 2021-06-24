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

namespace App\Database\ModelCache;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Model;
use Hyperf\DbConnection\Collector\TableCollector;
use Hyperf\ModelCache\Handler\HandlerInterface;

//use Hyperf\ModelCache\Handler\RedisHandler;
use App\Database\ModelCache\Handler\RedisHandler;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Hyperf\ModelCache\Config;

use App\Database\ModelCache\Redis\BatchFuzzyDelete;

class Manager
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var HandlerInterface[]
     */
    protected $handlers = [];

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @var TableCollector
     */
    protected $collector;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->collector = $container->get(TableCollector::class);
    }

    /**
     * 设置 Handler
     * @param null|string $name
     * @return $this
     */
    public function setHandler($name = null)
    {
        if (isset($this->handlers[$name])) {
            return $this;
        }

        $config = $this->container->get(ConfigInterface::class);
        if (!$config->has('databases')) {
            throw new InvalidArgumentException('config databases is not exist!');
        }

        if (empty($name)) {
            return $this;
        }

        $item = $config->get('databases.' . $name);
        $handlerClass = $item['cache']['handler'] ?? RedisHandler::class;
        $config = new Config($item['cache'] ?? [], $name);

        /** @var HandlerInterface $handler */
        $handler = make($handlerClass, ['config' => $config]);

        $this->handlers[$name] = $handler;

        return $this;
    }

    /**
     * 获取 Handler
     * @param null|string $name
     * @return $handler
     */
    public function getHandler($name = null)
    {
        $config = $this->container->get(ConfigInterface::class);
        if (!$config->has('databases')) {
            throw new InvalidArgumentException('config databases is not exist!');
        }

        if ($name === null) {
            foreach ($this->container->get(ConfigInterface::class)->get('databases') as $key => $item) {
                $this->setHandler($key);
            }
            return data_get($this->handlers, $name);
        }

        if (isset($this->handlers[$name])) {
            return $this->handlers[$name];
        }

        $this->setHandler($name);

        return data_get($this->handlers, $name);

    }

    /**
     * Fetch a model from cache.
     * @param mixed $id
     * @param Model $instance
     */
    public function findFromCache($id, Model $instance): ?Model
    {
        /** @var Model $instance */
        //$instance = new $class();

        $name = $instance->getConnectionName();
        $primaryKey = $instance->getKeyName();

        if ($handler = $this->getHandler($name) ?? null) {
            $key = $this->getCacheKey($id, $instance, $handler->getConfig());
            $data = $handler->get($key);

            if ($data) {
                return $instance->newFromBuilder(
                    $this->getAttributes($handler->getConfig(), $instance, $data)
                );
            }

            // Fetch it from database, because it not exist in cache handler.
            if (is_null($data)) {
                $model = $instance->newQuery()->where($primaryKey, '=', $id)->first();
                if ($model) {
                    $ttl = $this->getCacheTTL($instance, $handler);
                    $handler->set($key, $this->formatModel($model), $ttl);
                } else {
                    $ttl = $handler->getConfig()->getEmptyModelTtl();
                    $handler->set($key, [], $ttl);
                }

                return $model;
            }

            // It not exist in cache handler and database.
            return null;
        }

        $this->logger->alert('Cache handler not exist, fetch data from database.');

        return $instance->newQuery()->where($primaryKey, '=', $id)->first();
    }

    /**
     * Fetch many models from cache.
     * @param array $ids
     * @param Model $instance
     */
    public function findManyFromCache(array $ids, Model $instance): Collection
    {
        if (count($ids) === 0) {
            return new Collection([]);
        }

        /** @var Model $instance */
        //$instance = new $class();

        $name = $instance->getConnectionName();
        $primaryKey = $instance->getKeyName();

        if ($handler = $this->getHandler($name) ?? null) {
            $keys = [];
            foreach ($ids as $id) {
                $keys[] = $this->getCacheKey($id, $instance, $handler->getConfig());
            }
            $data = $handler->getMultiple($keys);
            $items = [];
            $fetchIds = [];
            foreach ($data ?? [] as $item) {
                if (isset($item[$primaryKey])) {
                    $items[] = $item;
                    $fetchIds[] = $item[$primaryKey];
                }
            }

            // Get ids that not exist in cache handler.
            $targetIds = array_diff($ids, $fetchIds);
            if ($targetIds) {
                $models = $instance->newQuery()->whereIn($primaryKey, $targetIds)->get();
                $ttl = $this->getCacheTTL($instance, $handler);
                /** @var Model $model */
                foreach ($models as $model) {
                    $id = $model->getKey();
                    $key = $this->getCacheKey($id, $instance, $handler->getConfig());
                    $handler->set($key, $this->formatModel($model), $ttl);
                }

                $items = array_merge($items, $this->formatModels($models));
            }
            $map = [];
            foreach ($items as $item) {
                $map[$item[$primaryKey]] = $this->getAttributes($handler->getConfig(), $instance, $item);
            }

            $result = [];
            foreach ($ids as $id) {
                if (isset($map[$id])) {
                    $result[] = $map[$id];
                }
            }

            return $instance->hydrate($result);
        }

        $this->logger->alert('Cache handler not exist, fetch data from database.');
        return $instance->newQuery()->whereIn($primaryKey, $ids)->get();
    }

    /**
     * Destroy the models for the given IDs from cache.
     * @param mixed $ids
     * @param Model $instance
     */
    public function destroy($ids, Model $instance): bool
    {
        /** @var Model $instance */
        //$instance = new $class();
        $name = $instance->getConnectionName();
        if ($handler = $this->getHandler($name) ?? null) {

            if ([null] == $ids) {
                return $this->batchFuzzyDelete('*', $instance) ? true : false;
            }

            $keys = [];
            foreach ($ids as $id) {
                $keys[] = $this->getCacheKey($id, $instance, $handler->getConfig());
            }

            return $handler->deleteMultiple($keys);
        }

        return false;
    }

    /**
     * Increment a column's value by a given amount.
     * @param mixed $id
     * @param mixed $column
     * @param mixed $amount
     * @param Model $instance
     */
    public function increment($id, $column, $amount, Model $instance): bool
    {
        /** @var Model $instance */
        //$instance = new $class();

        $name = $instance->getConnectionName();
        if ($handler = $this->getHandler($name) ?? null) {

            if ($id === null) {
                $this->batchFuzzyDelete('*', $instance);
                return false;
            }

            $key = $this->getCacheKey($id, $instance, $handler->getConfig());
            if ($handler->has($key)) {
                return $handler->incr($key, $column, $amount);
            }

            return false;
        }

        $this->logger->alert('Cache handler not exist, increment failed.');
        return false;
    }

    /**
     * @return \DateInterval|int
     */
    protected function getCacheTTL(Model $instance, HandlerInterface $handler)
    {
        if ($instance instanceof CacheableInterface) {
            return $instance->getCacheTTL() ?? $handler->getConfig()->getTtl();
        }
        return $handler->getConfig()->getTtl();
    }

    /**
     * @param int|string $id
     */
    protected function getCacheKey($id, Model $model, Config $config): string
    {
        // mc:$prefix:m:$model:$pk:$id
        return sprintf(
            $config->getCacheKey(),
            $config->getPrefix(),
            $model->getTable(),
            $model->getKeyName(),
            $id
        );
    }

    protected function formatModel(Model $model): array
    {
        return $model->getAttributes();
    }

    protected function formatModels($models): array
    {
        $result = [];
        foreach ($models as $model) {
            $result[] = $this->formatModel($model);
        }

        return $result;
    }

    protected function getAttributes(Config $config, Model $model, array $data)
    {
        if (!$config->isUseDefaultValue()) {
            return $data;
        }
        $defaultData = $this->collector->getDefaultValue(
            $model->getConnectionName(),
            $model->getTable()
        );
        return array_replace($defaultData, $data);
    }

    /**
     * 清空表缓存
     * @param string $key
     * @param Model $instance
     * @return false
     */
    public function batchFuzzyDelete($key = '*', $instance = null)
    {
        /** @var Model $instance */

        $name = $instance->getConnectionName();
        if ($handler = $this->getHandler($name) ?? null) {
            $key = $this->getCacheKey($key, $instance, $handler->getConfig());
            return $handler->handle(BatchFuzzyDelete::class, [$key]);
        }

        $this->logger->alert('Cache handler not exist, ' . __FUNCTION__ . ' failed.');
        return false;
    }

    /**
     * 批量清空缓存
     * @param string $name
     * @param string $key
     * @return false
     */
    public function batchDeleteCache($name='', $key = '*')
    {
        if ($handler = $this->getHandler($name) ?? null) {
            return $handler->handle(BatchFuzzyDelete::class, [$key]);
        }

        $this->logger->alert('Cache handler not exist, ' . __FUNCTION__ . ' failed.');
        return false;
    }


}
