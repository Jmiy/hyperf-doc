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

use Hyperf\Database\Model\Builder;
use Hyperf\Database\Query\Builder as QueryBuilder;
use App\Database\ModelCache\Builder as ModelCacheBuilder;

use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Model;
use Hyperf\Utils\ApplicationContext;

trait Cacheable
{
    /**
     * @var bool
     */
    protected $useCacheBuilder = false;

    /**
     * Boot the trait.
     */
    public static function bootCacheable()
    {
        (new static())->registerCacheableMacros();
    }

    /**
     * Register the searchable macros.
     */
    public function registerCacheableMacros()
    {
        $self = $this;
        Collection::macro('increment', function ($column, $amount = 1, array $extra = []) use ($self) {
            foreach ($this as $model){
                $model->increment($column, $amount, $extra);
            }
        });
        Collection::macro('decrement', function ($column, $amount = 1, array $extra = []) use ($self) {
            foreach ($this as $model){
                $model->decrement($column, $amount, $extra);
            }
        });
    }

    /**
     * Fetch a model from cache.
     * @param mixed $id
     * @return null|\Hyperf\ModelCache\Cacheable
     */
    public function findFromCache($id): ?Model
    {
        $manager = ApplicationContext::getContainer()->get(Manager::class);

        return $manager->findFromCache($id, $this);
    }

    /**
     * Fetch models from cache.
     */
    public function findManyFromCache(array $ids): Collection
    {
        $manager = ApplicationContext::getContainer()->get(Manager::class);

        $ids = array_unique($ids);
        return $manager->findManyFromCache($ids, $this);
    }

    /**
     * Delete model from cache.
     */
    public function deleteCache($ids=null): bool
    {
        $manager = ApplicationContext::getContainer()->get(Manager::class);

        return $manager->destroy($ids ?? [$this->getKey()], $this);
    }

    /**
     * Get the expire time for cache.
     */
    public function getCacheTTL(): ?int
    {
        return null;
    }

    /**
     * Increment a column's value by a given amount.
     * @param string $column
     * @param float|int $amount
     * @return int
     */
//    public function increment($column, $amount = 1, array $extra = [])
//    {
//        $res = parent::increment($column, $amount, $extra);
//        if ($res > 0) {
//            if (empty($extra)) {
//                // Only increment a column's value.
//                /** @var Manager $manager */
//                $manager = ApplicationContext::getContainer()->get(Manager::class);
//
//                $manager->increment($this->getKey(), $column, $amount, $this);
//            } else {
//                // Update other columns, when increment a column's value.
//                $this->deleteCache();
//            }
//        }
//        return $res;
//    }
//
//    /**
//     * Decrement a column's value by a given amount.
//     * @param string $column
//     * @param float|int $amount
//     * @return int
//     */
//    public function decrement($column, $amount = 1, array $extra = [])
//    {
//        $res = parent::decrement($column, $amount, $extra);
//        if ($res > 0) {
//            if (empty($extra)) {
//                // Only decrement a column's value.
//                /** @var Manager $manager */
//                $manager = ApplicationContext::getContainer()->get(Manager::class);
//                $manager->increment($this->getKey(), $column, -$amount, $this);
//            } else {
//                // Update other columns, when decrement a column's value.
//                $this->deleteCache();
//            }
//        }
//        return $res;
//    }

    /**
     * Create a new Model query builder for the model.
     * @param QueryBuilder $query
     */
    public function newModelBuilder($query): Builder
    {

        return new ModelCacheBuilder($query);

//        if ($this->useCacheBuilder) {
//            return new ModelCacheBuilder($query);
//        }
//
//        return parent::newModelBuilder($query);
    }

    public function newQuery(bool $cache = false): Builder
    {
        $this->useCacheBuilder = $cache;
        return parent::newQuery();
    }

    /**
     * @param bool $cache Whether to delete the model cache when batch update
     */
    /**
     * Begin querying the model.
     * @param bool $cache Whether to delete the model cache when batch update
     * @return \Hyperf\Database\Model\Builder
     */
    public static function query(bool $cache = false): Builder
    {
        return (new static())->newQuery($cache);
    }

    /**
     * 清空表缓存
     * @param string $key
     * @return false
     */
    public function batchFuzzyDelete($key = '*')
    {
        $manager = ApplicationContext::getContainer()->get(Manager::class);

        return $manager->batchFuzzyDelete($key, $this);
    }

    /**
     * 批量清空缓存
     * @param string $key
     * @return false
     */
    public function batchDeleteCache($key = '*')
    {
        $manager = ApplicationContext::getContainer()->get(Manager::class);

        return $manager->batchDeleteCache($key, $this);
    }
}
