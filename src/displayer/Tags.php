<?php

namespace tpext\builder\displayer;

/**
 * Tags标签输入组件（TomSelect create模式）
 */
class Tags extends Field
{
    protected $view = 'tags';
    protected $cssFamily = 'tomselect'; // 6.181 按需加载

    protected $js = [
        '/assets/tpextdaisyui/js/vendors/tom-select.min.js',
    ];

    protected $css = [
        '/assets/tpextdaisyui/css/tom-select.min.css',
    ];

    protected $placeholder = '';

    protected $jsOptions = [
        'height' => '33px',
        'width' => '100%',
        'defaultText' => '',
        'removeWithBackspace' => true,
        'delimiter' => [','],
    ];

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
     * x-select 元素配置：tags = create 模式的 TomSelect（回车生成标签）。
     * 只读时不初始化（旧版跳过 tagsScript，保持原生多选展示）
     *
     * @return string
     */
    protected function elementCfg()
    {
        $placeholder = $this->placeholder ?: __blang('builder_please_enter') . $this->label;

        if ($this->readonly) {
            return htmlspecialchars(json_encode(['ts' => false]), ENT_QUOTES, 'UTF-8');
        }

        $cfg = [
            'ts' => true,
            'create' => true,
            'delimiter' => ',',
            'placeholder' => $placeholder,
            'plugins' => ['remove_button', 'clear_button'],
            'maxItems' => null,
        ];

        return htmlspecialchars(json_encode($cfg, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 模板变量：标签列表与cfg配置
     *
     * @return array
     */
    public function customVars()
    {
        $tags = [];
        $rawValue = $this->value;
        if (is_array($rawValue)) {
            $tags = $rawValue;
        } elseif (is_string($rawValue) && $rawValue !== '') {
            $tags = array_filter(explode(',', $rawValue));
        }

        return [
            'placeholder' => $this->placeholder ?: __blang('builder_please_enter') . $this->label,
            'tags' => $tags,
            'cfg' => $this->elementCfg(),
        ];
    }
}
