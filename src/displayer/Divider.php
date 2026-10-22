<?php

namespace tpext\builder\displayer;

/**
 * Divider分割线组件（标题作为分割线文字）
 */
class Divider extends Field
{
    protected $view = 'divider';

    protected $isInput = false;

    /**
     * 创建组件：以label为显示文字并重置name
     *
     * @param string $fieldType
     * @return $this
     */
    public function created($fieldType = '')
    {
        parent::created($fieldType);

        $this->value = $this->label ? $this->label : $this->name;

        $this->name = 'divider' . mt_rand(100, 999);

        $this->label = '';

        // 6.489u 分割线不渲染 label 列：label 字符串已清空，但 showLabel 是独立
        // 属性且默认 true（commonVars 读的是它不是 label），空 label 元素照样
        // 渲染、占一列把分割线挤到右侧。置 false 并让内容占满 12 列，分割线独占整行
        $this->showLabel = false;

        $this->size(0, 12);

        return $this;
    }
}
