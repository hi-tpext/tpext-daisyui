<?php

namespace tpext\builder\displayer;

use tpext\builder\traits\HasMarkdownPreview;
use tpext\builder\traits\HasStorageDriver;
use tpext\builder\traits\HasImageDriver;

/**
 * MDReader Markdown只读展示组件（marked渲染）
 */
class MDReader extends Field
{
    use HasStorageDriver;
    use HasImageDriver;
    use HasMarkdownPreview;

    protected $view = 'mdreader';

    protected $isInput = false;

    protected $minify = false;

    protected $js = [
        '/assets/tpextdaisyui/js/vendors/marked.min.js',
    ];

    protected $jsOptions = [];

    /**
     * x-editor 元素配置：mdpreview 驱动（marked 渲染，容器无边框，同旧模板）
     *
     * @return string
     */
    protected function elementCfg()
    {
        $cfg = [
            'driver' => 'mdpreview',
            'configs' => $this->jsOptions,
            'pvClass' => 'field-show rounded-box md-body',
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
}
