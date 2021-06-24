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

use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Model;

interface CacheableInterface
{
    public function findFromCache($id): ?Model;

    public function findManyFromCache(array $ids): Collection;

    public function deleteCache(): bool;

    public function getCacheTTL(): ?int;
}
