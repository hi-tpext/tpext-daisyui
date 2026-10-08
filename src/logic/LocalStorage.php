<?php

namespace tpext\builder\logic;

use tpext\builder\inface\Storage;
use tpext\builder\common\model\Attachment;

/**
 * 本地存储驱动：文件已保存在本地，直接返回url
 */
class LocalStorage implements Storage
{
    /**
     * 返回附件的可访问url（本地存储无需额外处理）
     *
     * @param Attachment $attachment
     * @return string url
     */
    public function process($attachment)
    {
        return $attachment['url'];
    }
}
