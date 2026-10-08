<?php

namespace tpext\builder\displayer;

/**
 * Textarea多行文本输入组件
 */
class Textarea extends Field
{
    protected $view = 'textarea';

    protected $maxlength = 0;

    protected $rows = 3;

    protected $placeholder = '';

    protected $js = [];

    /**
     * 设置最大输入长度
     *
     * @param integer $val
     * @return $this
     */
    public function maxlength($val = 0)
    {
        $this->maxlength = $val;
        return $this;
    }

    /**
     * 设置行数
     *
     * @param integer $val
     * @return $this
     */
    public function rows($val = 3)
    {
        $this->rows = $val;
        return $this;
    }

    /**
     * 设置占位提示
     *
     * @param string $val
     * @return $this
     */
    public function placeholder($val)
    {
        $this->placeholder = $val;
        return $this;
    }

    /**
     * 渲染为HTML
     *
     * @return mixed
     */
    public function render()
    {
        if ($this->maxlength > 0) {
            $this->attr .= ' maxlength="' . $this->maxlength . '"';
        }

        $this->addAttr('rows="' . $this->rows . '"');

        $vars = $this->commonVars();

        $vars = array_merge($vars, [
            'placeholder' => $this->placeholder ?: __blang('builder_please_enter') . $this->label
        ]);

        $viewshow = $this->getViewInstance();

        return $viewshow->assign($vars)->getContent();
    }
}
