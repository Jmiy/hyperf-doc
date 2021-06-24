<?php

namespace App\Http\Controllers\Admin;

use App\Services\OrderReviewService;
use App\Constants\Constant;
use App\Utils\Response;
use Illuminate\Http\Request;

class OrderReviewController extends Controller {

    /**
     * 订单索评列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        $data = OrderReviewService::getListData($request->all());
        return Response::json($data);
    }

    /**
     * 审核
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function audit(Request $request) {
        $ids = $request->input('ids', []);
        $storeId = $request->input($this->storeIdKey, Constant::PARAMETER_INT_DEFAULT);
        $auditStatus = $request->input(Constant::AUDIT_STATUS, Constant::PARAMETER_INT_DEFAULT);
        $reviewer = $request->input('reviewer', Constant::PARAMETER_STRING_DEFAULT);
        $remarks = $request->input(Constant::DB_TABLE_REMARKS, Constant::PARAMETER_STRING_DEFAULT);
        if (empty($ids) || empty($storeId) || empty($auditStatus) || empty($reviewer) || !is_array($ids)) {
            return Response::json([], -1, Constant::PARAMETER_STRING_DEFAULT);
        }
        $data = OrderReviewService::audit($storeId, $ids, $auditStatus, $reviewer, $remarks);
        return Response::json($data[Constant::RESPONSE_DATA_KEY], $data[Constant::RESPONSE_CODE_KEY], $data[Constant::RESPONSE_MSG_KEY]);
    }

    /**
     * 导出
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function export(Request $request) {
        $requestData = $request->all();
        data_set($requestData, 'is_export', 1);
        $data = OrderReviewService::export($requestData);
        return Response::json($data);
    }

    /**
     * 订单索评统计
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function statList(Request $request) {
        $data = OrderReviewService::statList($request->all());
        return Response::json($data);
    }
}
