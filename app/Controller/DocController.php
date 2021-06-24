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
namespace App\Controller;

use App\Constants\Constant;
use App\Models\Customer;
use App\Services\BaseService;
use App\Services\CreditService;
use App\Services\ExpService;
use App\Services\CustomerService;
use App\Services\ActivityService;
use App\Services\Monitor\MonitorServiceManager;
use App\Utils\FunctionHelper;
use GuzzleHttp\RequestOptions;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Etcd\KVInterface;
use Hyperf\Guzzle\RetryMiddleware;
use Hyperf\HttpMessage\Upload\UploadedFile;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Config\Annotation\Value;

use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Context;

use App\Services\UserServiceInterface;

use Hyperf\Utils\Str;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util;
use Psr\Container\ContainerInterface;
use Hyperf\Utils\ApplicationContext;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;

//通过注解方式 注册中间件
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\Auth\FooMiddleware;
use App\Middleware\BarMiddleware;

//消息队列
use App\Utils\Services\QueueService;

//缓存
use App\Utils\Support\Facades\Cache;
use App\Utils\Support\Facades\Redis;

//Snowflake 是由 Twitter 提出的一个分布式全局唯一 ID 生成算法，算法生成 ID 的结果是一个 64bit 大小的长整
use Hyperf\Snowflake\IdGeneratorInterface;

use Hyperf\View\RenderInterface;//视图渲染者

use Illuminate\Mail\Contracts\FactoryInterface as FactoryContract;;//邮件工厂

use App\Utils\Response;

use Hyperf\Retry\Annotation\Retry;

use Hyperf\RateLimit\Annotation\RateLimit;//服务限流
use Hyperf\Di\Aop\ProceedingJoinPoint;

use Hyperf\Guzzle\HandlerStackFactory;
use GuzzleHttp\Client;



/**
 * @AutoController()
 * @Middlewares({
 *     @Middleware(FooMiddleware::class),
 *     @Middleware(BarMiddleware::class)
 * })
 */
class DocController extends AbstractController
{

    // Hyperf 会自动为此方法生成一个 /doc/index 的路由，允许通过 GET 或 POST 方式请求
    public function index()
    {
        // 从请求中获得 user 参数
        $user = $this->request->input('user', 'Hyperf');
        $method = $this->request->getMethod();

        $route = $this->request->getAttribute(Dispatched::class);

        //获取应用容器
        $container = ApplicationContext::getContainer();
        //通过应用容器 获取配置类对象
        $config = $container->get(ConfigInterface::class);

        return [
            'method' => $method,
            'message' => "Hello {$user}.",
            'route' => $route,
            'validator' => data_get($route,'validator'),
            'config.a' => $config->get('a'),
        ];
    }

    /**
     * @Inject
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @Value("server.servers.0.name")
     */
    private $configValue;

    // Hyperf 会自动为此方法生成一个 /doc/conf 的路由，允许通过 GET 或 POST 方式请求
    /**
     * config:https://hyperf.wiki/2.0/#/zh-cn/config?id=%e4%bd%bf%e7%94%a8-hyperf-config-%e7%bb%84%e4%bb%b6
     * @return array
     */
    public function conf()
    {
        // 从请求中获得 user 参数
        $user = $this->request->input('user', 'Hyperf');
        $method = $this->request->getMethod();

        var_dump($this->configValue);

        /**************修改配置*****************/
        //获取应用容器
        $container = ApplicationContext::getContainer();

        //通过应用容器 获取配置类对象
        $config = $container->get(ConfigInterface::class);

        //修改配置
        var_dump($config->set('a',888999));
        var_dump($config->get('a'));
        /**************修改配置*****************/

        return [
            'method' => $method,
            'message' => "Hello {$user}.".'===config==server.mode==>'.$this->config->get('server.mode',null).'===config==server.servers.0.name==>'.config('server.servers.0.name', null)//.'===config==server.servers.0.name==>'.$this->configValue,
        ];
    }

    /**
     * 可以通过注解 @Inject(lazy=true) 注入懒加载代理。通过注解实现懒加载不用创建配置文件
     * 当 @Inject(required=false) UserService 不存在于 DI 容器内或不可创建时，则注入 null
     * @Inject(lazy=true)
     * @var UserServiceInterface
     */
    private $userService;

    // Hyperf 会自动为此方法生成一个 /doc/di 的路由，允许通过 GET 或 POST 方式请求
    /**
     * 依赖注入：https://hyperf.wiki/2.0/#/zh-cn/di
     * @return array
     */
    public function di()
    {
        // 从请求中获得 user 参数
        $user = $this->request->input('user', 'Hyperf');
        $method = $this->request->getMethod();

//        make('UserServiceInterface1')->getInfoById(8999);
//        make('UserServiceInterface1')->getInfoById(666666);
//        make('UserServiceInterface1')->getInfoById(66);
//        make('UserServiceInterface1')->getInfoById(6);

        return [
            'method' => $method,
            'message' => "Hello {$user}.",
            //'Inject' => $this->userService->user(1),
            //'make' => make('UserServiceInterface1')->getInfoById(6),
            'user' => $this->userService->user(1),
        ];
    }

    // Hyperf 会自动为此方法生成一个 /doc/context 的路由，允许通过 GET 或 POST 方式请求
    /**
     * 协程上下文 相关doc
     * @return array
     */
    public function context()
    {
        // 从请求中获得 user 参数
        $user = $this->request->input('user', 'Hyperf');
        $method = $this->request->getMethod();

        Context::set('a.b','test');
        Context::set('a.c','testc');

        return [
            'method' => $method,
            'message' => "Hello {$user}.".'===context==get==>'.Context::get('server.mode',null),
            'ContextData' => Context::get('a',null),
        ];
    }

    /**
     * 获取容器
     * @return array
     */
    public function container(ContainerInterface $container)
    {
        // 从请求中获得 user 参数
        $user = $this->request->input('user', 'Hyperf');
        $method = $this->request->getMethod();

        //获取容器
        $_container = \Hyperf\Utils\ApplicationContext::getContainer();
        //var_dump($_container);

        return [
            'method' => $method,
            'message' => "Hello {$user}.",
            'container' => $container,
        ];
    }

    /**
     * @Inject
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function event()
    {
        $requestData = $this->request->all();

        // 完成账号注册的逻辑
        // 这里 dispatch(object $event) 会逐个运行监听该事件的监听器
        $this->eventDispatcher->dispatch(make(\App\Event\UserRegistered::class, ['user' => $requestData]));//new \App\Event\UserRegistered(['data' => $requestData])

        // 从请求中获得 user 参数
        $user = data_get($requestData, 'user', 'Hyperf');
        $method = $this->request->getMethod();

        return [
            'method' => $method,
            'message' => "Hello {$user}.",
        ];
    }

    /**
     * request
     * @return array
     */
    public function request()
    {
        $requestData = $this->request->all();

        $route = $this->request->getAttribute(Dispatched::class);

        // 存在则返回，不存在则返回默认值 null
        //$id = $this->request->route('id');
        // 存在则返回，不存在则返回默认值 0 Retrieve the data from route parameters. $route->params
        $id = $this->request->route('id', 0);

        //获取swoole ServerRequest
        $serverRequest = \Hyperf\Utils\Context::get(ServerRequestInterface::class);


        //获取请求路径
        //path() 方法返回请求的路径信息。也就是说，如果传入的请求的目标地址是 http://domain.com/foo/bar?baz=1，那么 path() 将会返回 foo/bar：
        $uri = $this->request->path();

        //is(...$patterns) 方法可以验证传入的请求路径和指定规则是否匹配。使用这个方法的时，你也可以传递一个 * 字符作为通配符：
        if ($this->request->is('user/*')) {
            // ...
        }

        //获取请求的 URL
        //你可以使用 url() 或 fullUrl() 方法去获取传入请求的完整 URL。url() 方法返回不带有 Query 参数 的 URL，而 fullUrl() 方法的返回值包含 Query 参数 ：
        // 没有查询参数
        $url = $this->request->url();
        // 带上查询参数
        $url = $this->request->fullUrl();

        //获取请求方法
        //getMethod() 方法将返回 HTTP 的请求方法。你也可以使用 isMethod(string $method) 方法去验证 HTTP 的请求方法与指定规则是否匹配：
        $method = $this->request->getMethod();
        if ($this->request->isMethod('post')) {
            // ...
        }

        //获取所有输入
        //您可以使用 all() 方法以 数组 形式获取到所有输入数据:
        $all = $this->request->all();

        //获取指定输入值
        //通过 input(string $key, $default = null) 和 inputs(array $keys, $default = null): array 获取 一个 或 多个 任意形式的输入值：
        // 存在则返回，不存在则返回 null
        $name = $this->request->input('name');
        // 存在则返回，不存在则返回默认值 Hyperf
        $name = $this->request->input('name', 'Hyperf');

        //如果传输表单数据中包含「数组」形式的数据，那么可以使用「点」语法来获取数组：
        $name = $this->request->input('products.0.name');
        $names = $this->request->input('products.*.name');

        //从查询字符串获取输入
        //使用 input, inputs 方法可以从整个请求中获取输入数据（包括 Query 参数），而 query(?string $key = null, $default = null) 方法可以只从查询字符串中获取输入数据：
        // 存在则返回，不存在则返回 null
        $name = $this->request->query('name');
        // 存在则返回，不存在则返回默认值 Hyperf
        $name = $this->request->query('name', 'Hyperf');
        // 不传递参数则以关联数组的形式返回所有 Query 参数
        $name = $this->request->query();

        //获取 JSON 输入信息
        //如果请求的 Body 数据格式是 JSON，则只要 请求对象(Request) 的 Content-Type Header 值 正确设置为 application/json，就可以通过 input(string $key, $default = null) 方法访问 JSON 数据，你甚至可以使用 「点」语法来读取 JSON 数组：
        // 存在则返回，不存在则返回 null
        $name = $this->request->input('user.name');
        // 存在则返回，不存在则返回默认值 Hyperf
        $name = $this->request->input('user.name', 'Hyperf');
        // 以数组形式返回所有 Json 数据
        $name = $this->request->all();

        //确定是否存在输入值
        //要判断请求是否存在某个值，可以使用 has($keys) 方法。如果请求中存在该值则返回 true，不存在则返回 false，$keys 可以传递一个字符串，或传递一个数组包含多个字符串，只有全部存在才会返回 true：
        // 仅判断单个值
        if ($this->request->has('name')) {
            // ...
        }
        // 同时判断多个值
        if ($this->request->has(['name', 'email'])) {
            // ...
        }

        /****************Cookies**************/
        //从请求中获取 Cookies
        //使用 getCookieParams() 方法从请求中获取所有的 Cookies，结果会返回一个关联数组。
        $cookies = $this->request->getCookieParams();

        //如果希望获取某一个 Cookie 值，可通过 cookie(string $key, $default = null) 方法来获取对应的值：
        // 存在则返回，不存在则返回 null
        $name = $this->request->cookie('name');
        // 存在则返回，不存在则返回默认值 Hyperf
        $name = $this->request->cookie('name', 'Hyperf');

        /**
         * 文件:https://hyperf.wiki/2.0/#/zh-cn/request?id=%e6%96%87%e4%bb%b6
         */
        //获取上传文件
        //你可以使用 file(string $key, $default): ?Hyperf\HttpMessage\Upload\UploadedFile 方法从请求中获取上传的文件对象。如果上传的文件存在则该方法返回一个 Hyperf\HttpMessage\Upload\UploadedFile 类的实例，该类继承了 PHP 的 SplFileInfo 类的同时也提供了各种与文件交互的方法：
        // 存在则返回一个 Hyperf\HttpMessage\Upload\UploadedFile 对象，不存在则返回 null
        $file = $this->request->file('photo');

        //检查文件是否存在
        //您可以使用 hasFile(string $key): bool 方法确认请求中是否存在文件：
        if ($this->request->hasFile('photo')) {
            // ...
        }

        //验证成功上传
        //除了检查上传的文件是否存在外，您也可以通过 isValid(): bool 方法验证上传的文件是否有效：
        if ($this->request->file('photo')->isValid()) {
            // ...
        }

        //文件路径 & 扩展名
        //UploadedFile 类还包含访问文件的完整路径及其扩展名方法。getExtension() 方法会根据文件内容判断文件的扩展名。该扩展名可能会和客户端提供的扩展名不同：
        // 该路径为上传文件的临时路径
        $path = $this->request->file('photo')->getPath();
        // 由于 Swoole 上传文件的 tmp_name 并没有保持文件原名，所以这个方法已重写为获取原文件名的后缀名
        $extension = $this->request->file('photo')->getExtension();

        //存储上传文件
        //上传的文件在未手动储存之前，都是存在一个临时位置上的，如果您没有对该文件进行储存处理，则在请求结束后会从临时位置上移除，所以我们可能需要对文件进行持久化储存处理，通过 moveTo(string $targetPath): void 将临时文件移动到 $targetPath 位置持久化储存，代码示例如下：
        $file = $this->request->file('photo');
        $file->moveTo('/foo/bar.jpg');
        // 通过 isMoved(): bool 方法判断方法是否已移动
        if ($file->isMoved()) {
            // ...
        }

        return [
            'method' => $this->request->getMethod(),
            'message' => $id,
            'route' => $route,
            'validator' => data_get($route,'validator'),
            'request_uri' => $this->request->getRequestUri(),
            'request_headers' => $this->request->getHeaders(),
            'serverRequest'=>$serverRequest->getQueryParams(),
            'requestData' => $this->request->all(),
            'requestContents' => $this->request->getBody()->getContents(),
        ];
    }

    /**
     * https://hyperf.wiki/2.0/#/zh-cn/response
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function response(){
        $data = [
            'key' => 'value'
        ];

        //返回 Json 格式
        $this->response->json($data);

        //返回 Xml 格式
        $this->response->xml($data);

        //返回 Raw 格式
        $this->response->raw('Hello Hyperf.');

        //https://hyperf.wiki/2.0/#/zh-cn/response?id=%e9%87%8d%e5%ae%9a%e5%90%91
        //重定向
        //Hyperf\HttpServer\Contract\ResponseInterface 提供了 redirect(string $toUrl, int $status = 302, string $schema = 'http') 返回一个已设置重定向状态的 Psr7ResponseInterface 对象。
        // redirect() 方法返回的是一个 Psr\Http\Message\ResponseInterface 对象，需再 return 回去
        //return $this->response->redirect('/anotherUrl');

        /**
         * Cookie 设置
         */
        $cookie = new Cookie('key', 'value');
        $this->response->withCookie($cookie)->withContent('Hello Hyperf.');

        /**
         * Gzip 压缩
         * 分块传输编码 Chunk
         * 文件下载
         * Hyperf\HttpServer\Contract\ResponseInterface 提供了 download(string $file, string $name = '') 返回一个已设置下载文件状态的 Psr7ResponseInterface 对象。
         * 如果请求中带有 if-match 或 if-none-match 的请求头，Hyperf 也会根据协议标准与 ETag 进行比较，如果一致则会返回一个 304 状态码的响应。
         * download 方法：
         */
        $this->response->download(BASE_PATH . '/public/file.csv', 'filename.csv');

        return $this->response->json($data);
    }

    /**
     * 路由相关
     * @return array
     */
    public function route()
    {
        $requestData = $this->request->all();

        $route = $this->request->getAttribute(Dispatched::class);

        // 存在则返回，不存在则返回默认值 null
        //$id = $this->request->route('id');
        // 存在则返回，不存在则返回默认值 0 Retrieve the data from route parameters. $route->params
        $id = $this->request->route('id', 0);

        return [
            'method' => $this->request->getMethod(),
            'route_params' => $this->request->route('id', 0),
            'route' => $route,
            'validator' => data_get($route,'handler.options.validator'),
            'request_uri' => $this->request->getRequestUri(),
            'request_headers' => $this->request->getHeaders(),
        ];
    }

    public function exception(){
//        throw new \App\Exception\FooException('Foo Exception...', 800);
//        throw new \Exception('Foo Exception...', 900);

        $a = [];
        var_dump($a[1]);

        return [
            'method' => $this->request->getMethod(),
        ];
    }

    public function cache()
    {

        $container = \Hyperf\Utils\ApplicationContext::getContainer();
//        $cache = $container->get(\Psr\SimpleCache\CacheInterface::class);
//
//        //$cache->set('test',false,6000);
//        return [
//            'data' => $cache->get('test'),
//        ];

//        $userId = 1;
//        $key = 'user:' . $userId;
//        $data = Cache::remember($key, 10, function () use ($userId) {
//            var_dump(898989);
//            return Customer::select('*')->where(Constant::DB_TABLE_CUSTOMER_PRIMARY, $userId)->first()->toArray();
//
//        });
//
//        return [
//            'data' => $data,
//        ];

//        $userId = 1;
//        $key = 'lock:' . $userId;
//        $data=Cache::tags(['{tags}'])->lock($key)->get(function () {
//            // 获取无限期锁并自动释放...
//            var_dump(1);
//        });

        //$data=Cache::tags(['{tags}'])->put('tags-test',88,6000);

        //$data=Cache::tags(['{tags}'])->get('tags-test');
//        return [
//            'data' => Cache::tags(['{tags}'])->flush(),
//            'data' => Cache::tags(['{tags}'])->get('tags-test'),
//        ];
//
//        $lock = Cache::lock('foo', 10);
//        $get = $lock->get();
//
//        if ($get) {
//            // 获取锁定10秒...
//
//            $lock->release();
//        }
//
//        return [
//            'data' => $get,
//        ];

//        $factory = $container->get(RedisFactory::class);
//        $get=$factory->get('cache')->set('set-test',88,6000);
//
//        return [
//            'data' => $get,
//        ];

        $lock = Cache::tags(['{lock_tags}'])->lock('foo', 1000);
        $get = $lock->get();
//        if ($get) {
//            // 获取锁定10秒...
//            $lock->release();
//        }
//
//        $get=Cache::tags(['{lock_tags}'])->lock('foo',6000)->get(function () {
//            // 获取无限期锁并自动释放...
//            var_dump('lock');
//            return 555;
//        });

        return [
            'data' => $get,
        ];

//        $userId = 1;
//        $user = Customer::select('*')->where(Constant::DB_TABLE_CUSTOMER_PRIMARY,1)->first()->toArray();
//
//        \App\Utils\Support\Facades\Cache::hMSet('user:'.$userId,$user);
//
//        return [
//            'data' => \App\Utils\Support\Facades\Cache::hGetAll('user:'.$userId),
//        ];
    }

    public function redis()
    {

//        $userId = 1;
//        $user = ActivityService::getModel(1)->select('*')->where(Constant::DB_TABLE_PRIMARY, 1)->first()->toArray();
//
//        $key = 'Activity:' . $userId;
//        Redis::hMSet($key, $user);

//        $action = Constant::ACTION_INVITE;
//        $type = Constant::SIGNUP_KEY;
//        $confKey = 'invite_credit';
//        $expType = Constant::SIGNUP_KEY;
//        $expConfKey = 'invite_exp';
//        $storeId = 1;
//        $inviterId = 905234;
//        $actId = 29;
//
//        $actCreditLogParameters = CreditService::getHandleLogParameters($storeId, $actId, null, $inviterId, Constant::REGISTERED, $action, 'credit');
//        $type = data_get($actCreditLogParameters, Constant::DB_TABLE_TYPE);
//        $confKey = data_get($actCreditLogParameters, Constant::DB_TABLE_VALUE);//邀请功能积分
//
//        $actExpLogParameters = ExpService::getHandleLogParameters($storeId, $actId, $expConfKey, $inviterId, Constant::SIGNUP_KEY, $action, 'exp');
//        $expType = data_get($actExpLogParameters, Constant::DB_TABLE_TYPE);;
//        $expConfKey = data_get($actExpLogParameters, Constant::DB_TABLE_VALUE);//邀请功能经验
//
//        if (null !== $type) {//如果 $actId 对应的活动没有限制邀请积分，就根据常规配置限制邀请积分if (null !== $type) {//如果 $actId 对应的活动没有限制邀请积分，就根据常规配置限制邀请积分
//            $creditLogParameters = CreditService::getHandleLogParameters($storeId, 0, $confKey, $inviterId, Constant::SIGNUP_KEY, $action, 'credit');
//            $type = data_get($creditLogParameters, Constant::DB_TABLE_TYPE);
//            $confKey = data_get($creditLogParameters, Constant::DB_TABLE_VALUE);//邀请功能积分
//        }
//
//        if (null !== $expType) {//如果 $actId 对应的活动没有限制邀请经验，就根据常规配置限制邀请经验
//            $expLogParameters = ExpService::getHandleLogParameters($storeId, 0, $expConfKey, $inviterId, Constant::SIGNUP_KEY, $action, 'exp');
//            $expType = data_get($expLogParameters, Constant::DB_TABLE_TYPE);;
//            $expConfKey = data_get($expLogParameters, Constant::DB_TABLE_VALUE);//邀请功能经验
//        }
//
//        return [$type,$confKey,$expType,$expConfKey];

        return [
            //'data' => Redis::hGetAll($key),
            'data' => \App\Utils\Support\Facades\Lua::doc('{mc:db_1:m:ranks}:id:*','db_model_cache_pool'),
        ];
    }

//    /**
//     * @Inject
//     * @var QueueService
//     */
//    protected $service;

    /**
     * 传统模式投递消息
     */
    public function queue(){

        /**
         * 任务执行流转流程主要包括以下几个队列:
         * 队列名	备注
         * waiting	等待消费的队列
         * reserved	正在消费的队列
         * delayed	延迟消费的队列
         * failed	消费失败的队列
         * timeout	消费超时的队列 (虽然超时，但可能执行成功)
         */

//        $this->service->push([
//            'group@hyperf.io',
//            'https://doc.hyperf.io',
//            'https://www.hyperf.io',
//        ]);

        $delay = 0;
        $queue = 'default';
        QueueService::push([
            'default',
        ], $queue, $delay);

        $delay = 0;
        $queue = 'log';
        QueueService::push([
            'group@hyperf.io',
            'https://doc.hyperf.io',
            'https://www.hyperf.io',
            $queue
        ], $queue, $delay);

        return 'success';
    }

    /**
     * 加密解密
     * @return array
     */
    public function encrypt()
    {

//        $requsst = app('request');
////        var_dump(data_get(make('Activity',['attributes'=>['a'=>'a']]),'a'));
////        var_dump(data_get(make('Activity',['attributes'=>['a'=>'b']]),'a'));
////        var_dump(data_get(make('Activity',['attributes'=>['a'=>'b']]),'a'));
////        var_dump(data_get(make('Activity',['attributes'=>['a'=>'b']]),'a'));
//
//        return $requsst->all();
//
//        return \App\Utils\Response::json($requsst->all(),1,'ok',true,201,['TEST'=>566]);

//        FunctionHelper::getCountry('50.92.177.65');
//
//        $request = $this->request->withAttribute('a', 'b');
//
//        $request = \Hyperf\Utils\Context::set(ServerRequestInterface::class, $request);
//
        $encrypt = encrypt('123456');


//        $agent = agent();
//        switch (true) {
//            case $agent->isMobile():
//                $deviceType = 1;
//
//                break;
//
//            case $agent->isTablet():
//                $deviceType = 2;
//
//                break;
//
//            case $agent->isDesktop():
//                $deviceType = 3;
//
//                break;
//
//            default:
//                break;
//        }
//
//        $isRobot = $agent->isRobot() ? 1 : 0;
//        $languages = $agent->languages();
//        $clientData = [
//            Constant::DEVICE => $agent->device(), //设备信息
//            Constant::DEVICE_TYPE => $deviceType, // 设备类型 1:手机 2：平板 3：桌面
//            Constant::DB_TABLE_PLATFORM => $agent->platform(), //系统信息
//            Constant::PLATFORM_VERSION => $agent->version($agent->platform()), //系统版本
//            Constant::BROWSER => $agent->browser(), // 浏览器信息  (Chrome, IE, Safari, Firefox, ...)
//            Constant::BROWSER_VERSION => $agent->version($agent->browser()), // 浏览器版本
//            Constant::LANGUAGES => is_array($languages) ? json_encode($languages, JSON_UNESCAPED_UNICODE) : $languages, // 语言 ['nl-nl', 'nl', 'en-us', 'en']
//            Constant::IS_ROBOT => $isRobot, //是否是机器人
//            Constant::DB_TABLE_UPDATED_MARK => '',//请求标识
//        ];

        //FunctionHelper::pushQueue(['a'=>898989]);//进入消息队列

//        return [
//            'encrypt' => $encrypt,
//            'decrypt' => decrypt($encrypt),
//            'requestData' => $this->request->all(),
//            'id' => app('request')->input('id'),
//
//            'parsedBody' => $this->request->getParsedBody(),
//            //'serverParams' => $this->request->getServerParams(),//服务端数据
//            'requestContents' => $this->request->getBody()->getContents(),
//            //'requestAttributes' => $this->request->getAttributes(),
//            'ip' => getClientIP(),
//            'request_Headers' => $this->request->getHeaders(),
//            'geoip' => geoip('50.92.177.65')->toArray(),
//            //'clientData'=>$this->request->getAttributes(),
//        ];


//        $key = 'X-Shopify-Hmac-Sha256';
//        $hmac = $this->request->getHeaderLine($key);//$request->getHeader($key, '');
//        //$data = file_get_contents('php://input');
//        // 调用getContent()来获取原始的POST body，而不能用file_get_contents('php://input')
//        $data = $this->request->getBody()->getContents();
//
//        var_dump($this->request->getHeaders(), $key, $hmac, $data);

        return \App\Utils\Response::json([
            'encrypt' => $encrypt,
            'decrypt' => decrypt($encrypt),
            'requestData' => $this->request->all(),
            'id' => app('request')->input('id'),
            'parsedBody' => $this->request->getParsedBody(),
            'serverParams' => $this->request->getServerParams(),//服务端数据
            'requestContents' => $this->request->getBody()->getContents(),
            'requestAttributes' => $this->request->getAttributes(),
            'ip' => getClientIP(),
            'request_Headers' => $this->request->getHeaders(),
            'geoip' => geoip('50.92.177.65')->toArray(),
            'clientData' => $this->request->getAttributes(),
        ]);
    }

    /**
     * Snowflake 是由 Twitter 提出的一个分布式全局唯一 ID 生成算法，算法生成 ID 的结果是一个 64bit 大小的长整
     * @return array
     */
    public function snowflake()
    {
        $container = ApplicationContext::getContainer();
        $generator = $container->get(IdGeneratorInterface::class);

        $id = $generator->generate();
        $meta = $generator->degenerate($id);

        //getTranslator()->setLocale('en');

        return [
            'id' => $id,
            'meta' => $meta,
            //'appSecret' => \App\Services\Store\Shopify\BaseService::getAttribute(12),
            //'apiKey' => \App\Services\Store\Shopify\BaseService::getAttribute(11),
            //'appSecret12' => \App\Services\Store\Shopify\Customers\Customer::createCustomer(11, 'test_ddd@patazon.net', '123456', true, 'firstName', 'lastName'),
            //'getCount' => \App\Services\Store\Shopify\Customers\Customer::getCount(11),
            //'app.patozon_app' => config('app.patozon_app'),

        ];

    }

    /**
     * 国际化 https://hyperf.wiki/2.0/#/zh-cn/translation
     * @return array
     */
    public function trans()
    {

        return [
            'trans' => getTranslator()->trans('messages.welcome', ['name' => 'hyperf'], 'zh_CN'),//
            'trans_func' => trans('messages.welcome', ['name' => 'hyperf']),
            '__' => __('messages.welcome', ['name' => 'hyperf']),
            'trans_choice' => trans_choice('messages.apples', 10),
            'transChoice' => getTranslator()->transChoice('messages.appless', 30),
        ];
    }

    /**
     * 验证器 https://hyperf.wiki/2.0/#/zh-cn/validation
     * @return array
     */
    public function validation()
    {

//        return [
//            'trans' => getTranslator()->trans('messages.welcome', ['name' => 'hyperf'], 'zh_CN'),//
//            'trans_func' => trans('messages.welcome', ['name' => 'hyperf']),
//            '__' => __('messages.welcome', ['name' => 'hyperf']),
//            'trans_choice' => trans_choice('messages.apples', 10),
//            'transChoice' => getTranslator()->transChoice('messages.appless', 30),
//        ];

        return \App\Utils\Response::json($this->request->all(),10028,'',true,201,['TEST'=>566]);
    }

    public function filesystem(\League\Flysystem\Filesystem $filesystem)
    {
        // Process Upload
        $file = $this->request->file('upload');
        $stream = fopen($file->getRealPath(), 'r+');
        $filesystem->writeStream(
            'uploads/'.$file->getClientFilename(),
            $stream
        );
        fclose($stream);

        // Write Files
        $filesystem->write('path/to/file.txt', 'contents');

        // Add local file
        $stream = fopen('local/path/to/file.txt', 'r+');
        $result = $filesystem->writeStream('path/to/file.txt', $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        // Update Files
        $filesystem->update('path/to/file.txt', 'new contents');

        // Check if a file exists
        $exists = $filesystem->has('path/to/file.txt');

        // Read Files
        $contents = $filesystem->read('path/to/file.txt');

        // Delete Files
        $filesystem->delete('path/to/file.txt');

        // Rename Files
        $filesystem->rename('filename.txt', 'newname.txt');

        // Copy Files
        $filesystem->copy('filename.txt', 'duplicate.txt');

        // list the contents
        $filesystem->listContents('path', false);
    }

    public function filesystemFactory()//\Hyperf\Filesystem\FilesystemFactory $factory
    {

        //$dd = base64_encode(file_get_contents(BASE_PATH . '/storage/thumbnail-1.png'));

        return \App\Utils\Cdn\CdnManager::upload(null,$this->request,'/upload/img/', 'UploadCdn',$is_del = false, $isCn = false, $fileName = '', $resourceType = 0, [Constant::DB_TABLE_STORE_ID=>1]);

//        return [
////            \App\Utils\Cdn\ResourcesCdn::getDistVitualPath(1,'/imadd/upload//'),
////            \App\Utils\Cdn\ResourcesCdn::getUploadFileName(1, '/img/upload//', '.png'),
//            \App\Utils\Cdn\AwsS3Cdn::getAttribute(1, 'driver'),
//            \App\Utils\Cdn\AwsS3Cdn::getAttribute(1, 'credentials'),
//            \App\Utils\Cdn\AwsS3Cdn::getAttribute(1, 'region'),
//            \App\Utils\Cdn\AwsS3Cdn::getAttribute(1, 'bucket_name'),
//            \App\Utils\Cdn\AwsS3Cdn::getAttribute(1, 'diskName'),
//        ];
//        $local = $factory->get('local');
//        // Write Files
//        $local->write('path/to/file.txt', 'contents');

        $factory = ApplicationContext::getContainer()->get(\Hyperf\Filesystem\FilesystemFactory::class);
        $filesystem = $factory->get('s3');

        $config = [
            'visibility' => AdapterInterface::VISIBILITY_PUBLIC,
            //'mimetype'=>'',
        ];


        // Process Upload
        $file = $this->request->file('file');
        //$file = new UploadedFile($file->getRealPath(), (int) filesize($file->getRealPath()), (int) $file->getError(), $file->getRealPath(), $file->getClientMediaType());

        $extension = $file->getExtension();
        $fileName = Str::random(10) . '.'.$extension;
        $stream = fopen($file->getRealPath(), 'r+');
        $path = 'uploads/' . $fileName;//$file->getClientFilename();

        $filesystem->writeStream(
            $path,
            $stream,
            $config
        );
        fclose($stream);

        return [
            'path' => $path,
        ];

//        // Write Files
//        $filesystem->write('path/to/file.txt', 'contents');
//
//        // Add local file
//        $stream = fopen('local/path/to/file.txt', 'r+');
//        $result = $filesystem->writeStream('path/to/file.txt', $stream);
//        if (is_resource($stream)) {
//            fclose($stream);
//        }
//
//        // Update Files
//        $filesystem->update('path/to/file.txt', 'new contents');
//
//        // Check if a file exists
//        $exists = $filesystem->has('path/to/file.txt');
//
//        // Read Files
//        $contents = $filesystem->read('path/to/file.txt');
//
//        // Delete Files
//        $filesystem->delete('path/to/file.txt');
//
//        // Rename Files
//        $filesystem->rename('filename.txt', 'newname.txt');
//
//        // Copy Files
//        $filesystem->copy('filename.txt', 'duplicate.txt');
//
//        // list the contents
//        $filesystem->listContents('path', false);

//        $fileName = Str::random(10) . '.txt';
//        $config = [
//            'visibility'=>AdapterInterface::VISIBILITY_PUBLIC,
//            //'mimetype'=>'',
//        ];
//
//        $path = 'path/to/' . $fileName;
//
//        $path = Util::normalizePath($path);
//        $s3->assertAbsent($path);
//        //$config = $s3->prepareConfig($config);
//
//        $config = new Config($config);
//        $config->setFallback($s3->getConfig());
//
//        return $s3->getAdapter()->write($path, 'contents', $config);

//        return [
//            'path' => $path,
//            'dd' => $s3->write($path, 'contents',$config)
//        ];
    }

    public function view(RenderInterface $render)
    {
        return $render->render('index', ['name' => 'Hyperf']);
    }

    public function mail()
    {
        return ApplicationContext::getContainer()->get(FactoryContract::class)->mailer()->to('Jmiy_cen@patazon.net')->send(new \App\Mail\OrderShipped());;
    }

    public function ding()
    {
        throw new \Exception('hyperf test ding', -101); // throw the original exception
    }

    /**
     * https://gitee.com/viest/php-ext-xlswriter#PECL
     * @param Request $request
     */
    public function excel() {
        $config = ['path' => BASE_PATH . '/runtime/logs'];

        //游标读取(按行读取)
        $excel = new \Vtiful\Kernel\Excel($config);

        //生产excel文件
//        $filePath = $excel->fileName('tutorial.xlsx')
//                ->header(['Item', 'Cost'])
//                ->output();
//
//        //全量读取excel文件
//        $data = $excel->openFile('deal_coupon_template.xlsx')
//                ->openSheet()
//                ->getSheetData();


        //游标读取(按行读取)
        $excel->openFile('deal_coupon_template.xlsx')
            ->openSheet();

        //var_dump($excel->nextRow()); // ['Item', 'Cost']
        //var_dump($excel->nextRow()); // NULL

        while ($row = $excel->nextRow()) {
            var_dump($row);
        }
        return $row;

//        $excel = new \Vtiful\Kernel\Excel($config);
//        // fileName 会自动创建一个工作表，你可以自定义该工作表名称，工作表名称为可选参数
//        $filePath = $excel
//                ->fileName('tutorial01.xlsx', 'sheet1')
//                ->header(['Item', 'Cost'])
//                ->data([
//                    ['Rent', 1000],
//                    ['Gas', 100],
//                    ['Food', 300],
//                    ['Gym', 50],
//                ])
//                ->output();
        //图表添加数据
//        $fileObject = new \Vtiful\Kernel\Excel($config);
//        $fileObject = $fileObject->fileName('chart.xlsx');
//        $fileHandle = $fileObject->getHandle();
//
//        //直方图
//        $chart = new \Vtiful\Kernel\Chart($fileHandle, \Vtiful\Kernel\Chart::CHART_COLUMN);
//        $chartResource = $chart
//                ->series('Sheet1!$A$2:$A$6')
//                ->seriesName('=Sheet1!$A$1')
//                ->series('Sheet1!$B$2:$B$6')
//                ->seriesName('=Sheet1!$B$1')
//                ->series('Sheet1!$C$2:$C$6')
//                ->seriesName('=Sheet1!$C$1')
//                ->toResource();
//
//        $filePath = $fileObject
//                        ->header(['Number', 'Batch 1', 'Batch 2'])
//                        ->data([
//                            [1, 2, 3],
//                            [2, 4, 6],
//                            [3, 6, 9],
//                            [4, 8, 12],
//                            [5, 10, 15],
//                        ])->insertChart(0, 3, $chartResource)->output();
//        exit;
//
//        //面积图
//        $config = ['path' => storage_path('logs')];
//        $fileObject = new \Vtiful\Kernel\Excel($config);
//        $fileObject = $fileObject->fileName('CHART_AREA.xlsx');
//        $fileHandle = $fileObject->getHandle();
//        $chart = new \Vtiful\Kernel\Chart($fileHandle, \Vtiful\Kernel\Chart::CHART_AREA);
//
//        $chartResource = $chart
//                ->series('=Sheet1!$B$2:$B$7', '=Sheet1!$A$2:$A$7')
//                ->seriesName('=Sheet1!$B$1')
//                ->series('=Sheet1!$C$2:$C$7', '=Sheet1!$A$2:$A$7')
//                ->seriesName('=Sheet1!$C$1')
//                ->style(11)// 值为 1 - 48，可参考 Excel 2007 "设计" 选项卡中的 48 种样式
//                ->axisNameX('Test number') // 设置 X 轴名称
//                ->axisNameY('Sample length (mm)') // 设置 Y 轴名称
//                ->title('Results of sample analysis') // 设置图表 Title
//                ->toResource();
//
//        $filePath = $fileObject->header(['Number', 'Batch 1', 'Batch 2'])
//                        ->data([
//                            [2, 40, 30],
//                            [3, 40, 25],
//                            [4, 50, 30],
//                            [5, 30, 10],
//                            [6, 25, 5],
//                            [7, 50, 10],
//                        ])->insertChart(0, 3, $chartResource)->output();
//        exit;
//
//        //单元格插入文字
//        函数原型
//        insertText(int $row, int $column, string|int|double $data[, string $format, resource $style])
//        int $row
//        单元格所在行
//
//        int $column
//        单元格所在列
//
//        string | int | double $data
//        需要写入的内容
//
//        string $format
//        内容格式
//
//        resource $style
//        单元格样式


        $excel = new \Vtiful\Kernel\Excel($config);
        $textFile = $excel->fileName("free32.xlsx")
            ->header(['customer_id', 'store_customer_id', 'store_id', 'account', 'status', 'ctime', 'source']);
        $row = 0;
        \App\Models\Customer::where('store_id', 1)->select(['*'])
            ->chunk(100, function ($data) use($textFile, &$row) {
                if ($data) {
                    foreach ($data as $item) {
                        $row = $row + 1;
                        $item = $item->toArray();
                        $item = array_values($item);
                        foreach ($item as $key => $value) {
                            $textFile->insertText($row, $key, $value);
                        }

//                            $textFile->insertText($row, 1, $item->store_customer_id, '#,##0');
//                            $textFile->insertText($row, 2, $item->store_id, '#,##0');
//                            $textFile->insertText($row, 3, $item->account, '#,##0');
//                            $textFile->insertText($row, 4, $item->status, '#,##0');
//                            $textFile->insertText($row, 5, $item->ctime, '#,##0');
//                            $textFile->insertText($row, 6, $item->source, '#,##0');
                    }
                }
            });
        $textFile->output();

//        for ($index = 0; $index < 1000000; $index++) {
//            $textFile->insertText($index + 1, 0, 'viest' . $index);
//            $textFile->insertText($index + 1, 1, 10000, '#,##0');
//        }
//        $textFile->output();
//
//        for ($index = 1000000; $index < 1000020; $index++) {
//            $textFile->insertText($index + 1, 0, 'viest' . $index);
//            $textFile->insertText($index + 1, 1, 10000, '#,##0');
//            $textFile->output();
//        }


        exit;
//
        //单元格插入链接
//        函数原型
//        insertUrl(int $row, int $column, string $url[, resource $format])
//        int $row
//        单元格所在行
//
//        int $column
//        单元格所在列
//
//        string $url
//        链接地址
//
//        resource $format
//        链接样式
//
//        实例
//        $excel = new \Vtiful\Kernel\Excel($config);
//
//        $urlFile = $excel->fileName("free.xlsx")
//            ->header(['url']);
//
//        $fileHandle = $fileObject->getHandle();
//
//        $format    = new \Vtiful\Kernel\Format($fileHandle);
//        $urlStyle = $format->bold()
//            ->underline(Format::UNDERLINE_SINGLE)
//            ->toResource();
//
//        $urlFile->insertUrl(1, 0, 'https://github.com', $urlStyle);
//
//        $textFile->output();
//
//
//        单元格插入公式
//        函数原型
//        insertFormula(int $row, int $column, string $formula)
//        int $row
//        单元格所在行
//
//        int $column
//        单元格所在列
//
//        string $formula
//        公式
//
//        实例
//        $excel = new \Vtiful\Kernel\Excel($config);
//
//        $freeFile = $excel->fileName("free.xlsx")
//            ->header(['name', 'money']);
//
//        for($index = 1; $index < 10; $index++) {
//            $textFile->insertText($index, 0, 'viest');
//            $textFile->insertText($index, 1, 10);
//        }
//
//        $textFile->insertText(12, 0, "Total");
//        $textFile->insertFormula(12, 1, '=SUM(B2:B11)');
//
//        $freeFile->output();
//
//        //单元格插入本地图片
//        函数原型
//        insertImage(int $row, int $column, string $localImagePath[, double $widthScale, double $heightScale])
//        int $row
//        单元格所在行
//
//        int $column
//        单元格所在列
//
//        string $localImagePath
//        图片路径
//
//        double $widthScale
//        对图像X轴进行缩放处理； 默认为1，保持图像原始宽度；值为0.5时，图像宽度为原图的1/2；
//
//        double $heightScale
//        对图像轴进行缩放处理； 默认为1，保持图像原始高度；值为0.5时，图像高度为原图的1/2；
//
//        实例
//        $excel = new \Vtiful\Kernel\Excel($config);
//        $freeFile = $excel->fileName("insertImage.xlsx");
//        $freeFile->insertImage(5, 0, storage_path('logs/loginbg.b9907988.png'));
//
//        $freeFile->output();
    }

    public function db() {

        /**
         * model
         * -->modelBuilder(Hyperf\Database\Model\Builder)
         * --->databaseQueryBuilder(Hyperf\Database\Query\Builder)
         * --->dbConnectionPoolFactory(Hyperf\DbConnection\Pool\PoolFactory)::getPool(string $name)
         * --->dbPool(Hyperf\DbConnection\Pool\DbPool)::get()--->createConnection()
         * --->dbConnection(Hyperf\DbConnection\Connection(Psr\Container\ContainerInterface $container, Hyperf\DbConnection\Pool\DbPool $pool, array $config))
         *     ---->getConnection()
         *     ---->getActiveConnection()
         * --->connectionFactory(Hyperf\Database\Connectors\ConnectionFactory)
         *     ---->make()
         *     ---->createSingleConnection($config)
         *          ---->createPdoResolver($config)
         *               ---->createPdoResolverWithoutHosts($config)
         *                    \Closure $connection==>
         *                    ---->createConnector(array $config)
         *                            ---->new Hyperf\Database\Connectors\MySqlConnector::connect(array $config)
         *          ---->createConnection($driver, \Closure $connection, $database, $prefix = '', array $config = [])
         *               ----->\Closure $connection
         *               ---->Hyperf\Database\MySqlConnection()
         *
         */

        /**
         * \App\Services\RankService::getModel(1)->where('id', '>', 0)->get();
         *
         * ###1:\App\Services\RankService::getModel(1)
         *    ###1.1:调用 model 所有的 Traits 中的 trait::bootSearchable()
         *      ###1.1.1 将 Scope 放入 GlobalScope container（容器）中
         *      ###1.1.2 将 Traits中的 'initialize' . class_basename($trait) 放入 TraitInitializers::$container容器中（TraitInitializers::$container[$class][] = $method;）
         *    ###1.2:调用 model::initializeTraits() 执行 TraitInitializers::$container容器中 当前类的 Traits中的 （'initialize' . class_basename($trait)） 方法
         *    ###1.3:调用 model::syncOriginal() 将 model的attributes 保存到 model的original 中 （$this->original = $this->getAttributes()） 以便比较 model的attributes 前后变化
         *    ###1.4:调用 model::fill(array $attributes) 用属性数组填充模型。
         *
         * ###2:where('id', '>', 0) 调用model::__call($method, $parameters)
         *    ###2.1.1:call([model::newQuery(), $method], $parameters);
         *    {
         *       ###2.1.1.1===>model::newQuery():Get a new query builder for the model's table.
         *          ###2.1.1.1.1===>model::newQueryWithoutScopes():
         *              ###2.1.1.1.1===>model::newModelQuery()->with($this->with)->withCount($this->withCount):
         *                  ###2.1.1.1.1.1===>model::newBaseQueryBuilder(): Get a new query builder instance for the connection.
         *                  ###2.1.1.1.1.1 return===>$databaseQueryBuilder=new Hyperf\Database\Query\Builder($connection, $connection->getQueryGrammar(), $connection->getPostProcessor()): Get a new query builder instance for the connection.
         *                  ###2.1.1.1.1.2===>model::newModelBuilder($databaseQueryBuilder)
         *                  ###2.1.1.1.1.2 return===>$modelBuilder=new Hyperf\Database\Model\Builder($databaseQueryBuilder):Get a new Model query builder for the model.
         *                  ###2.1.1.1.1.3 return===>$modelBuilder=$modelBuilder::setModel($model): Set a model instance for the model being queried.
         *              ###2.1.1.1.1===>return $modelBuilder:Get a new query builder that doesn't have any global scopes.
         *          ###2.1.1.1.1===>return modelBuilder(QueryWithoutScopes)
         *
         *          ###2.1.1.1.2===>model::registerGlobalScopes(###2.1.1.1.1===>modelBuilder(QueryWithoutScopes)):Register the global scopes for this builder instance.
         *              ###2.1.1.1.2.1===>将 Scope 从 GlobalScope container（容器）中 取出，并执行 modelBuilder->withGlobalScope($identifier, $scope)
         *                  ###2.1.1.1.2.1.1===>modelBuilder->withGlobalScope($identifier, $scope)
         *                      ###2.1.1.1.2.1.1.1 将当前类的所有 Scope 保存到 $modelBuilder->scopes[$identifier] = $scope;
         *                      ###2.1.1.1.2.1.1.2 执行当前类的所有 Scope::extend(modelBuilder)
         *          ###2.1.1.1.2===>return modelBuilder(QueryWithoutScopes)
         *       ###2.1.1.1 return ===>modelBuilder(\Hyperf\Database\Model\Builder QueryWithGlobalScopes)
         *
         *       ###2.1.1.2 ===>modelBuilder->$method($parameters)
         *          ###2.1.1.2.1===>$databaseQueryBuilder->$method($parameters)
         *       ###2.1.1.2 return ===>modelBuilder(QueryWithGlobalScopes)
         *    }
         *    ###2.1.1 return ===>modelBuilder(QueryWithGlobalScopes)
         *
         * ###2 return ===>modelBuilder(QueryWithGlobalScopes)
         *
         * ###3:modelBuilder(QueryWithGlobalScopes)->get()
         *    ###3.1===>modelBuilder::applyScopes(); 通过遍历 $modelBuilder->scopes 执行 scope::apply(\Hyperf\Database\Model\Builder $builder, \Hyperf\Database\Model\Model $model)
         *    ###3.2===>$databaseQueryBuilder::get()
         *     ###3.2--->dbConnectionPoolFactory(Hyperf\DbConnection\Pool\PoolFactory)::getPool(string $name)
         *     ###3.2--->dbPool(Hyperf\DbConnection\Pool\DbPool)::get()--->createConnection()
         *     ###3.2--->dbConnection(Hyperf\DbConnection\Connection(Psr\Container\ContainerInterface $container, Hyperf\DbConnection\Pool\DbPool $pool, array $config))
         *         ###3.2---->getConnection()
         *         ###3.2---->getActiveConnection()
         *     ###3.2--->connectionFactory(Hyperf\Database\Connectors\ConnectionFactory)
         *         ###3.2---->make()
         *         ###3.2---->createSingleConnection($config)
         *              ###3.2---->createPdoResolver($config)
         *                   ###3.2---->createPdoResolverWithoutHosts($config)
         *                        ###3.2\Closure $connection==>
         *                        ###3.2---->createConnector(array $config)
         *                                ###3.2---->new Hyperf\Database\Connectors\MySqlConnector::connect(array $config)
         *              ###3.2---->createConnection($driver, \Closure $connection, $database, $prefix = '', array $config = [])
         *                   ###3.2----->\Closure $connection
         *                   ###3.2---->Hyperf\Database\MySqlConnection()
         */


//        $select = [
//            'ci.' . Constant::DB_TABLE_ACCOUNT,
//            'ci.' . Constant::DB_TABLE_FIRST_NAME,
//            'ci.' . Constant::DB_TABLE_LAST_NAME,
//            'ci.' . Constant::DB_TABLE_GENDER,
//            'ci.' . Constant::DB_TABLE_BRITHDAY,
//            'ci.' . Constant::DB_TABLE_COUNTRY,
//            'ci.' . 'mtime',
//            'ci.' . Constant::DB_TABLE_IP,
//            'ci.' . Constant::DB_TABLE_PROFILE_URL,
//            'ci.' . Constant::DB_TABLE_EDIT_AT,
//            'ci.' . Constant::DB_TABLE_CUSTOMER_PRIMARY
//        ];
//
//        $storeId = 1;
////
//////        $genderData = \App\Services\DictService::getListByType(Constant::DB_TABLE_GENDER, Constant::DB_TABLE_DICT_KEY, Constant::DB_TABLE_DICT_VALUE);
//////        $dbExecutionPlan = [
//////            Constant::DB_EXECUTION_PLAN_PARENT => [
//////                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
//////                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
//////                Constant::DB_EXECUTION_PLAN_BUILDER => null,
//////                'make' => \App\Services\CustomerInfoService::getNamespaceClass(),
//////                'from' => 'customer_info as ci',
//////                Constant::DB_EXECUTION_PLAN_SELECT => $select,
//////                Constant::DB_EXECUTION_PLAN_WHERE => [],
//////                Constant::DB_EXECUTION_PLAN_LIMIT => 10,
//////                Constant::DB_EXECUTION_PLAN_OFFSET => 0,
//////                Constant::DB_EXECUTION_PLAN_IS_PAGE => false,
//////                Constant::DB_EXECUTION_PLAN_PAGINATION => [],
//////                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
//////                    'mtime' => [
//////                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_EDIT_AT,
//////                        'data' => [],
//////                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
//////                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
//////                        'glue' => '',
//////                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
//////                    ],
//////                    Constant::DB_TABLE_GENDER => [
//////                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_GENDER,
//////                        'data' => $genderData,
//////                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
//////                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
//////                        'glue' => '',
//////                        Constant::DB_EXECUTION_PLAN_DEFAULT => $genderData[0],
//////                    ],
//////                    'brithday' => [
//////                        Constant::DB_EXECUTION_PLAN_FIELD => 'brithday',
//////                        'data' => [],
//////                        Constant::DB_EXECUTION_PLAN_DATATYPE => 'datetime',
//////                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => 'Y-m-d',
//////                        'glue' => '',
//////                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
//////                    ],
//////                    'name' => [
//////                        Constant::DB_EXECUTION_PLAN_FIELD => 'first_name{connection}last_name',
//////                        'data' => [],
//////                        Constant::DB_EXECUTION_PLAN_DATATYPE => 'string',
//////                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
//////                        'glue' => ' ',
//////                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
//////                    ],
//////                    'region' => [
//////                        Constant::DB_EXECUTION_PLAN_FIELD => 'address_home.region{or}address_home.city',
//////                        'data' => [],
//////                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
//////                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
//////                        'glue' => '',
//////                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
//////                    ],
//////                    'interest' => [
//////                        Constant::DB_EXECUTION_PLAN_FIELD => 'interests.*.interest',
//////                        'data' => [],
//////                        Constant::DB_EXECUTION_PLAN_DATATYPE => 'string',
//////                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
//////                        'glue' => ',',
//////                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
//////                    ],
//////                ],
//////            ],
//////            'with' => [
//////                'address_home' => [
//////                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
//////                    Constant::DB_EXECUTION_PLAN_STOREID => 'default_connection_0',
//////                    'relation' => 'hasOne',
//////                    Constant::DB_EXECUTION_PLAN_DEFAULT => [
//////                        Constant::DB_TABLE_CUSTOMER_PRIMARY => Constant::DB_TABLE_CUSTOMER_PRIMARY,
//////                    ],
//////                    Constant::DB_EXECUTION_PLAN_SELECT => [
//////                        Constant::DB_TABLE_CUSTOMER_PRIMARY,
//////                        'region',
//////                        'city',
//////                    ],
//////                    Constant::DB_EXECUTION_PLAN_WHERE => [],
//////                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
//////                    //'unset' => ['address_home'],
//////                ],
//////            ],
//////            //'sqlDebug' => true,
//////        ];
//////
//////        Arr::set($dbExecutionPlan, 'with.order_data', [
//////            Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
//////            Constant::DB_EXECUTION_PLAN_STOREID => 1,
//////            'relation' => 'hasOne',
//////            Constant::DB_EXECUTION_PLAN_SELECT => [Constant::DB_TABLE_CUSTOMER_PRIMARY, 'orderno'],
//////            Constant::DB_EXECUTION_PLAN_DEFAULT => [],
//////            Constant::DB_EXECUTION_PLAN_WHERE => [],
//////            Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
//////            //'unset' => [Constant::DB_TABLE_INTERESTS],
//////        ]);
////
//        $dbExecutionPlan = [
//            Constant::DB_EXECUTION_PLAN_PARENT => [
//                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
//                Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
//                Constant::DB_EXECUTION_PLAN_BUILDER => null,
//                'make' => \App\Services\OrderWarrantyService::getNamespaceClass(),
//                'from' => '',
//                Constant::DB_EXECUTION_PLAN_SELECT => ['*'],
//                Constant::DB_EXECUTION_PLAN_WHERE => [],
//                Constant::DB_EXECUTION_PLAN_LIMIT => 1,
//                Constant::DB_EXECUTION_PLAN_OFFSET => 0,
//                Constant::DB_EXECUTION_PLAN_IS_PAGE => false,
//                Constant::DB_EXECUTION_PLAN_PAGINATION => [],
//                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
//            ],
//            'with' => [
//                'customer_info' => [
//                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
//                    Constant::DB_EXECUTION_PLAN_STOREID => 'default_connection_'.$storeId,
//                    'relation' => 'hasOne',
//                    Constant::DB_EXECUTION_PLAN_DEFAULT => [
//                        Constant::DB_TABLE_CUSTOMER_PRIMARY => Constant::DB_TABLE_CUSTOMER_PRIMARY,
//                    ],
//                    Constant::DB_EXECUTION_PLAN_SELECT => ['*'],
//                    Constant::DB_EXECUTION_PLAN_WHERE => [],
//                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
//                ],
//                'order' => [
//                    Constant::DB_EXECUTION_PLAN_SETCONNECTION => false,
//                    Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
//                    'relation' => 'hasOne',
//                    Constant::DB_EXECUTION_PLAN_DEFAULT => [],
//                    Constant::DB_EXECUTION_PLAN_SELECT => ['*'],
//                    Constant::DB_EXECUTION_PLAN_WHERE => [],
//                    Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [],
//                ],
//            ],
//            //'sqlDebug' => true,
//        ];
//
//        $dataStructure = 'list';
//        $flatten = false;
//        $_data = FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, false, $dataStructure);
//
//        return Response::json($_data);

        $storeIds = \App\Services\StoreService::getModel(0)->pluck('id');
//        $storeIds = [1];
//        for ($i = 0; $i < 1; $i++) {
//            foreach ($storeIds as $storeId) {
////                $data = [
////                    'customer_id' => $storeId,
////                ];
////                \App\Services\RankService::getModel($storeId)->insert($data);
//                $model = \App\Services\RankService::getModel($storeId);
//                $model->customer_id=$storeId;
//                $model->offsetUnset('storeId');
//                $model->save();
//
//            }
//        }

        /************************模型缓存:https://hyperf.wiki/2.0/#/zh-cn/db/model-cache *********************************************/
        // 查询单个缓存
        /** @var int|string $id */
        //$models = \App\Services\RankService::getModel(1)->where('id',233257240935550977)->update(['act_id'=>111]);
        //$models = \App\Services\RankService::getModel(1)->findFromCache(233257240935550977);

        FunctionHelper::setTimezone(1);
//        $ids = [233257240935550977,233257240985882624,3333];
//        $models = \App\Services\RankService::getModel(1)->findManyFromCache($ids);
//
//        $models = \App\Services\RankService::getModel(2)->findFromCache(1);
//
//        $models = \App\Services\RankService::getModel(1)->deleteCache();

        //var_dump(\App\Services\RankService::getModel(1)->where('id',233257240935550977)->first());

        //数据库聚合操作
//        $column='act_id';
//        $amount = 1;
//        $extra = [
//            //'customer_id' => 1
//        ];
//        $models=\App\Services\RankService::getModel(1)->whereIn('id', $ids)->get();
//        $models->increment($column, $amount, $extra);//

        //清空model缓存
//        \App\Services\RankService::getModel(1)->deleteCache();
//        \App\Services\RankService::getModel(1)->deleteCache();
        //$models=\App\Services\RankService::getModel(1)->whereIn('id', $ids)->decrement($column, $amount, $extra);//->whereIn('id', $ids)->get() ->get() $column, $amount = 1, array $extra = []  ->find(1) ->find(1)

        // 批量查询缓存，返回 Hyperf\Database\Model\Collection
        /** @var array $ids */
//        $models = \App\Services\RankService::getModel(1)->findManyFromCache($ids);
//        $models->loadCache(['rank_day' => function($relation) {
//            BaseService::createModel(1, null, [], '', $relation); //设置关联对象relation 数据库连接
//        }
//        ]);

//        $models = \App\Services\RankService::getModel(2)->findManyFromCache($ids);

        //模型缓存批量模糊删除
        //$models = \App\Services\RankService::getModel(1)->batchFuzzyDelete();

        /************************模型缓存:https://hyperf.wiki/2.0/#/zh-cn/db/model-cache *********************************************/

        /************************模型全文检索:https://hyperf.wiki/2.0/#/zh-cn/scout *********************************************/

        //###批量添加
        //\App\Services\RankService::getModel(1)->where('id', '>', 0)->searchable();//
        // 使用模型关系增加记录...
        //\App\Services\RankService::getModel(1)->where('id', '>', 0)->get()->orders()->searchable();
        // 使用集合增加记录...
        //\App\Services\RankService::getModel(1)->where('id', '>', 0)->get()->searchable();

        //###删除记录
        //###简单地使用 delete 从数据库中删除该模型就可以移除索引里的记录。这种删除形式甚至与软删除的模型兼容:
        //\App\Services\RankService::getModel(1)->where('id', '=', 3)->delete();//
        //###通过模型查询删除...
        //\App\Services\RankService::getModel(1)->where('id', '>', 3)->unsearchable();
        //###使用模型关系增加记录...
        //\App\Services\RankService::getModel(1)->where('id', '>', 0)->get()->orders()->unsearchable();
        //###使用集合增加记录...
        //\App\Services\RankService::getModel(1)->where('id', '>', 0)->get()->unsearchable();

//        $models = \App\Services\RankService::getModel(1)
//            ->search('33')//''
////            ->where([
////                'customer_id'=>118156,
////                //'customer_id'=>118156,
////                ])
//            ->raw()
//            //->get()
//        ;

        return Response::json($storeIds);
    }

    public function jsonRpc() {

        var_dump(__METHOD__);

        $client = ApplicationContext::getContainer()->get(\App\JsonRpc\Contracts\CalculatorServiceInterface::class);

        /** @var MathValue $result */
        $result = $client->add(1,2);

        return Response::json($result);
    }

    /**
     * 服务限流:https://hyperf.wiki/2.0/#/zh-cn/rate-limit
     * 配置	          默认值	备注
     * create	       1	每秒生成令牌数
     * consume	       1	每次请求消耗令牌数
     * capacity	       2	令牌桶最大容量
     * limitCallback  NULL	触发限流时回调方法
     * key	          NULL	生成令牌桶的 key
     * waitTimeout	   3	排队超时时间
     * @RateLimit(create=1, capacity=1, limitCallback={DocController::class, "limitCallback"})
     */
    public function rateLimit()
    {
        return ["QPS 1, 峰值3"];
    }

    /**
     * @RateLimit(create=2, consume=2, capacity=4)
     */
    public function rateLimit2()
    {
        return ["QPS 2, 峰值2"];
    }

    public static function limitCallback(float $seconds, ProceedingJoinPoint $proceedingJoinPoint)
    {
        // $seconds 下次生成Token 的间隔, 单位为秒
        // $proceedingJoinPoint 此次请求执行的切入点
        // 可以通过调用 `$proceedingJoinPoint->process()` 继续执行或者自行处理

        return ["访问过于频繁，请稍后访问"];

        //return $proceedingJoinPoint->process();
    }

    /**
     * etcd
     */
    public function etcd()
    {
        //获取应用容器
        $container = ApplicationContext::getContainer();
        $client = $container->get(KVInterface::class);

        return [
            $client->put('/application/test',json_encode(['a'=>'test etcd'], JSON_UNESCAPED_UNICODE)),
            $client->put('/application/etcd',json_encode(['a'=>'etcd==='], JSON_UNESCAPED_UNICODE)),
        ];
    }

    /**
     * etcd
     */
    public function getEtcdConfig()
    {
        //获取应用容器
        $container = ApplicationContext::getContainer();

        //通过应用容器 获取配置类对象
        $config = $container->get(ConfigInterface::class);

        $client = $container->get(KVInterface::class);

        return [
            $client->get('/application/etcd'),//'/application/etcd'
            $config->get('etcd')
        ];
    }

    /**
     * http client
     */
    public function httpClient()
    {

//        var_dump(pow(2, 16)-1);//pow(x,y)  返回 x 的 y 次方
//        var_dump('curl_multi_exec',function_exists('curl_multi_exec'));
//        var_dump('curl_exec',function_exists('curl_exec'));
//        var_dump('allow_url_fopen',ini_get('allow_url_fopen'));


        $option = [
            'max_connections' => 50,
        ];
        $middlewares = [
            'retry' => [RetryMiddleware::class, [1, 10]],
        ];
        $factory = new HandlerStackFactory();
        $stack = $factory->create($option, $middlewares);

        $client = make(Client::class, [
            'config' => [
                'handler' => $stack,
                //'base_uri' => 'https://www.baidu.com',
                //'base_uri' => 'https://brandwtest.patozon.net',
                //'base_uri' => 'http://192.168.152.128:81',
                //'base_uri' => 'https://brand.patozon.net/',
                //'base_uri' => 'https://brand-api.patozon.net',
                'base_uri' => 'http://httpbin.org',
                // Use a shared client cookie jar
                //RequestOptions::COOKIES=>true,
            ],
        ]);

        $data = [];
        try {
//
//        /************** https://docs.guzzlephp.org/en/stable/quickstart.html#making-a-request ***************/
//        //Sending Requests
//        $response = $client->get('http://httpbin.org/get');
//        $response = $client->delete('http://httpbin.org/delete');
//        $response = $client->head('http://httpbin.org/get');
//        $response = $client->options('http://httpbin.org/get');
//        $response = $client->patch('http://httpbin.org/patch');
//        $response = $client->post('http://httpbin.org/post');
//        $response = $client->put('http://httpbin.org/put');
//
//        $request = new \GuzzleHttp\Psr7\Request('PUT', 'http://httpbin.org/put');
//        $response = $client->send($request, ['timeout' => 2]);
//
//        /*****************Async Requests start ****************/
//        $promise = $client->getAsync('http://httpbin.org/get');
//        $promise = $client->deleteAsync('http://httpbin.org/delete');
//        $promise = $client->headAsync('http://httpbin.org/get');
//        $promise = $client->optionsAsync('http://httpbin.org/get');
//        $promise = $client->patchAsync('http://httpbin.org/patch');
//        $promise = $client->postAsync('http://httpbin.org/post');
//        $promise = $client->putAsync('http://httpbin.org/put');
//        //###You can also use the sendAsync() and requestAsync() methods of a client:
//        // Create a PSR-7 request object to send
//        $headers = ['X-Foo' => 'Bar'];
//        $body = 'Hello!';
//        $request = new \GuzzleHttp\Psr7\Request('HEAD', 'http://httpbin.org/head', $headers, $body);
//        $promise = $client->sendAsync($request);
//
//        // Or, if you don't need to pass in a request instance:
//        $promise = $client->requestAsync('GET', 'http://httpbin.org/get');
//
//        $promise->then(
//            function (\Psr\Http\Message\ResponseInterface $response) use ($i) {
//                dump($i, $response->getStatusCode(), $response->getBody()->getContents());
//            },
//            function (\GuzzleHttp\Exception\RequestException $e) use ($i) {
//                dump($i, $e->getMessage(), $e->getRequest()->getMethod());
//            }
//        );
//        $promise->wait();
//        $response = $promise->wait();
//        dump($response->getStatusCode(), $response->getBody()->getContents());
//        /*****************Async Requests end ****************/
//
            /*****************Concurrent requests(并发请求) start ****************/
            //You can send multiple requests concurrently using promises and asynchronous requests.
            // Initiate each request but do not block
//            $promises = [
//                'image' => $client->getAsync('https://ssl.gstatic.com/translate/apple.png'),
//                'png' => $client->getAsync('https://www.gstatic.com/inputtools/images/ita_sprite8.png'),
////            'jpeg'  => $client->getAsync('/image/jpeg'),
////            'webp'  => $client->getAsync('/image/webp')
//            ];
            // Wait for the requests to complete; throws a ConnectException
            // if any of the requests fail
            //$responses = \GuzzleHttp\Promise\Utils::unwrap($promises);

            // You can access each response using the key of the promise
            //dump($responses['image']->getHeader('Content-Length')[0]);
            //dump($responses['png']->getHeader('Content-Length')[0]);

            // Wait for the requests to complete, even if some of them fail
            //$responses = \GuzzleHttp\Promise\Utils::settle($promises)->wait();

            // Values returned above are wrapped in an array with 2 keys: "state" (either fulfilled or rejected) and "value" (contains the response)
//            var_dump($responses['image']['state']); // returns "fulfilled"
//            var_dump($responses['image']['value']->getHeader('Content-Length')[0]);
//            var_dump($responses['png']['value']->getHeader('Content-Length')[0]);

            /***************** Concurrent requests(并发请求) You can send multiple requests concurrently using promises and asynchronous requests. Start ****************/
//        $requests = function ($total) {
//            //$uri = 'http://127.0.0.1:8126/guzzle-server/perf';
//            $uri = '/';
//            for ($i = 0; $i < $total; $i++) {
//                yield new \GuzzleHttp\Psr7\Request('GET', $uri);
//            }
//        };
//
//            //###Or using a closure that will return a promise once the pool calls the closure.
//            $requests = function ($total) use ($client) {
//                $uri = '/get';
//                for ($i = 0; $i < $total; $i++) {
//                    yield function () use ($client, $uri) {
//                        return $client->getAsync($uri);
//                    };
//                }
//            };
//
//            //###Or using a closure that will return a promise once the pool calls the closure.
//            $pool = new \GuzzleHttp\Pool($client, $requests(2), [
//                'concurrency' => 5,
//                'fulfilled' => function (\GuzzleHttp\Psr7\Response $response, $index) {
//                    // this is delivered each successful response
//                    var_dump(
//                        $index,
//                        $response->getStatusCode(),
//                        $response->getBody()->getContents()
//                    );
//                },
//                'rejected' => function (\GuzzleHttp\Exception\RequestException $reason, $index) {//\GuzzleHttp\Exception\RequestException
//                    // this is delivered each failed request
//                    var_dump(
//                        'method==>' . $reason->getRequest()->getMethod(),
//                        'scheme==>' . $reason->getRequest()->getUri()->getScheme(),
//                        'host==>' . $reason->getRequest()->getUri()->getHost(),
//                        'port==>' . $reason->getRequest()->getUri()->getPort(),
//                        'path==>' . $reason->getRequest()->getUri()->getPath(),
//                        'query==>' . $reason->getRequest()->getUri()->getQuery(),
//                        'fragment==>' . $reason->getRequest()->getUri()->getFragment(),
//                        'authority==>' . $reason->getRequest()->getUri()->getAuthority(),
//                        'user_info==>' . $reason->getRequest()->getUri()->getUserInfo(),
//                        'message==>' . $reason->getMessage(),
//
//                    );
//                    //var_dump(get_class($reason));
//                },
//            ]);
//
//            // Initiate the transfers and create a promise
//            $promise = $pool->promise();
//            // Force the pool of requests to complete.
//            $responses = $promise->wait();
//            return $responses;
            /***************** Concurrent requests(并发请求) You can send multiple requests concurrently using promises and asynchronous requests. end ****************/

            //Query String Parameters
//        $response = $client->request('GET', 'http://httpbin.org?foo=bar');
//        $response = $client->request('GET', 'http://httpbin.org', [
//            'query' => ['foo' => 'bar']
//        ]);
//        $response = $client->request('GET', 'http://httpbin.org', ['query' => 'foo=bar']);

            //Using Responses
            //You can get the status code and reason phrase of the response:
//        $response = $client->request('GET', '/', [
//            'query' => ['foo' => 'bar']
//        ]);

            $method = 'post';
            $uri = '/api/admin/store/getStore';
            //$uri = '/';

            //$refer = 'https://api-localhost.com/'; //https://www.victsing.com/pages/vip-benefit
            $iv = '1234567891011121';

            $headers = [
                //'Content-Type'=>'application/json',
                //'Content-Type'=>'application/x-www-form-urlencoded',
                //'Content-Type'=>'multipart/form-data',
                'User-Agent' => $this->request->getHeaderLine('User-Agent'),

                //'Referer' => $refer,
//                'Version' => CURL_HTTP_VERSION_1_1,
//                'IvParameterSpec' => $iv,
//                'API_VERSION' => 27, //
//                //'Authorization: Bearer fa83e4f46be69a1417fd3de4bf6fa2a1',
//                //'Authorization: AUdCZgFK',
//                'Authorization' => 'Basic ' . base64_encode('foo:bar===='),//Zm9vOmJhcg==
//                //'Expect' => '',
//                'X-Requested-With' => 'XMLHttpRequest', //告诉服务器，当前请求是 ajax 请求
//                //'X-PJAX: '.false,//告诉服务器在收到这样的请求的时候, 要返回 json 数据
//                //'X-PJAX: '.true,//告诉服务器在收到这样的请求的时候, 只需要渲染部分页面返回就可以了
//                //'Accept: +json',//告诉服务器，要返回 json 数据
//                //'Accept: /json', //告诉服务器，要返回 json 数据
//                'X-Shopify-Hmac-Sha256' => 'aVB8fEJErbweBCKDsc5MI2kzR8JrfEgUM25Be1NWSQs=',
//                'X-Token' => '5d3addb5ddaec3a58d3809010adbf427_1564474859',
//                'Accept-Encoding' => 'gzip',
            ];

            $ip = '151.81.2.93';
            $remotesKeys = [
                'HTTP_X_FORWARDED_FOR',
                'HTTP_CLIENT_IP',
                'HTTP_X_FORWARDED',
                'HTTP_FORWARDED_FOR',
                'HTTP_FORWARDED',
                'REMOTE_ADDR',
                'HTTP_X_CLUSTER_CLIENT_IP',
                'X_FORWARDED_FOR',
                'CLIENT_IP',
                'X_FORWARDED',
                'FORWARDED_FOR',
                'FORWARDED',
                'ADDR',
                'X_CLUSTER_CLIENT_IP',
                'X-FORWARDED-FOR',
                'CLIENT-IP',
                'X-FORWARDED',
                'FORWARDED-FOR',
                'FORWARDED',
                'REMOTE-ADDR',
                'X-CLUSTER-CLIENT-IP',
            ];
//            foreach ($remotesKeys as $remotesKey) {
//                $headers[$remotesKey] = $ip;
//            }
//
//            $options = [
//                RequestOptions::HEADERS => $headers,
//                //RequestOptions::VERSION => '2.0',
//                //RequestOptions::BODY=>'{"store_id":"6","token":"eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJwYXRvem9uLm5ldCIsImlhdCI6MTYxNDgzOTUyMywiaWQiOiI0ODEifQ.41qlnIizkGzjII0AWwezZ5T3BAfHsJYvf-mzb6cVMfE","operator":"Jmiy_cen(岑永坚)","orderno":"","account":"","country":[],"asin":"","sku":"","type":[],"start_time":"","star":[],"end_time":"","audit_status":[],"page":1,"page_size":10,"is_psc":true}',
////            RequestOptions::FORM_PARAMS=>[//表单提交  POST/Form Requests  Used to send an application/x-www-form-urlencoded POST request.
////                'a'=>8888,
////            ],
//
////            RequestOptions::MULTIPART => [//表单上传文件  Sending form files Sets (the body of the request to a multipart/form-data form.)
////                [
////                    'name'     => 'field_name',
////                    'contents' => 'abc'
////                ],
////                [
////                    'name'     => 'file',
////                    'contents' => \GuzzleHttp\Psr7\Utils::tryFopen(BASE_PATH . '/storage/thumbnail-1.png', 'r'),
////                    'filename' => 'thumbnail-1.png',
////                ],
////                [
////                    'name'      => 'tags',
////                    'contents'  => json_encode([
////                        "external" => [
////                            "tenantId" => 23,
////                            "author" => 34,
////                            "description" => "these are additional tags"
////                        ]
////                    ])
////                ],
////            ],
//
//                RequestOptions::JSON => [//json
//                    'aaa' => 565,
////                [
////                    'name'     => 'file',
////                    'contents' => fopen(BASE_PATH . '/storage/thumbnail-1.png', 'r'),
////                    'filename' => 'thumbnail-1.png',
////                ],
//                    [
//                        'name' => 'tags',
//                        'contents' => [
//                            "external" => [
//                                "tenantId" => 23,
//                                "author" => 34,
//                                "description" => "these are additional tags"
//                            ]
//                        ],
//                    ],
//                ],
//
//                //Cookies
//                // Use a specific cookie jar
//                //RequestOptions::COOKIES => new \GuzzleHttp\Cookie\CookieJar,// Use a specific cookie jar
//            ];
//
//            $jar = \GuzzleHttp\Cookie\CookieJar::fromArray(
//                [
//                    'some_cookie' => 'foo',
//                    'other_cookie' => 'barbaz1234'
//                ],
//                '192.168.152.128'
//            );
//            $options[RequestOptions::COOKIES] = $jar;
//
//            $cookie = $jar->getCookieByName('some_cookie');
//        $cookie->getValue(); // 'foo'
//        $cookie->getDomain(); // 'example.org'
//        $cookie->getExpires(); // expiration date as a Unix timestamp

            //$response = $client->request($method, $uri, $options);
//            $response = $client->request('GET', 'http://github.com', [
//                //'allow_redirects' => false
//            ]);

            //登录shopify
//            $options = [
//                //RequestOptions::HEADERS => $headers,
//                //RequestOptions::VERSION => '2.0',
//                //RequestOptions::BODY=>'{"store_id":"6","token":"eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJwYXRvem9uLm5ldCIsImlhdCI6MTYxNDgzOTUyMywiaWQiOiI0ODEifQ.41qlnIizkGzjII0AWwezZ5T3BAfHsJYvf-mzb6cVMfE","operator":"Jmiy_cen(岑永坚)","orderno":"","account":"","country":[],"asin":"","sku":"","type":[],"start_time":"","star":[],"end_time":"","audit_status":[],"page":1,"page_size":10,"is_psc":true}',
//                RequestOptions::FORM_PARAMS => [//表单提交  POST/Form Requests
//                    'form_type' => 'customer_login',
//                    'utf8' => '✓',
//                    'checkout_url' => '/pages/myorder',
//                    'customer[email]' => 'Jmiy_cen@patazon.net',
//                    'customer[password]' => '123456',
//                ],
//            ];
//            $uri = 'https://www.hommakstore.com/account/login';
//            $response = $client->request($method, $uri, $options);
//
//            $response = $client->request('GET', 'https://www.hommakstore.com/pages/myorder', [
//                //'allow_redirects' => false
//            ]);

            /******************Request Options https://docs.guzzlephp.org/en/stable/request-options.html **********************************/
            //###allow_redirects
//            $onRedirect = function(
//                \Psr\Http\Message\RequestInterface $request,
//                \Psr\Http\Message\ResponseInterface $response,
//                \Psr\Http\Message\UriInterface $uri
//            ) {
//                var_dump('Redirecting! ' . $request->getUri() . ' to ' . $uri) ;
//            };
//
//            $response = $client->request('GET', 'http://github.com', [
//                'allow_redirects' => [
//                    'max'             => 10,        // allow at most 10 redirects.
//                    'strict'          => true,      // use "strict" RFC compliant redirects.
//                    'referer'         => true,      // add a Referer header
//                    'protocols'       => ['https'], // only allow https URLs
//                    'on_redirect'     => $onRedirect,
//                    'track_redirects' => true
//                ]
//            ]);
//
//            var_dump($response->getStatusCode());// 200
//            var_dump($response->getHeaderLine('X-Guzzle-Redirect-History'));// http://first-redirect, http://second-redirect, etc...
//            var_dump($response->getHeaderLine('X-Guzzle-Redirect-Status-History'));// 301, 302, etc...

            //###auth  body cert Cookies
            $options = [
                //headers
                RequestOptions::HEADERS => $headers,

                //auth
                \GuzzleHttp\RequestOptions::AUTH => ['Jmiy_cen@patazon.net', '123456'],

                //allow_redirects
//                \GuzzleHttp\RequestOptions::ALLOW_REDIRECTS => [
//                    'max'             => 10,        // allow at most 10 redirects.
//                    'strict'          => true,      // use "strict" RFC compliant redirects.
//                    'referer'         => true,      // add a Referer header
//                    'protocols'       => ['https'], // only allow https URLs
//                    'on_redirect'     => $onRedirect,
//                    'track_redirects' => true
//                ],

                //body
                //\GuzzleHttp\RequestOptions::BODY => 'foo',// You can send requests that use a string as the message body.
                //\GuzzleHttp\RequestOptions::BODY => \GuzzleHttp\Psr7\Utils::tryFopen('http://httpbin.org', 'r'),// You can send requests that use a stream resource as the body.
                \GuzzleHttp\RequestOptions::BODY => \GuzzleHttp\Psr7\Utils::streamFor('contents...'),// You can send requests that use a Guzzle stream object as the body

                //cert
                //\GuzzleHttp\RequestOptions::CERT => ['/path/server.pem', 'password'],//cert

                //Cookies
                // Use a specific cookie jar
                //RequestOptions::COOKIES => new \GuzzleHttp\Cookie\CookieJar,// Use a specific cookie jar

                //connect_timeout（以秒为单位）
                //\GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => 0.0001,// Timeout if the client fails to connect to the server in 3.14 seconds.

                //###read_timeout（以秒为单位） Float describing the timeout to use when reading a streamed body
                //\GuzzleHttp\RequestOptions::READ_TIMEOUT =>0.001,

                //###timeout 请求的总超时（以秒为单位）。 使用0:无限期等待（默认:0）
                //\GuzzleHttp\RequestOptions::TIMEOUT =>3.0,// Timeout if a server does not return a response in 3.14 seconds

                //debug(注意协程版 http客户端无效：Swoole\Coroutine\Http\Client)
                //\GuzzleHttp\RequestOptions::DEBUG => \GuzzleHttp\Psr7\Utils::tryFopen(BASE_PATH . '/runtime/logs/guzzle_http_debug.log', 'a+'),//$this->container->get(StdoutLoggerInterface::class),
                //\GuzzleHttp\RequestOptions::DEBUG => true,

                //###decode_content
                //\GuzzleHttp\RequestOptions::DECODE_CONTENT=> false,

                //###delay
                //\GuzzleHttp\RequestOptions::DELAY => 5000.0,//单位：毫秒 milliseconds

                //###expect  协程环境无效
                //\GuzzleHttp\RequestOptions::EXPECT => false,//
                //\GuzzleHttp\RequestOptions::EXPECT => true,//

                //###force_ip_resolve  Set to "v4" if you want the HTTP handlers to use only ipv4 protocol or "v6" for ipv6 protocol.
                //\GuzzleHttp\RequestOptions::FORCE_IP_RESOLVE => 'v4',
                //\GuzzleHttp\RequestOptions::FORCE_IP_RESOLVE => 'v6',

                //###http_errors  Set to false to disable throwing exceptions on an HTTP protocol errors (i.e., 4xx and 5xx responses). Exceptions are thrown by default when HTTP protocol errors are encountered.
                //###Default:true
                //\GuzzleHttp\RequestOptions::HTTP_ERRORS => true,

                //###国际化域名（IDN）支持（如果有intl扩展名，则默认启用）。
                //\GuzzleHttp\RequestOptions::IDN_CONVERSION => true,

                //###on_headers (注意协程版 http客户端无效：Swoole\Coroutine\Http\Client) (当已收到响应的HTTP标头但正文尚未开始下载时调用的可调用对象。) A callable that is invoked when the HTTP headers of the response have been received but the body has not yet begun to download.
                \GuzzleHttp\RequestOptions::ON_HEADERS => function (\Psr\Http\Message\ResponseInterface $response) {
                    //var_dump($response->getHeaderLine('Content-Length'));
                    if ($response->getHeaderLine('Content-Length') > 1024 * 1024) {
                        throw new \Exception('The file is too big!');
                    }
                },

                //on_stats curl_getinfo
                \GuzzleHttp\RequestOptions::ON_STATS => function (\GuzzleHttp\TransferStats $stats) {
                    var_dump($stats->getEffectiveUri()->__toString());
                    var_dump($stats->getTransferTime());
                    var_dump($stats->getHandlerStats());//curl_getinfo  (注意：stream=true 时 使用stream 发起请求 无法获取详细的响应数据)

                    //var_dump(\GuzzleHttp\Psr7\Message::toString($stats->getRequest()));

                    $request = $stats->getRequest();
                    $data = [
                        'request_method' => $request->getMethod(),//响应状态码 200
                        'request_uri' => $request->getUri(),//响应状态码 200
                        'request_protocol' => $request->getProtocolVersion(),//协议版本
                        'request_headers' => $request->getHeaders(),
                        'request_body' => (string)$request->getBody(),//__toString(),//->getContents(),//响应body
                        'request_body' => $request->getBody()->__toString(),//->getContents(),//响应body
                    ];
                    var_dump($data);

                    if ($stats->hasResponse()) {
                        var_dump($stats->getResponse()->getStatusCode());
                    } else {
                        // Error data is handler specific. You will need to know what
                        // type of error data your handler uses before using this
                        // value.
                        var_dump($stats->getHandlerErrorData());
                    }
                },

                //###query Associative array of query string values or query string to add to the request.
                \GuzzleHttp\RequestOptions::QUERY => ['foo' => 'bar'],

                //###progress (注意协程版 http客户端无效：Swoole\Coroutine\Http\Client) (定义在完成传输进度时要调用的函数。) Defines a function to invoke when transfer progress is made.
                \GuzzleHttp\RequestOptions::PROGRESS => function (
                    $downloadTotal,
                    $downloadedBytes,
                    $uploadTotal,
                    $uploadedBytes
                ) {
                    //do something
                    var_dump(func_get_args());
                },

                //###proxy (传递字符串以指定HTTP代理，或传递数组以指定用于不同协议的不同代理。) Pass a string to specify an HTTP proxy, or an array to specify different proxies for different protocols.
                \GuzzleHttp\RequestOptions::PROXY => [
                    'http' => 'http://192.168.152.128:9501/', // Use this proxy with "http"
                    'https' => 'http://192.168.152.128:9501/', // Use this proxy with "https",
                    'no' => ['.mit.edu', 'httpbin.org']    // Don't use a proxy with these
                ],

                //###stream (设置为true可以流式传输响应，而不是预先下载所有响应。 默认：false) Set to true to stream a response rather than download it all up-front.
                //\GuzzleHttp\RequestOptions::STREAM => true,

                //###sink 指定响应正文的保存位置。
                //\GuzzleHttp\RequestOptions::SINK => BASE_PATH . '/runtime/logs/sink.log',

                //###synchronous(设置为true可以通知HTTP处理程序您打算等待响应。 这对于优化可能很有用。) Set to true to inform HTTP handlers that you intend on waiting on the response. This can be useful for optimizations.
                //\GuzzleHttp\RequestOptions::SYNCHRONOUS => true,

                //###ssl 正式验证  // Use the system's CA bundle (this is the default setting)
                //\GuzzleHttp\RtimeoutequestOptions::VERIFY => true,// Use the system's CA bundle (this is the default setting)
                //\GuzzleHttp\RequestOptions::VERIFY => '/path/to/cert.pem',// Use a custom SSL certificate on disk.
                //\GuzzleHttp\RequestOptions::VERIFY => false,// Disable validation entirely (don't do this!).

                //###version 与请求一起使用的协议版本。
                //\GuzzleHttp\RequestOptions::VERSION =>CURL_HTTP_VERSION_1_1,

            ];

            //$response = $client->request('PUT', '/put', $options);

            //$response = $client->request('GET', '/get', $options);

            $response = $client->requestAsync('GET', '/get', $options)->wait();

            //$response = $client->request('POST', '/post', $options);

//            $response = $client->request('GET', 'https://www.gstatic.cn/inputtools/js/ita/inputtools_3.js', [
//                'decode_content' => false
//            ]);

            $data = [
//            $response->hasHeader('Content-Length'),//Check if a header exists.
//            data_get($response->getHeader('Content-Length'),0),
//            $response->getHeaderLine('Content-Length'),

                //'cookie' => $jar->toArray(),
                //'some_cookie' => $cookie->toArray(),
                'response_protocol' => $response->getProtocolVersion(),//协议版本
                'response_http_code' => $response->getStatusCode(),//响应状态码 200
                'response_reason_phrase' => $response->getReasonPhrase(),//响应状态码描述 OK
                'response_headers' => $response->getHeaders(),
                'response_body' => $response->getBody()->getContents(),//响应body
            ];

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            var_dump(\GuzzleHttp\Psr7\Message::toString($e->getRequest()));
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                var_dump(\GuzzleHttp\Psr7\Message::toString($response));
            }
        }

        return $data;
    }

    /**
     * elasticsearch client
     */
    public function elasticsearch()
    {

        // 如果在协程环境下创建，则会自动使用协程版的 Handler，非协程环境下无改变
        //$builder = $this->container->get(\Hyperf\Elasticsearch\ClientBuilderFactory::class)->create();
        $builder = \Elasticsearch\ClientBuilder::create();
        if (\Swoole\Coroutine::getCid() > 0) {
            $handler = make(\Hyperf\Guzzle\RingPHP\PoolHandler::class, [
                'option' => [
                    'max_connections' => 50,
                ],
            ]);
            $builder->setHandler($handler);
        }

        $client = $builder
            ->setHosts(['http://192.168.152.128:9200'])
            ->setConnectionPool('\Elasticsearch\ConnectionPool\SniffingConnectionPool', [])//sniffingConnectionPooledit 该池是动态的。 用户提供主机的种子列表，客户端使用该列表来“嗅探”并通过使用群集状态API来发现群集的其余部分。 在从群集中添加或删除新节点时，客户端将更新其活动连接池。
            ->setSelector('\Elasticsearch\ConnectionPool\Selectors\RoundRobinSelector')//服务选择器 （RoundRobinSelector 该选择器以循环方式返回连接 默认使用该选择器）
            ->build();

        //$response = $client->info();

        /**************Stats***********************/
        // Index Stats
        // Corresponds to curl -XGET localhost:9200/_stats
//        $response = $client->indices()->stats();

        //### Corresponds to curl -XGET localhost:9200/my_index/_stats
//        $params['index'] = 'my_index';
//        $response = $client->indices()->stats($params);
//
//        //### Corresponds to curl -XGET localhost:9200/my_index1,my_index2/_stats
//        $params['index'] = array('my_index', 'my_index');
//        $response = $client->indices()->stats($params);
//
//        //### The following example shows how you can add an alias to an existing index:
//        $params['body'] = array(
//            'actions' => array(
//                array(
//                    'add' => array(
//                        'index' => 'my_index',
//                        'alias' => 'myalias'
//                    )
//                )
//            )
//        );
//        $client->indices()->updateAliases($params);
//        $params = [
//            'index' => 'myalias',
//            'id'    => 'my_id',
//            'client' => [
//                'timeout' => 10,        // ten second timeout
//                'connect_timeout' => 10,
//                //'future' => 'lazy',//客户端支持异步，批量处理请求。 通过客户端选项中的future参数，在每个请求的基础上启用（如果您的HTTP处理程序支持的话）
//            ],
//        ];
//        $response = $client->getSource($params);

        // Node Stats
        // Corresponds to curl -XGET localhost:9200/_nodes/stats
        //$response = $client->nodes()->stats();

        // Cluster Stats
        // Corresponds to curl -XGET localhost:9200/_cluster/stats
        //$response = $client->cluster()->stats();

        /*************Index management operations: https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/index_management.html ********************************/
        //### Create an index
        //## The index operations are all contained under a distinct namespace, separated from other methods that are on the root client object. As an example, let’s create a new index:
//        $params = [
//            'index' => 'my_index'
//        ];
//        // Create the index
//        $response = $client->indices()->create($params);

        //## You can specify any parameters that would normally be included in a new index creation API. All parameters that would normally go in the request body are located in the body parameter:
//        $params = [
//            'index' => 'my_index',
//            'body' => [
//                'settings' => [
//                    'number_of_shards' => 3,
//                    'number_of_replicas' => 2
//                ],
//                'mappings' => [
//                    '_source' => [
//                        'enabled' => true
//                    ],
//                    'properties' => [
//                        'first_name' => [
//                            'type' => 'keyword'
//                        ],
//                        'age' => [
//                            'type' => 'integer'
//                        ]
//                    ]
//                ]
//            ]
//        ];
//        //# Create the index with mappings and settings now
//        $response = $client->indices()->create($params);

        //### Create an index (advanced example)
        //This is a more complicated example of creating an index,
        //showing how to define analyzers, tokenizers, filters and index settings.
        //Although essentially the same as the previous example,
        //the more complicated example can be helpful for "real world" usage of the client since this particular syntax is easy to mess up.
//        $params = [
//            'index' => 'reuters',
//            'body' => [
//                'settings' => [//###顶级设置包含有关索引（分片数量等）以及分析器的配置。
//                    'number_of_shards' => 1,//分片数
//                    'number_of_replicas' => 0,//副本数
//                    'analysis' => [//###分析器 (分析嵌套在设置中，并包含标记器，过滤器，字符过滤器和分析器。)analysis is nested inside of settings, and contains tokenizers, filters, char filters and analyzers.
//                        'filter' => [//过滤器
//                            'shingle' => [
//                                'type' => 'shingle'
//                            ]
//                        ],
//                        'char_filter' => [//字符过滤器
//                            'pre_negs' => [
//                                'type' => 'pattern_replace',
//                                'pattern' => '(\\w+)\\s+((?i:never|no|nothing|nowhere|noone|none|not|havent|hasnt|hadnt|cant|couldnt|shouldnt|wont|wouldnt|dont|doesnt|didnt|isnt|arent|aint))\\b',
//                                'replacement' => '~$1 $2'
//                            ],
//                            'post_negs' => [
//                                'type' => 'pattern_replace',
//                                'pattern' => '\\b((?i:never|no|nothing|nowhere|noone|none|not|havent|hasnt|hadnt|cant|couldnt|shouldnt|wont|wouldnt|dont|doesnt|didnt|isnt|arent|aint))\\s+(\\w+)',
//                                'replacement' => '$1 ~$2'
//                            ]
//                        ],
//                        'analyzer' => [//分析器
//                            'reuters' => [
//                                'type' => 'custom',
//                                'tokenizer' => 'standard',
//                                'filter' => ['lowercase', 'stop', 'kstem']
//                            ]
//                        ]
//                    ]
//                ],
//                'mappings' => [//###映射是嵌套在设置内的另一个元素，其中包含各种类型的映射。
//                    'properties' => [
//                        'title' => [
//                            'type' => 'text',
//                            'analyzer' => 'reuters',
//                            'copy_to' => 'combined'
//                        ],
//                        'body' => [
//                            'type' => 'text',
//                            'analyzer' => 'reuters',
//                            'copy_to' => 'combined'
//                        ],
//                        'combined' => [
//                            'type' => 'text',
//                            'analyzer' => 'reuters'
//                        ],
//                        'topics' => [
//                            'type' => 'keyword'
//                        ],
//                        'places' => [
//                            'type' => 'keyword'
//                        ]
//                    ]
//                ]
//            ]
//        ];
//        //$response = $client->indices()->create($params);
//        $params = [
//            'index' => 'reuters',
//        ];
//        $response = $client->indices()->getSettings($params);
//        $response = $client->indices()->getMapping();


        //### Delete an index
        //## Deleting an index is very simple:
//        $params = [
//            'index' => 'my_index'
//        ];
//        $response = $client->indices()->delete($params);
//
//        //### PUT Settings API
//        //## The PUT Settings API allows you to modify any index setting that is dynamic:
//        $params = [
//            'index' => 'my_index',
//            'body' => [
//                'settings' => [
//                    'number_of_replicas' => 0,
//                    'refresh_interval' => -1
//                ]
//            ]
//        ];
//        $response = $client->indices()->putSettings($params);
//
//        //### GET Settings API
//        //## The GET Settings API shows you the currently configured settings for one or more indices:
//
//        //### Get settings for one index
//        $params = ['index' => 'my_index'];
//        $response = $client->indices()->getSettings($params);
//
//        //### Get settings for several indices
//        $params = [
//            'index' => [ 'my_index', 'my_index2' ]
//        ];
//        $response = $client->indices()->getSettings($params);
//
//        //### PUT Mappings API
//        //The PUT Mappings API allows you to modify or add to an existing index’s mapping.
//        // Set the index and type
//        $params = [
//            'index' => 'my_index',
//            'body' => [
//                '_source' => [
//                    'enabled' => true
//                ],
//                'properties' => [
//                    'first_name' => [
//                        'type' => 'text',
//                        'analyzer' => 'standard'
//                    ],
//                    'age' => [
//                        'type' => 'integer'
//                    ]
//                ]
//            ]
//        ];
//        //## Update the index mapping
//        $response = $client->indices()->putMapping($params);
//
//        //### GET Mappings API
//        //The GET Mappings API returns the mapping details about your indices. Depending on the mappings that you wish to retrieve, you can specify one of more indices:
//
//        //## Get mappings for all indices
//        $response = $client->indices()->getMapping();
//
//        // Get mappings in 'my_index'
//        $params = ['index' => 'my_index'];
//        $response = $client->indices()->getMapping($params);
//
//        // Get mappings for two indices
//        $params = [
//            'index' => [ 'my_index', 'my_index' ]
//        ];
//        $response = $client->indices()->getMapping($params);


        /****************Search operations https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/search_operations.html ***********************/
        //The client gives you full access to every query and parameter exposed by the REST API, following the naming scheme as much as possible. Let’s look at a few examples so you can become familiar with the syntax.

        //Match queryedit
//        Here is a standard curl for a match query:
//        curl -XGET 'localhost:9200/my_index/_search' -d '{
//            "query" : {
//                "match" : {
//                    "testField" : "abc"
//                }
//            }
//        }'

        /****************向索引为：my_index 批量添加数据，数据id由es自动生成（唯一字符串）***********************/
//        $params = [];
//        for($i = 0; $i < 100; $i++) {
//            $params['body'][] = [
//                'index' => [
//                    '_index' => 'my_index',
//                ]
//            ];
//
//            $params['body'][] = [
//                'my_field'     => 'my_value',
//                'second_field' => 'some more values',
//                'testField' => 'abc 566ppp 走到底',
//                'my_field_cn'     => '中文分词',
//            ];
//        }
//        $response = $client->bulk($params);

        //分词 精确搜索 单字段匹配
//        $params = [
//            'index' => 'my_index',
//            'body'  => [
//                'query' => [
//                    'match' => [//完全匹配 （相当于  my_field=my_value）
//                        'testField' => 'abc'//value
//                    ]
//                ]
//            ]
//        ];
//        $response = $client->search($params);//

//        //Notice how the structure and layout of the PHP array is identical to that of the JSON request body. This makes it very simple to convert JSON examples into PHP. A quick method to check your PHP array (for more complex examples) is to encode it back to JSON and check it:
//        $params = [
//            'index' => 'my_index',
//            'body'  => [
//                'query' => [
//                    'match' => [
//                        'testField' => 'abc'
//                    ]
//                ]
//            ]
//        ];
//        print_r(json_encode($params['body'])): {"query":{"match":{"testField":"abc"}}}
//
//        //Using Raw JSON
//        //Sometimes it is convenient to use raw JSON for testing purposes,
//        //or when migrating from a different system. You can use raw JSON as a string in the body, and the client detects this automatically:
//        $json = '{
//            "query" : {
//                "match" : {
//                    "testField" : "abc"
//                }
//            }
//        }';
//        $params = [
//            'index' => 'my_index',
//            'body'  => $json
//        ];
//        $response = $client->search($params);
//
//        //Search results follow the same format as Elasticsearch search response,
//        //the only difference is that the JSON response is serialized back into PHP arrays.
//        //Working with the search results is as simple as iterating over the array values:
//        $params = [
//            'index' => 'my_index',
//            'body'  => [
//                'query' => [
//                    'match' => [
//                        'testField' => 'abc'
//                    ]
//                ]
//            ]
//        ];
//        $response = $client->search($params);
//
//        $milliseconds = $results['took'];//本次查询使用的实际 单位：毫秒
//        $maxScore     = $results['hits']['max_score'];//本次查询匹配度最高的分数
//        $score = $results['hits']['hits'][0]['_score'];//本次查询第一条记录匹配度的分数
//        $doc   = $results['hits']['hits'][0]['_source'];//本次查询第一条记录数据
//
//        //### Bool Queriesedit
//        //Bool queries can be easily constructed using the client. For example, this query:
//        curl -XGET 'localhost:9200/my_index/_search' -d '{
//            "query" : {
//                "bool" : {
//                    "must": [
//                        {
//                            "match" : { "testField" : "abc" }
//                        },
//                        {
//                            "match" : { "testField2" : "xyz" }
//                        }
//                    ]
//                }
//            }
//        }'
//        //Would be structured like this (note the position of the square brackets):
//        //分词 精确搜索 多字段匹配
//        $params = [
//            'index' => 'my_index',
//            'body'  => [
//                'query' => [
//                    'bool' => [
//                        'must' => [
//                            [ 'match' => [ 'testField' => 'abc' ] ],
//                            [ 'match' => [ 'my_field_cn' => '中文' ] ],
//                        ]
//                    ]
//                ]
//            ]
//        ];
//        $response = $client->search($params);
//
//        //A more complicated exampleedit
//        //Let’s construct a slightly more complicated example: a boolean query that contains both a filter and a query. This is a very common activity in Elasticsearch queries, so it will be a good demonstration.
//        //The curl version of the query:
//        curl -XGET 'localhost:9200/my_index/_search' -d '{
//            "query" : {
//                "bool" : {
//                    "filter" : {
//                        "term" : { "testField" : "abc" }
//                    },
//                    "should" : {
//                        "match" : { "my_field_cn" : "xyz" }
//                    }
//                }
//            }
//        }'
//        //And in PHP:
//        $params = [
//            'index' => 'my_index',
//            'body'  => [
//                'query' => [
//                    'bool' => [
//                        'filter' => [
//                            'term' => [ 'testField' => 'abc' ]
//                        ],
//                        'should' => [
//                            'match' => [ 'my_field_cn' => '分词' ]
//                        ]
//                    ]
//                ]
//            ]
//        ];
//        $response = $client->search($params);
//
//        //Scrolling 应用场景是  分页  每页的数据都带上本次结果的 _scroll_id（这个id需要请求端带回），查询下一页数据时以 _scroll_id 作为起点查询，直到查询所有数据为止
//        //The scrolling functionality of Elasticsearch is used to paginate over many documents in a bulk manner, such as exporting all the documents belonging to a single user. It is more efficient than regular search because it doesn’t need to maintain an expensive priority queue ordering the documents.
//        //Scrolling works by maintaining a "point in time" snapshot of the index which is then used to page over. This window allows consistent paging even if there is background indexing/updating/deleting. First, you execute a search request with scroll enabled. This returns a "page" of documents, and a scroll_id which is used to continue paginating through the hits.

          //More details about scrolling can be found in the reference documentation.
          //This is an example which can be used as a template for more advanced operations:
//        $params = [
//            'scroll' => '30s',          // how long between scroll requests. should be small!
//            'size' => 1000,             // how many results *per shard* you want back
//            'index' => 'my_index',
////            'body' => [
////                'query' => [
////                    'match_all' => new \stdClass()
////                ]
////            ],
////            'body' => [
////                'query' => [
////                    'bool' => [
////                        'filter' => [
////                            'term' => ['testField' => 'abc']
////                        ],
////                        'should' => [
////                            'match' => ['my_field_cn' => '分词']
////                        ]
////                    ]
////                ]
////            ],
//
//            'body' => [
//                'query' => [
//                    'bool' => [
//                        'filter' => [
//                            'term' => ['my_field' => 'my_value']
//                        ],
//                        'should' => [
//                            'match' => ['second_field' => 'some more values']
//                        ]
//                    ]
//                ]
//            ],
//
//        ];
//
//        // Execute the search
//        // The response will contain the first batch of documents
//        // and a scroll_id
//        $response = $client->search($params);
//
//        // Now we loop until the scroll "cursors" are exhausted
//        while (isset($response['hits']['hits']) && count($response['hits']['hits']) > 0) {
//
//            // **
//            // Do your work here, on the $response['hits']['hits'] array
//            // **
//
//            // When done, get the new scroll_id
//            // You must always refresh your _scroll_id!  It can change sometimes
//            $scroll_id = $response['_scroll_id'];//
//
//            // Execute a Scroll request and repeat
//            $response = $client->scroll([
//                'body' => [
//                    'scroll_id' => $scroll_id,  //...using our previously obtained _scroll_id
//                    'scroll' => '30s'        // and the same timeout window
//                ]
//            ]);
//        }

        /****************Indexing documents https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/indexing_documents.html ***********************/
        //1.Single document indexing
        //When indexing a document, you can either provide an ID or let Elasticsearch generate one for you.
        //Providing an ID value.
//        $params = [
//            'index' => 'my_index',
//            'id' => 'my_id',
//            'body' => ['testField' => 'abc']
//        ];
//        $response = $client->index($params);// Document will be indexed to my_index/_doc/my_id
//
//        //Omitting an ID value.
//        $params = [
//            'index' => 'my_index',
//            'body' => ['testField' => 'abc']
//        ];
//        $response = $client->index($params);// Document will be indexed to my_index/_doc/<autogenerated ID>

        //###If you need to set other parameters, such as a routing value, you specify those in the array alongside the index, and others. For example, let’s set the routing and timestamp of this new document:
        //Additional parameters.
//        $params = [
//            'index' => 'my_index',
//            'id' => 'my_id',
//            'routing' => 'company_xyz',
//            'timestamp' => strtotime("-1d"),
//            'body' => ['testField' => 'abc']
//        ];
//        $response = $client->index($params);

        //Bulk Indexing 批量添加
        //Elasticsearch also supports bulk indexing of documents. The bulk API expects JSON action/metadata pairs, separated by newlines. When constructing your documents in PHP, the process is similar. You first create an action array object (for example, an index object), then you create a document body object. This process repeats for all your documents.
        //A simple example might look like this:
        //Bulk indexing with PHP arrays.
//        $params = [];
//        for($i = 0; $i < 100; $i++) {
//            $params['body'][] = [
//                'index' => [
//                    '_index' => 'my_index',
//                ]
//            ];
//
//            $params['body'][] = [
//                'my_field'     => 'my_value',
//                'second_field' => 'some more values',
//                'testField' => 'abc 566ppp 走到底',
//                'my_field_cn'     => '中文分词',
//            ];
//        }
//        $response = $client->bulk($params);

        //In practice, you’ll likely have more documents than you want to send in a single bulk request. In that case, you need to batch up the requests and periodically send them:
        //Bulk indexing with batches.
        //批量导入到 es 时使用以下方式
//        $params = ['body' => []];
//        for ($i = 1; $i <= 1234567; $i++) {
//            $params['body'][] = [
//                'index' => [
//                    '_index' => 'my_index',
//                    '_id'    => $i
//                ]
//            ];
//
//            $params['body'][] = [
//                'my_field'     => 'my_value',
//                'second_field' => 'some more values'
//            ];
//
//            // Every 1000 documents stop and send the bulk request
//            if ($i % 1000 == 0) {
//                $response = $client->bulk($params);
//
//                // erase the old bulk request
//                $params = ['body' => []];
//
//                // unset the bulk response when you are done to save memory
//                unset($response);
//            }
//        }
//
//        // Send the last batch if it exists
//        if (!empty($params['body'])) {
//            $response = $client->bulk($params);
//        }

        /*************Getting documents https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/getting_documents.html **********************/
//        $params = [
//            'index' => 'my_index',
//            'id'    => 'my_id'
//        ];
//
//        /**
//         * Array
//        (
//        [_index] => my_index
//        [_type] => _doc
//        [_id] => my_id
//        [_version] => 1
//        [_seq_no] => 0
//        [_primary_term] => 1
//        [found] => 1
//        [_source] => Array
//        (
//        [testField] => abc
//        )
//
//        )
//         */
//        // Get doc at /my_index/_doc/my_id
//        $response = $client->get($params);

        /**
         * If you want to retrieve the _source field directly, there is the getSource method:
        $params = [
        'index' => 'my_index',
        'id'    => 'my_id'
        ];

        $source = $client->getSource($params);
        print_r($source);
         *
        The response will be just the _source value:
        Array
        (
        [testField] => abc
        )
         */
//        $params = [
//            'index' => 'my_index',
//            'id'    => 'my_id'
//        ];
//        $response = $client->getSource($params);

        /*************Updating documents https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/updating_documents.html **********************/
        //###Updating a document allows you to either completely replace the contents of the existing document, or perform a partial update to just some fields (either changing an existing field or adding new fields).
        //Partial document updateedit
        //If you want to partially update a document (for example, change an existing field or add a new one) you can do so by specifying the doc in the body parameter. This merges the fields in doc with the existing document.
//        $params = [
//            'index' => 'my_index',
//            'id' => '1',
//            'body' => [
//                'doc' => [
//                    'new_field' => 'abc',//不要求 new_field 字段必须存在，如果存在就更新，不存在就新增 new_field 字段并且赋值为：abc
//                    'counter' => 1,
//                ]
//            ]
//        ];
//        $response = $client->update($params);// Update doc at /my_index/_doc/my_id

        //Scripted document update 使用脚本更新 如 incrementing appending a new value to an array
        //Sometimes you need to perform a scripted update,
        //such as incrementing a counter or appending a new value to an array.
        //To perform a scripted update, you need to provide a script and usually a set of parameters:
//        $params = [
//            'index' => 'my_index',
//            'id' => '1',
//            'body' => [
//                'script' => 'ctx._source.counter += 1',//(注意：counter 字段必须存在，否则会报错)
////                'script' => [//(注意：counter 字段必须存在，否则会报错)
////                    'source' => 'ctx._source.counter += params.count',
////                    'params' => [
////                        'count' => 1
////                    ],
////                ],
//            ]
//        ];
//        $response = $client->update($params);

        //###Upserts（Update or Insert 实际使用时推荐使用该方式更新，兼容新更高）
        //Upserts are "Update or Insert" operations.
        //This means an upsert attempts to run your update script, but if the document does not exist (or the field you are trying to update doesn’t exist),
        //default values are inserted instead.
//        $params = [
//            'index' => 'my_index',
//            'id' => 6,
//            'body' => [
//                'script' => [//注意当 id=6 的记录存在时，counter必须存在，否则会报空指针异常
//                    'source' => 'ctx._source.counter += params.count',
//                    'params' => [
//                        'count' => 4
//                    ],
//                ],
//                'upsert' => [
//                    'counter' => 1
//                ],
//            ]
//        ];
//        $response = $client->update($params);

        /*************Deleting documents https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/updating_documents.html **********************/
        //Finally, you can delete documents by specifying their full /index/_doc_/id path:
        $params = [
            'index' => 'my_index',
            'id' => 1
        ];

        // Delete doc at /my_index/_doc_/my_id
        $response = $client->delete($params);//删除 index=my_index 并且 id=my_id  记录

        $params = [
            'index' => 'my_index',
            'id' => 1,
        ];
        $response = $client->get($params);


        return $response;

    }

}
