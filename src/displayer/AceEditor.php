<?php

namespace tpext\builder\displayer;

/**
 * AceEditor代码编辑器组件
 */
class AceEditor extends Field
{
    protected $view = 'aceeditor';
    protected $cssFamily = 'widgets'; // 6.181 按需加载

    protected $minify = false;

    protected $js = [
        //core
        '/assets/tpextdaisyui/js/ace/ace.js',
        //ext
        '/assets/tpextdaisyui/js/ace/ext-beautify.js',
        '/assets/tpextdaisyui/js/ace/ext-error_marker.js',
        '/assets/tpextdaisyui/js/ace/ext-language_tools.js',
        '/assets/tpextdaisyui/js/ace/ext-keybinding_menu.js',
        '/assets/tpextdaisyui/js/ace/ext-searchbox.js',
        '/assets/tpextdaisyui/js/ace/ext-spellcheck.js',
        '/assets/tpextdaisyui/js/ace/ext-static_highlight.js',
        '/assets/tpextdaisyui/js/ace/ext-statusbar.js',
        //mode
        '/assets/tpextdaisyui/js/ace/mode-css.js',
        '/assets/tpextdaisyui/js/ace/mode-text.js',
        '/assets/tpextdaisyui/js/ace/mode-html.js',
        '/assets/tpextdaisyui/js/ace/mode-javascript.js',
        //theme
        '/assets/tpextdaisyui/js/ace/theme-one_dark.js', //dark
        '/assets/tpextdaisyui/js/ace/theme-textmate.js', //bright
    ];

    protected $jsOptions = [
        'mode' => 'text',
        'dark' => true,
        'fontSize' => 14,
        'height' => '1000px',
        'width' => '100%',
        //
        'enableBasicAutocompletion' => true,
        'enableSnippets' => true,
        'enableLiveAutocompletion' => true,
    ];

    /**
     * x-editor 元素配置：ace 驱动（ace.edit 挂 #id-editor 派生挂载，
     * change 回写 textarea；主题/模式/字号/补全逐项同旧脚本）
     *
     * @return string
     */
    protected function elementCfg()
    {
        $cfg = [
            'driver' => 'ace',
            'configs' => $this->jsOptions,
            'ro' => $this->readonly || $this->disabled,
        ];

        return htmlspecialchars(json_encode($cfg, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 模板变量：x-editor cfg配置
     *
     * @return array
     */
    public function customVars()
    {
        return [
            'cfg' => $this->elementCfg(),
        ];
    }

    /**
     * 设置代码语言模式
     *
     * @param string $val css/html/javascript/text
     * @return $this
     */
    public function setMode($val = 'text')
    {
        $this->jsOptions['mode'] = $val;

        return $this;
    }

    /**
     * 设置是否为黑色模式
     *
     * @param boolean $val
     * @return $this
     */
    public function setDark($val = true)
    {
        $this->jsOptions['dark'] = $val;

        return $this;
    }
}
