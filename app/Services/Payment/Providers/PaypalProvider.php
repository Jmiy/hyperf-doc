<?php

namespace App\Services\Payment\Providers;

use Hyperf\HttpServer\Contract\RequestInterface as Request;
use App\Exception\Handler\AppExceptionHandler as ExceptionHandler;
use PayPal\Api\Payer;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Details;
use PayPal\Api\Amount;
use PayPal\Api\Transaction;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Payment;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Exception\PayPalConnectionException;
use PayPal\Rest\ApiContext;
use PayPal\Api\PaymentExecution;
use PayPal\Api\Refund;
use PayPal\Api\Sale;

class PaypalProvider extends AbstractProvider implements ProviderInterface {

    protected $payPal;

    /**
     * Create a new provider instance.
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $clientId
     * @param  string  $clientSecret
     * @param  string  $currency
     * @param  string  $callbackUri
     * @param  array  $guzzle
     * @return void
     */
    public function __construct($app, Request $request, $clientId, $clientSecret, $currency, $callbackUri, $guzzle = []) {
        parent::__construct($app, $request, $clientId, $clientSecret, $currency, $callbackUri, $guzzle);
    }

    protected function getPayPal() {
        $this->payPal = new ApiContext(
                new OAuthTokenCredential(
                $this->clientId, $this->clientSecret
                )
        );

        $mode = $this->app->make('config')['services.payment.paypal.mode'];
        if ($mode == 'live') {
            //如果是沙盒测试环境不设置，请注释掉
            $this->payPal->setConfig(
                    array(
                        'mode' => 'live',
                    )
            );
        }

        return $this->payPal;
    }

    /**
     * 获取支付地址
     * @param array $orderData 订单数据
     * @return string
     */
    protected function getPayUrl($orderData) {

        //创建付款人
        $payer = new Payer();
        $payer->setPaymentMethod('paypal'); //设置支付方式
        // 设置商品信息
        $items = [];
        $itemData = data_get($orderData, 'items', []);
        foreach ($itemData as $_item) {
            //设置订单item
            $item = new Item();
            $item->setName(data_get($_item, 'name', ''))
                    ->setCurrency(data_get($_item, 'currency', $this->currency))
                    ->setQuantity(data_get($_item, 'quantity', 1))
                    ->setSku(data_get($_item, 'sku', 'NONE'))
                    ->setPrice(data_get($_item, 'price', 0));
            $items[] = $item;
        }
        $itemList = new ItemList();
        $itemList->setItems($items);

        // 设置细节
        $details = new Details();
        $details->setShipping(data_get($orderData, 'total_shipping', 0))
                ->setTax(0)
                ->setHandlingFee(0)
                ->setInsurance(0)
                ->setShippingDiscount(data_get($orderData, 'total_discounts', 0) * (-1))
                ->setSubtotal(data_get($orderData, 'total_price', 0));

        // 设置价格
        $amount = new Amount();
        $amount->setCurrency(data_get($orderData, 'currency', $this->currency))
                ->setTotal(data_get($orderData, 'total_price', 0))
                ->setDetails($details);

        //设置交易
        $invoiceNumber = data_get($orderData, 'unique_id', '');
        $transaction = new Transaction();
        $transaction->setAmount($amount)
                ->setItemList($itemList)
                ->setDescription(data_get($orderData, 'description', ''))
                ->setInvoiceNumber($invoiceNumber)
        ; //设置商户订单id
        //设置返回地址
        $redirectUrls = new RedirectUrls();
        $redirectUrls
                ->setReturnUrl($this->buildUrl($this->callbackUri, ['success' => 1, 'unique_id' => $invoiceNumber]))//设置支付回调地址
                ->setCancelUrl($this->buildUrl($this->callbackUri, ['success' => 0, 'unique_id' => $invoiceNumber])) //设置取消支付回调地址
        ;

        // 设置支付
        $payment = new Payment();
        $payment->setIntent('sale')
                ->setPayer($payer)
                ->setRedirectUrls($redirectUrls)
                ->setTransactions([$transaction])
        ;

        try {
            $this->getPayPal();
            $payment->create($this->payPal);
        } catch (PayPalConnectionException $e) {
            echo $e->getData();
            return false;
        }

        return $payment->getApprovalLink();
    }

    /**
     * 支付回调
     */
    public function callback($requestData) {

        $success = trim(data_get($requestData, 'success'));
        $paymentId = data_get($requestData, 'paymentId');
        $payerId = data_get($requestData, 'PayerID');

        $rs = 0;
        if ($success === null || $paymentId === null || $payerId === null) {
            return 0; //支付失败
        }

        if ($success === '0' && $paymentId === null && $payerId === null) {
            return -1; //取消付款
        }

        if ($success === '0') {//余额不足，支付失败
            return -2; //支付失败，支付ID【' . $paymentId . '】,支付人ID【' . $payerId . '】
        }

        $this->getPayPal();
        $payment = Payment::get($paymentId, $this->payPal);
        $execute = new PaymentExecution();
        $execute->setPayerId($payerId);
        $payment->execute($execute, $this->payPal);

        return '支付成功，支付ID【' . $paymentId . '】,支付人ID【' . $payerId . '】';

        //return 1; //'支付成功，支付ID【' . $paymentId . '】,支付人ID【' . $payerId . '】'
    }

    /**
     * 支付通知
     * @return string
     */
    public function notify() {
        //获取回调结果
        $requestData = $this->get_JsonData();
        /*
          {
          "id":"WH-14K99343GV567250F-87U77188SJ904344R",
          "event_version":"1.0",
          "create_time":"2020-08-20T08:34:26.711Z",
          "resource_type":"payment",
          "event_type":"PAYMENTS.PAYMENT.CREATED",
          "summary":"Checkout payment is created and approved by buyer",
          "resource":{
          "update_time":"2020-08-20T08:34:26Z",
          "create_time":"2020-08-20T08:33:31Z",
          "redirect_urls":{
          "return_url":"https://testapidev.patozon.net/api/payment/paypal/callback?success=1&amp;unique_id=5f3e355a57267&amp;paymentId=PAYID-L47DKXA09836001436423120",
          "cancel_url":"https://testapidev.patozon.net/api/payment/paypal/callback?success=0&amp;unique_id=5f3e355a57267"
          },
          "links":[
          {
          "href":"https://api.sandbox.paypal.com/v1/payments/payment/PAYID-L47DKXA09836001436423120",
          "rel":"self",
          "method":"GET"
          },
          {
          "href":"https://api.sandbox.paypal.com/v1/payments/payment/PAYID-L47DKXA09836001436423120/execute",
          "rel":"execute",
          "method":"POST"
          },
          {
          "href":"https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&amp;token=EC-09W85227W9702301G",
          "rel":"approval_url",
          "method":"REDIRECT"
          }
          ],
          "id":"PAYID-L47DKXA09836001436423120",
          "state":"created",
          "transactions":[
          {
          "amount":{
          "total":"0.10",
          "currency":"USD",
          "details":{
          "subtotal":"0.10",
          "tax":"0.00",
          "shipping":"0.00",
          "insurance":"0.00",
          "handling_fee":"0.00",
          "shipping_discount":"0.00"
          }
          },
          "payee":{
          "merchant_id":"QUHSJHF8RHY5J",
          "email":"sb-zkbhs2954724@business.example.com"
          },
          "description":"description",
          "invoice_number":"5f3e355a57267",
          "item_list":{
          "items":[
          {
          "name":"test",
          "sku":"NONE",
          "price":"0.10",
          "currency":"USD",
          "quantity":1
          }
          ],
          "shipping_address":{
          "recipient_name":"Doe John",
          "line1":"NO 1 Nan Jin Road",
          "city":"Shanghai",
          "state":"Shanghai",
          "postal_code":"200000",
          "country_code":"C2",
          "default_address":false,
          "preferred_address":false,
          "primary_address":false,
          "disable_for_transaction":false
          }
          },
          "related_resources":[

          ]
          }
          ],
          "intent":"sale",
          "payer":{
          "payment_method":"paypal",
          "status":"VERIFIED",
          "payer_info":{
          "email":"sb-pl25u2945120@personal.example.com",
          "first_name":"John",
          "last_name":"Doe",
          "payer_id":"KBZAWNR4BXDYW",
          "shipping_address":{
          "recipient_name":"Doe John",
          "line1":"NO 1 Nan Jin Road",
          "city":"Shanghai",
          "state":"Shanghai",
          "postal_code":"200000",
          "country_code":"C2",
          "default_address":false,
          "preferred_address":false,
          "primary_address":false,
          "disable_for_transaction":false
          },
          "country_code":"C2"
          }
          },
          "cart":"09W85227W9702301G"
          },
          "links":[
          {
          "href":"https://api.sandbox.paypal.com/v1/notifications/webhooks-events/WH-14K99343GV567250F-87U77188SJ904344R",
          "rel":"self",
          "method":"GET"
          },
          {
          "href":"https://api.sandbox.paypal.com/v1/notifications/webhooks-events/WH-14K99343GV567250F-87U77188SJ904344R/resend",
          "rel":"resend",
          "method":"POST"
          }
          ]
          }

          {
          "id":"WH-6EF57946T62424504-8J3437476W447713H",
          "event_version":"1.0",
          "create_time":"2020-08-20T09:13:33.632Z",
          "resource_type":"payment",
          "event_type":"PAYMENTS.PAYMENT.CREATED",
          "summary":"Checkout payment is created and approved by buyer",
          "resource":{
          "update_time":"2020-08-20T09:13:18Z",
          "create_time":"2020-08-20T09:12:02Z",
          "links":[
          {
          "href":"https://api.sandbox.paypal.com/v1/payments/payment/PAYID-L47D4YQ55N38595XX586752V",
          "rel":"self",
          "method":"GET"
          }
          ],
          "id":"PAYID-L47D4YQ55N38595XX586752V",
          "state":"approved",
          "transactions":[
          {
          "amount":{
          "total":"0.20",
          "currency":"USD",
          "details":{
          "subtotal":"0.20",
          "tax":"0.00",
          "shipping":"0.00",
          "insurance":"0.00",
          "handling_fee":"0.00",
          "shipping_discount":"0.00"
          }
          },
          "description":"description",
          "invoice_number":"5f3e3e5eb5ed8",
          "item_list":{
          "items":[
          {
          "name":"test",
          "sku":"NONE",
          "price":"0.20",
          "currency":"USD",
          "tax":"0.00",
          "quantity":1
          }
          ],
          "shipping_address":{
          "recipient_name":"John Doe",
          "line1":"NO 1 Nan Jin Road",
          "city":"Shanghai",
          "state":"Shanghai",
          "postal_code":"200000",
          "country_code":"C2",
          "default_address":false,
          "preferred_address":false,
          "primary_address":false,
          "disable_for_transaction":false
          }
          },
          "related_resources":[
          {
          "sale":{
          "id":"09A69772C10087922",
          "state":"pending",
          "amount":{
          "total":"0.20",
          "currency":"USD",
          "details":{
          "subtotal":"0.20",
          "tax":"0.00",
          "shipping":"0.00",
          "insurance":"0.00",
          "handling_fee":"0.00",
          "shipping_discount":"0.00"
          }
          },
          "payment_mode":"INSTANT_TRANSFER",
          "reason_code":"UNILATERAL",
          "protection_eligibility":"INELIGIBLE",
          "parent_payment":"PAYID-L47D4YQ55N38595XX586752V",
          "create_time":"2020-08-20T09:13:18Z",
          "update_time":"2020-08-20T09:13:18Z",
          "links":[
          {
          "href":"https://api.sandbox.paypal.com/v1/payments/sale/09A69772C10087922",
          "rel":"self",
          "method":"GET"
          },
          {
          "href":"https://api.sandbox.paypal.com/v1/payments/sale/09A69772C10087922/refund",
          "rel":"refund",
          "method":"POST"
          },
          {
          "href":"https://api.sandbox.paypal.com/v1/payments/payment/PAYID-L47D4YQ55N38595XX586752V",
          "rel":"parent_payment",
          "method":"GET"
          }
          ]
          }
          }
          ]
          }
          ],
          "intent":"sale",
          "payer":{
          "payment_method":"paypal",
          "status":"VERIFIED",
          "payer_info":{
          "email":"sb-pl25u2945120@personal.example.com",
          "first_name":"John",
          "last_name":"Doe",
          "payer_id":"KBZAWNR4BXDYW",
          "shipping_address":{
          "recipient_name":"John Doe",
          "line1":"NO 1 Nan Jin Road",
          "city":"Shanghai",
          "state":"Shanghai",
          "postal_code":"200000",
          "country_code":"C2",
          "default_address":false,
          "preferred_address":false,
          "primary_address":false,
          "disable_for_transaction":false
          },
          "phone":"2135419982",
          "country_code":"C2"
          }
          },
          "cart":"86A75396GC834552V"
          },
          "links":[
          {
          "href":"https://api.sandbox.paypal.com/v1/notifications/webhooks-events/WH-6EF57946T62424504-8J3437476W447713H",
          "rel":"self",
          "method":"GET"
          },
          {
          "href":"https://api.sandbox.paypal.com/v1/notifications/webhooks-events/WH-6EF57946T62424504-8J3437476W447713H/resend",
          "rel":"resend",
          "method":"POST"
          }
          ]
          }

          {
          "id":"WH-2RF92759BW737664E-31H43979Y07279900",
          "event_version":"1.0",
          "create_time":"2020-08-20T09:13:34.129Z",
          "resource_type":"sale",
          "event_type":"PAYMENT.SALE.PENDING",
          "summary":"Payment pending for $ 0.2 USD",
          "resource":{
          "reason_code":"UNILATERAL",
          "parent_payment":"PAYID-L47D4YQ55N38595XX586752V",
          "amount":{
          "total":"0.20",
          "currency":"USD",
          "details":{
          "subtotal":"0.20"
          }
          },
          "payment_mode":"INSTANT_TRANSFER",
          "update_time":"2020-08-20T09:13:18Z",
          "create_time":"2020-08-20T09:13:18Z",
          "protection_eligibility":"INELIGIBLE",
          "links":[
          {
          "method":"GET",
          "rel":"self",
          "href":"https://api.sandbox.paypal.com/v1/payments/sale/09A69772C10087922"
          },
          {
          "method":"POST",
          "rel":"refund",
          "href":"https://api.sandbox.paypal.com/v1/payments/sale/09A69772C10087922/refund"
          },
          {
          "method":"GET",
          "rel":"parent_payment",
          "href":"https://api.sandbox.paypal.com/v1/payments/payment/PAYID-L47D4YQ55N38595XX586752V"
          }
          ],
          "id":"09A69772C10087922",
          "state":"pending",
          "invoice_number":"5f3e3e5eb5ed8"
          },
          "links":[
          {
          "href":"https://api.sandbox.paypal.com/v1/notifications/webhooks-events/WH-2RF92759BW737664E-31H43979Y07279900",
          "rel":"self",
          "method":"GET"
          },
          {
          "href":"https://api.sandbox.paypal.com/v1/notifications/webhooks-events/WH-2RF92759BW737664E-31H43979Y07279900/resend",
          "rel":"resend",
          "method":"POST"
          }
          ]
          }


         */
//        $eventId = data_get($requestData, 'id'); //Event ID
//        $invoice = $uniqueId = data_get($requestData, 'resource.invoice_number'); //商户订单id
//        $state = data_get($requestData, 'resource.state'); //陈述 事件：approved created pending
//        $txnId = data_get($requestData, 'resource.id'); //approved|created：PAYID-L47DKXA09836001436423120（callback.paymentId:支付id）; pending|completed:6GF93551XB192292P(付款id|退款id)
//        $total = data_get($requestData, 'resource.amount.total');
//        $status = data_get($requestData, 'status');

        return $requestData;
    }

    public function get_JsonData() {
        $json = file_get_contents('php://input');
        if ($json) {
            //$json = str_replace("'", '', $json);
            $json = json_decode($json, true);
        }
        return $json;
    }

    /**
     * 退款
     * @return type
     */
    public function refund($refundData) {

        $responseData = [];

        $saleId = data_get($refundData, 'sale_id'); //"09A69772C10087922";  //异步加调中拿到的id

        if (empty($saleId)) {
            $responseData['refundedSale'] = null;
            return $responseData; // 退款完成
        }

        $total = data_get($refundData, 'total', 0);
        $currency = data_get($refundData, 'currency', 'USD');

        try {

            $amt = new Amount();
            $amt->setCurrency($currency)
                    ->setTotal($total);  // 退款的费用

            $refund = new Refund();
            $refund->setAmount($amt);

            $sale = new Sale();
            $sale->setId($saleId);

            $this->getPayPal();

            $refundedSale = $sale->refund($refund, $this->payPal);
            $responseData['refundedSale'] = $refundedSale;
        } catch (\Exception $exc) {
            data_set($responseData, 'exc', ExceptionHandler::getMessage($exc));
            $responseData['refundedSale'] = false; // PayPal无效退款
        }

        return $responseData; // 退款完成
    }

}
