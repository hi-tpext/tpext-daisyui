<?php

namespace tpext\builder\common;

use think\Service as BaseService;

/**
 * 请求结束时销毁 Builder 实例
 */
class Service extends BaseService
{
    /**
     * 注册HttpEnd事件监听，请求结束时销毁Builder实例
     *
     * @return void
     */
    public function boot()
    {
        $this->app->event->listen('HttpEnd', function () {
            Builder::destroyInstance();
        });
    }
}
