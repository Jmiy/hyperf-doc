<?php

namespace App\Middleware\Translator;

use App\Services\DictStoreService;
use App\Services\Platform\OrderService;
use App\Constants\Constant;
use App\Utils\Response;
use App\Utils\FunctionHelper;
use Hyperf\Utils\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Contract\TranslatorInterface;

class LangMiddleware
{

    /**
     * @Inject
     * @var TranslatorInterface
     */
    private $translator;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        // 只在当前请求或协程生命周期有效
        $this->translator->setLocale(FunctionHelper::getCountry());

        $serverParams = $request->getServerParams();
        $requestUri = data_get($serverParams, 'request_uri');//$request->getRequestUri();

        $requestData = $request->getParsedBody();

        $storeId = data_get($requestData, Constant::DB_TABLE_STORE_ID, Constant::PARAMETER_INT_DEFAULT);
        $orderNo = data_get($requestData, Constant::DB_TABLE_ORDER_NO, Constant::PARAMETER_STRING_DEFAULT);

        if (!empty($orderNo) && is_string($orderNo)) {

            if (!FunctionHelper::checkOrderNo($orderNo)) {
                return Response::json(...Response::getResponseData(Response::getDefaultResponseData(39006)));
            }

            $countries = DictStoreService::getByTypeAndKey($storeId, 'lang', 'country', true); //国家
            $interfaceLangList = DictStoreService::getListByType($storeId, 'interface_lang'); //接口

            if (!empty($countries) && $interfaceLangList->isNotEmpty()) {
                $countries = explode(',', $countries);
                if (!empty($countries) && $interfaceLangList->firstWhere('conf_value', $requestUri)) {

                    $orderData = OrderService::getOrderData($orderNo, '', Constant::PLATFORM_SERVICE_AMAZON, $storeId);
                    if (data_get($orderData, Constant::RESPONSE_CODE_KEY, 0) != 1) {
                        return Response::json(...Response::getResponseData(Response::getDefaultResponseData(30002)));
                    }

                    $orderItemData = data_get($orderData, Constant::RESPONSE_DATA_KEY . Constant::LINKER . 'items', []);
                    if (empty($orderItemData)) {
                        return Response::json(...Response::getResponseData(Response::getDefaultResponseData(30001)));
                    }

                    $localeCountry = 'US';
                    $country = data_get(current($orderItemData), Constant::DB_TABLE_ORDER_COUNTRY, Constant::PARAMETER_STRING_DEFAULT);
                    $country = strtoupper($country);
                    if (in_array($country, $countries)) {
                        $localeCountry = $country;
                    }

                    $this->translator->setLocale(strtolower($localeCountry));

                    data_set($requestData, 'order_data', $orderData);
                    $request = $request->withParsedBody($requestData);
                    Context::set('http.request.parsedData', array_merge($request->getParsedBody(), $request->getQueryParams()));

                    $request = Context::set(ServerRequestInterface::class, $request);

                    return $handler->handle($request);
                }
            }
        }

        return $handler->handle($request);

    }

}
