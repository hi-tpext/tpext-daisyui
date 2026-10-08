<?php

namespace tpext\builder\displayer;

use tpext\think\View;

/**
 * Html自定义HTML内容组件
 */
class Html extends Field
{
    protected $view = 'html';

    protected $isInput = false;

    protected $content = null;

    /**
     * 实例化HTML组件
     *
     * @param string $html 内容
     * @param string $label 标签
     */
    public function __construct($html, $label = '')
    {
        $this->label = $label;
        $this->default = $html;
        $this->name = 'html' . mt_rand(100, 999);
    }

    /**
     * 创建组件：同步包装器名称
     *
     * @param string $fieldType
     * @return $this
     */
    public function created($fieldType = '')
    {
        $this->getWrapper()->setName($this->name);
        return parent::created($fieldType);
    }

    /**
     * 用模板文件渲染内容
     *
     * @param string $template
     * @param array $vars
     * @return $this
     */
    public function fetch($template = '', $vars = [])
    {
        $this->content = new View($template);
        $this->content->assign($vars);
        return $this;
    }

    /**
     * 用内容字符串渲染
     *
     * @param string $content
     * @param array $vars
     * @return $this
     */
    public function display($content = '', $vars = [])
    {
        $this->content = new View($content);
        $this->content->assign($vars)->isContent(true);
        return $this;
    }

    /**
     * 获取渲染用值：有内容模板时把值注入__val__后渲染
     *
     * @return string|int|float|null
     */
    public function renderValue()
    {
        $value = parent::renderValue();

        if ($this->content) {
            return $this->content->assign(['__val__' => $value])->getContent();
        }

        return $value;
    }
}
