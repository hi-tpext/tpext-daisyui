<?php

namespace tpext\builder\displayer;

use tpext\builder\traits\HasMarkdownPreview;
use tpext\builder\traits\HasStorageDriver;
use tpext\builder\traits\HasImageDriver;

/**
 * MDEditor Markdown编辑器组件（基于EasyMDE）
 */
class MDEditor extends Field
{
    use HasStorageDriver;
    use HasImageDriver;
    use HasMarkdownPreview;

    protected $view = 'mdeditor';
    protected $cssFamily = 'widgets'; // 6.181 按需加载

    protected $minify = false;

    protected $js = [
        '/assets/tpextdaisyui/js/vendors/easymde.min.js',
    ];

    protected $css = [
        // 工具栏图标已改用 Material Design Icons（全局 commonCss 已引入），
        // 不再依赖 FontAwesome：图标映射由官方 iconClassMap 选项覆盖成 mdi mdi-*，
        // 自动注入 FA 的开关由 autoDownloadFontAwesome:false 关闭，FA 的 css/字体文件已移除
        '/assets/tpextdaisyui/css/easymde.min.css',
    ];

    protected $jsOptions = [
        'minHeight'   => '300px',
        'placeholder' => '',
    ];

    /**
     * EasyMDE 官方选项默认值（不走 vendor 补丁，升级 easymde 时无需重打）
     *
     * @return array
     */
    protected function mdeDefaults()
    {
        return [
            // 官方选项：不再自动注入 FontAwesome 样式表（原本指向已停服的 maxcdn）
            'autoDownloadFontAwesome'  => false,
            // 官方选项：按钮 class 加前缀 → mde-table / mde-link / mde-italic，
            // 避开 daisyUI 全局类 .table(width:100%) / .link / .italic 的污染
            'toolbarButtonClassPrefix' => 'mde',
            // 官方选项：覆盖内置图标映射表（默认 fa fa-*）
            'iconClassMap'             => $this->iconClassMap(),
        ];
    }

    /**
     * 工具栏图标 → Material Design Icons（官方 iconClassMap 选项）
     *
     * 注意：v2.21.0 里该选项**尚未被消费**（源码只在 options 合并处出现
     * `e.iconClassMap=J({},te,e.iconClassMap||{})`，全文没有读取点），
     * 实际让图标变 MDI 的是 vendor 补丁 P1（改内置常量表 te）。
     * 保留本选项是为了上游修复后能自动接管，届时只需撤销 P1，详见
     * assets/js/vendors/easymde.PATCH.md。
     *
     * 全局 commonCss 已引入 materialdesignicons.min.css（v2.0.46，2060 字形），
     * 较新的图标名（如 mdi-format-list-numbered / mdi-image-plus / mdi-view-split-vertical）
     * 在该版本里不存在，已用同版本存在的字形替代，改动前请先 grep 确认。
     *
     * @return array
     */
    protected function iconClassMap()
    {
        return [
            'bold'            => 'mdi mdi-format-bold',
            'italic'          => 'mdi mdi-format-italic',
            'strikethrough'   => 'mdi mdi-format-strikethrough',
            'heading'         => 'mdi mdi-format-header-pound',
            'heading-smaller' => 'mdi mdi-format-header-decrease',
            'heading-bigger'  => 'mdi mdi-format-header-increase',
            'heading-1'       => 'mdi mdi-format-header-1',
            'heading-2'       => 'mdi mdi-format-header-2',
            'heading-3'       => 'mdi mdi-format-header-3',
            'code'            => 'mdi mdi-code-tags',
            'quote'           => 'mdi mdi-format-quote-open',
            'ordered-list'    => 'mdi mdi-format-list-numbered',
            'unordered-list'  => 'mdi mdi-format-list-bulleted',
            'check-list'      => 'mdi mdi-checkbox-marked-outline',
            'clean-block'     => 'mdi mdi-eraser',
            'link'            => 'mdi mdi-link',
            'image'           => 'mdi mdi-image',
            'upload-image'    => 'mdi mdi-upload',
            'table'           => 'mdi mdi-table',
            'horizontal-rule' => 'mdi mdi-minus',
            'preview'         => 'mdi mdi-eye',
            'side-by-side'    => 'mdi mdi-page-layout-sidebar-right',
            'fullscreen'      => 'mdi mdi-fullscreen',
            'guide'           => 'mdi mdi-help-circle',
            'undo'            => 'mdi mdi-undo',
            'redo'            => 'mdi mdi-redo',
        ];
    }

    /**
     * 工具栏按钮 name → 语言包 key
     *
     * EasyMDE 内置工具栏的 title 是英文硬编码（Bold / Insert Table ...），
     * 没有提供文案选项，只能在初始化后按 name 逐个覆盖。
     *
     * @return array
     */
    protected function toolbarLangKeys()
    {
        return [
            'bold'           => 'builder_md_bold',
            'italic'         => 'builder_md_italic',
            'heading'        => 'builder_md_heading',
            'unordered-list' => 'builder_md_unordered_list',
            'ordered-list'   => 'builder_md_ordered_list',
            'quote'          => 'builder_md_quote',
            'code'           => 'builder_md_code',
            'table'          => 'builder_md_table',
            'link'           => 'builder_md_link',
            'upload-image'   => 'builder_md_upload_image',
            'image'          => 'builder_md_image',
            'preview'        => 'builder_md_preview',
            'side-by-side'   => 'builder_md_side_by_side',
            'fullscreen'     => 'builder_md_fullscreen',
            'guide'          => 'builder_md_guide',
            'undo'           => 'builder_md_undo',
            'redo'           => 'builder_md_redo',
        ];
    }

    /**
     * 取当前语言下的工具栏文案
     *
     * @return array
     */
    protected function toolbarTitles()
    {
        $titles = [];

        foreach ($this->toolbarLangKeys() as $name => $key) {
            $titles[$name] = __blang($key);
        }

        return $titles;
    }

    /**
     * x-editor 元素配置：编辑态 driver=mde（options/imageUploadUrl/titles），
     * 只读态 driver=mdpreview（marked 渲染，pvClass 带边框）
     *
     * @return string
     */
    protected function elementCfg()
    {
        if ($this->readonly) {
            $cfg = [
                'driver' => 'mdpreview',
                'configs' => $this->jsOptions,
                'pvClass' => 'field-show rounded-box border border-base-300 md-body',
            ];
        } else {
            /**
             * 图片上传对接 admin/controller/Upload.php @ upfiles（utype=editormd）：
             * multipart 字段名 editormd-image-file，返回 {success: 0|1, message, url}
             */
            $imageUploadUrl = (string)url($this->getUploadUrl(), [
                'utype'          => 'editormd',
                'token'          => $this->getCsrfToken(),
                'driver'         => $this->getStorageDriver(),
                'is_rand_name'   => $this->isRandName(),
                'image_driver'   => $this->getImageDriver(),
                'image_commonds' => $this->getImageCommands(),
            ]);

            // array_merge 为浅合并：调用方在 jsOptions 里若传 iconClassMap 会整体覆盖默认值
            $cfg = [
                'driver' => 'mde',
                'options' => array_merge($this->mdeDefaults(), $this->jsOptions),
                'imageUploadUrl' => $imageUploadUrl,
                'titles' => $this->toolbarTitles(),
            ];
        }

        return htmlspecialchars(json_encode($cfg, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 渲染前处理：只读态追加marked库
     *
     * @return $this
     */
    public function beforRender()
    {
        if ($this->readonly) {
            // 只读态由 x-editor 的 mdpreview 驱动渲染，marked 库仍需引入。
            // 必须在 parent::beforRender() 之前追加，晚于它注册的 js 不会被上报给 Builder。
            $this->js[] = '/assets/tpextdaisyui/js/vendors/marked.min.js';
        }

        return parent::beforRender();
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
