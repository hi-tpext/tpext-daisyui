<?php

namespace tpext\builder\inface;

interface Auth
{
    /**
     * 检查当前用户是否有权限访问指定URL
     *
     * @param string $url
     * @return boolean
     */
    public static function checkUrl($url);
}
