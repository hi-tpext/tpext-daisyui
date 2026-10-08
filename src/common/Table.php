<?php

namespace tpext\builder\common;

use think\Collection;
use tpext\think\View;
use tpext\common\ExtLoader;
use tpext\builder\table\TEmpty;
use tpext\builder\table\TColumn;
use tpext\builder\traits\HasDom;
use tpext\builder\table\TWrapper;
use tpext\builder\displayer\Field;
use tpext\builder\table\Actionbar;
use tpext\builder\table\Paginator;
use tpext\builder\inface\Renderable;
use tpext\builder\inface\ReleaseAble;
use tpext\builder\traits\HasDestroyOnce;
use tpext\builder\table\FieldsContent;
use tpext\builder\toolbar\DropdownBtns;
use tpext\builder\table\MultipleToolbar;
use tpext\builder\displayer\MultipleFile;

/**
 * Table class
 */
class Table extends TWrapper implements Renderable, ReleaseAble
{
    use HasDom;
    use HasDestroyOnce;

    protected $js = [];

    protected $css = [];

    protected $id = 'the-table';

    protected $headTextAlign = 'text-center';

    protected $textAlign = 'text-center';

    protected $verticalAlign = 'vertical-middle';

    protected $headers = [];

    protected $list = [];

    /**
     * 表格的所有列
     *
     * @var TColumn[]
     */
    protected $cols = [];

    /**
     * 已创建的字段渲染器（键为 displayer名+字段名）
     * @var Field[]
     */
    protected $displayers = [];

    protected $data = [];

    protected $pk = 'id';

    protected $ids = [];

    protected $actionbars = [];

    protected $checked = [];

    protected $useCheckbox = true;

    /**
     * 6.230 表头冻结：滚动时表头固定（容器高度由 JS 按视口动态计算）。
     * 6.244 默认开启：null=未显式设置（applyFreezeDefaults 填默认 true），
     * 显式 freezeHeader(false) 关闭。
     * @var bool|null
     */
    protected $freezeHead = null;

    /** 6.230 列冻结：吸附在左侧的列名列表（含 '__check__' 复选框列、'__action__' 操作列） */
    protected $freezeLeftCols = [];

    /** 6.230 列冻结：吸附在右侧的列名列表 */
    protected $freezeRightCols = [];

    /**
     * 6.244 用户是否显式调用过 freezeColumn/unfreezeColumn。
     * 未调用时 applyFreezeDefaults 自动冻结 复选框列+主键列(左) 与 操作列(右)；
     * 一旦显式调用，列冻结完全交给用户，默认不再介入。
     * @var bool
     */
    protected $freezeColsExplicit = false;

    protected $pageSize = 0;

    protected $emptyText = '';

    /**
     * 当前fields分组
     *
     * @var FieldsContent|null
     */
    protected $__fields__ = null;

    /**
     * 表格上方的工具栏
     *
     * @var MultipleToolbar|null
     */
    protected $toolbar = null;

    protected $useToolbar = true;

    protected $lockForExporting = false;

    /**
     * 行操作按钮栏
     *
     * @var Actionbar|null
     */
    protected $actionbar = null;

    protected $useActionbar = true;

    protected $actionRowText = '';

    protected $isInitData = false;

    protected $sortable = ['id'];

    protected $sortOrder = '';

    protected $partial = false;

    protected $delay = true; //延迟读取数据，调用fill()填充数据后取消延迟

    /**
     * 表格上方的附加行（懒创建）
     *
     * @var Row|null
     */
    protected $addTop;

    /**
     * 表格下方的附加行（懒创建）
     *
     * @var Row|null
     */
    protected $addBottom;

    /**
     * 分页器
     *
     * @var Paginator|null
     */
    protected $paginator;

    /**
     * 关联的搜索表单
     *
     * @var Search|null
     */
    protected $searchForm = null;

    /**
     * 每页条数下拉选择
     *
     * @var DropdownBtns|null
     */
    protected $pagesizeDropdown = null;

    protected $usePagesizeDropdown = true;

    /**
     * 导出时的空元素占位
     *
     * @var TEmpty|null
     */
    protected $tEmpty = null;

    protected $rowScripts = [];

    /**
     * 实例创建时的初始化（默认样式、id、空数据提示等）
     *
     * @return $this
     */
    public function created()
    {
        $this->class = 'table-striped table-hover table-bordered table-condensed table-responsive';
        $this->id = input('get.__table__', 'the-table');

        $this->emptyText = Module::config('table_empty_text');
        $this->actionRowText = __blang('builder_action_operation');

        $this->tEmpty = new TEmpty;

        return $this;
    }

    /**
     * 添加一列
     *
     * @param string $name
     * @param \tpext\builder\table\TColumn $col
     * @return $this
     */
    public function addCol($name, $col)
    {
        $this->cols[$name] = $col;
        return $this;
    }

    /**
     * 获取所有列
     *
     * @return TColumn[]
     */
    public function getCols()
    {
        return $this->cols;
    }

    /**
     * 设置主键, 默认 为 'id'
     * @param string $val
     * @return $this
     */
    public function pk($val)
    {
        $this->pk = $val;
        return $this;
    }

    /**
     * 设置表格id
     *
     * @param string $val
     * @return $this
     */
    public function tableId($val)
    {
        $this->id = $val;
        return $this;
    }

    /**
     * 获取表格id
     *
     * @return string
     */
    public function getTableId()
    {
        return $this->id;
    }

    /**
     * 设置是否局部渲染（render时返回View对象）
     *
     * @param boolean $val
     * @return $this
     */
    public function partial($val = true)
    {
        $this->partial = $val;
        return $this;
    }

    /**
     * 获取js文件列表
     *
     * @return array
     */
    public function getJs()
    {
        return $this->js;
    }

    /**
     * 获取css文件列表
     *
     * @return array
     */
    public function getCss()
    {
        return $this->css;
    }

    /**
     * 获取表头文字列表
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * 获取所有字段渲染器
     *
     * @return Field[]
     */
    public function getDisplayers()
    {
        return $this->displayers;
    }

    /**
     * 设置单元格垂直对齐方式
     * vertical-middle | vertical-mtop | vertical-bottom
     * @param string $val
     * @return $this
     */
    public function verticalAlign($val)
    {
        $this->verticalAlign = $val;
        return $this;
    }

    /**
     * 设置单元格文字对齐方式
     * text-left | text-center | text-right
     * @param string $val
     * @return $this
     */
    public function textAlign($val)
    {
        $this->textAlign = $val;
        return $this;
    }

    /**
     * 设置表头文字对齐方式
     * text-left | text-center | text-right
     * @param string $val
     * @return $this
     */
    public function headTextAlign($val)
    {
        $this->headTextAlign = $val;
        return $this;
    }

    /**
     * 6.230 表头冻结：表格容器限高滚动、表头 sticky 固定。
     * 容器 max-height 由 tpextbuilder.initTableFreeze() 按视口动态计算
     * （视口高 - 容器顶部偏移 - 底部分页预留），多次局部刷新后会重算。
     * @return $this
     */
    public function freezeHeader($val = true)
    {
        $this->freezeHead = (bool)$val;
        return $this;
    }

    /**
     * 6.230 列冻结：横向滚动时指定列吸附在对应边缘。
     * 单元格偏移量（左边累计宽度/右边累计宽度）由 JS 按 DOM 实测分配，
     * 支持连续多列（如 __check__ + id）。操作列传 '__action__'，复选框列传 '__check__'。
     * 6.244 一旦调用本方法，列冻结默认（复选框+主键左、操作右）不再自动注入，
     * 完全以显式调用为准。
     * @param string $name 字段名
     * @param string $direction left|right
     * @return $this
     */
    public function freezeColumn($name, $direction = 'left')
    {
        $this->freezeColsExplicit = true;
        if ('right' === strtolower($direction)) {
            $this->freezeRightCols[$name] = $name;
        } else {
            $this->freezeLeftCols[$name] = $name;
        }
        return $this;
    }

    /**
     * 6.244 撤销某列的冻结（也可在默认注入后用于去掉不想要的默认列）
     * @param string $name
     * @return $this
     */
    public function unfreezeColumn($name)
    {
        $this->freezeColsExplicit = true;
        unset($this->freezeLeftCols[$name], $this->freezeRightCols[$name]);
        return $this;
    }

    /**
     * 6.244 冻结默认值：所有表格默认 冻结表头 + 左冻结(复选框列、主键列) + 右冻结(操作列)。
     * 仅在未显式配置时注入；未显示的列（无复选框/无操作列/主键列未展示）自动跳过。
     * 在 render() 构建模板变量前调用。
     * @return void
     */
    protected function applyFreezeDefaults()
    {
        if ($this->freezeHead === null) {
            $this->freezeHead = true;
        }

        if ($this->freezeColsExplicit) {
            return;
        }

        // 复选框列：与模板 useCheckbox 变量条件保持一致
        if ($this->useCheckbox && $this->useToolbar) {
            $this->freezeLeftCols['__check__'] = '__check__';
        }

        // 主键列：仅当它作为数据列显示时（列键名等于 pk）
        $pk = isset($this->pk) ? $this->pk : '';
        if ($pk && isset($this->cols[$pk])) {
            $this->freezeLeftCols[$pk] = $pk;
        }

        // 操作列
        if ($this->useActionbar) {
            $this->freezeRightCols['__action__'] = '__action__';
        }
    }

    /**
     * 设置可排序字段列表
     *
     * @param string|array $val
     * @return $this
     */
    public function sortable($val)
    {
        if (!is_array($val)) {
            $val = explode(',', $val);
        }

        $this->sortable = $val;
        return $this;
    }

    /**
     * 设置默认勾选的行主键
     *
     * @param array|string $val
     * @return $this
     */
    public function checked($val)
    {
        $this->checked = is_array($val) ? $val : explode(',', $val);
        return $this;
    }

    /**
     * 设置空数据提示文字
     *
     * @param string $val
     * @return $this
     */
    public function emptyText($val)
    {
        $this->emptyText = $val;
        return $this;
    }

    /**
     * 设置表格数据（同fill，但会覆盖已有数据）
     *
     * @param array|Collection|\IteratorAggregate $data
     * @return $this
     */
    public function data($data = [])
    {
        $this->delay = false;
        $this->data = $data;

        return $this;
    }

    /**
     * 设置是否锁定导出（导出时工具栏/操作栏方法调用返回空元素）
     *
     * @param bool $val
     * @return $this
     */
    public function lockForExporting($val = true)
    {
        $this->lockForExporting = $val;

        if ($this->toolbar) {
            $this->toolbar->lockForExporting($val);
        }
        if ($this->actionbar) {
            $this->actionbar->lockForExporting($val);
        }

        return $this;
    }

    /**
     * 批量设置表头文字
     *
     * @param array $val
     * @return $this
     */
    public function setHeaders($val)
    {
        $this->headers = $val;

        return $this;
    }

    /**
     * 填充数据（为空时跳过；无列时按数据键自动创建全部列）
     *
     * @param array|Collection|\IteratorAggregate $data
     * @return $this
     */
    public function fill($data = [])
    {
        $this->delay = false;
        if (empty($data)) {
            return $this;
        }

        $this->data = $data;
        if (count($data) > 0 && empty($this->cols)) {
            $cols = [];
            $first = $data[0];
            if (is_object($first) && method_exists($first, 'toArray')) {
                $first = $first->toArray();
            }
            $cols = array_keys($first);
            foreach ($cols as $col) {
                $this->show($col, ucfirst($col));
            }
        }
        return $this;
    }

    /**
     * 设置默认排序
     *
     * @param string $val
     * @return $this
     */
    public function sortOrder($val)
    {
        $this->sortOrder = $val;
        return $this;
    }

    /**
     * 获取表格数据
     *
     * @return array|Collection|\IteratorAggregate
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * 获取可勾选显示的列列表
     *
     * @return array
     */
    public function getChooseColumns()
    {
        return $this->getToolbar()->getChooseColumns();
    }

    /**
     * 设置分页器
     *
     * @param int $dataTotal
     * @param integer $pageSize
     * @param string $paginatorClass
     * @return $this
     */
    public function paginator($dataTotal, $pageSize = 10, $paginatorClass = '')
    {
        if (!$pageSize) {
            $pageSize = 10;
        }

        $paginator = new Paginator($this->data, $pageSize, input('get.__page__/d', 1), $dataTotal);

        if ($dataTotal < 10) {
            $this->usePagesizeDropdown = false;
        }

        if ($paginatorClass) {
            $paginator->paginatorClass($paginatorClass);
        }

        $this->pageSize = $pageSize;

        $this->paginator = $paginator;

        return $this;
    }

    /**
     * 获取分页器
     *
     * @return Paginator|null
     */
    public function getPaginator()
    {
        return $this->paginator;
    }

    /**
     * 获取一个toolbar
     *
     * @return MultipleToolbar
     */
    public function getToolbar()
    {
        if (empty($this->toolbar)) {
            $this->toolbar = Widget::makeWidget('MultipleToolbar');
            $this->toolbar->extKey('-' . $this->id);
        }

        return $this->toolbar;
    }

    /**
     * 设置是否使用工具栏
     *
     * @param boolean $val
     * @return $this
     */
    public function useToolbar($val)
    {
        $this->useToolbar = $val;
        return $this;
    }

    /**
     * 设置是否使用行操作栏
     *
     * @param boolean $val
     * @return $this
     */
    public function useActionbar($val)
    {
        $this->useActionbar = $val;
        return $this;
    }

    /**
     * 设置是否使用复选框列
     * @param boolean $val
     * @return $this
     */
    public function useCheckbox($val)
    {
        $this->useCheckbox = $val;
        return $this;
    }

    /**
     * 设置是否使用导出功能
     *
     * @param boolean $val
     * @return $this
     */
    public function useExport($val = true)
    {
        $this->getToolbar()->useExport($val);

        return $this;
    }

    /**
     * 设置列显示选择功能
     *
     * @param boolean|array|string $val 默认显示的字段，false则禁用
     * @return $this
     */
    public function useChooseColumns($val = true)
    {
        $this->getToolbar()->useChooseColumns($val);

        return $this;
    }

    /**
     * 弃用，使用｀useExport｀代替
     * @deprecated 1.8.93
     * @param boolean $val
     * @return $this
     */
    public function hasExport($val = true)
    {
        $this->getToolbar()->useExport($val);

        return $this;
    }

    /**
     * 是否已锁定导出
     *
     * @return bool
     */
    public function isLockForExporting()
    {
        return $this->lockForExporting;
    }

    /**
     * 获取一个actionbar
     *
     * @return Actionbar
     */
    public function getActionbar()
    {
        if (empty($this->actionbar)) {
            $this->actionbar = Widget::makeWidget('Actionbar');
        }

        return $this->actionbar;
    }

    /**
     * 设置操作列表头文字
     *
     * @param string $val
     * @return $this
     */
    protected function actionRowText($val)
    {
        $this->actionRowText = $val;
        return $this;
    }

    /**
     * 设置每页条数下拉选项（传false禁用）
     *
     * @param array|boolean $items
     * @return DropdownBtns|null
     */
    public function pagesizeDropdown($items)
    {
        if ($items === false) {
            $this->usePagesizeDropdown = false;
            return null;
        }

        if (empty($this->pagesizeDropdown)) {
            $this->pagesizeDropdown = new DropdownBtns('pagesize', __blang('builder_paginator_num_per_page', ['num' => $this->pageSize]));
        }

        $this->pagesizeDropdown->items($items)->class('btn-xs btn-secondary')->addGroupClass('dropup pull-right m-r-10');

        return $this->pagesizeDropdown;
    }

    /**
     * 获取一个搜索
     *
     * @return Search
     */
    public function getSearch()
    {
        if (empty($this->searchForm)) {
            $this->searchForm = Widget::makeWidget('Search');
        }
        return $this->searchForm;
    }

    /**
     * 渲染前的准备：初始化数据、注册资源与交互脚本、准备搜索表单和工具栏
     *
     * @return $this
     */
    public function beforRender()
    {
        ExtLoader::trigger('tpext_table_befor_render', $this);

        $this->initData();

        if (!request()->isAjax()) {

            Builder::getInstance()->addJs($this->js);
            Builder::getInstance()->addCss($this->css);

            if ($this->useToolbar) {
                $toolbar = $this->getToolbar();

                $toolbar->useSearch(!empty($this->searchForm));
                $toolbar->setTableCols($this->cols);
                $toolbar->beforRender();
            }

            if (empty($this->searchForm)) {
                $this->getSearch();
                $this->searchForm->addClass('form-empty');
            }

            $this->searchForm->setTableId($this->getTableId());
            $this->searchForm->chooseColumns($this->getChooseColumns());
            $this->searchForm->beforRender();

            $this->tableScript();
        }

        if ($this->addTop) {
            $this->addTop->beforRender();
        }

        if ($this->addBottom) {
            $this->addBottom->beforRender();
        }

        return $this;
    }

    /**
     * 生成表格交互脚本（行选/全选/双击编辑等）
     *
     * @return string
     */
    protected function tableScript()
    {
        $table = $this->getTableId();
        $form = $this->getSearch()->getFormId();

        $delay = $this->delay ? 'true' : 'false';

        $script = <<<EOT

        // tableScript — 原生 JS 实现（替代原 jQuery 版）
        // 使用事件委托挂到 document，AJAX 刷新表格后新 DOM 自动生效
        (function(tableId, formId, delay) {
            if (window.__table_script_bound) return;
            window.__table_script_bound = true;

            document.addEventListener('dblclick', function(e) {
                // 排除输入组件 / 按钮 / 链接 —— 双击它们不应触发行双击
                if (e.target.closest('input,textarea,select,button,a,label,[contenteditable]')) return;
                var tr = e.target.closest('#' + tableId + ' tbody tr');
                if (!tr) return;
                var a = tr.querySelector('td a.dbl-click:not(.hidden):not(.disabled)')
                    || tr.querySelector('td a.action-edit:not(.hidden):not(.disabled)')
                    || tr.querySelector('td a.action-view:not(.hidden):not(.disabled)');
                if (a) { a.click(); e.preventDefault(); }
            });

            document.addEventListener('click', function(e) {
                var td = e.target.closest('#' + tableId + ' tbody tr td');
                if (!td) return;
                if (td.classList.contains('table-checkbox') || td.classList.contains('row-__action__')) return;
                if (e.target.closest('input,textarea,select,button,a,label,[contenteditable]')) return;  // 排除输入组件自身的点击
                var cbTd = td.parentElement.querySelector('td.table-checkbox input[type=checkbox]');
                if (cbTd) { cbTd.checked = !cbTd.checked; cbTd.dispatchEvent(new Event('change', {bubbles: true})); }
            });

            // ========== 全选 / 行选 逻辑 ==========
            // 使用 batchSelecting flag 避免 Handler 2 在 Handler 1 批量勾选过程中
            // 反复覆盖 checkall.checked 状态
            var __batchSelecting = false;

            // Handler 1: checkall 改变 → 批量设置所有行 checkbox
            document.addEventListener('change', function(e) {
                if (!e.target.matches('#' + tableId + ' input.checkall')) return;
                var checkall = e.target;
                var cls = checkall.getAttribute('data-check');
                if (!cls) return;
                var cbs = document.querySelectorAll('#' + tableId + ' .' + cls);
                __batchSelecting = true;
                cbs.forEach(function(cb) {
                    if (cb.disabled || cb.readOnly) return;
                    cb.checked = checkall.checked;
                    cb.dispatchEvent(new Event('change', {bubbles: true}));
                });
                __batchSelecting = false;
            });

            // Handler 2: 单个行 checkbox 改变 → 更新行样式 + 同步 checkall 状态
            document.addEventListener('change', function(e) {
                if (!e.target.matches('#' + tableId + ' input[type=checkbox].checkbox,' +
                                     '#' + tableId + ' .table-row-checkbox')) return;
                var tr = e.target.closest('tr');
                if (tr && !e.target.classList.contains('checkall')) {
                    if (e.target.checked) {
                        tr.classList.add('active');
                    } else {
                        tr.classList.remove('active');
                    }
                }
                // 批量选行期间不更新 checkall（Handler 1 最后统一处理）
                if (__batchSelecting) return;
                // 点的是 checkall 本身也跳过（Handler 1 已经处理）
                if (e.target.classList.contains('checkall')) return;

                var checkall = document.querySelector('#' + tableId + ' input.checkall');
                if (!checkall) return;
                var cls = checkall.getAttribute('data-check');
                if (!cls) return;
                var cbs = document.querySelectorAll('#' + tableId + ' .' + cls);
                var total = cbs.length, checked = 0;
                cbs.forEach(function(cb) { if (cb.checked) checked++; });
                checkall.checked = total > 0 && checked === total;
            });

            // 初始状态同步：页面加载 / AJAX 刷新后，让 checkall 状态和行 checkbox 对齐
            // 替代原模板里的 firstCheckall.dispatchEvent('change')
            (function syncInitialState() {
                var checkall = document.querySelector('#' + tableId + ' input.checkall');
                if (!checkall) return;
                var cls = checkall.getAttribute('data-check');
                if (!cls) return;
                var cbs = document.querySelectorAll('#' + tableId + ' .' + cls);
                var total = cbs.length, checked = 0;
                cbs.forEach(function(cb) {
                    if (cb.checked) {
                        checked++;
                        var tr = cb.closest('tr');
                        if (tr) { tr.classList.add('active'); }
                    }
                });
                checkall.checked = total > 0 && checked === total;
            })();

            if (delay && window.__forms__ && window.__forms__[formId]) {
                window.__forms__[formId].formSubmit();
            }
        })('{$table}', '{$form}', {$delay});

EOT;
        Builder::getInstance()->addScript($script);

        return $script;
    }

    /**
     * 初始化渲染数据：填充各列渲染器、收集行操作栏
     *
     * @return void
     */
    protected function initData()
    {
        ExtLoader::trigger('tpext_table_init_data', $this);

        $this->list = [];

        $pk = $this->pk;

        $actionbar = $this->getActionbar();

        $actionbar->pk($this->pk);

        $cols = array_keys($this->cols);

        $rows = 0;

        $chooseColumns = ['*'];
        if (request()->isAjax()) {
            $__columns__ = input('get.__columns__', '*');
            if ($__columns__) {
                $chooseColumns  = explode(',', $__columns__);
            }
            $colAttr = [];
            foreach ($cols as $col) {
                $colunm = $this->cols[$col];
                if (!($colunm instanceof TColumn)) {
                    continue;
                }
                $colAttr = $colunm->getColAttr();

                if ($colAttr['sortable']) {
                    $this->sortable[] = $colunm->getName();
                }
            }
        } else {
            $colAttr = [];

            $columns = [];

            foreach ($cols as $col) {
                $colunm = $this->cols[$col];
                if (!($colunm instanceof TColumn)) {
                    continue;
                }
                $colAttr = $colunm->getColAttr();

                if ($colAttr['sortable']) {
                    $this->sortable[] = $colunm->getName();
                }

                if (!$colAttr['hidden']) {
                    $columns[] = $colunm->getName();
                }
            }

            $useChooseColumns = $this->getToolbar()->getChooseColumns();

            if ($useChooseColumns) {
                if ($useChooseColumns[0] == '*') { //*号代表全部，转换为具体的字段列表
                    $this->getToolbar()->useChooseColumns($columns);
                    $chooseColumns = $columns;
                }
            } else {
                $chooseColumns = false;
            }
        }

        foreach ($this->data as $key => $data) {
            $rows += 1;

            if (isset($data[$pk])) {

                $this->ids[$key] = $data[$pk];
            } else {
                $this->ids[$key] = $key;
            }

            foreach ($cols as $col) {
                $colunm = $this->cols[$col];

                if (!($colunm instanceof TColumn)) {
                    continue;
                }

                $displayer = $colunm->getDisplayer();

                if ($chooseColumns && $chooseColumns[0] != '*'  && !in_array($col, $chooseColumns)) {
                    if (isset($this->headers[$col])) {
                        $displayer->beforRender();
                        unset($this->headers[$col]);
                    }
                    continue;
                }

                $displayer->clearScript();

                $displayer
                    ->arrayName(false)
                    ->value('')
                    ->fill($data)
                    ->extKey('-' . $this->id . '-' . $key)
                    ->extNameKey('-' . $key)
                    ->showLabel(false)
                    ->size('0', '12 col-lg-12 col-sm-12 col-xs-12')
                    ->beforRender();

                $this->list[$key][$col] = [
                    'label' => $displayer->getLabel(),
                    'displayer' => $displayer,
                    'value' => $displayer->render(),
                    'attr' => $displayer->getAttrWithStyle(),
                    'wrapper' => $colunm,
                ];
                $this->rowScripts = array_merge($this->rowScripts, $displayer->getScript());
            }

            if ($this->useActionbar) {

                $actionbar->extKey('-' . $this->id . '-' . $key)->rowData($data)->beforRender();

                $this->actionbars[$key] = $actionbar->render();
            }
        }

        if ($rows == 0) { // 数据为空，但某些js脚本是需要的，空跑一遍，把js脚本加载
            foreach ($cols as $col) {
                if ($chooseColumns && $chooseColumns[0] != '*'  && !in_array($col, $chooseColumns)) {
                    unset($this->headers[$col]);
                }
                $colunm = $this->cols[$col];
                if (!($colunm instanceof TColumn)) {
                    continue;
                }
                $displayer = $colunm->getDisplayer();
                $displayer->beforRender();
            }

            if ($this->useActionbar) {

                $actionbar->extKey('-' . $this->id . '-' . 0)->beforRender();

                $this->actionbars[0] = $actionbar->render();
            }
        }

        $this->isInitData = true;
    }

    /**
     * 获取表格上方附加行（懒创建）
     *
     * @return Row
     */
    public function addTop()
    {
        if (empty($this->addTop)) {
            $this->addTop = Row::make();
            $this->addTop->class('table-top');
        }

        return $this->addTop;
    }

    /**
     * 获取表格下方附加行（懒创建）
     *
     * @return Row
     */
    public function addBottom()
    {
        if (empty($this->addBottom)) {
            $this->addBottom = Row::make();
            $this->addBottom->class('table-bottom');
        }

        return $this->addBottom;
    }

    /**
     * 创建一个新的fields分组
     *
     * @return FieldsContent
     */
    public function createFields()
    {
        $this->__fields__ = new FieldsContent();
        $this->__fields__->setTable($this);
        return $this->__fields__;
    }

    /**
     * 结束当前fields分组
     *
     * @return $this
     */
    public function fieldsEnd()
    {
        $this->__fields__ = null;
        return $this;
    }

    /**
     * 子类可覆盖，返回要附加到视图的自定义变量
     *
     * @return array
     */
    public function customVars()
    {
        return [];
    }

    /**
     * 获取视图模板路径
     *
     * @return string
     */
    public function getViewTemplate()
    {
        $template = Module::getInstance()->getViewsPath() . 'table.html';

        return $template;
    }

    /**
     * 渲染表格（partial时返回View对象，否则返回HTML字符串）
     *
     * @return string|View
     */
    public function render()
    {
        if ($this->lockForExporting) {
            return 'lockForExporting';
        }

        if (!$this->isInitData) {
            $this->initData();
        }

        // 6.244 冻结默认值（表头+复选框/主键左、操作列右），须在构建模板变量前
        $this->applyFreezeDefaults();

        $viewshow = new View($this->getViewTemplate());

        $count = count($this->data);
        if (!$this->paginator) {
            $this->pageSize = $count ? $count : 10;
            $this->paginator = new Paginator($this->data, $this->pageSize, 1, $count);
            $this->usePagesizeDropdown = false;
        }

        if ($this->paginator->total() <= 6) {
            $this->usePagesizeDropdown = false;
        }

        $sort = $this->sortOrder ?: input('get.__sort__');
        $sortKey = '';
        $sortOrder = '';

        if ($sort) {
            $arr = explode(' ', $sort);
            if (count($arr) == 2) {
                $sortKey = $arr[0];
                $sortOrder = $arr[1];
                if (!empty($this->sortable) && !in_array($sortKey, $this->sortable)) {
                    $this->sortable[] = $sortKey;
                }
            }
        }

        if ($this->usePagesizeDropdown && $this->pageSize && empty($this->pagesizeDropdown)) {
            $items = [
                0 => __blang('builder_pagesize_default'),
                6 => '6',
                10 => '10',
                14 => '14',
                20 => '20',
                30 => '30',
                40 => '40',
                50 => '50',
                60 => '60',
                90 => '90',
                120 => '120',
                200 => '200',
                350 => '350',
            ];

            ksort($items);

            $this->pagesizeDropdown($items);
        }

        $fetchData = input('__fetch_data__') == 'y';

        $emptyText = $this->emptyText;
        if (!$fetchData && $this->delay) {
            $emptyText = '<div class="text-center">' . __blang('builder_loading') . '</div>';
        }

        if ($this->partial && !empty($this->rowScripts)) {
            $this->addBottom()->display('<script>' . PHP_EOL . implode('', array_unique($this->rowScripts)) . PHP_EOL . '</script>');
        }

        $vars = [
            'class' => $this->class,
            'attr' => $this->getAttrWithStyle(),
            'headers' => $this->headers,
            'cols' => $this->cols,
            'list' => $this->list,
            'data' => $this->data,
            'emptyText' => $emptyText,
            'headTextAlign' => $this->headTextAlign,
            'ids' => $this->ids,
            'sortable' => $this->sortable,
            'sortKey' => $sortKey,
            'sortOrder' => $sortOrder,
            'sort' => $sort,
            'useCheckbox' => $this->useCheckbox && $this->useToolbar,
            'name' => time() . mt_rand(1000, 9999),
            'tdClass' => $this->verticalAlign . ' ' . $this->textAlign,
            'verticalAlign' => $this->verticalAlign,
            'textAlign' => $this->textAlign,
            'id' => $this->id,
            'paginator' => $this->paginator,
            'partial' => $this->partial ? 1 : 0,
            'searchForm' => !$this->partial ? $this->searchForm : null,
            'toolbar' => $this->useToolbar && !$this->partial ? $this->toolbar : null,
            'actionbars' => $this->actionbars,
            'actionRowText' => $this->actionRowText,
            'checked' => $this->checked,
            'pagesizeDropdown' => $this->usePagesizeDropdown ? $this->pagesizeDropdown : null,
            'addTop' => $this->addTop,
            'addBottom' => $this->addBottom,
            'freezeHead' => $this->freezeHead,
            'freezeLeftCols' => array_keys($this->freezeLeftCols),
            'freezeRightCols' => array_keys($this->freezeRightCols),
            'freezeOn' => $this->freezeHead || $this->freezeLeftCols || $this->freezeRightCols,
        ];

        $customVars = $this->customVars();

        if (!empty($customVars)) {
            $vars = array_merge($vars, $customVars);
        }

        if ($this->partial) {
            return $viewshow->assign($vars);
        }

        return $viewshow->assign($vars)->getContent();
    }

    /**
     * 转为字符串时返回渲染后的HTML
     *
     * @return string
     */
    public function __toString()
    {
        $this->partial = false;
        return $this->render();
    }

    /**
     * 魔术方法：以displayer类名（小驼峰）创建表格列，如 ->show('name', '名称')
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if ($this->lockForExporting) {
            return  $this->tEmpty;
        }

        $count = count($arguments);

        if ($count > 0 && static::isDisplayer($name)) {

            $col = TColumn::make($arguments[0], $count > 1 ? $arguments[1] : '', $count > 2 ? $arguments[2] : 0);

            $col->setTable($this);

            if ($this->__fields__) {
                $this->__fields__->addCol($col);
            } else {
                $displayer = $col->$name($arguments[0], $count > 1 ? $arguments[1] : '');

                $this->cols[$arguments[0]] = $col;
                $this->headers[$arguments[0]] = $displayer->getLabel();
            }

            $displayer = $col->$name($arguments[0], $count > 1 ? $arguments[1] : '');

            $col->setLabel($displayer->getLabel());

            if ($displayer instanceof MultipleFile) { //表格中默认禁止直接上传图片
                $displayer->canUpload(false);
                $displayer->jsOptions(['istable' => 1]);
            }

            $this->displayers[$name . $arguments[0]] = $displayer;

            return $displayer;
        }

        throw new \InvalidArgumentException(__blang('builder_invalid_argument_exception') . ' : ' . $name);
    }

    /**
     * 创建自身
     *
     * @param mixed $arguments
     * @return static
     */
    public static function make(...$arguments)
    {
        return Widget::makeWidget('Table', $arguments);
    }

    /**
     * 释放资源，销毁所有列、工具栏和搜索表单
     *
     * @return void
     */
    public function destroy()
    {
        // 6.261 已销毁直接返回：同一组件可被多归属路径重复触达（契约见 traits\HasDestroyOnce）
        if ($this->__destroyed__) {
            return;
        }

        $this->__fields__ = null;
        // 6.260 审计补：toolbar/actionbar 是 Toolbar 系（implements ReleaseAble，
        // destroy 递归释放全部 Bar），原来只置空引用=Bar 全泄漏
        if ($this->toolbar instanceof ReleaseAble) {
            $this->toolbar->destroy();
        }
        $this->toolbar = null;
        if ($this->actionbar instanceof ReleaseAble) {
            $this->actionbar->destroy();
        }
        $this->actionbar = null;
        $this->pagesizeDropdown = null;
        foreach ($this->cols as $col) {
            $col->destroy();
        }
        // 6.260 数组属性复位为空数组（保持类型恒定，二次 destroy 自然幂等）；对象引用置 null
        $this->cols = [];
        $this->displayers = [];
        $this->data = [];
        $this->tEmpty = null;
        $this->rowScripts = [];
        $this->ids = [];
        $this->actionbars = [];
        if ($this->searchForm) {
            $this->searchForm->destroy();
            $this->searchForm = null;
        }
        if ($this->addTop) {
            $this->addTop->destroy();
            $this->addTop = null;
        }
        if ($this->addBottom) {
            $this->addBottom->destroy();
            $this->addBottom = null;
        }
        $this->__destroyed__ = true;
    }
}