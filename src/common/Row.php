<?php

namespace tpext\builder\common;

use tpext\builder\inface\ReleaseAble;
use tpext\builder\traits\HasDestroyOnce;

use tpext\builder\traits\HasDom;
use tpext\builder\tree\Tree;
use tpext\think\View;

class Row extends Widget implements ReleaseAble
{
    use HasDom;
    use HasDestroyOnce;

    /**
     * 行包含的所有列
     *
     * @var Column[]
     */
    protected $cols = [];

    /**
     * 新增一列
     *
     * @param integer|string $size
     * @return Column
     */
    public function column($size = 12)
    {
        $col = self::makeWidget('Column', $size);
        $this->cols[] = $col;
        return $col;
    }

    /**
     * 获取一个form
     *
     * @param integer|string $size
     * @return Form
     */
    public function form($size = 12)
    {
        return $this->column($size)->form();
    }

    /**
     * 获取一个表格
     *
     * @param integer|string $size
     * @return Table
     */
    public function table($size = 12)
    {
        return $this->column($size)->table();
    }

    /**
     * 获取一个工具栏
     *
     * @param integer|string $size
     * @return Toolbar
     */
    public function toolbar($size = 12)
    {
        return $this->column($size)->toolbar();
    }

    /**
     * 获取一个 Tree 组件
     *
     * @param integer|string $size
     * @return Tree
     */
    public function tree($size = 12)
    {
        return $this->column($size)->tree();
    }

    /**
     * 获取一个 Tree 组件 (原 ZTree, 已废弃)
     *
     * @deprecated 请使用 tree()
     * @param integer|string $size
     * @return Tree
     */
    public function zTree($size = 12)
    {
        return $this->column($size)->zTree();
    }

    /**
     * 获取一个 Tree 组件 (原 JSTree, 已废弃)
     *
     * @deprecated 请使用 tree()
     * @param integer|string $size
     * @return Tree
     */
    public function jsTree($size = 12)
    {
        return $this->column($size)->jsTree();
    }

    /**
     * 获取一个自定义内容
     *
     * @param integer|string $size
     * @return Content
     */
    public function content($size = 12)
    {
        return $this->column($size)->content();
    }

    /**
     * 渲染指定模板内容
     *
     * @param string $template
     * @param array $vars
     * @param integer|string $size col大小
     * @return $this
     */
    public function fetch($template = '', $vars = [], $size = 12)
    {
        $this->content($size)->fetch($template, $vars);

        return $this;
    }

    /**
     * 直接输出自定义内容
     *
     * @param string $content
     * @param array $vars
     * @param integer|string $size col大小
     * @return $this
     */
    public function display($content = '', $vars = [], $size = 12)
    {
        $this->content($size)->display($content, $vars);

        return $this;
    }

    /**
     * 获取一个tab
     *
     * @param integer|string $size
     * @return Tab
     */
    public function tab($size = 12)
    {
        return $this->column($size)->tab();
    }

    /**
     * 获取一Swiper
     *
     * @param integer|string $size col大小
     * @return Swiper
     */
    public function swiper($size = 12)
    {
        return $this->column($size)->swiper();
    }

    /**
     * 获取行内所有列
     *
     * @return Column[]
     */
    public function getCols()
    {
        return $this->cols;
    }

    /**
     * 渲染前的准备：递归准备各列
     *
     * @return $this
     */
    public function beforRender()
    {
        foreach ($this->cols as $col) {
            $col->beforRender();
        }

        return $this;
    }

    /**
     * 子类可覆盖，返回要附加到视图的自定义变量
     *
     * @return array
     */
    public function customVars()
    {
        return [];
    }

    /**
     * 渲染为HTML
     *
     * @return string
     */
    public function render()
    {
        $template = Module::getInstance()->getViewsPath() . 'row.html';

        $viewshow = new View($template);

        $vars = [
            'cols' => $this->cols,
            'class' => $this->class,
            'attr' => $this->getAttrWithStyle(),
        ];

        $customVars = $this->customVars();

        if (!empty($customVars)) {
            $vars = array_merge($vars, $customVars);
        }

        return $viewshow->assign($vars)->getContent();
    }

    /**
     * 转为字符串时返回渲染后的HTML
     *
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }

    /**
     * 释放资源，销毁所有列
     *
     * @return void
     */
    public function destroy()
    {
        // 6.261 已销毁直接返回：同一组件可被多归属路径重复触达（契约见 traits\HasDestroyOnce）
        if ($this->__destroyed__) {
            return;
        }

        foreach ($this->cols as $col) {
            $col->destroy();
        }

        // 6.260 数组属性复位为空数组（保持类型恒定，二次 destroy 自然幂等）
        $this->cols = [];
        $this->__destroyed__ = true;
    }
}
