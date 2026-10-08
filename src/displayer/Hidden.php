<?php

namespace tpext\builder\displayer;

/**
 * Hidden隐藏字段组件
 */
class Hidden extends Field
{
    protected $view = 'hidden';

    /**
     * 实例化隐藏字段
     *
     * @param string $name 字段名
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * 创建组件：隐藏所在行
     *
     * @param string $fieldType
     * @return $this
     */
    public function created($fieldType = '')
    {
        $this->getWrapper()->addStyle('display:none;');
    }
}
