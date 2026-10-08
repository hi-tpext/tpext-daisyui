<?php

namespace tpext\builder\displayer;

use tpext\builder\traits\HasStorageDriver;
use tpext\builder\traits\HasImageDriver;

/**
 * CKEditor富文本编辑器组件
 */
class CKEditor extends Field
{
    use HasStorageDriver;
    use HasImageDriver;

    protected $view = 'ckeditor';
    protected $cssFamily = 'widgets'; // 6.181 按需加载

    protected $minify = false;

    protected $js = [
        '/assets/builderckeditor/ckeditor.js',
    ];

    protected $jsOptions = [
        'language' => 'zh-cn',
        'uiColor' => '#eeeeee',
        'height' => 600,
        'image_previewText' => ' ',
    ];

    /**
     * 资源包缺失时输出提示脚本
     *
     * @return string
     */
    protected function editorScript()
    {
        // 资源包缺失时仍走脚本弹窗提示（与历史行为一致）；有包时由 x-editor 的
        // ckeditor 驱动初始化（提交回写由 CKEDITOR 自身机制处理，行为不变）
        if (!class_exists('\\tpext\\builder\\ckeditor\\common\\Resource')) {
            $this->js = [];
            $this->script[] = 'layer.alert("未安装ckeditor资源包！<pre>composer require ichynul/builder-ckeditor</pre>");';
            return;
        }
        return '';
    }

    /**
     * x-editor 元素配置：ckeditor 驱动（CKEDITOR.replace(textarea.name, configs)，
     * 图片上传地址在 configs.filebrowserImageUploadUrl）
     *
     * @return string
     */
    protected function elementCfg()
    {
        // 配置可放在config.js中
        // 成功返回格式{"uploaded":1,"fileName":"图片名称","url":"图片访问路径"}
        // 失败返回格式{"uploaded":0,"error":{"message":"失败原因"}}
        if (!isset($this->jsOptions['filebrowserImageUploadUrl']) || empty($this->jsOptions['filebrowserImageUploadUrl'])) {

            $token = $this->getCsrfToken();

            $this->jsOptions['filebrowserImageUploadUrl'] = (string)url($this->getUploadUrl(), [
                'utype' => 'ckeditor',
                'token' => $token,
                'driver' => $this->getStorageDriver(),
                'is_rand_name' => $this->isRandName(),
                'image_driver' => $this->getImageDriver(),
                'image_commonds' => $this->getImageCommands()
            ]);
        }

        $cfg = [
            'driver' => 'ckeditor',
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
