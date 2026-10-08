<?php

namespace tpext\builder\traits;

trait HasDom
{
    protected $class = '';

    protected $attr = '';

    protected $style = '';

    /**
     * 设置样式类名
     *
     * @param string $val
     * @return $this
     */
    function class($val)
    {
        $this->class = $val;
        return $this;
    }

    /**
     * 设置附加属性
     *
     * @param string $val
     * @return $this
     */
    public function attr($val)
    {
        $this->attr = $val;
        return $this;
    }

    /**
     * 设置样式
     *
     * @param string $val
     * @return $this
     */
    public function style($val)
    {
        $this->style = $val;
        return $this;
    }

    /**
     * 追加样式类名
     *
     * @param string $val
     * @return $this
     */
    public function addClass($val)
    {
        $this->class .= ' ' . $val;
        return $this;
    }

    /**
     * 追加附加属性
     *
     * @param string $val
     * @return $this
     */
    public function addAttr($val)
    {
        $this->attr .= ' ' . $val;
        return $this;
    }

    /**
     * 追加样式
     *
     * @param string $val
     * @return $this
     */
    public function addStyle($val)
    {
        $this->style .= $val;
        return $this;
    }

    /**
     * 获取附加属性
     *
     * @return string
     */
    public function getAttr()
    {
        return $this->attr;
    }

    /**
     * 获取样式
     *
     * @return string
     */
    public function getStyle()
    {
        return $this->style;
    }

    /**
     * 获取含样式的附加属性
     *
     * @return string
     */
    public function getAttrWithStyle()
    {
        return implode(' ', array_unique(explode(' ', $this->attr))) . (empty($this->style) ? '' : ' style="' . $this->style . '"');
    }

    /**
     * 获取样式类名
     *
     * @return string
     */
    public function getClass()
    {
        $arr = explode(' ', $this->class);

        return ' ' . implode(' ', array_unique($arr));
    }
}
