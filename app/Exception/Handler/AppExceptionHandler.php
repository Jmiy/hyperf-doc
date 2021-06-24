<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io(应用异常处理程序)
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace App\Exception\Handler;

use App\Services\Monitor\MonitorServiceManager;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class AppExceptionHandler extends ExceptionHandler
{
    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function __construct(StdoutLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $this->logger->error(sprintf('%s[%s] in %s', $throwable->getMessage(), $throwable->getLine(), $throwable->getFile()));
        $this->logger->error($throwable->getTraceAsString());

//        try {
//
//            $exceptionData = static::getMessage($throwable);
//
//            //添加系统异常监控
//            $exceptionName = '系统异常：';
//            $message = data_get($exceptionData, 'message', '');
//            $code = data_get($exceptionData, 'exception_code') ? data_get($exceptionData, 'exception_code') : (data_get($exceptionData, 'http_code') ? data_get($exceptionData, 'http_code') : -101);
//            $parameters = [$exceptionName, $message, $code, data_get($exceptionData, 'file'), data_get($exceptionData, 'line'), $exceptionData];
//            MonitorServiceManager::handle('Ali', 'Ding', 'report', $parameters);
//
//        } catch (\Exception $ex) {
//        }

        return $response->withHeader('Server', 'Hyperf')->withStatus(500)->withBody(new SwooleStream('Internal Server Error.'));
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }

//    /**
//     * @param Throwable $exception
//     * @return array
//     */
    public static function getMessage(Throwable $exception, $debug = true) {

//        static::$debug = $debug;
//        $fe = FlattenException::create($exception);
//
//        $responseData = static::convertExceptionToArray($fe);

//        $traces = data_get($responseData, Constant::RESPONSE_DATA_KEY, []);
//        $depth = config('app.debug_depth', 3);
//        data_set($traces, '0.trace', array_slice(data_get($traces, '0.trace', []), 0, $depth));

        return [
            'exception_code' => $exception->getCode(),
            "http_code" => $exception->getCode(),
            'message' => $exception->getMessage(),
            'type' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'stack-trace' => $exception->getTrace(),
        ];
    }
}
