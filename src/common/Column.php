<?php

namespace tpext\builder\common;

use tpext\builder\common\Form;
use tpext\builder\common\Table;
use tpext\builder\inface\Renderable;
use tpext\builder\inface\ReleaseAble;
use tpext\builder\traits\HasDestroyOnce;
use tpext\builder\traits\HasDom;
use tpext\builder\tree\Tree;

class Column extends Widget implements ReleaseAble
{
    use HasDom;
    use HasDestroyOnce;

    public $size = 12;

    protected $elms = [];

    public function __construct($size = 12)
    {
        $this->size = $size;
    }

    /**
     * 创建并挂载一个子部件
     *
     * @param string $name
     * @param mixed $arguments
     *
     * @return mixed
     */
    protected function createWidget($name, ...$arguments)
    {
        $widget = Widget::makeWidget($name, $arguments);
        $this->elms[] = $widget;
        return $widget;
    }

    /**
     * 追加一个可渲染对象
     *
     * @param Renderable $rendable
     * @return $this
     */
    public function append($rendable)
    {
        $this->elms[] = $rendable;
        return $this;
    }

    /**
     * 获取一个form
     *
     * @return Form
     */

    public function form()
    {
        return $this->createWidget('Form');
    }

    /**
     * 获取一个表格
     *
     * @return Table
     */
    public function table()
    {
        return $this->createWidget('Table');
    }

    /**
     * 获取一个Toolbar
     *
     * @return Toolbar
     */
    public function toolbar()
    {
        return $this->createWidget('Toolbar');
    }

    /**
     * 获取一个 Tree 组件
     *
     * @return Tree
     */
    public function tree()
    {
        return $this->createWidget('Tree');
    }

    /**
     * 获取一个 Tree 组件 (原 ZTree, 已废弃)
     *
     * @deprecated 请使用 tree()
     * @return Tree
     */
    public function zTree()
    {
        return $this->createWidget('Tree');
    }

    /**
     * 获取一个 Tree 组件 (原 JSTree, 已废弃)
     *
     * @deprecated 请使用 tree()
     * @return Tree
     */
    public function jsTree()
    {
        return $this->createWidget('Tree');
    }

    /**
     * 获取一个自定义内容
     *
     * @return Content
     */
    public function content()
    {
        return $this->createWidget('Content');
    }

    /**
     * 获取一个 tab
     *
     * @return Tab
     */
    public function tab()
    {
        return $this->createWidget('Tab');
    }

    /**
     * 获取一Swiper
     *
     * @return Swiper
     */
    public function swiper()
    {
        return $this->createWidget('Swiper');
    }

    /**
     * 获取一新行
     *
     * @return Row
     */
    public function row()
    {
        return $this->createWidget('Row');
    }

    /**
     * 获取列内的所有元素
     *
     * @return array
     */
    public function getElms()
    {
        return $this->elms;
    }

    /**
     * 获取列宽
     *
     * @return int|string
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * 获取适配后的尺寸 CSS 类（Tailwind grid 版本）
     *
     * @return string
     */
    public function getSizeClass()
    {
        return Widget::getSizeAdapter()->adjustColSize($this->size);
    }

    /**
     * 渲染指定模板内容
     *
     * @param string $template
     * @param array $vars
     * @return $this
     */
    public function fetch($template = '', $vars = [])
    {
        $this->content()->fetch($template, $vars);

        return $this;
    }

    /**
     * 直接输出自定义内容
     *
     * @param string $content
     * @param array $vars
     * @return $this
     */
    public function display($content = '', $vars = [])
    {
        $this->content()->display($content, $vars);

        return $this;
    }

    /**
     * 渲染前的准备：递归准备列内元素
     *
     * @return $this
     */
    public function beforRender()
    {
        foreach ($this->elms as $elm) {
            if (!($elm instanceof Renderable)) {
                continue;
            }
            $elm->beforRender();
        }

        return $this;
    }

    /**
     * 魔术方法：以方法名创建对应部件（如 ->Swiper()）
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (self::isWidget($name)) {

            $widget = $this->createWidget($name, $arguments);

            return $widget;
        }

        throw new \InvalidArgumentException(__blang('builder_invalid_argument_exception') . ' : ' . $name);
    }

    /**
     * 释放资源，销毁列内所有元素
     *
     * @return void
     */
    public function destroy()
    {
        // 6.261 已销毁直接返回：同一组件可被多归属路径重复触达（契约见 traits\HasDestroyOnce）
        if ($this->__destroyed__) {
            return;
        }

        foreach ($this->elms as $elm) {
            if ($elm instanceof ReleaseAble || method_exists($elm, 'destroy')) {
                $elm->destroy();
            }
        }

        // 6.260 数组属性复位为空数组（保持类型恒定，二次 destroy 自然幂等）
        $this->elms = [];
        $this->__destroyed__ = true;
    }
}
