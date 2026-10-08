<?php

namespace tpext\builder\common;

use tpext\think\View;
use tpext\builder\form\When;
use tpext\builder\search\SRow;
use tpext\builder\common\Module;
use tpext\builder\form\Fillable;
use tpext\builder\traits\HasDom;
use tpext\builder\common\Builder;
use tpext\builder\displayer\Text;
use tpext\builder\search\TabLink;
use tpext\builder\displayer\Field;
use tpext\builder\search\SWrapper;
use tpext\builder\displayer\Button;
use tpext\builder\inface\Renderable;
use tpext\builder\inface\ReleaseAble;
use tpext\builder\traits\HasDestroyOnce;
use tpext\builder\form\FieldsContent;

/**
 * Search class
 */
class Search extends SWrapper implements Renderable, ReleaseAble
{
    use HasDom;
    use HasDestroyOnce;

    protected $action = '';

    protected $id = 'search';

    protected $method = 'get';

    /**
     * 搜索表单包含的所有行（普通行或fields分组）
     *
     * @var SRow[]|FieldsContent[]
     */
    protected $rows = [];

    protected $searchButtonsCalled = false;

    protected $ajax = true;

    protected $defaultDisplayerSize = [4, 8];

    protected $defaultDisplayerColSize = 2;

    protected $butonsSizeClass = 'btn-xs';

    protected $open = true;

    protected $tableId = '';

    protected $chooseColumns = ['*'];

    /**
     * 搜索区上方的附加行（懒创建）
     *
     * @var Row|null
     */
    protected $addTop;

    /**
     * 搜索区下方的附加行（懒创建）
     *
     * @var Row|null
     */
    protected $addBottom;

    /**
     * tab切换搜索组件
     *
     * @var TabLink|null
     */
    protected $tablink = null;

    /**
     * 当前fields分组
     *
     * @var FieldsContent|null
     */
    protected $__fields__ = null;

    /**
     * 当前when条件组
     *
     * @var When|null
     */
    protected $__when__ = null;

    /**
     * 初始化默认样式类和展开状态
     *
     * @return $this
     */
    public function created()
    {
        $this->class = 'form-horizontal';

        $this->open = Module::config('search_open') == 1;

        return $this;
    }

    /**
     * 添加一行
     *
     * @param SRow|Fillable $row
     * @return $this
     */
    public function addRow($row)
    {
        $this->rows[] = $row;
        return $this;
    }

    /**
     * 获取所有行
     *
     * @return array|SRow[]
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * 表单是否填充了默认值
     * @return bool
     */
    public function hasDefault(): bool
    {
        if ($this->tablink && !($this->tablink->getActive() === '' || $this->tablink->getActive() === null)) {
            return true;
        }

        foreach ($this->rows as $row) {
            if ($row instanceof SRow) {
                $default = $row->getDisplayer()->getDefault();

                if (!($default === '' || $default === null || $default === [])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 创建一个新的fields分组
     *
     * @return FieldsContent
     */
    public function createFields()
    {
        $this->__fields__ = new FieldsContent();
        $this->__fields__->setForm($this);
        return $this->__fields__;
    }

    /**
     * 创建一个when条件组（监视字段值变化切换字段显示）
     * @param Field $watchFor
     * @param string|int|array $cases
     * @return When
     */
    public function createWhen($watchFor, $cases)
    {
        $this->__when__ = new When();
        $this->__when__->watch($watchFor, $cases);
        $this->__when__->setForm($this);
        return $this->__when__;
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
     * 结束当前when条件组
     *
     * @return $this
     */
    public function whenEnd()
    {
        $this->__when__ = null;
        return $this;
    }

    /**
     * 设置关联的表格id
     *
     * @param string $tableId
     * @return $this
     */
    public function setTableId($tableId)
    {
        $this->tableId = $tableId;
        $this->id = 'search-' . $this->tableId;
        return $this;
    }

    /**
     * 设置搜索表单提交方式
     *
     * @param string $val
     * @return $this
     */
    public function method($val)
    {
        $this->method = $val;
        return $this;
    }

    /**
     * 设置搜索表单是否默认展开
     *
     * @param boolean $val
     * @return $this
     */
    public function open($val = true)
    {
        $this->open = $val;
        return $this;
    }

    /**
     * 获取搜索表单id
     *
     * @return string
     */
    public function getFormId()
    {
        return $this->id;
    }

    /**
     * 创建tab切换搜索组件（点击tab按指定字段值过滤）
     *
     * @param string $key 触发字段
     * @return TabLink
     */
    public function tabLink($key)
    {
        if (empty($this->tablink)) {
            $this->tablink = new TabLink();
            $this->tablink->key($key);
        }

        return $this->tablink;
    }

    /**
     * 获取搜索区上方附加行（懒创建）
     *
     * @return Row
     */
    public function addTop()
    {
        if (empty($this->addTop)) {
            $this->addTop = Row::make();
            $this->addTop->class('search-top');
        }

        return $this->addTop;
    }

    /**
     * 获取搜索区下方附加行（懒创建）
     *
     * @return Row
     */
    public function addBottom()
    {
        if (empty($this->addBottom)) {
            $this->addBottom = Row::make();
            $this->addBottom->class('search-bottom');
        }

        return $this->addBottom;
    }

    /**
     * 设置按钮尺寸类
     * btn-lg btn-sm btn-xs
     * @param string $val
     * @return $this
     */
    public function butonsSizeClass($val)
    {
        $this->butonsSizeClass = $val;
        return $this;
    }

    /**
     * 设置字段默认的label/元素宽度
     *
     * @param integer $label
     * @param integer $element
     * @return $this
     */
    public function defaultDisplayerSize($label = 4, $element = 8)
    {
        $this->defaultDisplayerSize = [$label, $element];
        return $this;
    }

    /**
     * 设置字段默认占列数
     *
     * @param integer $size
     * @return $this
     */
    public function defaultDisplayerColSize($size = 2)
    {
        $this->defaultDisplayerColSize = $size;
        return $this;
    }

    /**
     * 设置默认显示的字段
     *
     * @param array $val 默认显示的字段
     * @return $this
     */
    public function chooseColumns($val)
    {
        $this->chooseColumns = $val;
        return $this;
    }

    /**
     * 生成默认的筛选/重置按钮区
     *
     * @param boolean $create
     * @return $this
     */
    public function searchButtons($create = true)
    {
        if ($create) {
            $this->fieldsEnd();
            $this->fields('search_buttons', ' ', '3 col-lg-3 col-sm-12 col-xs-12 search-buttons')
                ->size('3 col-lg-4 col-sm-2 col-xs-12', '9 col-lg-8 col-sm-8 col-xs-12')
                ->with(
                    $this->button('submit', __blang('builder_button_filter'), '6 col-lg-6 col-sm-6 col-xs-6')->class('btn-info ' . $this->butonsSizeClass),
                    // 6.395 重置为次级色（无色按钮统一映射 btn-secondary）
                    $this->button('button', __blang('builder_button_reset'), '6 col-lg-6 col-sm-6 col-xs-6')->class('btn-secondary ' . $this->butonsSizeClass)->attr('onclick="location.replace(location.href)"')
                );
        }

        $this->searchButtonsCalled = true;
        return $this;
    }

    /**
     * 添加筛选按钮
     *
     * @param string $label
     * @param integer|string $size
     * @param string $class
     * @return $this
     */
    public function btnSubmit($label = '筛&nbsp;&nbsp;选', $size = '2 col-lg-2 col-sm-6 col-xs-12', $class = 'btn-info')
    {
        if ($label == '筛&nbsp;&nbsp;选') {
            $label = __blang('builder_button_filter');
        }
        $this->fieldsEnd();
        $this->button('submit', $label, $size)->class($class . ' ' . $this->butonsSizeClass);
        $this->searchButtonsCalled = true;
        return $this;
    }

    /**
     * 添加重置按钮
     *
     * @param string $label
     * @param integer|string $size
     * @param string $class
     * @return $this
     */
    public function btnReset($label = '重&nbsp;&nbsp;置', $size = '2 col-lg-2 col-sm-6 col-xs-12', $class = 'btn-secondary')
    {
        if ($label == '重&nbsp;&nbsp;置') {
            $label = __blang('builder_button_reset');
        }
        $this->button('reset', $label, $size)->class($class . ' ' . $this->butonsSizeClass)->addAttr('onclick="location.replace(location.href)"');
        return $this;
    }

    /**
     * 渲染前的准备：补充隐藏域、按钮区、交互脚本
     *
     * @return $this
     */
    public function beforRender()
    {
        if (!$this->open) {
            $this->addClass('hidden');
        }

        $empty = empty($this->rows);

        if (!$empty) {
            if (!$this->searchButtonsCalled) {
                $this->searchButtons();
            }
        } else {
            $this->addClass('form-empty');
            $this->button('submit', 'submit', '1')->getWrapper()->addClass('hidden');
        }

        $this->hidden('__page__')->value(1);
        $this->hidden('__pagesize__');
        $this->hidden('__search__')->value($this->getFormId());
        $this->hidden('__table__')->value($this->tableId);
        $this->hidden('__sort__');
        $this->hidden('__columns__')->value(implode(',', $this->chooseColumns));
        $this->hidden('__fetch_data__')->value('y');

        $this->addClass('search-form');
        $this->button('refresh', 'refresh', '1')->addClass('search-refresh')->getWrapper()->addClass('hidden');
        $this->searchScript();

        foreach ($this->rows as $row) {
            if ($row instanceof SRow) {
                $row->getDisplayer()->extKey('-' . $this->tableId);
            }
            $row->beforRender();
        }

        if ($this->tablink) {
            $this->tablink->searchId($this->getFormId());
            $this->tablink->beforRender();
        }

        if (!in_array(strtolower($this->method), ['get', 'post'])) {
            $this->hidden('_method')->value($this->method);
            $this->method = 'post';
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
     * 生成搜索交互脚本（翻页/跳页/改页大小/排序/导出/列选择/展开收起等）
     *
     * @return string
     */
    protected function searchScript()
    {
        $form = $this->getFormId();

        $extKey = '-' . $this->tableId;

        $script = <<<EOT

        // searchScript — 原生 JS 实现（替代原 jQuery 版）
        // layer.msg/lightyear.notify 等由 tpb.js 兼容层提供
        (function(formId, tableId, extKey) {
            function layerApi() { return window.layer || window.tpb; }
            var notifyApi = (window.tpb && window.tpb.notify) || (window.lightyear && window.lightyear.notify);

            // 6.255 导出点击即发 AJAX（不再先弹确认框）：后端生成文件返回下载 URL 后，
            // exportPost 内部弹 downloadBox 供用户点击下载
            function runExport(url, fileType) {
                if (window.__forms__ && window.__forms__[formId]) window.__forms__[formId].exportPost(url, fileType, 1);
            }

            document.addEventListener('keydown', function(event) {
                if ((event.key === 'Enter' || event.keyCode === 13) && !event.target.closest('textarea')) {
                    var form = document.querySelector('#' + formId + ' form');
                    if (!form || form.classList.contains('form-empty')) return;
                    if (document.querySelectorAll('form').length > 1) return;
                    if (window.__forms__ && window.__forms__[formId]) {
                        window.__forms__[formId].formSubmit();
                    }
                    event.preventDefault();
                }
                if ((event.key === 'Escape' || event.keyCode === 0x1B) && layerApi()) {
                    var form = document.querySelector('#' + formId + ' form');
                    if (!form || form.classList.contains('form-empty')) return;
                    var index = layerApi().msg(__blang.builder_reset_filter_criteria, {
                        time: 2000,
                        btn: [__blang.builder_button_ok, __blang.builder_button_cancel],
                        yes: function () {
                            layerApi().close(index);
                            location.replace(location.href);
                        }
                    });
                    event.preventDefault();
                }
            });

            document.addEventListener('click', function(e) {
                // pagination 翻页
                var pageLink = e.target.closest('#' + tableId + ' ul.pagination li a:not(.goto-page)');
                if (pageLink) {
                    var m = pageLink.getAttribute('href').match(/\?page=(\d+)/);
                    if (m) {
                        var form = document.querySelector('#' + formId + ' form');
                        if (form) { form.querySelector('input[name="__page__"]').value = m[1]; }
                        if (window.__forms__ && window.__forms__[formId]) {
                            window.__forms__[formId].formSubmit();
                        }
                    }
                    e.preventDefault();
                    return;
                }

                // pagination goto-page
                var gotoBtn = e.target.closest('#' + tableId + ' ul.pagination .goto-page');
                if (gotoBtn && layerApi()) {
                    var last = parseInt(gotoBtn.getAttribute('data-last')) || 1;
                    layerApi().prompt({
                        formType: 0, value: '',
                        btn: [__blang.builder_button_ok, __blang.builder_button_cancel],
                        title: __blang.builder_please_enter_the_page_number + '(1~' + last + ')'
                    }, function(value) {
                        var page = parseInt(value);
                        if (!page || page < 1) { layerApi().msg(__blang.builder_page_number_input_error, {time: 1500}); return; }
                        if (page > last) { layerApi().msg(__blang.builder_page_number_cannot_exceed + ' :' + last, {time: 1500}); return; }
                        var form = document.querySelector('#' + formId + ' form');
                        if (form) { form.querySelector('input[name="__page__"]').value = page; }
                        if (window.__forms__ && window.__forms__[formId]) window.__forms__[formId].formSubmit();
                    });
                    e.preventDefault();
                    return;
                }

                // pagesize dropdown
                var psLink = e.target.closest('#' + tableId + ' #dropdown-pagesize-div .dropdown-content li a');
                if (psLink) {
                    var pagesize = psLink.getAttribute('data-key');
                    var form = document.querySelector('#' + formId + ' form');
                    var oldsize = form ? form.querySelector('input[name="__pagesize__"]').value : null;
                    if (pagesize == oldsize) return;
                    if (pagesize > oldsize && form) { form.querySelector('input[name="__page__"]').value = 1; }
                    if (form) { form.querySelector('input[name="__pagesize__"]').value = pagesize; }
                    var psTxt = document.querySelector('#' + tableId + ' #dropdown-pagesize-div .pagesize-text');
                    if (psTxt) psTxt.textContent = pagesize;
                    if (window.__forms__ && window.__forms__[formId]) window.__forms__[formId].formSubmit();
                    e.preventDefault();
                    return;
                }

                // refresh
                if (e.target.closest('#btn-refresh' + extKey + ', #form-refresh' + extKey)) {
                    if (window.__forms__ && window.__forms__[formId]) window.__forms__[formId].formSubmit();
                    return;
                }

                // btn-search 展开/收起
                if (e.target.closest('#btn-search' + extKey)) {
                    var searchForm = document.querySelector('#' + formId + ' form');
                    if (searchForm) searchForm.classList.toggle('hidden');
                    return;
                }

                // export
                var exportBtn = e.target.closest('#btn-export' + extKey);
                if (exportBtn) {
                    var url = exportBtn.getAttribute('data-export-url');
                    runExport(url, '');
                    return;
                }

                // dropdown-exports
                var exportLink = e.target.closest('#dropdown-exports' + extKey + '-div .dropdown-content li a');
                if (exportLink) {
                    var exportBtn = document.getElementById('dropdown-exports' + extKey);
                    var url = exportBtn ? exportBtn.getAttribute('data-export-url') : '';
                    var fileType = exportLink.getAttribute('data-key');
                    runExport(url, fileType);
                    e.preventDefault();
                    return;
                }

                // choose_columns dropdown
                if (e.target.closest('#dropdown-choose_columns' + extKey + '-div .dropdown-content')) {
                    e.stopPropagation();
                    var colLink = e.target.closest('#dropdown-choose_columns' + extKey + '-div .dropdown-content li a');
                    if (colLink) {
                        colLink.classList.toggle('checked');
                        var icon = colLink.querySelector('i');
                        if (icon) {
                            if (colLink.classList.contains('checked')) {
                                icon.classList.remove('mdi-checkbox-blank-outline');
                                icon.classList.add('mdi-checkbox-marked-outline');
                            } else {
                                icon.classList.remove('mdi-checkbox-marked-outline');
                                icon.classList.add('mdi-checkbox-blank-outline');
                            }
                        }
                        var checkedCols = document.querySelectorAll('#dropdown-choose_columns' + extKey + '-div .dropdown-content li a.checked');
                        var columns = [];
                        checkedCols.forEach(function(a) { columns.push(a.getAttribute('data-key')); });
                        if (!columns.length) {
                            if (notifyApi) notifyApi(__blang.builder_show_at_least_one_field, 'warning');
                            return;
                        }
                        // 防抖：连续勾选多列时重置计时器，只在最后一次点击后提交一次请求
                        clearTimeout(window.__columnsTimer);
                        window.__columnsTimer = setTimeout(function() {
                            var form = document.querySelector('#' + formId + ' form');
                            if (form) { form.querySelector('input[name="__columns__"]').value = columns.join(','); }
                            if (window.__forms__ && window.__forms__[formId]) window.__forms__[formId].formSubmit();
                        }, 600);
                        e.preventDefault();
                    }
                    return;
                }

                // form-submit (搜索按钮)
                if (e.target.closest('#form-submit' + extKey)) {
                    var form = document.querySelector('#' + formId + ' form');
                    if (form) { form.querySelector('input[name="__page__"]').value = 1; }
                    if (window.__forms__ && window.__forms__[formId]) window.__forms__[formId].formSubmit();
                    return;
                }

                // .sortable 点击排序
                var sortLink = e.target.closest('.table .sortable');
                if (sortLink) {
                    var sort = '';
                    var hasDesc = sortLink.classList.contains('mdi-sort-descending');
                    sort = sortLink.getAttribute('data-key') + (hasDesc ? ' asc' : ' desc');
                    document.querySelectorAll('.sortable.mdi-sort-ascending, .sortable.mdi-sort-descending').forEach(function(el) {
                        el.classList.remove('mdi-sort-ascending', 'mdi-sort-descending');
                        el.classList.add('mdi-sort');
                    });
                    if (hasDesc) {
                        sortLink.classList.remove('mdi-sort-descending');
                        sortLink.classList.add('mdi-sort-ascending');
                    } else {
                        sortLink.classList.remove('mdi-sort');
                        sortLink.classList.add('mdi-sort-descending');
                    }
                    var form = document.querySelector('#' + formId + ' form');
                    if (form) { form.querySelector('input[name="__sort__"]').value = sort; }
                    if (window.__forms__ && window.__forms__[formId]) window.__forms__[formId].formSubmit();
                    e.preventDefault();
                    return;
                }
            });

            // 初始 btn-search 可见性
            (function() {
                var form = document.querySelector('#' + formId + ' form');
                var btnSearch = document.getElementById('btn-search' + extKey);
                var wrap = document.getElementById(formId);
                if (!form) return;
                if (form.classList.contains('form-empty')) {
                    if (wrap) wrap.classList.add('hidden');
                } else {
                    if (btnSearch) btnSearch.classList.remove('hidden');
                }
            })();
        })('{$form}', '{$this->tableId}', '{$extKey}');

EOT;
        Builder::getInstance()->addScript($script);

        return $script;
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
        $template = Module::getInstance()->getViewsPath() . 'table' . DIRECTORY_SEPARATOR . 'search.html';

        return $template;
    }

    /**
     * 渲染搜索表单为HTML
     *
     * @return string
     */
    public function render()
    {
        $viewshow = new View($this->getViewTemplate());

        $vars = [
            'rows' => $this->rows,
            'action' => $this->action,
            'method' => strtoupper($this->method),
            'class' => $this->class,
            'attr' => $this->getAttrWithStyle(),
            'id' => $this->getFormId(),
            'ajax' => $this->ajax,
            'searchFor' => $this->tableId,
            'tablink' => $this->tablink,
            'addTop' => $this->addTop,
            'addBottom' => $this->addBottom,
        ];

        $customVars = $this->customVars();

        if (!empty($customVars)) {
            $vars = array_merge($vars, $customVars);
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
        return $this->render();
    }

    /**
     * 魔术方法：以displayer类名（小驼峰）创建搜索字段，如 ->text('name', '名称')
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $count = count($arguments);

        if ($count > 0 && static::isDisplayer($name)) {

            $row = SRow::make($arguments[0], $count > 1 ? $arguments[1] : '', $count > 2 ? $arguments[2] : $this->defaultDisplayerColSize, $count > 3 ? $arguments[3] : '');

            if ($this->__fields__) {
                $this->__fields__->addRow($row);
            } else {
                $this->rows[] = $row;
            }

            $row->setForm($this);

            $displayer = $row->$name($arguments[0], $count > 1 ? $arguments[1] : '');

            $row->setLabel($displayer->getLabel());

            if ($this->__when__) {
                $this->__when__->toggle($displayer);
            }

            if ($this->defaultDisplayerSize) {
                $displayer->size($this->defaultDisplayerSize[0], $this->defaultDisplayerSize[1]);
            }

            $displayer->extKey('-' . $this->tableId);

            if ($displayer instanceof Text) {
                $displayer->befor('');
                $displayer->after('');
            } else if ($displayer instanceof Button) {
                $displayer->size(0, 12);
            }

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
        return Widget::makeWidget('Search', $arguments);
    }

    /**
     * 释放资源，销毁所有行和附加行
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
        $this->__when__ = null;
        if ($this->addTop) {
            $this->addTop->destroy();
            $this->addTop = null;
        }
        if ($this->addBottom) {
            $this->addBottom->destroy();
            $this->addBottom = null;
        }
        $this->tablink = null;
        foreach ($this->rows as $row) {
            if ($row instanceof ReleaseAble) {
                $row->destroy();
            }
        }
        // 6.260 数组属性复位为空数组（保持类型恒定，二次 destroy 自然幂等）
        $this->rows = [];
        $this->__destroyed__ = true;
    }
}
