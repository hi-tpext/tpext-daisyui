<?php

namespace tpext\builder\displayer;

use tpext\builder\traits\HasOptions;
use tpext\builder\traits\HasWhen;

/**
 * Checkbox多选组件
 */
class Checkbox extends Field
{
    use HasOptions;
    use HasWhen;

    protected $view = 'checkbox';

    protected $inline = true;

    protected $checkallBtn = '';

    protected $default = [];

    protected $checked = [];

    protected $disabledOptions = [];

    protected $readonlyOptions = [];

    protected $blockStyle = false;

    protected $isArrayValue = true;

    /**
     * 设置是否横排显示
     *
     * @param boolean $val
     * @return $this
     */
    public function inline($val = true)
    {
        $this->inline = $val;
        return $this;
    }

    /**
     * 设置方形按钮块样式
     *
     * @param boolean $val
     * @return $this
     */
    public function blockStyle($val = true)
    {
        $this->blockStyle = $val;
        return $this;
    }

    /**
     * 设置禁用的选项
     *
     * @param string|array $val
     * @return $this
     */
    public function disabledOptions($val)
    {
        $this->disabledOptions = $val;
        return $this;
    }

    /**
     * 设置只读的选项
     *
     * @param string|array $val
     * @return $this
     */
    public function readonlyOptions($val)
    {
        $this->readonlyOptions = $val;
        return $this;
    }

    /**
     * 设置全选按钮文字
     *
     * @param string $val
     * @return $this
     */
    public function checkallBtn($val = '全选')
    {
        $this->checkallBtn = $val;
        return $this;
    }

    /**
     * 设置默认选中值
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
     * 渲染为HTML
     *
     * @return mixed
     */
    public function render()
    {
        $vars = $this->commonVars();

        if (!($this->value === '' || $this->value === null || $this->value === [])) {
            $this->checked = is_array($this->value) ? $this->value : explode(',', $this->value);
        } else if (!($this->default === '' || $this->default === null || $this->default === [])) {
            $this->checked = is_array($this->default) ? $this->default : explode(',', $this->default);
        }

        $checkall = false;

        if ($this->checkallBtn) {
            $count = 0;
            foreach ($this->options as $key => $op) {
                if (in_array($key, $this->checked)) {
                    $count += 1;
                }
            }
            $checkall = $count > 0 && $count == count($this->options);
        }

        foreach ($this->checked as &$ck) {
            $ck = '-' . $ck;
        }

        unset($ck);

        if ($this->disabledOptions && !is_array($this->disabledOptions)) {
            $this->disabledOptions = explode(',', $this->disabledOptions);
        }

        if ($this->readonlyOptions && !is_array($this->readonlyOptions)) {
            $this->readonlyOptions = explode(',', $this->readonlyOptions);
        }

        foreach ($this->disabledOptions as &$di) {
            $di = '-' . $di;
        }

        foreach ($this->readonlyOptions as &$ro) {
            $ro = '-' . $ro;
        }

        unset($ck, $di, $ro);

        $vars = array_merge($vars, [
            // 布局与视觉解耦：inline 只管横排/竖排；blockStyle 是方形按钮块视觉，见模板 {$blockStyle}
            'inline' => $this->inline ? 'flex flex-wrap gap-3' : 'flex flex-col gap-1',
            'blockStyle' => $this->blockStyle ? 'checkbox-block' : '',
            'checkallBtn' => $this->checkallBtn,
            'checkall' => $checkall,
            'checked' => $this->checked,
            'options' => $this->options,
            'disabledOptions' => $this->disabledOptions,
            'readonlyOptions' => $this->readonlyOptions,
        ]);

        $viewshow = $this->getViewInstance();

        return $viewshow->assign($vars)->getContent();
    }

    /**
     * 渲染前处理：生成when联动脚本
     *
     * @return $this
     */
    public function beforRender()
    {
        $this->whenScript();
        return parent::beforRender();
    }
}
