<?php

namespace tpext\builder\displayer;

/**
 * Month月份选择组件（自定义Alpine面板）
 */
class Month extends Date
{
    protected $format = 'MM';
    protected $cssFamily = 'widgets'; // 6.181 按需加载（Alpine 面板，非 flatpickr）

    protected $timespan = '';

    protected $js = [];

    protected $css = [];

    protected $flatpickrOptions = [];

    protected $flatpickrPluginsJs = '';

    protected $checked = '';

    protected $view = 'month';

    /**
     * Year/Month 用自定义 Alpine.js picker，不走 flatpickr。
     * 跳 DateTime::beforRender() 里的 dateTimeScript()
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
            $this->checked = intval($this->value);
        } else if (!empty($this->default)) {
            $this->checked = intval($this->default);
        }

        $vars = $this->commonVars();
        $vars['value'] = $this->checked ?: '';

        $viewshow = $this->getViewInstance();
        return $viewshow->assign($vars)->getContent();
    }
}
