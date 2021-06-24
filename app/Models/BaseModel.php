<?php

namespace App\Models;

//use App\Database\Eloquent\Builder;
use App\Utils\Arrays\MyArr;
use App\Database\Eloquent\Concerns\HasRelationships;
use Hyperf\Database\Model\Model;
use Hyperf\Utils\Arr;
use App\Models\Statistical\ReportLog;
use App\Constants\Constant;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\ApplicationContext;

//Snowflake 生产唯一分布式id
use Hyperf\Database\Model\Events\Creating;
use Hyperf\Snowflake\Concern\Snowflake;

class BaseModel extends Model {

    use HasRelationships;
    use Snowflake {
        creating as create;
    }

    public function creating(Creating $event){
        $this->create();
    }

    /**
     * 需要被转换成日期的属性。
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'ctime', 'mtime'];

    //数据表名称
    /**
     * 与模型关联的数据表
     *
     * @var string
     */
    //protected $table = 'flights';

    /**
     * The primary key for the model.
     * 主键
     * Eloquent 也会假设每个数据表都有一个叫做 id 的主键字段。你也可以定义一个 $primaryKey 属性来重写这个约定。
     *
     * @var string
     */
    //protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     * 如果你的主键不是一个整数，你需要将模型上受保护的 $keyType 属性设置为 string
     * @var string
     */
    //protected $keyType = 'int';

    /**
     * Indicates if the IDs are auto-incrementing.
     * 主键 此外，Eloquent 假定主键是一个递增的整数值，这意味着在默认情况下主键将自动的被强制转换为 int。 如果你想使用非递增或者非数字的主键，你必须在你的模型 public $incrementing 属性设置为false。
     *
     * @var bool
     */
    //public $incrementing = true;

    /**
     * Indicates if the model should be timestamped.
     * 时间戳
     * 默认情况下，Eloquent 会认为在你的数据库表有 created_at 和 updated_at 字段。如果你不希望让 Eloquent 来自动维护这两个字段，可在模型内将 $timestamps 属性设置为 false
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The storage format of the model's date columns.
     * 时间戳
     * 如果你需要自定义自己的时间戳格式，可在模型内设置 $dateFormat 属性。这个属性决定了日期应如何在数据库中存储，以及当模型被序列化成数组或 JSON 格式
     *
     * @var string
     */
    //protected $dateFormat;
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * The connection name for the model.
     * 数据库连接
     * 默认情况下，所有的 Eloquent 模型会使用应用程序中默认的数据库连接设置。如果你想为模型指定不同的连接，可以使用 $connection 属性：
     *
     * @var string
     */
    //protected $connection;

    /**
     * 不可被批量赋值的属性。
     * $guarded 属性包含的是不想被批量赋值的属性的数组。即所有不在数组里面的属性都是可以被批量赋值的。也就是说，$guarded 从功能上讲更像是一个「黑名单」。而在使用的时候，也要注意只能是 $fillable 或 $guarded 二选一
     * 如果想让所有的属性都可以被批量赋值，就把 $guarded 定义为空数组。
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    //const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    //const UPDATED_AT = 'updated_at';
    const STATUS_AT = Constant::DB_TABLE_STATUS;
    const DELETED_AT = Constant::DB_TABLE_DELETED_AT;
    const CREATED_MARK = Constant::DB_TABLE_CREATED_MARK;
    const UPDATED_MARK = Constant::DB_TABLE_UPDATED_MARK;
    const TABLE_ALIAS = null;

    /**
     * 商城id
     *
     * @var int
     */
    public $storeId = 0;
    public $morphToConnection = [];

    public function setMorphToConnection($morphToConnection = []) {
        $this->morphToConnection = Arr::collapse([$this->morphToConnection, $morphToConnection]);
        return $this;
    }

    public function getMorphToConnection() {
        return $this->morphToConnection;
    }

    /**
     * 设置商城id
     * @param int $storeId 商城id
     * @return $this
     */
    public function setStoreId($storeId) {
        $this->storeId = $storeId;
        return $this;
    }

    /**
     * 获取商城id
     * @return int $storeId 商城id
     */
    public function getStoreId() {
        return $this->storeId;
    }

    public static function setConfig($connection){

        //获取应用容器
        $container = ApplicationContext::getContainer();

        //通过应用容器 获取配置类对象
        $config = $container->get(ConfigInterface::class);

        return true;

    }

    /**
     * 获取模型
     * @param int $storeId 店铺id
     * @param string $make 模型别名
     * @param array $parameters 参数
     * @param string $country 国家
     * @param \Hyperf\Database\Model\Relations\Relation $relation
     * @return type
     */
    public static function createModel($storeId = 1, $make = null, array $parameters = [], $country = '', &$relation = null, $dbConfig = []) {

        if ($make === null && $relation === null) {
            return null;
        }

        if (false !== strpos($storeId, 'default_connection_')) {

//            if ($relation instanceof \Hyperf\Database\Model\Relations\Relation) {
//                return $relation;
//            }

            if ($relation instanceof \Hyperf\Database\Model\Relations\Relation) {

                $connection = $relation->getRelated()->getConnectionName() ?? 'mysql';
                static::setConfig($connection);

                /**
                 * Get the underlying query for the relation. $relation->getQuery()
                 *
                 * @return \Hyperf\Database\Model\Builder
                 */
                //设置数据库连接
                $relation->getRelated()->setConnection($connection);

                //设置关联对象relation 数据库连接
                /**
                 * $relation->getBaseQuery() $relation->getRelated()->getQuery()
                 * Get the base query builder driving the Eloquent builder.
                 *
                 * @return \Illuminate\Database\Query\Builder
                 */
                $relation->getBaseQuery()->connection = $relation->getRelated()->getQuery()->connection;

                //dump('relation',$connection,config('databases.'.$connection));

                return $relation;
            }

            if ($make) {
                $storeId = str_replace('default_connection_', '', $storeId);
                //data_set($parameters, 'attributes.storeId', $storeId, false);
                $model = make($make, $parameters);//model禁止使用单例，否则数据库操作会导致混乱
                $model->setStoreId($storeId);

                $connection = $model->getConnectionName()?? 'mysql';
                static::setConfig($connection);
                $model->setConnection($connection);

                //dump('default_connection',$model,config('databases.'.$connection));

                return $model;
            }

            return null;
        }

        //data_set($parameters, 'attributes.storeId', $storeId, false);

        $database = data_get($dbConfig, 'database', 'ptxcrm');

        $connection = 'db_' . $storeId;
        $config = getConfig();
        if (!($config->has('databases.' . $connection))) {

            $dbConfig = config('databases.default');
            $dbConfig['database'] = $database;
            $dbConfig['cache']['prefix'] = $connection;

            $config->set('databases.' . $connection, $dbConfig);

            static::setConfig($connection);
        }

        if ($relation instanceof \Hyperf\Database\Model\Relations\Relation) {
            /**
             * Get the underlying query for the relation. $relation->getQuery()
             *
             * @return \Hyperf\Database\Model\Builder
             */
            //设置数据库连接
            $relation->getRelated()->setConnection($connection);

            //设置关联对象relation 数据库连接
            /**
             * $relation->getBaseQuery() $relation->getRelated()->getQuery()
             * Get the base query builder driving the Eloquent builder.
             *
             * @return \Hyperf\Database\Query\Builder
             */
            $relation->getBaseQuery()->connection = $relation->getRelated()->getQuery()->connection;

            //dump('relation_store_id',$connection,config('databases.' . $connection));

            return $relation;
        }

        if ($make) {
            //var_dump(__METHOD__,$parameters);
            $model = make($make, $parameters);//model禁止使用单例，否则数据库操作会导致混乱
            $model->setConnection($connection);
            $model->setStoreId($storeId);

            //dump(data_get($model,'storeId'),$make, $parameters,$connection,config('databases.' . $connection));

            return $model;
        }

        return $relation;
    }

    /**
     * 模型的连接名称
     *
     * @var string
     */
    //protected $connection = 'connection-name';

    public function customer() {
        return make('Customer')->hasOne(Customer::class, 'customer_id', 'customer_id');
    }

    /**
     * 模型的默认属性值。
     *
     * @var array
     */
//    protected $attributes = [
//        'delayed' => false,
//    ];

    /**
     * 构建where
     * eg：$where = [
      'u.id' => [1, 2, 3],
      'or' => [
      'u.id' => 4,
      'u.name1' => 5,
      [
      ['u.id', '=', 10],
      ['u.id', '=', 11]
      ],
      [['u.id', 'like', '%55%']],
      [['u.username', 'like', '%55%']],
      ],
      [
      ['u.id', '=', 6],
      ['u.id', '=', 7]
      ],
      'u.username' => '565',
      'u.username' => DB::raw('password'),
      'u.a=kkk',
      ];
      //->onlyTrashed()  withTrashed
      $query = \App\Models\User::from('user as u')->withoutTrashed()->buildWhere($where)
      ->leftJoin('user_roles as b', function ($join) {
      $join->on('b.user_id', '=', 'u.id'); //->where('b.status', '=', 1);
      })
      ;
     * @param \Hyperf\Database\Model\Builder $query
     * @param array $where where条件
     * @param string $boolean 布尔运算符
     * @param boolean $getSql 是否获取sql
     * @return \Hyperf\Database\Model\Builder|string $query
     */
    public function scopeBuildWhere($query, $where = [], $boolean = 'and', $getSql = false) {

        foreach ($where as $column => $value) {

            if (is_string($column)) {
                if ($column === '{customizeWhere}') {//自定义where
                    foreach ($value as $customizeWhereItem) {
                        $method = data_get($customizeWhereItem, 'method', '');
                        $parameters = data_get($customizeWhereItem, 'parameters', []);
                        if (is_array($parameters)) {
                            $query->{$method}(...$parameters);
                        } else {
                            $query->{$method}($parameters);
                        }
                    }
                } else {
                    if (is_array($value)) {
                        $query->where(function ($query) use($column, $value, $boolean) {
                            if (MyArr::isIndexedArray($value) && !is_array(Arr::first($value))) {
                                foreach ($value as $item) {
                                    $query->OrWhere($column, '=', $item);
                                }
                            } elseif (MyArr::isAssocArray($value)) {
                                $boolean = $column;
                                $operator = '=';
                                foreach ($value as $_column => $item) {
                                    $query->where($_column, $operator, $item, $boolean);
                                }
                            } else {
                                $this->scopeBuildWhere($query, $value, $column);
                            }
                        });
                    } else {
                        $query->where($column, '=', $value, $boolean);
                    }
                }

                continue;
            }

            if (is_string($value)) {
                $query->whereRaw($value, [], $boolean);
            } else {
                $query->where($value, null, null, $boolean);
            }
        }

//        if ($getSql) {
//            $query->getConnection()->enableQueryLog();
//            $query->getConnection()->getQueryLog();
//        }

        return $getSql ? ['query' => $query->toSql(), 'bindings' => $query->getBindings(), 'time'] : $query; //->toSql() ->dump() ->dd()
    }

    /**
     * 开启sql调试模式
     * @return null
     */
    public function enableQueryLog() {
        return $this->getConnection()->enableQueryLog();
    }

    /**
     * 获取sql调试数据
     * @return array
     */
    public function getQueryLog() {
        return $this->getConnection()->getQueryLog();
    }

//    /**
//     * Create a new Model query builder for the model.
//     *
//     * @param \Hyperf\Database\Query\Builder $query
//     * @return \App\Database\Eloquent\Builder|static
//     */
//    public function newModelBuilder($query)
//    {
//        return new Builder($query);
//    }

    /**
     * 客户端基本数据
     * @return type
     */
    public function client_data() {
        return $this->hasOne(ReportLog::class, Constant::DB_TABLE_CREATED_MARK, Constant::DB_TABLE_CREATED_MARK);
    }

    /**
     * 属性数据
     * @return \Hyperf\Database\Model\Relations\HasMany
     */
    public function metafields() {
        return \App\Services\MetafieldService::getModel($this->getStoreId())->hasMany(Metafield::class, Constant::OWNER_ID, Constant::DB_TABLE_UNIQUE_ID);
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  array  $attributes
     * @param  bool  $exists
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        // This method just provides a convenient way for us to generate fresh model
        // instances of this current model. It is particularly useful during the
        // hydration of new objects via the Eloquent query builder instances.
        $model = parent::newInstance($attributes, $exists);

        if (method_exists($model,'setStoreId') && method_exists($this,'getStoreId')){
            $model->setStoreId($this->getStoreId());
        }

        return $model;
    }

}
