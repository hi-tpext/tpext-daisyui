<?php

namespace tpext\builder\displayer;

/**
 * Raw原样输出组件（不做HTML转义）
 */
class Raw extends Field
{
    protected $view = 'raw';

    protected $isInput = false;
    
    protected $inline = false;

    /**
     * 设置是否行内显示
     *
     * @param boolean $val
     * @return $this
     */
    public function inline($val = true)
    {
        $this->inline = $val;
        return $this;
    }

    /**
     * 是否行内显示（6.485c Fields 组合器据此判定行内流布局）
     *
     * @return boolean
     */
    public function isInline()
    {
        return $this->inline;
    }

    /**
     * 模板变量：行内标记
     *
     * @return array
     */
    public function customVars()
    {
        return [
            'inline' => $this->inline,
        ];
    }
}
