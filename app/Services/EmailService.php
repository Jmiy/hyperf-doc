<?php

/**
 * 邮件服务
 * User: Jmiy
 * Date: 2019-05-16
 * Time: 16:50
 */

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Hyperf\Utils\Arr;
use Carbon\Carbon;
use App\Mail\Coupon;
use App\Mail\OrderReview;
use App\Mail\ActivateCustomer;
use App\Services\CouponService;
use App\Utils\FunctionHelper;
use App\Utils\PublicValidator;
use App\Mail\PublicMail;
use App\Services\Traits\GetDefaultConnectionModel;
use App\Constants\Constant;
use App\Exception\Handler\AppExceptionHandler as ExceptionHandler;

class EmailService extends BaseService {

    use GetDefaultConnectionModel;

    public static $statusData = [
        0 => '未发送',
        1 => '发送',
        2 => '失败',
        3 => '同ip不发送',
    ];

    /**
     * 检查是否存在
     * @param int $storeId
     * @param string $country
     * @param array $where
     * @param bool $getData 是否获取数据 true:是 false:否 默认:false
     * @return mixed|boolean|array
     */
    public static function exists($storeId = 0, $country = '', $where = Constant::PARAMETER_ARRAY_DEFAULT, $getData = false) {
        return static::existsOrFirst($storeId, $country, $where, $getData);
    }

    /**
     * 获取邮件配置数据
     * @param int $storeId
     * @param string $from
     * @param string $fromName
     * @param string $subject
     * @param array $extData
     * @return array 邮件配置数据  [
      'host' => $host, //发送邮件服务器host
      'port' => $port, //发送邮件服务器port
      Constant::ENCRYPTION => $encryption, //发送邮件加密方式
      Constant::USERNAME => $username, //发送邮件服务器账号
      Constant::DB_TABLE_PASSWORD => $password, //发送邮件服务器密码
      Constant::DB_EXECUTION_PLAN_FROM => $from, //发件人邮箱
      'fromName' => $fromName, //发件人名称
      Constant::SUBJECT => $subject, //邮件主题
      ]
     */
    public static function getEmailConfig($storeId, $from = '', $fromName = '', $subject = '', $extData = Constant::PARAMETER_ARRAY_DEFAULT) {

        $host = null; //发送邮件服务器host
        $port = null; //发送邮件服务器port
        $encryption = null; //发送邮件加密方式
        $username = null; //发送邮件服务器账号
        $password = null; //发送邮件服务器密码
        $from = $from ? $from : ''; //发件人邮箱
        $fromName = $fromName ? $fromName : ''; //发件人名称
        //获取活动配置数据
        $actId = data_get($extData, Constant::ACT_ID, 0);
        $activityConfigData = Constant::PARAMETER_ARRAY_DEFAULT;
        if ($actId) {//获取活动邮件配置数据
            $type = data_get($extData, Constant::ACTIVITY_CONFIG_TYPE, Constant::DB_TABLE_EMAIL);
            $activityConfigData = ActivityService::getActivityConfigData($storeId, $actId, $type);

            //挑选发送次数最少的邮箱, by roy_qiu
            $activityConfigData = EmailStatisticsService::handleEmailConfigs($storeId, $activityConfigData, $extData);
        }

        if ($activityConfigData) {
            $host = data_get($activityConfigData, $type . '_host.value', null);
            $port = data_get($activityConfigData, $type . '_port.value', null);
            $encryption = data_get($activityConfigData, $type . '_encryption.value', null);
            $username = data_get($activityConfigData, $type . '_username.value', null);
            $password = data_get($activityConfigData, $type . '_password.value', null);
            $from = $from ? $from : data_get($activityConfigData, $type . '_from.value', null);
            $fromName = $fromName ? $fromName : data_get($activityConfigData, $type . '_from_name.value', $from);
        } else {//获取系统字典邮件配置数据
            $type = data_get($extData, Constant::STORE_DICT_TYPE, Constant::DB_TABLE_EMAIL);
            $orderby = 'sorts asc';
            $keyField = 'conf_key';
            $valueField = 'conf_value';
            $emailConfig = DictStoreService::getListByType($storeId, $type, $orderby, $keyField, $valueField);
            if ($emailConfig->isEmpty() && $type != Constant::DB_TABLE_EMAIL) {
                $type = Constant::DB_TABLE_EMAIL;
                $emailConfig = DictStoreService::getListByType($storeId, $type, $orderby, $keyField, $valueField);
            }

            $host = data_get($emailConfig, 'host', null);
            $port = data_get($emailConfig, 'port', null);
            $encryption = data_get($emailConfig, Constant::ENCRYPTION, null);
            $username = data_get($emailConfig, Constant::USERNAME, null);
            $password = data_get($emailConfig, Constant::DB_TABLE_PASSWORD, null);
            $from = $from ? $from : data_get($emailConfig, Constant::DB_EXECUTION_PLAN_FROM, null);
            $fromName = $fromName ? $fromName : data_get($emailConfig, 'from_name', $from);
        }

        return [
            'host' => $host !== null ? $host : config('mail.host'), //发送邮件服务器host
            'port' => $port !== null ? $port : config('mail.port'), //发送邮件服务器port
            Constant::ENCRYPTION => $encryption !== null ? $encryption : config('mail.encryption'), //发送邮件加密方式
            Constant::USERNAME => $username !== null ? $username : config('mail.username'), //发送邮件服务器账号
            Constant::DB_TABLE_PASSWORD => $password !== null ? $password : config('mail.password'), //发送邮件服务器密码
            Constant::DB_EXECUTION_PLAN_FROM => $from !== null ? $from : config('mail.from.address'), //发件人邮箱
            'fromName' => $fromName !== null ? $fromName : config('mail.from.address'), //发件人名称
            Constant::SUBJECT => $subject, //邮件主题
        ];
    }

    /**
     * 初始化邮件
     * @param int $storeId 商城id
     * @param obj $mailable 邮件对象
     * @param string $from  发送者邮箱
     * @param string $fromName 发送者邮箱
     * @param string $subject  邮件主题
     * @param string $view  邮件模板名称
     * @param string $viewData 邮件模板数据
     * @param array $extData 扩展数据
     * @return obj $mailable
     */
    public static function init($storeId, $mailable, $from, $fromName = '', $subject = '', $view = 'emails.coupon.default', $viewData = Constant::PARAMETER_ARRAY_DEFAULT, $extData = Constant::PARAMETER_ARRAY_DEFAULT) {
        try {

            $emailConfig = static::getEmailConfig($storeId, $from, $fromName, $subject, $extData);
            $host = data_get($emailConfig, 'host', null); //发送邮件服务器host
            $port = data_get($emailConfig, 'port', null); //发送邮件服务器port
            $encryption = data_get($emailConfig, Constant::ENCRYPTION, null); //发送邮件加密方式
            $username = data_get($emailConfig, Constant::USERNAME, null); //发送邮件服务器账号
            $password = data_get($emailConfig, Constant::DB_TABLE_PASSWORD, null); //发送邮件服务器密码
            $from = data_get($emailConfig, Constant::DB_EXECUTION_PLAN_FROM, null); //发件人邮箱
            $fromName = data_get($emailConfig, 'fromName', null); //发件人名称
            $subject = data_get($emailConfig, Constant::SUBJECT, ''); //邮件主题

            $swiftMailer = $mailable->getMailService()->getSwiftMailer();
            $transport = $swiftMailer->getTransport()
                ->setHost(config('mail.host'))
                ->setPort(config('mail.port'))
                ->setEncryption(config('mail.encryption'))
                ->setUsername(config('mail.username'))
                ->setPassword(config('mail.password'))
            ;

            if ($host) {//发送邮件服务器host
                $transport->setHost($host);
            }

            if ($port) {//发送邮件服务器port
                $transport->setPort($port);
            }

            if ($encryption) {//发送邮件加密方式
                $transport->setEncryption($encryption);
            }

            if ($username) {//发送邮件服务器账号
                $transport->setUsername($username);
            }

            if ($password) {//发送邮件服务器密码
                $transport->setPassword($password);
            }

            if ($mailable) {

                if ($from) {
                    $mailable->from($from, $fromName);
                }

                if ($subject) {
                    $mailable->subject($subject); //邮件的标题
                }

                $cc = data_get($extData, 'cc', Constant::PARAMETER_ARRAY_DEFAULT); //抄送
                if ($cc) {
                    $mailable->cc($cc); //Set the recipients of the message.
                }

                $replyTo = data_get($extData, 'replyTo', Constant::PARAMETER_ARRAY_DEFAULT); //回复人
                if ($replyTo) {
                    $mailable->replyTo(data_get($replyTo, 'address', ''), data_get($replyTo, 'name', null)); //Set the "reply to" address of the message.
                }

                $mailable->view($view)//邮件的模板
                        ->with($viewData)
                ; //邮件的内容
                //https://learnku.com/docs/laravel/5.8/mail/3920#sending-mail
//                $mailable->from($from, $fromName)
//                        ->subject($subject)//邮件的标题
//                        ->view($view)//邮件的模板
//                        ->with($viewData)
//                        ->attach('/path/to/file', [//附加文件到消息
//                    'as' => 'name.pdf',
//                    'mime' => 'application/pdf',
//                ])
//                         ->attachFromStorage('/path/to/file', 'name.pdf', [//从磁盘中添加附件
//                   'mime' => 'application/pdf'
//               ]);;
//                ; //邮件的内容
                //更新邮件发送数, by roy_qiu
                EmailStatisticsService::updateSendNums($storeId, $emailConfig, $extData);
            }
        } catch (\Exception $exc) {
            LogService::addSystemLog(Constant::LEVEL_ERROR, 'init_email', __METHOD__, '初始化邮件失败', json_encode(func_get_args(), JSON_UNESCAPED_UNICODE) . '==>' . $exc->getTraceAsString()); //添加系统日志
        }

        return $mailable ? $mailable : null;
    }

    /**
     * 邮件发送
     * @param $data [username pwd from to subject content attach]
     * @return int
     */
    public static function send($to_email, $message, $data) {

        $result = true;
        try {
            Mail::to($to_email)->send($message);

            //延迟消息队列#
            //
            //想要延迟发送队列化的邮件消息，可以使用 later 方法。later 方法的第一个参数的第一个参数是标示消息何时发送的 DateTime 实例
//            $when = now()->addMinutes(10);
//            Mail::to($to_email)
//                ->cc($moreUsers)
//                ->bcc($evenMoreUsers)
//                ->later($when, $message);

        } catch (\Exception $exc) {
            $result = false;
            LogService::addSystemLog(Constant::LEVEL_ERROR, 'send_email', __METHOD__, '邮件发送失败', json_encode($data, JSON_UNESCAPED_UNICODE) . '==>' . $exc->getTraceAsString()); //添加系统日志
        }
        return $result;
    }

    /**
     * 优惠券邮件
     * @param $storeId
     * @param $data
     * @return int
     */
    public static function sendCouponEmailBak($storeId, $data) {

        $result = ['code' => 1, 'msg' => '', Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT];

        $to_email = data_get($data, Constant::DB_TABLE_ACCOUNT, '');
        $validatorData = [
            Constant::TO_EMAIL => $to_email,
        ];
        $rules = [
            Constant::TO_EMAIL => 'required|email',
        ];
        $validator = PublicValidator::handle($validatorData, $rules, Constant::PARAMETER_ARRAY_DEFAULT, 'couponEmail');
        if ($validator !== true) {//如果验证没有通过就提示用户
            return $validator->getData(true);
        }

        switch (true) {
            case (false !== stripos($to_email, 'qq.com'))://如果是qq邮箱，就不发送优惠券邮件
                return $result;

                break;
            case (false !== stripos($to_email, '163.com'))://如果是163邮箱，就不发送优惠券邮件
                return $result;

                break;
            case (false !== stripos($to_email, '139.com'))://如果是139邮箱，就不发送优惠券邮件
                return $result;

                break;

            default:
                break;
        }

        $coupon_country = strtoupper(data_get($data, Constant::DB_TABLE_COUNTRY, ''));
        if (strtoupper($coupon_country) == 'CN') {//如果是中国的用户，统一不发送优惠券
            return $result;
        }

        $countryMap = data_get(CouponService::$countryMap, $storeId, Constant::PARAMETER_ARRAY_DEFAULT);
        $coupon_country = data_get($countryMap, $coupon_country, data_get($countryMap, 'OTHER', $coupon_country));

        //同一个ip下，时间最先注册的那个发送coupon，其他的不发
        $source = data_get($data, 'source', '');
        if ($source != 6) {//如果不是定时任务同步的，就要根据ip限制发送
            $ip = FunctionHelper::getClientIP(data_get($data, Constant::DB_TABLE_IP));
            $emialLogWhere = [
                Constant::DB_TABLE_STORE_ID => $storeId,
                'type' => Constant::COUPON,
                'ip' => $ip,
            ];
        } else {
            $emialLogWhere = [
                Constant::DB_TABLE_STORE_ID => $storeId,
                'type' => Constant::COUPON,
                Constant::TO_EMAIL => $to_email,
            ];
        }
        $isExists = static::exists($storeId, $coupon_country, $emialLogWhere);

        $coupons = collect(Constant::PARAMETER_ARRAY_DEFAULT);
        $remark = $data[Constant::DB_TABLE_REMARK] ?? '';
        $status = 0;
        if (!$isExists) {
            $coupons = CouponService::getRegCoupon($storeId, $coupon_country, data_get($data, 'customer_id', ''), $to_email);
            if ($coupons->isEmpty()) {//如果没有优惠券，提示用户 优惠券 不足
                $result['code'] = 151;
                $result['msg'] = 'low stocks';

                LogService::addSystemLog(Constant::LEVEL_ERROR, Constant::DB_TABLE_EMAIL, Constant::COUPON, $data[Constant::DB_TABLE_COUNTRY] . ' coupon库存不足'); //添加系统日志
                static::sendToAdmin($storeId, 'coupon库存不足', '官网：' . $storeId . ' ' . $coupon_country . ' coupon 库存不足'); //发送 邮件 提醒管理员

                return $result;
            }
        } else {
            $remark .= ($source != 6 ? ('同一个ip:' . $ip . ' 时间最先注册的那个发送coupon，其他的不发') : ('同一个to_email:' . $to_email . ' 只发一次coupon'));
            $status = $source != 6 ? 3 : 5;

            $result['code'] = $status;
            $result['msg'] = $remark;
        }

        //记录发送日志
        $ctime = isset($data[Constant::DB_TABLE_OLD_CREATED_AT]) && $data[Constant::DB_TABLE_OLD_CREATED_AT] ? Carbon::parse($data[Constant::DB_TABLE_OLD_CREATED_AT])->toDateTimeString() : Carbon::now()->toDateTimeString();
        $extData = [Constant::STORE_DICT_TYPE => Constant::STORE_DICT_TYPE_EMAIL_COUPON];
        $emailConfig = static::getEmailConfig($storeId, '', '', '', $extData); //获取邮件配置数据
        $from = data_get($emailConfig, Constant::DB_EXECUTION_PLAN_FROM, '');
        $emialHistory = Arr::collapse([
                    $emialLogWhere,
                    [
                        Constant::DB_EXECUTION_PLAN_GROUP => $data[Constant::DB_EXECUTION_PLAN_GROUP] ?? Constant::CUSTOMER,
                        Constant::DB_TABLE_COUNTRY => $coupon_country,
                        Constant::DB_TABLE_FROM_EMAIL => $from,
                        Constant::TO_EMAIL => $to_email,
                        Constant::DB_TABLE_CONTENT => '',
                        Constant::DB_TABLE_EXTINFO => json_encode($coupons->pluck('code')->all()),
                        Constant::DB_TABLE_STATUS => $status,
                        Constant::DB_TABLE_OLD_CREATED_AT => $ctime,
                        Constant::DB_TABLE_REMARK => $remark,
                        Constant::DB_TABLE_ROW_STATUS => 1,
                    ]
        ]);
        $emailModel = static::getModel($storeId, $coupon_country);
        $id = $emailModel->insertGetId($emialHistory); //添加邮件流水

        if (!$isExists) {

            /*             * **************发送邮件 start ***************************** */
            $data[Constant::DB_TABLE_COUNTRY] = $coupon_country;
            $data['coupons'] = $coupons;
            $data = Arr::collapse([$extData, $data]);
            $message = new Coupon($data);
            $is_send = static::send($to_email, $message, $data);
            /*             * **************发送邮件 end   ***************************** */

            $status = 1; //更新邮件流水状态为发送成功
            if (empty($is_send)) {
                $status = 2;
                $result['code'] = 0;
                $result['msg'] = 'send email false';
                LogService::addSystemLog(Constant::LEVEL_ERROR, Constant::DB_TABLE_EMAIL, Constant::COUPON, $data[Constant::DB_TABLE_ACCOUNT], 'send fail');
            }

            $attributes = [
                Constant::DB_TABLE_CONTENT => $message->render(),
                Constant::DB_TABLE_STATUS => $status,
            ];
            $emailModel->where('id', $id)->update($attributes);
        }

        return $result;
    }

    /**
     * 优惠券邮件
     * @param $storeId
     * @param $data
     * @return int
     */
    public static function sendCouponEmail($storeId, $data) {

        $result = ['code' => 1, 'msg' => '', Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT];

        $to_email = data_get($data, Constant::DB_TABLE_ACCOUNT, '');
        $validatorData = [
            Constant::TO_EMAIL => $to_email,
        ];
        $rules = [
            Constant::TO_EMAIL => 'required|email',
        ];
        $validator = PublicValidator::handle($validatorData, $rules, Constant::PARAMETER_ARRAY_DEFAULT, 'couponEmail');
        if ($validator !== true) {//如果验证没有通过就提示用户
            return $validator->getData(true);
        }

        switch (true) {
            case (false !== stripos($to_email, 'qq.com'))://如果是qq邮箱，就不发送优惠券邮件
                return $result;

                break;
            case (false !== stripos($to_email, '163.com'))://如果是163邮箱，就不发送优惠券邮件
                return $result;

                break;
            case (false !== stripos($to_email, '139.com'))://如果是139邮箱，就不发送优惠券邮件
                return $result;

                break;

            default:
                break;
        }

        $coupon_country = strtoupper(data_get($data, Constant::DB_TABLE_COUNTRY, ''));
        if (strtoupper($coupon_country) == 'CN') {//如果是中国的用户，统一不发送优惠券
            return $result;
        }

        $countryMap = data_get(CouponService::$countryMap, $storeId, Constant::PARAMETER_ARRAY_DEFAULT);
        $coupon_country = data_get($countryMap, $coupon_country, data_get($countryMap, 'OTHER', $coupon_country));

        //同一个ip下，时间最先注册的那个发送coupon，其他的不发
        $source = data_get($data, 'source', '');
        $ip = FunctionHelper::getClientIP(data_get($data, Constant::DB_TABLE_IP));
        $isIpLimitWhitelist = false; //是否为白名单 true:是  false:否 默认:false
        if ($source != 6) {//如果不是定时任务同步的，就要根据ip限制发送
            $emialLogWhere = [
                Constant::DB_TABLE_STORE_ID => $storeId,
                'type' => Constant::COUPON,
                'ip' => $ip,
            ];

            $ipLimitWhitelist = DictService::getByTypeAndKey('signup', 'ip_limit_whitelist', true); //获取注册ip白名单
            if (!empty($ipLimitWhitelist)) {
                $ipLimitWhitelist = explode(',', $ipLimitWhitelist);
                foreach ($ipLimitWhitelist as $value) {
                    if (false !== strpos($ip, $value)) {
                        $isIpLimitWhitelist = true;
                        break;
                    }
                }
            }
        } else {
            $emialLogWhere = [
                Constant::DB_TABLE_STORE_ID => $storeId,
                'type' => Constant::COUPON,
                Constant::TO_EMAIL => $to_email,
            ];
        }

        $isExists = false;
        if (!$isIpLimitWhitelist) {//如果不是白名单，就根据ip限制注册的会员数
            $isExists = static::exists($storeId, $coupon_country, $emialLogWhere);
        }

        $remark = $data[Constant::DB_TABLE_REMARK] ?? '';
        $status = 0;
        if ($isExists) {
            $remark .= ($source != 6 ? ('同一个ip:' . $ip . ' 时间最先注册的那个发送coupon，其他的不发') : ('同一个to_email:' . $to_email . ' 只发一次coupon'));
            $status = $source != 6 ? 3 : 5;

            $result['code'] = $status;
            $result['msg'] = $remark;
        }

        $service = static::getNamespaceClass();
        $method = 'handle'; //邮件处理
        $group = data_get($data, Constant::DB_EXECUTION_PLAN_GROUP, Constant::CUSTOMER);
        $type = Constant::COUPON;
        $extId = '';
        $extType = '';
        $extService = CouponService::getNamespaceClass();
        $extMethod = 'getEmailData'; //获取审核邮件数据
        $actId = data_get($data, Constant::ACT_ID, data_get($data, 'act_id', 0));
        data_set($data, Constant::DB_TABLE_COUNTRY, $coupon_country);
        data_set($data, 'isExists', $isExists);
        data_set($data, 'emailStatus', $status);
        //data_set($data, Constant::STORE_DICT_TYPE, Constant::STORE_DICT_TYPE_EMAIL_COUPON);
        $extParameters = [$storeId, $actId, $data];
        $extData = Arr::collapse([[
                Constant::STORE_DICT_TYPE => Constant::STORE_DICT_TYPE_EMAIL_COUPON,
                Constant::ACT_ID => $actId,
                    ], FunctionHelper::getJobData($extService, $extMethod, $extParameters)]); //扩展数据
        $parameters = [$storeId, $to_email, $group, $type, $remark, $extId, $extType, $extData];

        FunctionHelper::pushQueue(FunctionHelper::getJobData($service, $method, $parameters));

        return $result;
    }

    /**
     * 给管理员发邮件
     * @param $storeId
     * @param $title
     * @param string $content
     */
    public static function sendToAdmin($storeId, $title, $content = '') {

        $to_email = config('mail.mail_data.to.admin_email', '');

        $validatorData = [
            Constant::TO_EMAIL => $to_email,
        ];
        $rules = [
            Constant::TO_EMAIL => 'required|email',
        ];
        $validator = PublicValidator::handle($validatorData, $rules, Constant::PARAMETER_ARRAY_DEFAULT, 'adminEmail');
        if ($validator !== true) {//如果验证没有通过就提示用户
            return $validator->getData(true);
        }

        $type = Constant::STORE_DICT_TYPE_EMAIL_COUPON;
        $conf_key = 'cc';
        $onlyValue = true;
        $cc = DictStoreService::getByTypeAndKey($storeId, $type, $conf_key, $onlyValue);
        $data = [
            Constant::DB_EXECUTION_PLAN_STOREID => $storeId,
            Constant::SUBJECT => $title . '-' . config('app.env', 'production'),
            Constant::DB_TABLE_CONTENT => $content,
            'cc' => $cc ? explode(',', $cc) : Constant::PARAMETER_ARRAY_DEFAULT,
        ];

        $message = new PublicMail($data);
        return static::send($to_email, $message, $data);
    }

    /**
     * 用户激活邮件
     * @param int $storeId  商店id
     * @param int $customerId 会员id
     * @param string $toEmail 收件人邮件
     * @param string $code 激活码
     * @param string $inviteCode 邀请码
     * @param string $country 国家
     * @param string $orderno 订单
     * @param string $ip 会员ip
     * @param string $remark 备注
     * @param string $createdAt 记录创建时间
     * @param string $extId 扩展id
     * @param int $handleActivate 是否处理激活 1:是  0:否  默认:1
     * @return array
     */
    public static function sendActivateEmail($storeId, $customerId, $toEmail, $code, $inviteCode, $country = '', $orderno = '', $ip = '', $remark = '', $createdAt = '', $extId = 0, $handleActivate = 1, $extData = Constant::PARAMETER_ARRAY_DEFAULT) {

        $result = [
            'code' => 1,
            'msg' => '',
        ];

        $validatorData = [
            Constant::TO_EMAIL => $toEmail,
        ];
        $rules = [
            Constant::TO_EMAIL => 'required|email',
        ];
        $validator = PublicValidator::handle($validatorData, $rules, Constant::PARAMETER_ARRAY_DEFAULT, 'activateEmail');
        if ($validator !== true) {//如果验证没有通过就提示用户
            return $validator->getData(true);
        }

        //记录邮件流水
        $emailConfig = static::getEmailConfig($storeId, '', '', '', $extData); //获取邮件配置数据
        $fromEmail = data_get($emailConfig, Constant::DB_EXECUTION_PLAN_FROM, '');
        $now_at = Carbon::now()->toDateTimeString();
        $emialHistory = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_EXECUTION_PLAN_GROUP => Constant::CUSTOMER,
            'type' => 'activate',
            Constant::DB_TABLE_COUNTRY => $country,
            Constant::DB_TABLE_FROM_EMAIL => $fromEmail,
            Constant::TO_EMAIL => $toEmail,
            Constant::DB_TABLE_CONTENT => '',
            Constant::DB_TABLE_EXTINFO => json_encode(func_get_args()),
            Constant::DB_TABLE_STATUS => data_get($extData, Constant::DB_TABLE_STATUS, 0),
            Constant::DB_TABLE_REMARK => $remark,
            'ip' => FunctionHelper::getClientIP($ip),
            Constant::DB_TABLE_ROW_STATUS => data_get($extData, 'rowStatus', 1),
            Constant::DB_TABLE_OLD_CREATED_AT => data_get($extData, 'created_at', $now_at),
            Constant::DB_TABLE_EXT_ID => $extId, //关联id
            'ext_type' => data_get($extData, 'extType', ''), //关联模型
        ];

        $emailModel = static::getModel($storeId, '');
        $id = $emailModel->insertGetId($emialHistory); //添加邮件流水

        $isSendEmail = data_get($extData, 'isSendEmail', true); //是否发送邮件  true：发送 false：不发送  默认：true
        if (!$isSendEmail) {
            data_set($result, 'msg', "Don't send mail");
            return $result;
        }

        /*         * **************发送邮件 start ***************************** */
        $data = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            'customer_id' => $customerId,
            Constant::DB_TABLE_ACCOUNT => $toEmail,
            'code' => $code,
            'invite_code' => $inviteCode,
            'orderno' => $orderno,
            Constant::DB_TABLE_COUNTRY => $country,
            Constant::DB_TABLE_EXT_ID => $extId,
            'handleActivate' => $handleActivate,
        ];
        $data = Arr::collapse([$data, $extData]);
        $url = route('customer_activate_get', [Constant::RESPONSE_DATA_KEY => encrypt(json_encode($data))]);
        $replacePairs = [
            'http://' => 'https://',
        ];
        data_set($data, 'url', strtr($url, $replacePairs));
        data_set($data, 'extData', $extData);
        $message = new ActivateCustomer($data);
        $is_send = static::send($toEmail, $message, $data);
        /*         * **************发送邮件 end   ***************************** */

        $status = 1; //更新邮件流水状态为发送成功
        if (empty($is_send)) {
            $status = 2;
            $result['code'] = 0;
            $result['msg'] = 'send email false';
            LogService::addSystemLog(Constant::LEVEL_ERROR, Constant::DB_TABLE_EMAIL, 'ActivateEmail', $toEmail, 'send fail');
        }

        //更新邮件流水
        $attributes = [
            Constant::DB_TABLE_CONTENT => $message->render(),
            Constant::DB_TABLE_STATUS => $status,
        ];
        $emailModel->where('id', $id)->update($attributes);

        return $result;
    }

    /**
     * 评论审核邮件
     * @param $storeId
     * @param $data
     * @return int
     */
    public static function sendReviewEmail($storeId, $data, $review_status, $review_credit) {

        $result = ['code' => 1, 'msg' => '', Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT];

        $to_email = data_get($data, Constant::DB_TABLE_ACCOUNT, '');
        $validatorData = [
            Constant::TO_EMAIL => $to_email,
        ];
        $rules = [
            Constant::TO_EMAIL => 'required|email',
        ];
        $validator = PublicValidator::handle($validatorData, $rules, Constant::PARAMETER_ARRAY_DEFAULT, 'reviewEmail');
        if ($validator !== true) {//如果验证没有通过就提示用户
            return $validator->getData(true);
        }

        //记录发送日志
        $ctime = isset($data[Constant::DB_TABLE_OLD_CREATED_AT]) && $data[Constant::DB_TABLE_OLD_CREATED_AT] ? Carbon::parse($data[Constant::DB_TABLE_OLD_CREATED_AT])->toDateTimeString() : Carbon::now()->toDateTimeString();
        $fromEmail = DictStoreService::getByTypeAndKey($storeId, Constant::DB_TABLE_EMAIL, Constant::DB_EXECUTION_PLAN_FROM, true); //发送的邮箱
        $emialHistory = [
            Constant::DB_TABLE_STORE_ID => $storeId,
            Constant::DB_EXECUTION_PLAN_GROUP => $data[Constant::DB_EXECUTION_PLAN_GROUP],
            'type' => 'check',
            Constant::DB_TABLE_COUNTRY => $data[Constant::DB_TABLE_COUNTRY],
            Constant::DB_TABLE_FROM_EMAIL => $fromEmail,
            Constant::TO_EMAIL => $to_email,
            Constant::DB_TABLE_CONTENT => '',
            Constant::DB_TABLE_EXTINFO => json_encode(func_get_args()),
            Constant::DB_TABLE_STATUS => 0,
            Constant::DB_TABLE_OLD_CREATED_AT => $ctime,
            Constant::DB_TABLE_REMARK => $data[Constant::DB_TABLE_REMARK],
            Constant::DB_TABLE_IP => FunctionHelper::getClientIP(data_get($data, Constant::DB_TABLE_IP)),
        ];
        $emailModel = static::getModel($storeId, '');
        $id = $emailModel->insertGetId($emialHistory); //添加邮件流水

        /*         * **************发送邮件 start ***************************** */
        $data[Constant::DB_TABLE_FROM_EMAIL] = $fromEmail;
        $data['review_credit'] = $review_credit;
        $data['review_status'] = $review_status;
        $message = new OrderReview($data); //选择发送的邮件模板
        $is_send = static::send($to_email, $message, $data); //发送邮件
        /*         * **************发送邮件 end   ***************************** */

        $status = 1; //更新邮件流水状态为发送成功
        if (empty($is_send)) {
            $status = 2;
            $result['code'] = 0;
            $result['msg'] = 'send email false';
            LogService::addSystemLog(Constant::LEVEL_ERROR, Constant::DB_TABLE_EMAIL, 'review', $data[Constant::DB_TABLE_ACCOUNT], 'send fail');
        }

        $attributes = [
            Constant::DB_TABLE_CONTENT => $message->render(),
            Constant::DB_TABLE_STATUS => $status,
        ];
        $emailModel->where('id', $id)->update($attributes); //更新邮件流水

        return $result;
    }

    /**
     * 执行回调
     * @param array $callBack 要执行的回调数据
     * @return boolean
     */
    public static function doCallBack($callBack = Constant::PARAMETER_ARRAY_DEFAULT) {
        foreach ($callBack as $item) {
            $service = data_get($item, Constant::SERVICE_KEY, '');
            $method = data_get($item, Constant::METHOD_KEY, '');
            $parameters = data_get($item, Constant::PARAMETERS_KEY, Constant::PARAMETER_ARRAY_DEFAULT);
            if (method_exists($service, $method)) {
                $service::{$method}(...$parameters);
            }
        }

        return true;
    }

    /**
     * 邮件处理
     * @param int $storeId  商店id
     * @param int $customerId 会员id
     * @param string $toEmail 收件人邮件
     * @param string $remark 备注
     * @param string $extId 扩展id
     * @param string $extType 扩展关联模型
     * @param array $extData 扩展数据
     * @return string
     */
    public static function handle($storeId, $toEmail, $group = '', $type = '', $remark = '', $extId = 0, $extType = '', $extData = Constant::PARAMETER_ARRAY_DEFAULT) {

        $callBack = data_get($extData, 'callBack', Constant::PARAMETER_ARRAY_DEFAULT);
        $service = data_get($extData, Constant::SERVICE_KEY, '');
        $method = data_get($extData, Constant::METHOD_KEY, '');
        $parameters = data_get($extData, Constant::PARAMETERS_KEY, Constant::PARAMETER_ARRAY_DEFAULT);
        $completeMethod = $service . '::' . $method;

        $result = [
            'code' => 1,
            'msg' => '',
            Constant::METHOD_KEY => __METHOD__,
            Constant::PARAMETERS_KEY => func_get_args(),
            'ext_method' => $completeMethod,
            'ext_parameters' => $parameters,
        ];

        $logSubtype = __METHOD__ . FunctionHelper::randomStr(8);
        LogService::addSystemLog('log', Constant::LOG_TYPE_EMAIL_DEBUG, $logSubtype, $completeMethod, $result);

        $validatorData = [
            Constant::TO_EMAIL => $toEmail,
        ];
        $rules = [
            Constant::TO_EMAIL => 'required|email',
        ];
        $validator = PublicValidator::handle($validatorData, $rules, Constant::PARAMETER_ARRAY_DEFAULT, __FUNCTION__);
        if ($validator !== true) {//如果验证没有通过就提示用户
            $_result = $validator->getData(true);

            data_set($result, 'code', data_get($_result, 'code', 0));
            data_set($result, 'msg', data_get($_result, 'msg', 0));
            static::doCallBack($callBack);
            LogService::addSystemLog('log', Constant::LOG_TYPE_EMAIL_DEBUG, $logSubtype, $completeMethod, $result);

            return $result;
        }

        try {

            if (!method_exists($service, $method)) {

                data_set($result, 'code', 9900000000);
                data_set($result, 'msg', $completeMethod . ' undefined.');
                static::doCallBack($callBack);
                LogService::addSystemLog('log', Constant::LOG_TYPE_EMAIL_DEBUG, $logSubtype, $completeMethod, $result);

                return $result;
            }

            $data = $service::{$method}(...$parameters);
            data_set($result, 'ext_reture_data', $data);
            if (empty($data)) {
                data_set($result, 'code', 9900000001);
                data_set($result, 'msg', $completeMethod . ' return empty.');
                static::doCallBack($callBack);
                LogService::addSystemLog('log', Constant::LOG_TYPE_EMAIL_DEBUG, $logSubtype, $completeMethod, $result);

                return $result;
            }

            $code = data_get($data, 'code', 0);
            $msg = data_get($data, 'msg', 0);
            if ($code != 1) {
                data_set($result, 'code', $code);
                data_set($result, 'msg', $msg);
                static::doCallBack($callBack);
                LogService::addSystemLog('log', Constant::LOG_TYPE_EMAIL_DEBUG, $logSubtype, $completeMethod, $result);

                return $result;
            }

            //记录邮件日志
            $emailConfig = static::getEmailConfig($storeId, '', '', '', $extData); //获取邮件配置数据
            $fromEmail = data_get($emailConfig, Constant::DB_EXECUTION_PLAN_FROM, '');
            $status = data_get($data, 'emailStatus', 0);
            $actId = data_get($extData, Constant::ACT_ID, 0);
            $emialHistory = [
                Constant::DB_TABLE_STORE_ID => $storeId,
                Constant::DB_EXECUTION_PLAN_GROUP => $group,
                'type' => $type,
                Constant::DB_TABLE_COUNTRY => data_get($data, Constant::DB_TABLE_COUNTRY, ''),
                Constant::DB_TABLE_FROM_EMAIL => $fromEmail,
                Constant::TO_EMAIL => $toEmail,
                Constant::DB_TABLE_CONTENT => data_get($data, Constant::DB_TABLE_CONTENT, ''),
                Constant::DB_TABLE_EXTINFO => json_encode(data_get($data, Constant::DB_TABLE_EXTINFO, func_get_args())),
                Constant::DB_TABLE_STATUS => $status,
                Constant::DB_TABLE_OLD_CREATED_AT => Carbon::now()->toDateTimeString(),
                Constant::DB_TABLE_REMARK => $remark,
                Constant::DB_TABLE_IP => FunctionHelper::getClientIP(data_get($data, Constant::DB_TABLE_IP)),
                Constant::DB_TABLE_EXT_ID => $extId, //关联id
                'ext_type' => $extType, //关联模型
                Constant::DB_TABLE_ROW_STATUS => $status > 2 ? 0 : 1, //记录状态 1:有效 0:无效
                'act_id' => $actId,
            ];
            $emailModel = static::getModel($storeId, '');

            $modelClass = get_class($emailModel);
            $requestMark = data_get($data, 'requestData.request_mark', '');
            if (defined($modelClass . '::CREATED_MARK') && $modelClass::CREATED_MARK) {
                data_set($emialHistory, $modelClass::CREATED_MARK, $requestMark, false);
            }

            if (defined($modelClass . '::UPDATED_MARK') && $modelClass::UPDATED_MARK) {
                data_set($emialHistory, $modelClass::UPDATED_MARK, $requestMark, false);
            }

            $id = $emailModel->insertGetId($emialHistory);

            $isSendEmail = data_get($data, 'isSendEmail', true); //是否发送邮件  true：发送 false：不发送  默认：true
            if (!$isSendEmail) {
                data_set($result, 'msg', $completeMethod . '===>控制不发送邮件');
                static::doCallBack($callBack);
                LogService::addSystemLog('log', Constant::LOG_TYPE_EMAIL_DEBUG, $logSubtype, $completeMethod, $result);

                return $result;
            }

            /*             * **************发送邮件 start ***************************** */
            $data = Arr::collapse([$extData, $data]);
            $message = new PublicMail($data);

            $queueConnection = config('queue.mail_queue_connection');
            $message->onConnection($queueConnection)->onQueue(config('queue.connections.' . $queueConnection . '.queue'));
            $is_send = static::send($toEmail, $message, $extData);
            /*             * **************发送邮件 end   ***************************** */

            $status = 1; //更新邮件流水状态为发送成功
            if (empty($is_send)) {
                $status = 2;
                data_set($result, 'code', 80000);
                data_set($result, 'msg', 'send email false');
            }

            //更新邮件流水
            $attributes = [
                Constant::DB_TABLE_STATUS => $status,
            ];
            if (defined($modelClass . '::UPDATED_MARK') && $modelClass::UPDATED_MARK) {
                data_set($attributes, $modelClass::UPDATED_MARK, $requestMark, false);
            }
            $emailModel->where('id', $id)->update($attributes);
        } catch (\Exception $exc) {
            data_set($result, 'code', 80001);
            data_set($result, 'exc', ExceptionHandler::getMessage($exc));

            LogService::addSystemLog(Constant::LEVEL_ERROR, 'email_error', $logSubtype, $completeMethod, $result); //添加系统日志
        }

        data_set($result, 'exc', '');
        static::doCallBack($callBack);

        return $result;
    }

    /**
     * 获取db query
     * @param array $where
     * @return \Illuminate\Database\Query\Builder|static $query
     */
    public static function getQuery($storeId = 0, $country = '', $where = Constant::PARAMETER_ARRAY_DEFAULT) {
        return static::getModel($storeId, $country)->buildWhere($where);
    }

    /**
     * 获取公共参数
     * @param array $params 请求参数
     * @return array
     */
    public static function getPublicData($params, $order = Constant::PARAMETER_ARRAY_DEFAULT) {

        $where = Constant::PARAMETER_ARRAY_DEFAULT;

        $storeId = data_get($params, Constant::DB_TABLE_STORE_ID, 0);
        $account = data_get($params, Constant::DB_TABLE_ACCOUNT, '');
        $startTime = data_get($params, 'start_time', '');
        $endTime = data_get($params, 'end_time', '');
        $country = data_get($params, Constant::DB_TABLE_COUNTRY, 0);

        if ($storeId) {//商城ID
            $where[] = [Constant::DB_TABLE_STORE_ID, '=', $storeId];
        }

        if ($account) {//账号
            $where[] = [Constant::TO_EMAIL, '=', $account];
        }

        if ($startTime) {//开始时间
            $where[] = [Constant::DB_TABLE_OLD_CREATED_AT, '>=', $startTime];
        }

        if ($endTime) {//结束时间
            $where[] = [Constant::DB_TABLE_OLD_CREATED_AT, '<=', $endTime];
        }

        if ($country) {//国家简写
            $where[] = [Constant::DB_TABLE_COUNTRY, '=', $country];
        }

        $_where = Constant::PARAMETER_ARRAY_DEFAULT;
        if (data_get($params, 'id', 0)) {
            $_where['id'] = $params['id'];
        }

        if ($where) {
            $_where[] = $where;
        }

        $order = $order ? $order : ['id', 'desc'];
        return Arr::collapse([parent::getPublicData($params, $order), [
                        Constant::DB_EXECUTION_PLAN_WHERE => $_where,
        ]]);
    }

    /**
     * 列表
     * @param $params
     * @param bool $toArray
     * @param bool $isPage
     * @param array $select
     * @param boolean $isRaw 是否原始 select
     * @param boolean $isGetQuery 是否获取 query
     * @return array|\Hyperf\Database\Model\Builder
     */
    public static function getListData($params, $toArray = true, $isPage = true, $select = Constant::PARAMETER_ARRAY_DEFAULT, $isRaw = false, $isGetQuery = false, $isOnlyGetCount = false) {

        $_data = static::getPublicData($params);

        $where = data_get($_data, Constant::DB_EXECUTION_PLAN_WHERE, Constant::PARAMETER_ARRAY_DEFAULT);
        $order = data_get($params, 'orderBy', data_get($_data, 'order', Constant::PARAMETER_ARRAY_DEFAULT));
        $pagination = data_get($_data, Constant::DB_EXECUTION_PLAN_PAGINATION, Constant::PARAMETER_ARRAY_DEFAULT);
        $limit = data_get($params, Constant::DB_EXECUTION_PLAN_LIMIT, data_get($pagination, 'page_size', 10));
        $offset = data_get($params, Constant::DB_EXECUTION_PLAN_OFFSET, data_get($pagination, Constant::DB_EXECUTION_PLAN_OFFSET, 0));

        $select = $select ? $select : ['id', Constant::TO_EMAIL, Constant::DB_TABLE_COUNTRY, 'type', Constant::DB_TABLE_EXTINFO, Constant::DB_TABLE_STATUS, Constant::DB_TABLE_REMARK, Constant::DB_TABLE_OLD_CREATED_AT];
        $dbExecutionPlan = [
            Constant::DB_EXECUTION_PLAN_PARENT => [
                Constant::DB_EXECUTION_PLAN_SETCONNECTION => true,
                Constant::DB_EXECUTION_PLAN_STOREID => 0,
                Constant::DB_EXECUTION_PLAN_BUILDER => null,
                Constant::DB_EXECUTION_PLAN_MAKE => static::getModelAlias(),
                Constant::DB_EXECUTION_PLAN_FROM => '',
                Constant::DB_EXECUTION_PLAN_JOIN_DATA => [
                ],
                Constant::DB_EXECUTION_PLAN_SELECT => $select,
                Constant::DB_EXECUTION_PLAN_WHERE => $where,
                Constant::DB_EXECUTION_PLAN_ORDERS => [
                    $order
                ],
                Constant::DB_EXECUTION_PLAN_OFFSET => $offset,
                Constant::DB_EXECUTION_PLAN_LIMIT => $limit,
                Constant::DB_EXECUTION_PLAN_IS_PAGE => $isPage,
                Constant::DB_EXECUTION_PLAN_IS_ONLY_GET_COUNT => $isOnlyGetCount,
                Constant::DB_EXECUTION_PLAN_PAGINATION => $pagination,
                Constant::DB_EXECUTION_PLAN_HANDLE_DATA => [
                    Constant::DB_TABLE_STATUS => [//状态
                        Constant::DB_EXECUTION_PLAN_FIELD => Constant::DB_TABLE_STATUS,
                        Constant::RESPONSE_DATA_KEY => static::$statusData,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        Constant::DB_EXECUTION_PLAN_GLUE => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => data_get(static::$statusData, '0', ''),
                        Constant::DB_EXECUTION_PLAN_TIME => '',
                    ],
                    'callbackHandle' => [//
                        Constant::DB_EXECUTION_PLAN_FIELD => null,
                        Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        Constant::DB_EXECUTION_PLAN_TIME => '',
                        Constant::DB_EXECUTION_PLAN_GLUE => '',
                        Constant::DB_EXECUTION_PLAN_ONLY => Constant::PARAMETER_ARRAY_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_CALLBACK => [
                            Constant::DB_TABLE_EXTINFO => function($item) {
                                if (data_get($item, 'type', '') == Constant::COUPON) {
                                    $field = [
                                        Constant::DB_EXECUTION_PLAN_FIELD => 'json|extinfo',
                                        Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
                                        Constant::DB_EXECUTION_PLAN_DATATYPE => 'string',
                                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                                        Constant::DB_EXECUTION_PLAN_GLUE => ',',
                                    ];
                                    return FunctionHelper::handleData($item, $field);
                                } else {
                                    return '';
                                }
                            },
                        ],
                        Constant::DB_EXECUTION_PLAN_DEFAULT => Constant::PARAMETER_ARRAY_DEFAULT,
                    ],
                    Constant::DB_TABLE_EXTINFO => [//邮件内容
                        Constant::DB_EXECUTION_PLAN_FIELD => 'callbackHandle.extinfo',
                        Constant::RESPONSE_DATA_KEY => Constant::PARAMETER_ARRAY_DEFAULT,
                        Constant::DB_EXECUTION_PLAN_DATATYPE => '',
                        Constant::DB_EXECUTION_PLAN_DATA_FORMAT => '',
                        Constant::DB_EXECUTION_PLAN_GLUE => '',
                        Constant::DB_EXECUTION_PLAN_DEFAULT => '',
                        Constant::DB_EXECUTION_PLAN_TIME => '',
                    ],
                ],
                'unset' => ['callbackHandle'],
            ],
        ];

        if (data_get($params, 'isOnlyGetPrimary', false)) {//如果仅仅获取主键id，就不需要处理数据，不关联
            data_set($dbExecutionPlan, 'parent.handleData', Constant::PARAMETER_ARRAY_DEFAULT);
            data_set($dbExecutionPlan, 'with', Constant::PARAMETER_ARRAY_DEFAULT);
        }

        $dataStructure = 'list';
        $flatten = false;
        return FunctionHelper::getResponseData(null, $dbExecutionPlan, $flatten, $isGetQuery, $dataStructure);
    }

}
