<?php

/**
 * Created by Patazon.
 * @desc   :
 * @author : Roy_qiu
 * @email  : Roy_qiu@patazon.net
 * @date   : 2021/1/9 15:06
 */

namespace App\Services;

use App\Constants\Constant;
use Hyperf\DbConnection\Db as DB;

class ProductFileCategoryService extends BaseService {

    /**
     * 添加类目
     * @param $storeId
     * @param $oneCategoryName
     * @param $twoCategoryName
     * @param $threeCategoryName
     * @return array
     */
    public static function addCategory($storeId, $oneCategoryName, $twoCategoryName, $threeCategoryName) {
        //一级标题数据
        if (empty($oneCategoryName)) {
            return [];
        }
        $where = [
            Constant::ONE_CATEGORY_NAME => $oneCategoryName,
            Constant::TWO_CATEGORY_NAME => '',
            Constant::THREE_CATEGORY_NAME => '',
        ];
        $oneRs = static::updateOrCreate($storeId, $where, []);

        //二级标题数据
        if (empty($twoCategoryName)) {
            return $oneRs;
        }
        $where = [
            Constant::ONE_CATEGORY_NAME => $oneCategoryName,
            Constant::TWO_CATEGORY_NAME => $twoCategoryName,
            Constant::THREE_CATEGORY_NAME => '',
        ];
        $twoRs = static::updateOrCreate($storeId, $where, []);

        //三级标题数据
        if (empty($threeCategoryName)) {
            return $twoRs;
        }
        $where = [
            Constant::ONE_CATEGORY_NAME => $oneCategoryName,
            Constant::TWO_CATEGORY_NAME => $twoCategoryName,
            Constant::THREE_CATEGORY_NAME => $threeCategoryName,
        ];
        return static::updateOrCreate($storeId, $where, []);
    }

    /**
     * 获取类目
     * @param $storeId
     * @param $oneCategoryName
     * @param $twoCategoryName
     * @param $threeCategoryName
     * @return array
     */
    public static function getCategoriesAdmin($storeId, $oneCategoryName, $twoCategoryName, $threeCategoryName) {
        if (empty($oneCategoryName)) {
            $select = DB::raw("distinct one_category_name as name");
            $where = [];
        }

        if (!empty($oneCategoryName) && empty($twoCategoryName)) {
            $select = DB::raw("distinct two_category_name as name");
            $where = [
                Constant::ONE_CATEGORY_NAME => $oneCategoryName,
            ];
        }

        if (!empty($oneCategoryName) && !empty($twoCategoryName) && empty($threeCategoryName)) {
            $select = DB::raw("distinct three_category_name as name");
            $where = [
                Constant::ONE_CATEGORY_NAME => $oneCategoryName,
                Constant::TWO_CATEGORY_NAME => $twoCategoryName,
            ];
        }

        if (empty($select) && empty($where)) {
            return [];
        }

        $result = [];
        $data =  static::getModel($storeId)->buildWhere($where)->select($select)->get();
        foreach ($data as $item) {
            $name = data_get($item, Constant::DB_TABLE_NAME, Constant::PARAMETER_STRING_DEFAULT);
            !empty($name) && $result[] = $name;
        }
        return $result;
    }

    /**
     * 获取类目
     * @param $storeId
     * @return array
     */
    public static function getCategories($storeId) {
        $categoryResult = [];

        $select = DB::raw("DISTINCTROW " .
            'one_category_name,' .
            'two_category_name,' .
            'three_category_name'
        );

        $where = [];

        $categories = static::getModel($storeId)
            ->from('product_file_categories as pfc')
            ->join('product_files as pf', function ($join) {
                $join->on('pf.category_id', '=', 'pfc.id')
                    ->where('pf.status', 1);
            })
            ->buildWhere($where)->select($select)->get();

        if ($categories->isEmpty()) {
            return $categoryResult;
        }

        $oneTitleMap = [];
        $twoTitleMap = [];
        $threeTitleMap = [];
        foreach ($categories as $category) {
            $oneCategoryName = data_get($category, Constant::ONE_CATEGORY_NAME, Constant::PARAMETER_STRING_DEFAULT);
            $twoCategoryName = data_get($category, Constant::TWO_CATEGORY_NAME, Constant::PARAMETER_STRING_DEFAULT);
            $threeCategoryName = data_get($category, Constant::THREE_CATEGORY_NAME, Constant::PARAMETER_STRING_DEFAULT);

            if (!empty($oneCategoryName) && !in_array($oneCategoryName, $oneTitleMap)) {
                $categoryResult[] = [
                    'title' => $oneCategoryName,
                    'key' => $oneCategoryName,
                    'children' => [],
                ];
                $oneTitleMap[] = $oneCategoryName;
            }
            $oneIdx = array_search($oneCategoryName, $oneTitleMap);

            !isset($twoTitleMap[$oneCategoryName]) && $twoTitleMap[$oneCategoryName] = [];
            if (!empty($twoCategoryName) && !in_array($twoCategoryName, $twoTitleMap[$oneCategoryName])) {
                $categoryResult[$oneIdx]['children'][] = [
                    'title' => $twoCategoryName,
                    'key' => "$oneCategoryName{#}$twoCategoryName",
                    'children' => [],
                ];
                $twoTitleMap[$oneCategoryName][] = $twoCategoryName;
            }
            $twoIdx = array_search($twoCategoryName, $twoTitleMap[$oneCategoryName]);

            !isset($threeTitleMap[$oneCategoryName][$twoCategoryName]) && $threeTitleMap[$oneCategoryName][$twoCategoryName] = [];
            if (!empty($threeCategoryName) && !in_array($threeCategoryName, $threeTitleMap[$oneCategoryName][$twoCategoryName])) {
                $categoryResult[$oneIdx]['children'][$twoIdx]['children'][] = [
                    'title' => $threeCategoryName,
                    'key' => "$oneCategoryName{#}$twoCategoryName{#}$threeCategoryName",
                    'children' => [],
                ];
            }
        }

        return $categoryResult;
    }
}
