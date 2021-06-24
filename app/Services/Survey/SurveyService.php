<?php

/**
 * 调查问券服务
 * User: Jmiy
 * Date: 2020-01-04
 * Time: 12:00
 */

namespace App\Services\Survey;

use App\Services\BaseService;
use App\Utils\FunctionHelper;
use App\Services\Traits\GetDefaultConnectionModel;

class SurveyService extends BaseService {

    use GetDefaultConnectionModel;

    public static function getCacheTags() {
        return 'survey';
    }

    /**
     * 获取公共sql
     * @param int $storeId 商城id
     * @param int $actId 活动id
     * @param string $productCountry 奖品国家
     * @return array $dbExecutionPlan
     */
    public static function getDbExecutionPlan($storeId = 0, $order = [], $offset = null, $limit = null, $isPage = false, $pagination = []) {

        $select = ['id', 'name']; //

        $dbExecutionPlan = [
            'parent' => [
                'setConnection' => false,
                'storeId' => 'default_connection_survey',
                'builder' => null,
                'make' => static::getModelAlias(),
                'from' => '',
                'joinData' => [
                ],
                'select' => $select,
                'where' => ['store_id' => $storeId],
                'orders' => $order,
                'offset' => $offset,
                'limit' => $limit,
                'isPage' => $isPage,
                'pagination' => $pagination,
                'handleData' => [
                ],
                'unset' => [],
            ],
            'with' => [
                'survey_items' => [
                    'setConnection' => false,
                    'storeId' => 'default_connection_survey',
                    'relation' => 'hasMany',
                    'default' => [
                        'survey_id' => 'survey_id',
                    ],
                    'select' => ['id', 'survey_id', 'name', 'item_type', 'is_required', 'validation_rules'],
                    'where' => [],
                    'orders' => [['sort', 'ASC']],
                    'handleData' => [
                    ],
                    'with' => [
                        'survey_item_options' => [
                            'setConnection' => false,
                            'storeId' => 'default_connection_survey',
                            'relation' => 'hasMany',
                            'default' => [
                                'item_id' => 'item_id',
                            ],
                            'select' => [
                                'id',
                                'item_id',
                                'survey_id',
                                'name',
                            ],
                            'where' => [],
                            'orders' => [['sort', 'ASC']],
                            'handleData' => [
                            ],
                        ],
                    ]
                ],
            ],
                //'sqlDebug' => true,
        ];

        return $dbExecutionPlan;
    }
    
    /**
     * 获取要清空的tags
     * @return array
     */
    public static function getClearTags() {
        return [static::getCacheTags()];
    }

    /**
     * 获取详情
     * @param int $storeId    商城id
     * @param int $surveyId      问券id
     * @return array 
     */
    public static function getItemData($storeId = 0, $surveyId = 0) {

        $tag = static::getCacheTags();
        $ttl = static::getCacheTtl(); //认证缓存时间 单位秒
        $key = md5(json_encode(func_get_args()));

        $actionData = [
            'service' => static::getNamespaceClass(),
            'method' => 'remember',
            'parameters' => [
                $key,
                $ttl,
                function () use($storeId, $surveyId) {
                    $pagination = [];
                    $offset = 0;
                    $limit = 1;
                    $order = [];

                    $dbExecutionPlan = static::getDbExecutionPlan($storeId, $order, $offset, $limit, false, $pagination);
                    if ($surveyId) {
                        data_set($dbExecutionPlan, 'parent.where.id', $surveyId);
                    } else {
                        data_set($dbExecutionPlan, 'parent.order', [['created_at', 'DESC']]);
                    }

                    $dataStructure = 'one';
                    $data = FunctionHelper::getResponseData(null, $dbExecutionPlan, false, false, $dataStructure);

                    return $data;
                }
            ],
        ];

        $data = static::handleCache($tag, $actionData);

        return $data;
    }

}
