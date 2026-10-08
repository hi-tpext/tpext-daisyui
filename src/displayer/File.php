<?php

namespace tpext\builder\displayer;

/**
 * File单文件上传组件
 */
class File extends MultipleFile
{
    /**
     * 设置默认文件
     *
     * @param string $val
     * @return $this
     */
    public function default($val = '') {
        $this->default = $val;
        return $this;
    }

    /**
     * 渲染为HTML（限制单文件上传）
     *
     * @return mixed
     */
    public function render()
    {
        $this->jsOptions = array_merge($this->jsOptions, [
            'fileNumLimit' => 1,
            'multiple' => false,
        ]);

        // 表格/ items 中不再强制列宽 = thumbnailWidth：
        // 缩略图实际渲染尺寸由 JS 按 jsOptions 的 thumbnailWidth/Height 决定，
        // td 内边距各主题不同，强制窄于内容的宽度反而导致列宽与图片对不上；
        // 不设宽度时表格自动布局会让列宽贴住图片宽度

        return parent::render();
    }
}
