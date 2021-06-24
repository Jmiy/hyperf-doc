<?php

namespace App\Services\Payment;

use App\Services\BaseService;
use App\Services\Payment\Facades\Payment;
use App\Services\LogService;
use App\Utils\FunctionHelper;
use App\Exception\Handler\AppExceptionHandler as ExceptionHandler;

class PaymentService extends BaseService {

    public static function pay($driver, $requestData) {
        $total = data_get($requestData, 'total', 0.10);
        $orderData = [
            'total_price' => $total,
            'total_discounts' => 0,
            'total_shipping' => 0,
            'description' => '565656',
            'unique_id' => uniqid(),
            'items' => [
                [
                    'name' => 'test',
                    'currency' => 'USD',
                    'quantity' => 1,
                    'sku' => 'NONE',
                    'price' => $total,
                ],
            ]
        ];

        return Payment::driver($driver)->redirect($orderData);
    }

    /**
     * 回调
     */
    public static function callback($driver, $requestData) {

        $uniqueId = data_get($requestData, 'unique_id');
        try {
            $rs = Payment::driver($driver)->callback($requestData);
        } catch (\Exception $exc) {
            $rs = -3;
            data_set($requestData, 'exc', ExceptionHandler::getMessage($exc));
            static::logs('error', 'payment', $driver, __FUNCTION__, $requestData, $uniqueId, $rs);

            return $rs; //支付失败，支付ID【' . $paymentId . '】,支付人ID【' . $payerId . '】
        }

        static::logs('log', 'payment', $driver, __FUNCTION__, $requestData, $uniqueId, $rs);
        return $rs;
    }

    public static function notify($driver) {

        $notifyData = Payment::driver($driver)->notify();

        $uniqueId = data_get($notifyData, 'resource.invoice_number', data_get($notifyData, 'resource.transactions.0.invoice_number', '')); //商户订单id
        $state = data_get($notifyData, 'resource.state'); //陈述 事件：approved created pending

        $logData = ['log', 'payment', $driver, __FUNCTION__, $notifyData, $uniqueId, $state];
        static::logs(...$logData);

        if (empty($notifyData)) {
            return "fail";
        }

        return "success";
    }

    public static function refund($driver, $requestData) {

        $responseData = Payment::driver($driver)->refund($requestData);

        $saleId = data_get($requestData, 'sale_id'); //"09A69772C10087922";  //异步加调中拿到的id
        static::logs('log', 'payment', $driver, __FUNCTION__, $requestData, $saleId, $responseData);

        return Payment::driver($driver)->refund($requestData);
    }

}
