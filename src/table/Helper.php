<?php

namespace tpext\builder\table;

use tpext\builder\common\Search;
use tpext\builder\common\Table;
use tpext\builder\common\Builder;

class Helper
{
    /**
     * 数据模型
     *
     * @var \think\Model|mixed
     */
    protected $dataModel;
    protected $pk = 'id';
    protected $pagesize = 14;
    protected $sortOrder = 'id desc';
    protected $useSearch = true;

    /**
     * 搜索对象
     *
     * @var Search
     */
    protected $search;

    /**
     * 表格对象
     *
     * @var Table
     */
    protected $table;

    /**
     * 列表页关联加载 如 ['category']
     * @var array
     */
    protected $indexWith = [];

    /**
     * 列表页允许的字段
     * @var array|string|true
     */
    protected $indexFieldsOnly = '*';

    /**
     * 列表页排除的字段，过长的文本字段影响查询速度，而列表页又不显示此字段时可以把它排除。
     * [indexFieldsOnly]和[indexFieldsExcept]只能用其中一种
     * @var array|string
     */
    protected $indexFieldsExcept = '';

    /**
     * @var \Closure
     */
    protected $callBuildTable = null;
    /**
     * @var \Closure
     */
    protected $callBuildSearch = null;
    /**
     * @var \Closure
     */
    protected $callFilterWhere = null;
    /**
     * @var \Closure
     */
    protected $callBuildDataList = null;

    /**
     * @param \think\Model|mixed $dataModel
     * @param integer $pagesize
     * @param string $sortOrder
     */
    public function __construct($dataModel, $pagesize = 14, $sortOrder = 'id desc')
    {
        $this->dataModel = $dataModel;
        $this->pagesize = $pagesize;
        $this->sortOrder = $sortOrder;
    }

    /**
     * 渲染列表页
     *
     * @param string $title
     * @param string $desc
     * @return mixed
     */
    public function render($title = '', $desc = '')
    {
        if (request()->isAjax()) {
            request()->withPost(request()->get()); //兼容以post方式获取参数，tp5.1貌似有问题。filterWhere尽量使用get/param获取参数
        }

        $builder = Builder::getInstance($title, $desc);

        $this->createTable($builder);

        $this->initTable();
        if (request()->isAjax()) {
            return $this->table->partial()->render();
        }

        if ($this->useSearch) {
            $this->search = $this->table->getSearch();
            if ($this->callBuildSearch) {
                $this->callBuildSearch->call($this, $this->search);
            }
        }

        return $builder->render();
    }

    /**
     * 创建表格
     * @param Builder $builder
     * @return void
     */
    protected function createTable($builder)
    {
        $this->table = $builder->table();
    }

    /**
     * 生成并填充数据
     *
     * @return void
     */
    protected function initTable()
    {
        $table = $this->table;

        $this->table->pk($this->getPk());

        $where = [];
        $data = [];

        if ($this->callFilterWhere) {
            $where = $this->callFilterWhere->call($this);
        }
        $sortOrder = $this->getSortorder();
        $page = input('get.__page__/d', 1);
        $page = $page < 1 ? 1 : $page;

        $pagesize = input('get.__pagesize__/d', 0);
        $this->pagesize = $pagesize ?: $this->pagesize;

        $total = -1;

        if ($this->callBuildDataList) {
            $data = $this->callBuildDataList->call($this, $where, $sortOrder, $page, $total);
        } else {
            $data = $this->buildData($where, $sortOrder, $page, $total);
        }

        if ($this->callBuildTable) {
            $this->callBuildTable->call($this, $data, $this->table);
        }

        $table->fill($data);
        $table->paginator($total, $this->pagesize);
        $table->sortOrder($sortOrder);
        $table->useExport(false);
    }

    /**
     * 获取排序方式
     *
     * @return string
     */
    protected function getSortOrder()
    {
        $sortOrder = input('get.__sort__', $this->sortOrder ? $this->sortOrder : $this->getPk() . ' desc');
        return $sortOrder;
    }


    /**
     * 构建表格
     * @param \Closure $callable
     * @return $this
     */
    public function buildTable($callable)
    {
        $this->callBuildTable = $callable;
        return $this;
    }

    /**
     * 构建搜索
     * @param \Closure $callable
     * @return $this
     */
    public function buildSearch($callable)
    {
        $this->callBuildSearch = $callable;
        return $this;
    }

    /**
     * 构建查询条件
     * @param \Closure $callable
     * @return $this
     */
    public function filterWhere($callable)
    {
        $this->callFilterWhere = $callable;
        return $this;
    }

    /**
     * 构建数据源
     * @param \Closure $callable
     * @return $this
     */
    public function buildDataList($callable)
    {
        $this->callBuildDataList = $callable;
        return $this;
    }

    /**
     * 设置主键
     * @param string $pk
     * @return $this
     */
    protected function pk($pk)
    {
        $this->pk = $pk;
        return $this;
    }

    /**
     * 是否使用搜索
     *
     * @param boolean $use
     * @return $this
     */
    public function useSearch($use = true)
    {
        $this->useSearch = $use;
        return $this;
    }

    /**
     * 设置分页大小
     * @param int $pagesize
     * @return $this
     */
    public function pagesize($pagesize)
    {
        $this->pagesize = $pagesize;
        return $this;
    }

    /**
     * 设置默认排序方式
     * @param string $sortOrder
     * @return $this
     */
    public function sortOrder($sortOrder)
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    /**
     * 设置数据模型
     * @param \think\Model|mixed
     * @return $this
     */
    public function dataModel($dataModel)
    {
        $this->dataModel = $dataModel;
        return $this;
    }

    /**
     * 设置列表页关联加载，如 ['category']
     *
     * @param array|string $with
     * @return $this
     */
    public function indexWith($with)
    {
        $this->indexWith = $with;
        return $this;
    }

    /**
     * 设置列表页仅查询/显示的字段
     *
     * @param string $fields
     * @return $this
     */
    public function indexFieldsOnly($fields)
    {
        $this->indexFieldsOnly = $fields;
        return $this;
    }

    /**
     * 设置列表页排除的字段
     *
     * @param string $fields
     * @return $this
     */
    public function indexFieldsExcept($fields)
    {
        $this->indexFieldsExcept = $fields;
        return $this;
    }

    /**
     * 获取主键字段名
     *
     * @return string
     */
    protected function getPk()
    {
        if (empty($this->pk)) {
            if ($this->dataModel) {
                $this->pk = $this->dataModel->getPk();
                $this->pk = !empty($this->pk) && is_string($this->pk) ? $this->pk : 'id';
            } else {
                $this->pk = 'id';
            }
        }

        return $this->pk;
    }

    /**
     * 获取表格对象
     *
     * @return Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * 获取搜索对象
     *
     * @return Search
     */
    public function getSearch()
    {
        return $this->search;
    }

    /**
     * 生成数据，如数据不是从`$this->dataModel`得来时，可重写此方法
     * 比如使用db()助手方法、多表join、或以一个自定义数组为数据源
     *
     * @param array $where
     * @param string $sortOrder
     * @param integer $page
     * @param integer $total
     * @return array|\think\Collection|\IteratorAggregate|\Generator
     */
    protected function buildData($where = [], $sortOrder = '', $page = 1, &$total = -1)
    {
        $data = [];
        $total = 0;
        if (!$this->dataModel) {
            return $data;
        }

        $total = $this->dataModel->where($where)->count();
        if ($total == 0) {
            return $data;
        }

        if ($page > 1 && $page > $total / $this->pagesize) {
            if ($total % $this->pagesize == 0) {
                $page = intval($total / $this->pagesize);
            } else {
                $page = intval($total / $this->pagesize) + 1;
            }
        }

        $data = $this->dataModel->with($this->indexWith)
            ->where($where)->order($sortOrder)
            ->limit(($page - 1) * $this->pagesize, $this->pagesize)
            ->field($this->indexFieldsOnly)
            ->withoutField($this->indexFieldsExcept)
            ->select();

        return $data;
    }
}
