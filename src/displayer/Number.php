<?php

namespace tpext\builder\displayer;

/**
 * Number数字输入组件（带步进按钮）
 */
class Number extends Field
{
    protected $view = 'number';
    protected $cssFamily = 'widgets'; // 6.181 按需加载

    protected $rules = 'number';

    protected $size = [2, 3];

    protected $js = [];

    protected $css = [];

    protected $placeholder = '';

    protected $decimals = 0;

    protected $jsOptions = [
        //'postfix' => '%',
        //'prefix' => '¥',
        'min' => 0,
        'max' => 9999999,
        'step' => 1,
        'verticalbuttons' => true,
        'initval' => 0,
    ];

    /**
     * 设置最小值
     *
     * @param int $val
     * @return $this
     */
    public function min($val)
    {
        $this->jsOptions['min'] = $val;
        return $this;
    }

    /**
     * 设置最大值
     *
     * @param int $val
     * @return $this
     */
    public function max($val)
    {
        $this->jsOptions['max'] = $val;
        return $this;
    }

    /**
     * 设置步进值
     *
     * @param int $val
     * @return $this
     */
    public function step($val)
    {
        $this->jsOptions['step'] = $val;
        return $this;
    }

    /**
     * 小数位数（固定 N 位输出，对齐原库 TouchSpin 的 decimals 选项）
     * 原库无此方法，只能通过 ->jsOptions(['decimals' => 2]) 传入；
     * 这里额外提供便捷方法，写入同一个 jsOptions['decimals']。
     *
     * @param int $val
     * @return $this
     */
    public function decimals($val)
    {
        $this->decimals = max(0, (int) $val);
        $this->jsOptions['decimals'] = $this->decimals;
        return $this;
    }

    /**
     * 前缀（内嵌在输入框左侧，位置同日期组件的日历图标）
     * 原库经 jsOptions(['prefix' => '¥']) 传给 TouchSpin，这里提供便捷方法。
     *
     * @param string $val
     * @return $this
     */
    public function prefix($val)
    {
        $this->jsOptions['prefix'] = $val;
        return $this;
    }

    /**
     * 后缀（内嵌在输入框右侧、+/- 按钮左侧）
     *
     * @param string $val
     * @return $this
     */
    public function postfix($val)
    {
        $this->jsOptions['postfix'] = $val;
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
     * x-number 元素配置：小数位与前后缀（min/max/step 走宿主属性原样搬运）
     *
     * @return string
     */
    protected function elementCfg()
    {
        $decimals = $this->decimals > 0 ? $this->decimals : (int) ($this->jsOptions['decimals'] ?? 0);

        $cfg = [
            'decimals' => $decimals,
            'prefix' => (string) ($this->jsOptions['prefix'] ?? ''),
            'postfix' => (string) ($this->jsOptions['postfix'] ?? ''),
        ];

        return htmlspecialchars(json_encode($cfg, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 模板变量：占位、jsOptions与cfg配置
     *
     * @return array
     */
    public function customVars()
    {
        $decimals = $this->decimals > 0 ? $this->decimals : (int) ($this->jsOptions['decimals'] ?? 0);

        return [
            'placeholder' => $this->placeholder ?: __blang('builder_please_enter') . $this->label,
            'jsOptions' => $this->jsOptions,
            'decimals' => $decimals,
            'prefix' => (string) ($this->jsOptions['prefix'] ?? ''),
            'postfix' => (string) ($this->jsOptions['postfix'] ?? ''),
            'cfg' => $this->elementCfg(),
        ];
    }
}
