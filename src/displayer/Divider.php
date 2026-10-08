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
    }
}
