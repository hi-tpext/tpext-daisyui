<?php

namespace tpext\builder\displayer;

/**
 * Image单图片上传组件
 */
class Image extends File
{
    /**
     * 创建组件：设置图片大小限制
     *
     * @param string $fieldType
     * @return $this
     */
    public function created($fieldType = '')
    {
        parent::created($fieldType);
        $this->jsOptions['fileSingleSizeLimit'] = 2 * 1024 * 1024;
    }

    /**
     * 渲染为HTML（图片模式）
     *
     * @return mixed
     */
    public function render()
    {
        $this->image();

        $this->canUpload = !$this->readonly && $this->canUpload && ($this->isInTable || empty($this->extKey) || stripos($this->extKey, '-watch-') !== false);

        if (!$this->canUpload) {
            if (empty($this->default)) {
                $this->default = '/assets/tpextdaisyui/images/default.png';
            }
        }

        $this->jsOptions['isImage'] = true;

        return parent::render();
    }
}
