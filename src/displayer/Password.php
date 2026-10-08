<?php

namespace tpext\builder\displayer;

/**
 * Password密码输入组件
 */
class Password extends Text
{
    protected $view = 'password';
    protected $cssFamily = 'widgets'; // 6.181 按需加载
}
