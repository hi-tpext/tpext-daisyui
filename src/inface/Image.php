<?php

namespace tpext\builder\inface;

use tpext\builder\common\model\Attachment;

interface Image
{
    /**
     * 处理上传的图片附件（如生成缩略图、加水印），返回处理后的URL
     *
     * @param Attachment $attachment
     * @param array $args
     * @return string url
     */
    public function process($attachment, $args);
}
