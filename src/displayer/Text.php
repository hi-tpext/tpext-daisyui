<?php

namespace tpext\builder\displayer;

/**
 * Text单行文本输入组件
 */
class Text extends Field
{
    protected $view = 'text';

    protected $befor = '';

    protected $after = '';

    protected $maxlength = 0;

    protected $placeholder = '';

    protected $js = [];

    /**
     * 设置最大输入长度
     *
     * @param integer $val
     * @return $this
     */
    public function maxlength($val = 0)
    {
        $this->maxlength = $val;
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
     * 设置元素前附加的HTML（6.309 恢复旧库左侧前缀；6.310 起单独使用时渲染前转投右侧。
     * 与 beforSymbol 的分工：本方法原样输出自由 HTML——图标、链接等）
     *
     * @param string $html
     * @return $this
     */
    public function befor($html)
    {
        $this->befor = $html;
        return $this;
    }

    /**
     * 设置元素后附加的HTML（原样输出自由 HTML——图标、链接等；简单文字用 afterSymbol()）
     *
     * @param string $html
     * @return $this
     */
    public function after($html)
    {
        $this->after = $html;
        return $this;
    }

    /**
     * 设置前缀符号——字面约定为纯文本（6.313 定稿：不转义，传 HTML 也原样输出，
     * 如 <span style="color:red">元</span> 这类纯样式片段；内容由调用方自行保证安全。
     * 与旧库 input-group-addon 包装行为一致，.text-symbol 仅作样式钩子；
     * 更复杂的自由 HTML 用 befor()）
     *
     * @param string $text
     * @return $this
     */
    public function beforSymbol($text)
    {
        $this->befor = '<span class="text-symbol">' . $text . '</span>';
        return $this;
    }

    /**
     * 设置后缀符号——约定规则同 beforSymbol()
     *
     * @param string $text
     * @return $this
     */
    public function afterSymbol($text)
    {
        $this->after = '<span class="text-symbol">' . $text . '</span>';
        return $this;
    }

    /**
     * 渲染为HTML
     *
     * @return mixed
     */
    public function render()
    {
        if ($this->maxlength > 0) {
            $this->attr .= ' maxlength="' . $this->maxlength . '"';
        }

        // 6.310：左侧前缀与全库「图标/附加物居右」风格不统一——只有 befor 没有 after 时
        // 转投右侧渲染；两者并存仍按旧库左右布局输出
        if ($this->befor !== '' && $this->after === '') {
            $this->after = $this->befor;
            $this->befor = '';
        }

        $vars = $this->commonVars();

        $vars = array_merge($vars, [
            'befor' => $this->befor,
            'after' => $this->after,
            'placeholder' => $this->placeholder ?: $this->placeholderText()
        ]);

        $viewshow = $this->getViewInstance();

        return $viewshow->assign($vars)->getContent();
    }

    /**
     * 默认占位文案（6.164）：输入类「请输入{label}」；选择类子类（DateTime 系）覆盖为「请选择{label}」
     *
     * @return string
     */
    protected function placeholderText()
    {
        return __blang('builder_please_enter') . $this->label;
    }
}
