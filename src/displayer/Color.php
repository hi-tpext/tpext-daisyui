<?php

namespace tpext\builder\displayer;

/**
 * Color颜色选择组件（基于pickr）
 */
class Color extends Text
{
    protected $size = [2, 3];
    protected $cssFamily = 'trees'; // 6.181 按需加载（pickr 在 builder-trees.css）

    protected $view = 'color';

    protected $js = [
        '/assets/tpextdaisyui/js/vendors/pickr/pickr.min.js',
    ];

    protected $css = [
        '/assets/tpextdaisyui/js/vendors/pickr/themes/monolith.min.css',
    ];

    protected $jsOptions = [
        'format' => 'hex',
        'inline' => false,
        'swatches' => [
            'rgba(244, 67, 54, 1)',
            'rgba(233, 30, 99, 1)',
            'rgba(156, 39, 176, 1)',
            'rgba(103, 58, 183, 1)',
            'rgba(63, 81, 181, 1)',
            'rgba(33, 150, 243, 1)',
            'rgba(3, 169, 244, 1)',
            'rgba(0, 188, 212, 1)',
            'rgba(0, 150, 136, 1)',
            'rgba(76, 175, 80, 1)',
            'rgba(139, 195, 74, 1)',
            'rgba(205, 220, 57, 1)',
            'rgba(255, 235, 59, 1)',
            'rgba(255, 193, 7, 1)',
            'rgba(255, 152, 0, 1)',
            'rgba(255, 87, 34, 1)',
            'rgba(121, 85, 72, 1)',
            'rgba(158, 158, 158, 1)',
            'rgba(0, 0, 0, 1)',
        ],
    ];

    /**
     * 设置颜色格式
     *
     * @param string $val
     * @return $this
     */
    public function format($val)
    {
        $this->jsOptions['format'] = $val;
        return $this;
    }

    /**
     * 使用rgb格式
     *
     * @return $this
     */
    public function rgb()
    {
        $this->format('rgb');
        return $this;
    }

    /**
     * 使用rgba格式
     *
     * @return $this
     */
    public function rgba()
    {
        $this->format('rgba');
        return $this;
    }

    /**
     * 使用hsl格式
     *
     * @return $this
     */
    public function hsl()
    {
        $this->format('hsl');
        return $this;
    }

    /**
     * 使用hsla格式
     *
     * @return $this
     */
    public function hsla()
    {
        $this->format('hsla');
        return $this;
    }

    /**
     * 使用hex格式
     *
     * @return $this
     */
    public function hex()
    {
        $this->format('hex');
        return $this;
    }

    /**
     * 设置是否内联显示色板
     *
     * @param boolean $val
     * @return $this
     */
    public function inline($val = true)
    {
        $this->jsOptions['inline'] = $val;
        return $this;
    }

    /**
     * x-color 元素配置：色板（format 等旧脚本未传给 Pickr，仅 swatches 生效，
     * 按钮文案由元素内 BE.blang 取）
     *
     * @return string
     */
    protected function elementCfg()
    {
        $cfg = [
            'swatches' => $this->jsOptions['swatches'],
        ];

        return htmlspecialchars(json_encode($cfg, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 模板变量：x-color cfg配置
     *
     * @return array
     */
    public function customVars()
    {
        return [
            'cfg' => $this->elementCfg(),
        ];
    }
}
