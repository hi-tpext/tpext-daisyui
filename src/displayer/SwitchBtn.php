<?php

namespace tpext\builder\displayer;

/**
 * SwitchBtn开关切换组件
 */
class SwitchBtn extends Field
{
    protected $view = 'switchbtn';

    protected $checked = '';

    protected $pair = ['on' => 1, 'off' => 0];

    protected $required = false;

    /**
     * 设置开/关两个状态对应的值
     * @example 1 (1, 0) / ('yes', 'no') / ('on', 'off') etc...
     * @param mixed $on
     * @param mixed $off
     * @return $this
     */
    public function pair($on = 1, $off = 0)
    {
        $this->pair = ['on' => $on, 'off' => $off];;

        return $this;
    }

    /**
     * 获取开/关状态值
     *
     * @return array
     */
    public function getPair()
    {
        return $this->pair;
    }

    /**
     * SwitchBtn 不需要设置 required
     *
     * @param boolean $val
     * @return $this
     */
    public function required($val = true)
    {
        $this->required = false;
        return $this;
    }

    /**
     * 渲染为HTML
     *
     * @return mixed
     */
    public function render()
    {
        $vars = $this->commonVars();

        if (!($this->value === '' || $this->value === null)) {
            $this->checked = $this->value;
        } else {
            $this->checked = $this->default;
        }

        $vars = array_merge($vars, [
            'checked' => $this->checked,
            'pair' => $this->pair,
        ]);

        $viewshow = $this->getViewInstance();

        return $viewshow->assign($vars)->getContent();
    }
}
