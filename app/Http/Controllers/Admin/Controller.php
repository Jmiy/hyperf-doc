<?php

namespace App\Http\Controllers\Admin;

use App\Controller\AbstractController as BaseController;
use Hyperf\HttpServer\Contract\RequestInterface as Request;
use App\Constants\Constant;

class Controller extends BaseController {

    public $storeIdKey = 'store_id';
    public $accoutKey = 'account';
    public $customerPrimaryKey = 'customer_id';
    public $remarkKey = 'remark';
    public $actionKey = 'action';
    public $countryKey = 'country';
    public $listMethod = 'getListData';
    public $creditKey = 'credit';
    public $storeId = Constant::PARAMETER_INT_DEFAULT; //商城id
    public $token = Constant::PARAMETER_STRING_DEFAULT; //后台token
    public $operator = Constant::PARAMETER_STRING_DEFAULT; //后台用户

    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function __construct(Request $request) {
        $this->storeId = $request->input(Constant::DB_TABLE_STORE_ID, $this->storeId); //商城id
        $this->token = $request->input(Constant::TOKEN, $this->token); //商城id
        $this->operator = $request->input(Constant::DB_TABLE_OPERATOR, $this->operator); //后台用户
    }

}
