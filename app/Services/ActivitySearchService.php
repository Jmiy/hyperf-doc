<?php

namespace App\Services;

use App\Constants\Constant;

class ActivitySearchService extends BaseService {

    /**
     * 分割字符串，并更新数据到搜索表
     * @param int $storeId 官网id
     * @param int $actId 活动id
     * @param int $extId 关联id
     * @param string $extType 关联名称
     * @param string $texts 待分割处理的字符串
     * @return bool
     */
    public static function generateData($storeId, $actId, $extId, $extType, $texts) {
        $words = static::wordSegment($texts);
        $words = static::removeInvalidWords($words);

        $where = [
            Constant::DB_TABLE_ACT_ID => $actId,
            Constant::DB_TABLE_EXT_ID => $extId,
            Constant::DB_TABLE_EXT_TYPE => $extType
        ];
        $result = static::getModel($storeId)->buildWhere($where)->select(['word'])->get();
        $dataWords = [];
        if (!$result->isEmpty()) {
            $dataWords = $result->toArray();
        }

        $addWords = array_diff($words, $dataWords);
        $delWords = array_diff($dataWords, []);

        //新增词
        if (!empty($addWords)) {
            $batchData = [];
            foreach ($addWords as $addWord) {
                $batchData[] = [
                    Constant::DB_TABLE_ACT_ID => $actId,
                    'word' => $addWord,
                    Constant::DB_TABLE_EXT_ID => $extId,
                    Constant::DB_TABLE_EXT_TYPE => $extType,
                ];
            }
            static::getModel($storeId)->insert($batchData);
        }

        //删除词
        if (!empty($delWords)) {
            $where = [
                Constant::DB_TABLE_ACT_ID => $actId,
                Constant::DB_TABLE_EXT_ID => $extId,
                Constant::DB_TABLE_EXT_TYPE => $extType,
                'word' => $delWords
            ];
            static::getModel($storeId)->buildWhere($where)->delete();
        }

        return true;
    }

    /**
     * 去掉除字母数字之外的其他字符，并分割词
     * @param string $text 需要按单词分割的一段字符串
     * @return array 分割后的词
     */
    public static function wordSegment($text) {
        $text = strtolower($text);

        $retText = '';
        $flag = false;
        for ($i = 0; $i < strlen($text); $i++) {
            if ($text[$i] >= 'a' && $text[$i] <= 'z' || $text[$i] >= '0' && $text[$i] <= '9') {
                $retText .= $text[$i];
                $flag = true;
            } else {
                $flag && $retText .= ' ';
                $flag = false;
            }
        }

        $words = explode(' ', trim($retText));

        return array_unique($words);
    }

    /**
     * 去除无效的词
     * @param array $words 词数组
     * @return mixed
     */
    public static function removeInvalidWords($words) {
        foreach ($words as $key => $word) {
            if (strlen(trim($word)) <= 1) {
                unset($words[$key]);
            }
        }

        return $words;
    }

}
