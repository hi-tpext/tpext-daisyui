<?php

namespace tpext\builder\displayer;

use tpext\builder\traits\HasOptions;

/**
 * Matche值匹配转义展示组件（把值转为options中的对应文本）
 */
class Matche extends Raw
{
    use HasOptions;

    protected $view = 'matche';

    protected $isInput = false;

    protected $checked = '';

    /**
     * 渲染前处理：确定当前选中值
     *
     * @return $this
     */
    public function beforRender()
    {
        $this->checked = !($this->value === '' || $this->value === null) ? $this->value : $this->default;
        return parent::beforRender();
    }

    /**
     * 获取渲染用值：匹配options转换为文本
     *
     * @return string|int|float|null
     */
    public function renderValue()
    {
        $this->value = !($this->value === '' || $this->value === null) ? $this->value : $this->default;

        if (isset($this->options[$this->value])) {
            $this->value = $this->options[$this->value];
        } else if (isset($this->options['__default__'])) {
            $this->value = $this->options['__default__'];
        }

        return parent::renderValue();
    }

    /**
     * 设置选项为是/否
     *
     * @return $this
     */
    public function yesOrNo()
    {
        $this->options = [1 => __blang('builder_option_yes'), 0 => __blang('builder_option_no')];
        return $this;
    }

    /**
     * 模板变量：附加选中值
     *
     * @return array
     */
    public function customVars()
    {
        $this->checked = (string)$this->checked;

        return array_merge(parent::customVars(), [
            'checked' => $this->checked,
        ]);
    }
}
