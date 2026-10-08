<?php

namespace tpext\builder\displayer;

use tpext\builder\traits\HasOptions;

/**
 * Matches多值匹配转义展示组件（多选值批量转为对应文本）
 */
class Matches extends Raw
{
    use HasOptions;

    protected $view = 'matche';

    protected $isInput = false;

    protected $separator = '、';

    protected $checked = '';

    /**
     * 设置多个文本间的连接符，默认'、'
     *
     * @param string $val
     * @return $this
     */
    public function separator($val = '、')
    {
        $this->separator = $val;
        return $this;
    }

    /**
     * 创建组件：使用语言包默认连接符
     *
     * @param string $fieldType
     * @return $this
     */
    public function created($fieldType = '')
    {
        parent::created($fieldType);
        $this->separator = __blang('builder_default_separator');
        return $this;
    }

    /**
     * 渲染前处理：确定当前选中值
     *
     * @return $this
     */
    public function beforRender()
    {
        $this->checked = !($this->value === '' || $this->value === null || $this->value === []) ? $this->value : $this->default;
        return parent::beforRender();
    }

    /**
     * 获取渲染用值：多值批量匹配options转换为文本
     *
     * @return string|int|float|null
     */
    public function renderValue()
    {
        $this->value = !($this->value === '' || $this->value === null || $this->value === []) ? $this->value : $this->default;

        $values = is_array($this->value) ? $this->value : explode(',', $this->value);
        $texts = [];

        foreach ($values as $value) {
            if (isset($this->options[$value])) {
                $texts[] = $this->options[$value];
            }
        }

        $this->value = implode($this->separator, $texts);

        return parent::renderValue();
    }

    /**
     * 模板变量：附加选中值
     *
     * @return array
     */
    public function customVars()
    {
        $this->checked = is_array($this->checked) ? implode(',', $this->checked) : (string)$this->checked;

        return array_merge(parent::customVars(), [
            'checked' => $this->checked,
        ]);
    }
}
