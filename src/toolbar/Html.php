<?php

namespace tpext\builder\toolbar;

class Html extends Bar
{
    protected $view = 'html';

    /**
     * 创建自定义HTML工具栏元素
     *
     * @param string $html
     */
    public function __construct($html)
    {
        $this->label = $html;
    }

    /**
     * 渲染自定义HTML
     *
     * @return mixed
     */
    public function render()
    {
        $vars = $this->commonVars();

        $viewshow = $this->getViewInstance();

        return $viewshow->assign($vars)->getContent();
    }

    /**
     * 空实现，仅为兼容操作栏接口
     *
     * @param string $val
     * @return $this
     */
    public function dataId($val)
    {
        return $this;
    }

    /**
     * 空实现，仅为兼容操作栏接口
     *
     * @param array|\think\Model $data
     * @return $this
     */
    public function parseUrl($data)
    {
        return $this;
    }

    /**
     * 空实现，仅为兼容操作栏接口
     *
     * @param array $data
     * @return $this
     */
    public function parseMapClass($data)
    {
        return $this;
    }
}
