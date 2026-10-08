<?php

namespace tpext\builder\displayer;

use tpext\builder\traits\HasStorageDriver;
use tpext\builder\traits\HasImageDriver;

/**
 * WangEditor富文本编辑器组件
 */
class WangEditor extends Field
{
    use HasStorageDriver;
    use HasImageDriver;

    protected $view = 'wangeditor';
    protected $cssFamily = 'widgets'; // 6.181 按需加载

    protected $minify = false;

    protected $js = [
        '/assets/tpextdaisyui/js/wangEditor/wangEditor.min.js',
    ];

    protected $jsOptions = [
        'uploadImgMaxSize' => 20 * 1024 * 1024,
        'uploadImgMaxLength' => 10,
        'uploadImgTimeout' => 30000,
        'uploadFileName' => 'file',
        'zIndex' => 99
    ];

    /**
     * 预留的脚本钩子，当前无输出
     *
     * @return string
     */
    protected function editorScript()
    {
        return '';
    }

    /**
     * x-editor 元素配置：wang 驱动（wangEditor 挂 #id-div 派生挂载 p，
     * onchange 回写 textarea；上传地址/参数在 configs）
     *
     * @return string
     */
    protected function elementCfg()
    {
        if (!isset($this->jsOptions['uploadImgServer']) || empty($this->jsOptions['uploadImgServer'])) {

            $token = $this->getCsrfToken();

            $this->jsOptions['uploadImgServer'] = (string)url($this->getUploadUrl(), [
                'utype' => 'wangeditor',
                'token' => $token,
                'driver' => $this->getStorageDriver(),
                'is_rand_name' => $this->isRandName(),
                'image_driver' => $this->getImageDriver(),
                'image_commonds' => $this->getImageCommands()
            ]);
        }

        $this->jsOptions['uploadImgParams'] = [];

        $cfg = [
            'driver' => 'wang',
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
     * 渲染前处理
     *
     * @return $this
     */
    public function beforRender()
    {
        return parent::beforRender();
    }
}
