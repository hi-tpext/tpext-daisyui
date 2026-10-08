<?php

namespace tpext\builder\inface;

use tpext\builder\common\model\Attachment;

interface Storage
{
    /**
     * 存储附件并返回可访问的URL
     *
     * @param Attachment $attachment
     * @return string url
     */
    public function process($attachment);
}
