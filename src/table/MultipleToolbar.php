<?php

namespace tpext\builder\table;

use think\facade\Session;
use tpext\builder\common\Module;
use tpext\builder\common\Builder;
use tpext\builder\common\Toolbar;

class MultipleToolbar extends Toolbar
{
    protected $useSearch = false;

    protected $btnSearch = null;

    protected $useExport = true;

    protected $useChooseColumns = ['*'];

    protected $btnExport = null;

    protected $tableCols = [];

    protected $actions = [];

    /**
     * 设置表格列
     *
     * @param array $cols
     * @return $this
     */
    public function setTableCols($cols)
    {
        $this->tableCols = $cols;

        return $this;
    }

    /**
     * 设置所有按钮使用弹层方式打开
     *
     * @param boolean $val
     * @param array|string $size
     * @return $this
     */
    public function useLayerAll($val, $size = [])
    {
        foreach ($this->elms as $elm) {
            $elm->useLayer($val, $size);
        }

        return $this;
    }

    /**
     * 创建工具栏
     *
     * @param string $barType
     * @return $this
     */
    public function created($barType = '')
    {
        parent::created();
        return $this;
    }

    /**
     * 设置是否显示搜索按钮
     *
     * @param boolean $val
     * @return $this
     */
    public function useSearch($val = true)
    {
        $this->useSearch = $val;

        return $this;
    }

    /**
     * 设置是否显示导出按钮
     *
     * @param boolean $val
     * @return $this
     */
    public function useExport($val = true)
    {
        $this->useExport = $val;

        return $this;
    }

    /**
     * 设置可选择显示的列
     *
     * @param boolean|array|string $val 默认显示的字段，false则禁用
     * @return $this
     */
    public function useChooseColumns($val = ['*'])
    {
        if ($val === true) {
            $val = ['*'];
        } else if (empty($val)) {
            $val = [];
        } else if (is_string($val)) {
            $val = explode(',', $val);
        }

        $this->useChooseColumns = $val;

        return $this;
    }

    /**
     * 获取默认显示的列
     *
     * @return array
     */
    public function getChooseColumns()
    {
        return is_array($this->useChooseColumns) ? $this->useChooseColumns : [];
    }

    /**
     * 渲染前处理，生成默认按钮及导出、搜索、显示列按钮
     *
     * @return $this
     */
    public function beforRender()
    {
        if (empty($this->elms)) {
            $this->buttons();
        }

        if ($this->useChooseColumns) {
            $items = [];

            foreach ($this->tableCols as $col) {
                $name = $col->getName();
                $checked = $this->useChooseColumns[0] == '*' || in_array($name, $this->useChooseColumns);
                $items[] = [
                    'key' => $col->getName(),
                    'label' => preg_replace('/<[bh]r\s*\/?>/i', '', $col->getLabel()),
                    'icon' => $checked ? 'mdi-checkbox-marked-outline' : 'mdi-checkbox-blank-outline',
                    'url' => '#',
                    'attr' => '',
                    'class' => $checked ? 'checked' : '',
                ];
            }

            $this->btnChooseColumns($items);
        }

        if ($this->useExport && !$this->btnExport) {
            $this->btnExports();
        }

        if ($this->useSearch && !$this->btnSearch) {
            $this->btnToggleSearch();
        }

        return parent::beforRender();
    }

    /**
     * 添加默认的新增、删除、刷新按钮
     *
     * @return $this
     */
    public function buttons()
    {
        $this->btnAdd();
        $this->btnDelete();
        $this->btnRefresh();

        return $this;
    }

    /**
     * 添加新增按钮
     *
     * @param string $url
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @return $this
     */
    public function btnAdd($url = '', $label = '添加', $class = 'btn-primary', $icon = 'mdi-plus', $attr = '')
    {
        if (empty($url)) {
            $url = url('add');
        }
        if ($label == '添加') {
            $label = __blang('builder_action_add');
        }
        $this->actions['add'] = 'add';
        $this->linkBtn('add', $label)->href($url)->icon($icon)->addClass($class)->addAttr($attr);
        return $this;
    }

    /**
     * 添加批量删除按钮（作用于勾选行）
     *
     * @param string $postUrl
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @param boolean|string $confirm
     * @return $this
     */
    public function btnDelete($postUrl = '', $label = '删除', $class = 'btn-error', $icon = 'mdi-delete', $attr = '', $confirm = true)
    {
        if (empty($postUrl)) {
            $postUrl = url('delete');
        }
        if ($label == '删除') {
            $label = __blang('builder_action_delete');
        }
        $this->actions['delete'] = 'delete';
        $this->linkBtn('delete', $label)->postChecked($postUrl, $confirm)->addClass($class)->icon($icon)->addAttr($attr);
        return $this;
    }

    /**
     * 添加批量禁用按钮（作用于勾选行）
     *
     * @param string $postUrl
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @param boolean|string $confirm
     * @return $this
     */
    public function btnDisable($postUrl = '', $label = '禁用', $class = 'btn-warning', $icon = 'mdi-block-helper', $attr = '', $confirm = true)
    {
        if (empty($postUrl)) {
            $postUrl = url('enable', ['state' => 0]);
        }
        if ($label == '禁用') {
            $label = __blang('builder_action_disable');
        }
        $this->actions['disable'] = 'disable';
        $this->linkBtn('disable', $label)->postChecked($postUrl, $confirm)->addClass($class)->icon($icon)->addAttr($attr);
        return $this;
    }

    /**
     * 添加批量启用按钮（作用于勾选行）
     *
     * @param string $postUrl
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @param boolean|string $confirm
     * @return $this
     */
    public function btnEnable($postUrl = '', $label = '启用', $class = 'btn-success', $icon = 'mdi-check', $attr = '', $confirm = true)
    {
        if (empty($postUrl)) {
            $postUrl = url('enable', ['state' => 1]);
        }
        if ($label == '启用') {
            $label = __blang('builder_action_enable');
        }
        $this->actions['enable'] = 'enable';
        $this->linkBtn('enable', $label)->postChecked($postUrl, $confirm)->addClass($class)->icon($icon)->addAttr($attr);
        return $this;
    }

    /**
     * 添加批量启用/禁用一对按钮（作用于勾选行）
     *
     * @param string $enableTitle
     * @param string $disableTitle
     * @return $this
     */
    public function btnEnableAndDisable($enableTitle = '启用', $disableTitle = '禁用')
    {
        if ($enableTitle == '启用') {
            $enableTitle = __blang('builder_action_enable');
        }
        if ($disableTitle == '禁用') {
            $disableTitle = __blang('builder_action_disable');
        }
        $this->btnEnable()->getCurrent()->attr('title="' . $enableTitle . '"')->label($enableTitle);
        $this->btnDisable()->getCurrent()->attr('title="' . $disableTitle . '"')->label($disableTitle);

        return $this;
    }

    /**
     * 添加刷新按钮
     *
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @return $this
     */
    public function btnRefresh($label = '', $class = 'btn-cyan', $icon = 'mdi-refresh', $attr = 'title="刷新"')
    {
        if ($attr == 'title="刷新"') {
            $attr = 'title="' . __blang('builder_action_refresh') . '"';
        }
        $this->actions['refresh'] = 'refresh';
        $this->linkBtn('refresh', $label)->addClass($class)->icon($icon)->addAttr($attr);
        return $this;
    }

    /**
     * 添加显示/隐藏搜索栏按钮
     *
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @return $this
     */
    public function btnToggleSearch($label = '', $class = 'btn-secondary', $icon = 'mdi-magnify', $attr = 'title="搜索"')
    {
        if ($attr == 'title="搜索"') {
            $attr = 'title="' . __blang('builder_action_search') . '"';
        }
        $this->actions['search'] = 'search';
        $this->linkBtn('search', $label)->addClass($class)->icon($icon)->addClass('hidden')->addAttr($attr);

        $this->btnSearch = true;
        return $this;
    }

    /**
     * 添加导入按钮，打开数据导入页面
     *
     * @param string $afterSuccessUrl
     * @param string|array $acceptedExts
     * @param array $layerSize
     * @param int $fileSize MB
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @param string $driver
     * @return $this
     */
    public function btnImport($afterSuccessUrl = '', $acceptedExts = "rar,zip,doc,docx,xls,xlsx,ppt,pptx,pdf", $layerSize = ['800px', '550px'], $fileSize = '20', $label = '导入', $class = 'btn-pink', $icon = 'mdi-cloud-upload', $attr = 'title="上传文件"', $driver = '\\tpext\\builder\\logic\\LocalStorage')
    {
        if (empty($afterSuccessUrl)) {
            $afterSuccessUrl = url('/admin/import/afterSuccess');
        }

        if (is_array($acceptedExts)) {
            $acceptedExts = implode(',', $acceptedExts);
        }

        $afterSuccessUrl = urlencode($afterSuccessUrl);

        $afterSuccessUrl = preg_replace('/(.+?)(\.html)?$/', '$1', $afterSuccessUrl);

        $importpagetoken = Session::has('importpagetoken') ? Session::get('importpagetoken') : md5('importpagetoken' . time() . uniqid());

        Session::set('importpagetoken', $importpagetoken);

        $driver = str_replace('\\', '-', $driver);

        $pagetoken = md5($importpagetoken . $acceptedExts . $fileSize);

        $url = url(Module::getInstance()->getImportUrl()) . '?successUrl=' . $afterSuccessUrl . '&acceptedExts=' . $acceptedExts . '&fileSize=' . $fileSize . '&pageToken=' . $pagetoken . '&driver=' . $driver;

        if ($label == '导入') {
            $label = __blang('builder_action_import');
        }
        if ($attr == 'title="上传文件"') {
            $attr = 'title="' . __blang('builder_action_upload_file') . '"';
        }
        $this->actions['import'] = 'import';
        $this->linkBtn('import', $label)->useLayer(true, $layerSize)->href($url)->icon($icon)->addClass($class)->addAttr($attr);

        return $this;
    }

    /**
     * 添加导出按钮
     *
     * @param string $postUrl
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @return $this
     */
    public function btnExport($postUrl = '', $label = '导出', $class = 'btn-secondary', $icon = 'mdi-export', $attr = 'title="导出"')
    {
        if (empty($postUrl)) {
            $postUrl = url('export');
        }

        if (!Builder::checkUrl($postUrl)) {
            return $this;
        }
        if ($label == '导出') {
            $label = __blang('builder_action_export');
        }
        if ($attr == 'title="导出"') {
            $attr = 'title="' . __blang('builder_action_export') . '"';
        }
        $this->actions['export'] = 'export';
        $this->linkBtn('export', $label)->addClass($class)->icon($icon)->addAttr($attr . ' data-export-url="' . $postUrl . '"');
        return $this;
    }

    /**
     * 添加导出格式下拉按钮组
     *
     * @param array $items ['csv' => '导出csv', 'xls' => '导出xls', 'xlsx' => '导出xlsx']
     * @param string $postUrl
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @return $this
     */
    public function btnExports($items = [], $postUrl = '', $label = '导出', $class = 'btn-secondary', $icon = 'mdi-export', $attr = 'title="导出"')
    {
        if (empty($postUrl)) {
            $postUrl = url('export');
        }

        if (empty($items)) {
            $items = ['csv' => __blang('builder_action_export_csv')];

            if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet') || class_exists('\\Vtiful\\Kernel\\Excel') || class_exists('\\PHPExcel')) {
                $items = array_merge($items, [
                    'xlsx' => __blang('builder_action_export_xlsx'),
                ]);
            }
        }

        $this->btnExport = true;

        if (!Builder::checkUrl($postUrl)) {
            return $this;
        }
        if ($label == '导出') {
            $label = __blang('builder_action_export');
        }
        if ($attr == 'title="导出"') {
            $attr = 'title="' . __blang('builder_action_export') . '"';
        }
        $this->actions['exports'] = 'exports';
        $this->dropdownBtns('exports', $label)->items($items)->groupClass('drp-exports')->addClass($class)->icon($icon)
            ->addAttr($attr . ' data-export-url="' . $postUrl . '"')->pullRight();
        return $this;
    }

    /**
     * 添加显示列选择下拉按钮组
     *
     * @param array $items
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @return $this
     */
    public function btnChooseColumns($items, $label = '显示列', $class = 'btn-secondary', $icon = 'mdi-grid', $attr = 'title="选择要显示的列"')
    {
        if ($label == '显示列') {
            $label = __blang('builder_action_columns');
        }
        if ($attr == 'title="选择要显示的列"') {
            $attr = 'title="' . __blang('builder_action_choose_columns') . '"';
        }
        $this->dropdownBtns('choose_columns', $label)->items($items)->groupClass('drp-choose_columns')
            ->addClass($class)->icon($icon)->addAttr($attr)->pullRight();
        return $this;
    }

    /**
     * 添加链接按钮
     *
     * @param string $url
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @return $this
     */
    public function btnLink($url, $label = '', $class = 'btn-secondary', $icon = 'mdi-checkbox-marked-outline', $attr = '')
    {
        $action = preg_replace('/.+?\/(\w+)(\.\w+)?$/', '$1', $url, -1, $count);

        if (!$count) {
            $action = preg_replace('/\W/', '_', $url);
        }

        if (isset($this->actions[$action])) {
            $action .= mt_rand(100, 999);
        }

        $this->actions[$action] = $action;

        $this->linkBtn($action, $label)->href($url)->icon($icon)->addClass($class)->addAttr($attr);

        return $this;
    }

    /**
     * 添加对勾选行提交 POST 请求的按钮
     *
     * @param string $url
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @param boolean|string $confirm
     * @return $this
     *
     */
    public function btnPostChecked($url, $label = '', $class = 'btn-secondary', $icon = 'mdi-checkbox-marked-outline', $attr = '', $confirm = true)
    {
        $action = preg_replace('/.+?\/(\w+)(\.\w+)?$/', '$1', $url, -1, $count);

        if (!$count) {
            $action = preg_replace('/\W/', '_', $url);
        }

        if (isset($this->actions[$action])) {
            $action .= mt_rand(100, 999);
        }

        $this->actions[$action] = $action;

        $this->linkBtn($action, $label)->postChecked($url, $confirm)->addClass($class)->icon($icon)->addAttr($attr);

        return $this;
    }

    /**
     * 添加打开勾选行数据页面（如编辑页）的按钮
     *
     * @param string $url
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @param boolean|string $confirm
     * @return $this
     *
     */
    public function btnOpenChecked($url, $label = '', $class = 'btn-secondary', $icon = 'mdi-checkbox-marked-outline', $attr = '')
    {
        $action = preg_replace('/.+?\/(\w+)(\.\w+)?$/', '$1', $url, -1, $count);

        if (!$count) {
            $action = preg_replace('/\W/', '_', $url);
        }

        if (isset($this->actions[$action])) {
            $action .= mt_rand(100, 999);
        }

        $this->actions[$action] = $action;

        $this->linkBtn($action, $label)->openChecked($url)->addClass($class)->icon($icon)->addAttr($attr);

        return $this;
    }

    /**
     * 设置自定义 HTML 内容
     *
     * @param string $val
     * @return $this
     */
    public function html($val)
    {
        parent::html($val);
        return $this;
    }
}
