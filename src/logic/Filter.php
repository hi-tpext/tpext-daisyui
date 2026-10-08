<?php

namespace tpext\builder\logic;

use tpext\builder\common\Search;

/**
 * 搜索条件构建器：把搜索表单数据转换为查询where数组
 */
class Filter
{
    /**
     * 根据搜索表单行和提交数据构建查询条件
     *
     * @param Search $search
     * @param array $searchData
     * @return array
     */
    public function getQuery($search, $searchData)
    {
        $where = [];

        $rows = $search->getRows();

        $comumn = '';

        foreach ($rows as $row) {

            $comumn = $row->getName();

            if (isset($searchData[$comumn]) && $searchData[$comumn] !== '' && $searchData[$comumn] !== []) {

                $filter = $row->getFilter() ?: '=';

                if (is_array($searchData[$comumn])) {
                    $filter = 'in';
                }
                if ($filter == 'like') {
                    $where[] = [$comumn, $filter, "%{$searchData[$comumn]}%"];
                } else {
                    $where[] = [$comumn, $filter, $searchData[$comumn]];
                }
            }
        }

        return $where;
    }
}
