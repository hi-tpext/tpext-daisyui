<?php

namespace tpext\builder\displayer;

use tpext\builder\traits\HasOptions;
use tpext\builder\traits\HasWhen;

/**
 * Transfer穿梭框组件
 */
class Transfer extends Field
{
    use HasOptions;
    use HasWhen;

    protected $view = 'transfer';
    protected $cssFamily = 'widgets'; // 6.181 按需加载

    protected $default = [];

    protected $checked = [];

    protected $disabledOptions = [];

    protected $js = [];

    protected $css = [];

    protected $group = false;

    protected $isArrayValue = true;

    /**
     * 6.211 两侧搜索框默认不显示，showFilter(true) 开启
     */
    protected $showFilter = false;

    protected $jsOptions = [
        'nonSelectedListLabel' => '<span class="help-block">未选择的选项</span>',
        'selectedListLabel' => '<span class="help-block">已选择的选项</span>',
        'filterPlaceHolder' => '筛选',
        'moveSelectedLabel' => "添加",
        'moveAllLabel' => '添加所有',
        'removeSelectedLabel' => "移除",
        'removeAllLabel' => '移除所有',
        'infoText' => '共{0}项',
        'infoTextFiltered' => '搜索到{0}项 ,共{1}项',
        'infoTextEmpty' => '空',
        'filterTextClear' => '清空',
        'moveOnSelect' => true,
        'selectorMinimalHeight' => 100,
    ];

    /**
     * 设置默认选中值
     *
     * @param array|string $val
     * @return $this
     */
    public function default($val = [])
    {
        $this->default = $val;
        return $this;
    }

    /**
     * 设置搜索框占位提示（左右两个搜索框共用，未设置时用通用「搜索」文案）
     *
     * @param string $val
     * @return $this
     */
    public function placeholder($val)
    {
        $this->jsOptions['placeholder'] = $val;
        return $this;
    }

    /**
     * 是否显示搜索框
     *
     * @param boolean $val
     * @return $this
     */
    public function showFilter($val = true)
    {
        $this->showFilter = $val;
        return $this;
    }

    /**
     * 设置禁用的选项
     *
     * @param string|array $val
     * @return $this
     */
    public function disabledOptions($val)
    {
        $this->disabledOptions = $val;
        return $this;
    }

    /**
     * 获取双选框脚本（新版由模板样式实现，返回空）
     *
     * @return string
     */
    protected function dualListScript()
    {
        // 使用模板里的 Tailwind 双选框样式，无需 bootstrap-duallistbox
        return '';
    }

    /**
     * 判断options是否为分组结构
     *
     * @return boolean
     */
    protected function isGroup()
    {
        foreach ($this->options as $option) {

            if (isset($option['options']) && isset($option['label'])) {
                $this->group = true;
                break;
            }
        }

        return $this->group;
    }

    /**
     * 渲染前处理：生成when联动脚本
     *
     * @return $this
     */
    public function beforRender()
    {
        $this->dualListScript();
        $this->whenScript();

        return parent::beforRender();
    }

    /**
     * 渲染为HTML
     *
     * @return mixed
     */
    public function render()
    {
        $vars = $this->commonVars();

        // 6.214 过滤空串：存储值带首尾逗号时（,A,）explode 出空元素，
        // dataSelected 拼回 ',A,' 会让穿梭框回显匹配不上
        if (!($this->value === '' || $this->value === null || $this->value === [])) {
            $this->checked = is_array($this->value) ? $this->value : array_values(array_filter(explode(',', $this->value), 'strlen'));
        } else if (!($this->default === '' || $this->default === null || $this->default === [])) {
            $this->checked = is_array($this->default) ? $this->default : array_values(array_filter(explode(',', $this->default), 'strlen'));
        }

        $this->isGroup();

        $dataSelected = $this->checked;

        foreach ($this->checked as &$ck) {
            $ck = '-' . $ck;
        }

        if ($this->disabledOptions && !is_array($this->disabledOptions)) {
            $this->disabledOptions = explode(',', $this->disabledOptions);
        }
        foreach ($this->disabledOptions as &$di) {
            $di = '-' . $di;
        }

        unset($ck);

        $vars = array_merge($vars, [
            'checked' => $this->checked,
            'dataSelected' => implode(',', $dataSelected),
            'showFilter' => $this->showFilter,
            'group' => $this->group,
            'options' => $this->options,
            'disabledOptions' => $this->disabledOptions,
        ]);

        // 6.299 占位真正接入：placeholder() 写入的 jsOptions['placeholder'] 此前无任何消费方
        // （Alpine transferBox 不读 jsOptions，旧库靠 multiselect 引擎消费，重写后成了死配置）。
        // 未设置时回退搜索框通用文案
        $ph = $this->jsOptions['placeholder'] ?? '';
        $vars['searchPlaceholder'] = $ph !== '' ? $ph : __blang('builder_search');

        $viewshow = $this->getViewInstance();

        return $viewshow->assign($vars)->getContent();
    }
}
