<?php

namespace tpext\builder\traits;

trait HasMarkdownPreview
{
    /**
     * markdown 预览渲染脚本（MDReader 与 MDEditor 只读态共用）
     *
     * 前置条件：
     * 1. 模板必须输出容器 `<div id="{$id}-preview">markdown 原文</div>`；
     * 2. 原文必须经 htmlspecialchars 转义——否则原文里的 `<`、HTML 标签会被浏览器
     *    解析成真实节点，textContent 取回的内容不再等于原文，渲染结果错乱；
     * 3. 需要引入 /assets/tpextdaisyui/js/vendors/marked.min.js（marked v4.3.0，UMD 全局 marked）。
     *
     * 脚本在页面底部执行、库已加载，故不做 typeof 守卫（见 COMPONENT_SPEC 第 1 条）；
     * 用 __mdRendered 标记保证幂等（见第 5 条：table 多行 / AJAX 刷新会重复执行）。
     *
     * @return string
     */
    protected function mdPreviewScript()
    {
        $inputId = $this->getId();

        $configs = json_encode($this->jsOptions, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS);

        $script = <<<EOT

        (function () {
            var el = document.getElementById('{$inputId}-preview');
            if (!el || el.__mdRendered) { return; }
            el.__mdRendered = true;
            el.innerHTML = marked.parse(el.textContent, Object.assign({ breaks: true }, {$configs}));
        })();

EOT;
        $this->script[] = $script;

        return $script;
    }
}
