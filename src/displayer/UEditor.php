<?php

namespace tpext\builder\displayer;

use tpext\builder\traits\HasStorageDriver;
use tpext\builder\traits\HasImageDriver;

/**
 * UEditor百度富文本编辑器组件
 */
class UEditor extends Field
{
    use HasStorageDriver;
    use HasImageDriver;

    protected $view = 'ueditor';
    protected $cssFamily = 'widgets'; // 6.181 按需加载

    protected $minify = false;

    protected $js = [
        '/assets/builderueditor/ueditor.all.min.js',
    ];

    protected $configJsPath = '/assets/builderueditor/ueditor.config.js';

    protected $uploadUrl = '';

    /**
     * 设置ueditor.config.js路径
     *
     * @param string $val
     * @return $this
     */
    public function configJsPath($val)
    {
        $this->configJsPath = $val;
        return $this;
    }

    /**
     * 设置上传地址
     *
     * @param string $val
     * @return $this
     */
    public function uploadUrl($val)
    {
        $this->uploadUrl = $val;
        return $this;
    }

    /**
     * 资源包缺失时输出提示脚本
     *
     * @return string
     */
    protected function editorScript()
    {
        // 资源包缺失时仍走脚本弹窗提示（与历史行为一致）；有包时由 x-editor 的
        // ueditor 驱动初始化（init 里 window.uploadUrl 全局赋值同旧内联 script）
        if (!class_exists('\\tpext\\builder\\ueditor\\common\\Resource')) {
            $this->js = [];
            $this->script[] = 'layer.alert("未安装ueditor资源包！<pre>composer require ichynul/builder-ueditor</pre>");';
            return;
        }

        return '';
    }

    /**
     * x-editor 元素配置：ueditor 驱动（UE.getEditor 挂 script[text/plain] 载体，
     * initialFrameWidth/Height 与旧脚本一致）
     *
     * @return string
     */
    protected function elementCfg()
    {
        $cfg = [
            'driver' => 'ueditor',
            'uploadUrl' => $this->uploadUrl,
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
     * 渲染前处理：前置配置js并检查资源包
     *
     * @return $this
     */
    public function beforRender()
    {
        $this->js = array_merge([$this->configJsPath], $this->js);

        if (!$this->readonly) {
            $this->editorScript();
        }

        return parent::beforRender();
    }

    /**
     * 渲染为HTML
     *
     * @return mixed
     */
    public function render()
    {
        if (empty($this->uploadUrl)) {

            $token = $this->getCsrfToken();

            $this->uploadUrl = (string)url($this->getUploadUrl(), [
                'utype' => 'ueditor',
                'token' => $token,
                'driver' => $this->getStorageDriver(),
                'is_rand_name' => $this->isRandName(),
                'image_driver' => $this->getImageDriver(),
                'image_commonds' => $this->getImageCommands()
            ]);
        }

        $vars = $this->commonVars();

        $vars = array_merge($vars, [
            'uploadUrl' => $this->uploadUrl,
        ]);

        $viewshow = $this->getViewInstance();

        return $viewshow->assign($vars)->getContent();
    }
}
