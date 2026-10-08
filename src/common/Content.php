<?php

namespace tpext\builder\common;

use tpext\builder\inface\Renderable;
use tpext\builder\inface\ReleaseAble;
use tpext\builder\traits\HasDestroyOnce;
use tpext\think\View;

class Content extends Widget implements Renderable, ReleaseAble
{
    use HasDestroyOnce;

    /**
     * 模板视图对象
     *
     * @var View|null
     */
    protected $content;

    protected $contentRaw = '';

    protected $partial = false;

    /**
     * 设置是否局部渲染（render时直接返回View对象）
     *
     * @param boolean $val
     * @return $this
     */
    public function partial($val = true)
    {
        $this->partial = $val;
        return $this;
    }

    /**
     * 加载模板文件
     *
     * @param string $template
     * @param array $vars
     * @return $this
     */
    public function fetch($template = '', $vars = [])
    {
        $this->content = new View($template);

        $this->content->assign($vars);
        return $this;
    }

    /**
     * 设置要显示的内容（无变量时原样输出不解析模板）
     *
     * @param string $content
     * @param array $vars
     * @return $this
     */
    public function display($content = '', $vars = [])
    {
        $this->content = new View($content);

        $this->content->assign($vars)->isContent(true);

        if (empty($vars)) {
            $this->contentRaw = $content;//如果没有变量，那么就不解析模板
        }

        return $this;
    }

    /**
     * 渲染前的准备
     *
     * @return $this
     */
    public function beforRender()
    {
        return $this;
    }

    /**
     * 渲染为HTML字符串（partial时返回View对象）
     *
     * @return string|View
     */
    public function render()
    {
        if ($this->partial) {
            return $this->content;
        }

        if ($this->contentRaw) {
            return $this->contentRaw;
        }

        return $this->content->getContent();
    }

    /**
     * 转为字符串时返回渲染后的内容
     *
     * @return string
     */
    public function __toString()
    {
        $this->partial = false;
        return $this->render();
    }

    /**
     * 释放资源，销毁视图对象
     *
     * @return void
     */
    public function destroy()
    {
        // 6.261 已销毁直接返回：同一组件可被多归属路径重复触达（契约见 traits\HasDestroyOnce）
        if ($this->__destroyed__) {
            return;
        }

        $this->content = null;
        $this->__destroyed__ = true;
    }
}
