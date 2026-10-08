<?php

namespace tpext\builder\displayer;

/**
 * Icon图标选择组件
 */
class Icon extends Text
{
    protected $view = 'icon';
    protected $cssFamily = 'widgets'; // 6.181 按需加载

    protected $size = [2, 3];

    protected $js = [];

    protected $css = [];

    protected $jsOptions = [];

    /**
     * 获取图标选择器脚本（改用Alpine + TomSelect实现，返回空）
     *
     * @return string
     */
    protected function iconScript()
    {
        // 图标选择器改用 Alpine + Tom-Select 搜索，无需 fontIconPicker
        return '';
    }

    /**
     * 渲染前处理
     *
     * @return $this
     */
    public function beforRender()
    {
        $this->iconScript();

        return parent::beforRender();
    }
}
