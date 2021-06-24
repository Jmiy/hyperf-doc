<?php

namespace App\Middleware;

use App\Utils\FunctionHelper;
use Carbon\Carbon;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\Utils\Arr;
use App\Constants\Constant;
use App\Services\ReportLogService;
use App\Services\CustomerInfoService;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Hyperf\Utils\Context;


use App\Utils\Services\QueueService;
use App\Processes\CustomProcess;

class RequestMiddleware implements MiddlewareInterface
{

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        $serverParams = $request->getServerParams();
        $uri = data_get($serverParams,'request_uri');//$request->getRequestUri();

        if(false !== stripos($uri, '/favicon.ico')){
            return $handler->handle($request);
        }

        $requestData = $request->getParsedBody()
            +$request->getQueryParams()
//            +$request->getCookieParams()
//            +$request->getUploadedFiles()
//            +$request->getServerParams()
//            +$request->getAttributes()
//            +$request->getHeaders()
        ;

        //var_dump($requestData,$request->getHeaderLine('X-Shopify-Hmac-Sha256'));
        /**
         * "Hyperf\HttpServer\Router\Dispatched" => array:3 [▼
//                "status" => 1
//                "handler" => array:3 [▼
//                    "callback" => array:2 [▼
//                        0 => "App\Controller\DocController"
//                        1 => "encrypt"
//                    ]
//                    "route" => "/api/shop/encrypt[/{id:\d+}]"
//                    "options" => array:4 [▼
//                        "middleware" => []
//                        "as" => "test_user"
//                        "validator" => array:3 [▼
//                            "type" => "test"
//                            "messages" => []
//                            "rules" => []
//                        ]
//                        "nolog" => "test_nolog"
//                    ]
//                ]
//                "params" => array:1 [▼
//                    "id" => "996"
//                ]
//            ]
         */
        $routeInfo = $request->getAttribute(Dispatched::class);

        if(empty($request->getUploadedFiles())){//如果不是上传文件，就把原始请求体记录到请求数据中
            data_set($requestData, 'requestBodyContents', $request->getBody()->getContents(), false);
        }

        $routeParameters = data_get($routeInfo, 'params', Constant::PARAMETER_ARRAY_DEFAULT);//获取通过路由传递的参数
        foreach ($routeParameters as $routeKey => $routeParameter) {
            if (!(Arr::has($requestData, $routeKey))) {//如果 input 请求参数没有 $routeKey 对应的参数，就将 $routeKey 对应的参数设置到 input 参数中以便后续统一通过 input 获取
                if ($routeKey == 'data') {
                    $_data = decrypt($routeParameter);
                    $_data = json_decode($_data, true);
                    foreach ($_data as $key => $value) {
                        data_set($requestData, $key, $value, false);
                    }
                } else {
                    data_set($requestData, $routeKey, $routeParameter, false);
                }
            }
        }

        if (Arr::has($requestData, 'email')) {
            data_set($requestData, Constant::DB_TABLE_ACCOUNT, data_get($requestData, 'email', ''), false);
        }

        if (FunctionHelper::getClientIP() == '47.254.95.132' && Arr::has($requestData, Constant::DB_TABLE_ACCOUNT)) {//如果是mpow社区过来的请求，账号统一清空账号的换行符和前后空格
            data_set($requestData, Constant::DB_TABLE_ACCOUNT, trim(data_get($requestData, Constant::DB_TABLE_ACCOUNT, '')));//清空账号的换行符
        }

        /**
         * 订单是否存在接口 使用 order_no 作为订单key，请求流水和响应流水 是从 orderno 获取订单号的，
         * 所以当且仅当有 order_no 没有 orderno 时，就使用order_no 作为 orderno的值
         */
        if (Arr::has($requestData, 'order_no') && !Arr::has($requestData, 'orderno')) {
            data_set($requestData, Constant::DB_TABLE_ORDER_NO, data_get($requestData, 'order_no', ''), false);
        }

        if (false === stripos($uri, '/api/admin/')) {//如果是前端api，就进行一下处理
            if (!Arr::has($requestData, 'source')) {
                data_set($requestData, 'source', data_get($routeInfo, 'handler.options.source', 1), false);
            }

            $ip = ('production' == config('app.env', 'production')) ? FunctionHelper::getClientIP() : data_get($requestData, Constant::DB_TABLE_IP, FunctionHelper::getClientIP());

            data_set($requestData, Constant::DB_TABLE_IP, $ip);

            if (!(Arr::has($requestData, Constant::DB_TABLE_COUNTRY))) {//has 方法将确定是否所有指定值都存在
                $country = FunctionHelper::getCountry($ip);
                data_set($requestData, Constant::DB_TABLE_COUNTRY, $country);
            }
        }

        //设置时区
        $storeId = data_get($requestData, Constant::DB_TABLE_STORE_ID, 0);
        $actId = data_get($requestData, Constant::DB_TABLE_ACT_ID, Constant::PARAMETER_INT_DEFAULT);
        FunctionHelper::setTimezone($storeId);

        //记录请求日志
        $noLog = data_get($routeInfo, 'handler.options.noLog', []);
        if ($noLog) {
            foreach ($noLog as $key) {
                if (isset($requestData[$key])) {
                    unset($requestData[$key]);
                }
            }
        }

        //设置请求唯一标识
        $requestMark = FunctionHelper::randomStr(10);
        data_set($requestData, Constant::REQUEST_MARK, $requestMark);

        //处理请求头数据兼容传统的http请求头
        $headers = $request->getHeaders();
        $serverParams = $request->getServerParams();
        $headerServerMapping = [
            'x-real-ip' => 'REMOTE_ADDR',
            'x-real-port' => 'REMOTE_PORT',
            'server-protocol' => 'SERVER_PROTOCOL',
            'server-name' => 'SERVER_NAME',
            'server-addr' => 'SERVER_ADDR',
            'server-port' => 'SERVER_PORT',
            'scheme' => 'REQUEST_SCHEME',
        ];
        foreach ($headers as $key => $value) {
            $value = is_array($value) ? implode('', array_unique(array_filter($value))) : $value;
            // Fix client && server's info
            if (isset($headerServerMapping[$key])) {
                $serverParams[$headerServerMapping[$key]] = $value;
            } else {
                $key = str_replace('-', '_', $key);
                $key = false === stripos(strtolower($key), 'http_') ? 'http_' . $key : $key;
                $serverParams[$key] = $value;
            }
        }
        $headerData = array_change_key_case($serverParams, CASE_UPPER);
        data_set($requestData, Constant::REQUEST_HEADER_DATA, $headerData);

        //获取客户端数据
        $deviceType = 1;
        $agent = agent();
        switch (true) {
            case $agent->isMobile():
                $deviceType = 1;

                break;

            case $agent->isTablet():
                $deviceType = 2;

                break;

            case $agent->isDesktop():
                $deviceType = 3;

                break;

            default:
                break;
        }

        $isRobot = $agent->isRobot() ? 1 : 0;
        $languages = $agent->languages();
        $clientData = [
            Constant::DEVICE => $agent->device(), //设备信息
            Constant::DEVICE_TYPE => $deviceType, // 设备类型 1:手机 2：平板 3：桌面
            Constant::DB_TABLE_PLATFORM => $agent->platform(), //系统信息
            Constant::PLATFORM_VERSION => $agent->version($agent->platform()), //系统版本
            Constant::BROWSER => $agent->browser(), // 浏览器信息  (Chrome, IE, Safari, Firefox, ...)
            Constant::BROWSER_VERSION => $agent->version($agent->browser()), // 浏览器版本
            Constant::LANGUAGES => is_array($languages) ? json_encode($languages, JSON_UNESCAPED_UNICODE) : $languages, // 语言 ['nl-nl', 'nl', 'en-us', 'en']
            Constant::IS_ROBOT => $isRobot, //是否是机器人
            Constant::DB_TABLE_UPDATED_MARK => $requestMark,//请求标识
        ];
        data_set($requestData, Constant::CLIENT_DATA, $clientData);

        $service = '\App\Services\LogService';
        $method = 'addAccessLog';
        $action = data_get($requestData, 'account_action', data_get($routeInfo, 'handler.options.account_action', ''));
        data_set($requestData, 'account_action', $action);

        //设置客户访问url
        $fromUrl = data_get($requestData, Constant::CLIENT_ACCESS_URL, (data_get($headerData,'HTTP_REFERER','no')));

        data_set($requestData, Constant::CLIENT_ACCESS_URL, $fromUrl);

        $account = data_get($requestData, Constant::DB_TABLE_ACCOUNT, data_get($requestData, 'help_account', data_get($requestData, 'operator', '')));
        $cookies = data_get($requestData, 'account_cookies', '').'';
        $ip = FunctionHelper::getClientIP(data_get($requestData, Constant::DB_TABLE_IP, null));
        $apiUrl = $uri;
        $createdAt = data_get($requestData, 'created_at', Carbon::now()->toDateTimeString());
        $extId = data_get($requestData, 'id', 0);
        $extType = data_get($requestData, 'ext_type', '');

        $parameters = [$action, $storeId, $actId, $fromUrl, $account, $cookies, $ip, $apiUrl, $createdAt, $extId, $extType, $requestData];//

        $_parameters = [
            'apiUrl' => $apiUrl,
            'storeId' => $storeId,
            Constant::DB_TABLE_ACCOUNT => $account,
            'createdAt' => $createdAt,
        ];

        $queueConnection = config('app.log_queue');
        $extData = [
            'queueConnectionName' => $queueConnection,//Queue Connection
            //'queue' => config('async_queue.' . $queueConnection . '.channel'),//Queue Name
            //'delay' => 1,//任务延迟执行时间  单位：秒
        ];

        $logTaskData = FunctionHelper::getJobData($service, $method, $parameters, $requestData, $extData);//
        $taskData = [
            $logTaskData
        ];
        if ($storeId && $account) {
            $taskData[] = FunctionHelper::getJobData(CustomerInfoService::getNamespaceClass(), 'updateLastlogin', [$_parameters], $requestData, $extData);
        }

        //通过进程间通讯，把写入日志的任务交给自定义进程处理
        $processData = FunctionHelper::getJobData(QueueService::class, 'pushQueue', [$taskData], []);//
        CustomProcess::write($processData);

        data_set($requestData, 'isRequestLog', 1); //设置已经记录过请求流水
        data_set($requestData, Constant::CLIENT_ACCESS_API_URI, $apiUrl, false);

        $report = data_get($routeInfo, 'handler.options.report');
        if ($report) {
            if (!(Arr::has($requestData, Constant::ACTION_TYPE))) {//如果 input 请求参数没有 Constant::ACTION_TYPE 对应的参数，就将 Constant::ACTION_TYPE 对应的参数设置到 input 参数中以便后续统一通过 input 获取
                $actionType = data_get($routeInfo, 'handler.options.' . Constant::ACTION_TYPE);
                data_set($requestData, Constant::ACTION_TYPE, $actionType, false);
            }
            ReportLogService::handle($requestData);
        }

        $request = $request->withParsedBody($requestData);
        Context::set('http.request.parsedData', array_merge($request->getParsedBody(), $request->getQueryParams()));
        $request = Context::set(ServerRequestInterface::class, $request);
        //$request = Context::set(ServerRequestInterface::class, $request->withParsedBody($requestData));

        //设置 协程上下文请求数据
        Context::set(Constant::CONTEXT_REQUEST_DATA, $requestData);

        unset($requestData);

        return $handler->handle($request);
    }

}
