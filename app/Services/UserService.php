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

namespace App\Services;

use Hyperf\Config\Annotation\Value;

use App\Models\Customer;
use Hyperf\Cache\Annotation\Cacheable;
use Hyperf\Cache\Annotation\CachePut;

use App\Constants\Constant;
use Hyperf\Di\Annotation\Inject;
use Psr\SimpleCache\CacheInterface;


class UserService implements UserServiceInterface
{

    /**
     * @Value("cache.default.driver")
     */
    private $enableCache;

    public function __construct()//bool $enableCache
    {
        // 接收值并储存于类属性中
        //$this->enableCache = $enableCache;
        //var_dump(__METHOD__, $this->enableCache);
    }

    public function getInfoById(int $id)
    {
        var_dump(__METHOD__, $this->enableCache);
        return $id;

        // 我们假设存在一个 Info 实体
        //return (new Info())->fill($id);
    }

    /**
     * 当设置 value 后，@Cacheable(prefix="userBook", ttl=6666, value="_#{user.id}") 框架会根据设置的规则，进行缓存 KEY 键命名。如下实例，当 $user->id = 1 时，缓存 KEY 为 c:userBook:_1
     * @Cacheable(prefix="user", ttl=9000, listener="user-update")
     */
    public function user($id)
    {
        $user = Customer::with('info')->select('*')->where(Constant::DB_TABLE_CUSTOMER_PRIMARY,$id)->first();

        if($user){
            return $user->toArray();
        }

        return null;
    }

    /**
     * @Cacheable(prefix="cache", value="_#{id}", listener="user-update")
     */
    public function getCache(int $id)
    {
        return $id . '_' . uniqid();
    }

    /**
     * @CachePut(prefix="user", ttl=3601)
     */
    public function updateUser(int $id)
    {
        $user = Customer::query()->find($id);
        $user->account = 'HyperfDoc';
        $user->save();

        return [
            'user' => $user->toArray(),
            'uuid' => $this->unique(),
        ];
    }

    /**
     * @Inject
     * @var CacheInterface
     */
    private $redis;

    public function get($userId, $id)
    {
        return $this->getArray($userId)[$id] ?? 0;
    }

    /**
     * @Cacheable(prefix="test", group="co")
     */
    public function getArray(int $userId): array
    {
        return $this->redis->hGetAll($userId);
    }
}
