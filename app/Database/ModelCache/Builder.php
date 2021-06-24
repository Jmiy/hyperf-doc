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

use App\Database\ModelCache\Handler\RedisHandler;
use Hyperf\Contract\ConfigInterface;
use Hyperf\ModelCache\Builder as ModelCacheBuilder;
use Hyperf\ModelCache\Config;
use Hyperf\Utils\ApplicationContext;

class Builder extends ModelCacheBuilder
{

    /**
     * Run the increment or decrement method on the model.
     *
     * @param string $column
     * @param float|int $amount
     * @param array $extra
     * @param string $method
     * @return int
     */
    protected function incrementOrDecrement($column, $amount = 1, array $extra = [], $method = '')
    {
        $res = parent::{$method}($column, $amount, $extra);
        if ($res > 0) {
            $amount = ($method === 'increment' ? $amount : $amount * -1);
            $model = $this->getModel();
            if (empty($extra)) {
                // Only increment Or decrement a column's value.
                /** @var Manager $manager */
                $id = $model->getKey();
                $ids = [];
                if (null === $id) {

                    $config = ApplicationContext::getContainer()->get(ConfigInterface::class);
                    if (!$config->has('databases')) {
                        throw new InvalidArgumentException('config databases is not exist!');
                    }

                    $name = $model->getConnectionName();
                    $incrementLimit = $config->get('databases.' . $name . '.cache.increment_limit');
                    if ($incrementLimit > 0) {
                        $queryBuilder = clone $this;
                        $primaryKey = $model->getKeyName();
                        $count = $queryBuilder->count();
                        if ($count > 0) {
                            if ($count <= $incrementLimit) {//如果需要更新的数据小于等于 100，就更新缓存数据
                                $ids = $queryBuilder->get([$primaryKey])->pluck($primaryKey)->all();//decrement($column, $amount, $extra);
                            } else {//如果需要更新的数据 大于等于 100，就直接删除缓存数据
                                $ids[] = $id;
                            }
                        }
                    } else {
                        $ids[] = $id;
                    }
                } else {
                    $ids[] = $id;
                }

                if ($ids) {
                    $manager = ApplicationContext::getContainer()->get(Manager::class);
                    foreach ($ids as $id) {
                        $manager->increment($id, $column, $amount, $model);
                    }
                }

            } else {
                // Update other columns, when increment Or decrement a column's value.
                $model->deleteCache();
            }
        }

        return $res;
    }

    /**
     * Increment a column's value by a given amount.
     *
     * @param string $column
     * @param float|int $amount
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        return $this->incrementOrDecrement($column, $amount, $extra,__FUNCTION__);
    }

    /**
     * Decrement a column's value by a given amount.
     *
     * @param string $column
     * @param float|int $amount
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        return $this->incrementOrDecrement($column, $amount, $extra,__FUNCTION__);
    }

    protected function deleteCache(\Closure $closure)
    {
        $queryBuilder = clone $this;
        $primaryKey = $this->model->getKeyName();
        $ids = [];
        $models = $queryBuilder->get([$primaryKey]);
        foreach ($models as $model) {
            $ids[] = $model->{$primaryKey};
        }

        if (empty($ids)) {
            return 0;
        }

        $result = $closure();

        $manger = ApplicationContext::getContainer()->get(Manager::class);

        $manger->destroy($ids, $this->model);

        return $result;
    }
}
