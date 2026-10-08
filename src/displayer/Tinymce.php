<?php

namespace tpext\builder\displayer;

use tpext\builder\traits\HasStorageDriver;
use tpext\builder\traits\HasImageDriver;


/**
 * Tinymce富文本编辑器组件
 */
class Tinymce extends Field
{
    use HasStorageDriver;
    use HasImageDriver;

    protected $view = 'tinymce';
    protected $cssFamily = 'widgets'; // 6.181 按需加载

    protected $minify = false;

    protected $js = [
        '/assets/buildertinymce/tinymce.min.js',
    ];

    protected $jsOptions = [
        'language' => 'zh_CN',
        'directionality' => 'ltl',
        'browser_spellcheck' => true,
        'contextmenu' => false,
        'height' => 600,
        'plugins' => [
            "advlist autolink lists link image charmap print preview anchor",
            "searchreplace visualblocks code fullscreen",
            "insertdatetime media table contextmenu paste imagetools wordcount",
            "code",
        ],
        'toolbar' => "insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | code",
    ];

    /**
     * 资源包缺失时输出提示脚本
     *
     * @return string
     */
    protected function editorScript()
    {
        if (!class_exists('\\tpext\\builder\\tinymce\\common\\Resource')) {
            $this->js = [];
            $this->script[] = 'layer.alert("未安装tinymce资源包！<pre>composer require ichynul/builder-tinymce</pre>");';
            return;
        }
        // 有包时由 x-editor 的 tinymce 驱动初始化（tinymce.init(selector 指向载体)）
        return '';
    }

    /**
     * x-editor 元素配置：tinymce 驱动（tinymce.init(configs)，selector 指向载体，
     * 图片上传地址在 configs.images_upload_url）
     *
     * @return string
     */
    protected function elementCfg()
    {
        if (!isset($this->jsOptions['images_upload_url']) || empty($this->jsOptions['images_upload_url'])) {

            $token = $this->getCsrfToken();

            $this->jsOptions['images_upload_url'] = (string)url($this->getUploadUrl(), [
                'utype' => 'tinymce',
                'token' => $token,
                'driver' => $this->getStorageDriver(),
                'is_rand_name' => $this->isRandName(),
                'image_driver' => $this->getImageDriver(),
                'image_commonds' => $this->getImageCommands()
            ]);
        }

        $this->jsOptions['selector'] = '#' . $this->getId();

        $cfg = [
            'driver' => 'tinymce',
            'configs' => $this->jsOptions,
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
     * 渲染前处理：非只读时检查资源包
     *
     * @return $this
     */
    public function beforRender()
    {
        if (!$this->readonly) {
            $this->editorScript();
        }

        return parent::beforRender();
    }
}
