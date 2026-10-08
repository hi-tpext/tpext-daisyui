<?php

namespace tpext\builder\inface;

interface Renderable
{
    /**
     * 渲染为HTML
     *
     * @return mixed
     */
    public function render();

    /**
     * 渲染前的准备工作
     *
     * @return void
     */
    public function beforRender();
}
