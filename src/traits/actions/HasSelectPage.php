<?php

namespace tpext\builder\traits\actions;

/**
 * 下拉数据源
 */

trait HasSelectPage
{
    /**
     * 默认查询条件
     *
     * @var array
     */
    protected $selectScope = []; //如 [['enable', '=', 1]]

    /**
     * 模糊查询字段，如 'name|title'
     *
     * @var string
     */
    protected $selectSearch = '';

    /**
     * 默认 Text 字段 如 '{id}#{nickname}'
     *
     * @var string
     */
    protected $selectTextField = '';

    /**
     * 默认 id 字段
     *
     * @var string
     */
    protected $selectIdField = '';

    /**
     * 查询时的字段，优化查询速度
     *
     * @var string
     */
    protected $selectFields = '*';

    /**
     * 排序
     *
     * @var string
     */
    protected $selectOrder = '';

    /**
     * 分页大小
     *
     * @var string
     */
    protected $selectPagesize = 20;

    /**
     * 下拉列表关联加载 如 ['level']
     * 设置后`selectTextField`可以为'{id}#{nickname}{level.name}'
     * 若设置了`selectFields`,必须包含关联字段`level_id`，
     * 如：$this->selectFields='id,nickname,level_id';
     * @var array
     */
    protected $selectWith = [];

    /**
     * 模型是否使用了`tpext\builder\traits\TreeModel`,显示为树形结构
     *
     * @return boolean
     */
    protected function asTreeSelectList()
    {
        return $this->dataModel && method_exists($this->dataModel, 'asTreeList') && $this->dataModel->asTreeList();
    }

    /**
     * 下拉分页数据源（供select组件异步加载选项）
     *
     * @return mixed
     */
    public function selectPage()
    {
        if (!$this->dataModel) {
            return json(
                [
                    'code' => 1,
                    'data' => $this->buildDataList(),
                    'has_more' => 0,
                ]
            );
        }

        $selected = input('selected', '');

        if (is_array($selected)) {
            $selected = implode(',', $selected);
        }

        if ($this->asTreeSelectList()) { //如果此模型使用了`tpext\builder\traits\TreeModel`,显示为树形结构

            $list = $this->dataModel->getOptionsData();
            $data = [];

            $arr = array_filter(explode(',', $selected));

            foreach ($list as $k => $v) {
                if ($selected !== '') {
                    if (in_array($k, $arr)) {
                        $data[] = [
                            '__id__' => $k,
                            '__text__' => $v,
                        ];
                    }
                } else {
                    $data[] = [
                        '__id__' => $k,
                        '__text__' => $v,
                    ];
                }
            }
            return json(
                [
                    'code' => 1,
                    'data' => $data,
                    'has_more' => 0,
                ]
            );
        }

        $where = [];
        $list = [];

        if (!$this->selectTextField && $this->selectSearch) {
            $this->selectTextField = explode('|', $this->selectSearch)[0];
        }

        $idField = input('idField', '_');
        $textField = input('textField', '_');

        if (empty($idField) || $idField == '_') {
            $idField = $this->selectIdField ?: $this->getPk();
        }
        if (empty($textField) || $textField == '_') {
            $textField = 'text';
        }

        if ($textField == 'text') {
            $textField = $this->selectTextField;
        }

        $sortOrder = $this->selectOrder ?: ($this->sortOrder ?: $idField . ' desc');
        $pagesize = $this->selectPagesize ?: ($this->pagesize ?: 20);

        $hasMore = 1;
        if ($selected !== '') { //初始化已选中的
            if (is_numeric($selected)) {
                $where[] = [$idField, '=', $selected];
            } else {
                $arr = array_filter(explode(',', $selected));
                if (count($arr) > 1) {
                    $where[] = [$idField, 'in', $selected];
                } else {
                    $where[] = [$idField, '=', $selected];
                }
            }
            $list = $this->dataModel->with($this->selectWith)->where($where)->order($sortOrder)->field($this->selectFields)->select();
            $hasMore = 0;
        } else {
            $q = trim(input('q', ''));
            $page = input('page/d', 1);

            $page = $page < 1 ? 1 : $page;
            $whereOr = [];

            $where = $this->selectScope;

            if ($q) {
                if ($this->selectSearch) {
                    if (is_numeric($q)) {
                        $sarr = array_filter(explode('|', $this->selectSearch));
                        if (!in_array($idField, $sarr)) {
                            $this->selectSearch .= '|' . $idField;
                        }
                    }
                    $where[] = [$this->selectSearch, 'like', '%' . $q . '%'];
                }
            }

            $list = $this->dataModel->with($this->selectWith)->where($where)->whereOr($whereOr)->order($sortOrder)->limit(($page - 1) * $pagesize, $pagesize)->field($this->selectFields)->select();

            $hasMore = count($list) == $pagesize ? 1 : 0;
        }

        $data = [];
        if ($textField && $textField != 'text') {
            $keys = [];
            $replace = [];
            $arr = [];
            preg_match_all('/\{([\w\.]+)\}/', $textField, $matches);
            foreach ($list as $li) {
                $li = $li->toArray();
                $keys = [];
                $replace = [];
                if (isset($matches[1]) && count($matches[1]) > 0) {
                    foreach ($matches[1] as $match) {
                        $arr = explode('.', $match);
                        if (count($arr) == 1) {

                            $keys[] = '{' . $arr[0] . '}';
                            $replace[] = isset($li[$arr[0]]) ? $li[$arr[0]] : '';
                        } else if (count($arr) == 2) {

                            $keys[] = '{' . $arr[0] . '.' . $arr[1] . '}';
                            $replace[] = isset($li[$arr[0]]) && isset($li[$arr[0]][$arr[1]]) ? $li[$arr[0]][$arr[1]] : '-';
                        } else {
                            //最多支持两层 xx 或 xx.yy
                        }
                    }
                    $li['__text__'] = str_replace($keys, $replace, $textField);
                } else {
                    $li['__text__'] = $li[$textField] ?? '-';
                }

                $li['__id__'] = $li[$idField];
                $data[] = $li;
            }
        } else {
            foreach ($list as $li) {
                $li = $li->toArray();
                $li['text'] = implode(',', array_slice(array_values($li), 0, 4));
                $li['__id__'] = $li[$idField];
                $li['__text__'] = $li['text'];
                $data[] = $li;
            }
        }
        return json(
            [
                'code' => 1,
                'data' => $data,
                'has_more' => $hasMore,
                'textField' => $textField,
            ]
        );
    }
}
