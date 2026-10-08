<?php

namespace tpext\builder\displayer;

/**
 * Load远程数据加载展示组件
 */
class Load extends Field
{
    protected $view = 'load';

    protected $isInput = false;

    public $loadingText = '加载中...';

    protected $jsOptions = [
        'ajax' => [
            'url' => '',
            'text' => '',
            'separator' => '、'
        ]
    ];

    /**
     * 创建组件：设置加载文案
     *
     * @param string $fieldType
     * @return $this
     */
    public function created($fieldType = '')
    {
        $this->loadingText = __blang('builder_loading');
        parent::created($fieldType);
        return $this;
    }

    /**
     * 设置加载中提示文案
     *
     * @param string $val 加载中...|&nbsp;
     * @return $this
     */
    public function loadingText($val = '&nbsp;')
    {
        $this->loadingText = $val;

        return $this;
    }

    /**
     * 设置远程数据源
     *
     * @param string $url
     * @param string $textField text|name
     * @return $this
     */
    public function dataUrl($url, $textField = '')
    {
        $this->jsOptions['ajax'] = array_merge($this->jsOptions['ajax'], [
            'url' => $url,
            'text' => $textField,
        ]);

        return $this;
    }

    /**
     * x-loadtext 元素配置：远程拉文本的 url/文本字段/连接符
     *
     * @return string
     */
    protected function elementCfg()
    {
        $ajax = $this->jsOptions['ajax'];

        $cfg = [
            'url' => (string)$ajax['url'],
            'text' => empty($ajax['text']) ? '_' : $ajax['text'],
            'separator' => empty($ajax['separator']) ? '、' : $ajax['separator'],
        ];

        return htmlspecialchars(json_encode($cfg, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 模板变量：选中值、加载文案与cfg配置
     *
     * @return array
     */
    public function customVars()
    {
        $checked = '';

        if (!($this->value === '' || $this->value === null)) {
            $checked = $this->value;
        } else {
            $checked = $this->default;
        }

        return [
            'checked' => $checked,
            'loadingText' => $this->loadingText,
            'cfg' => $this->elementCfg(),
        ];
    }
}
