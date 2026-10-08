<?php

namespace tpext\builder\displayer;

/**
 * Loads多值远程加载展示组件
 */
class Loads extends Load
{
    protected $view = 'load';

    protected $isInput = false;
    
    /**
     * 设置多个文本间的连接符，默认'、'
     *
     * @param string $val
     * @return $this
     */
    public function separator($val = '、')
    {
        $this->jsOptions['ajax']['separator'] = $val;
        return $this;
    }

    /**
     * 设置默认值
     *
     * @param array|string $val
     * @return $this
     */
    public function default($val = [])
    {
        $this->default = $val;
        return $this;
    }

    /**
     * 模板变量：数组值归一为逗号串
     *
     * @return array
     */
    public function customVars()
    {
        if (is_array($this->value)) {
            $this->value = implode(',', $this->value);
        }

        if (is_array($this->default)) {
            $this->default = implode(',', $this->default);
        }

        return parent::customVars();
    }
}
