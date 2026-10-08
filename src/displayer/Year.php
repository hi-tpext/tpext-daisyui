<?php

namespace tpext\builder\displayer;

/**
 * Year年份选择组件（自定义Alpine面板）
 */
class Year extends Date
{
    protected $format = 'YYYY';
    protected $cssFamily = 'widgets'; // 6.181 按需加载（Alpine 面板，非 flatpickr）

    protected $timespan = 'Y';

    protected $js = [];

    protected $css = [];

    protected $flatpickrOptions = [];

    protected $flatpickrPluginsJs = '';

    protected $checked = '';

    protected $view = 'year';

    /**
     * Year/Month 用自定义 Alpine.js picker，不走 flatpickr。
     * 跳 DateTime::beforRender() 里的 dateTimeScript()（那会初始化 flatpickr 到 hidden input 上，
     * 把它变成 inline-block 占 21px 高度，把下方的 .relative 按钮容器挤下去）
     */
    /**
     * 渲染前处理：跳过flatpickr初始化
     *
     * @return $this
     */
    public function beforRender()
    {
        return Field::beforRender();
    }

    /**
     * 渲染为HTML
     *
     * @return mixed
     */
    public function render()
    {
        if (!empty($this->value)) {
            $this->checked = $this->value;
        } else if (!empty($this->default)) {
            $this->checked = $this->default;
        }

        $vars = $this->commonVars();
        $vars['checked'] = $this->checked;

        $viewshow = $this->getViewInstance();
        return $viewshow->assign($vars)->getContent();
    }
}
