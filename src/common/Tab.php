<?php

namespace tpext\builder\common;

use think\Model;
use tpext\builder\form\FieldsContent;
use tpext\builder\inface\Renderable;
use tpext\builder\inface\ReleaseAble;
use tpext\builder\traits\HasDestroyOnce;
use tpext\builder\traits\HasDom;
use tpext\think\View;

class Tab extends Widget implements Renderable, ReleaseAble
{
    use HasDom;
    use HasDestroyOnce;

    protected $view = 'tab';

    protected $rows = [];

    protected $labels = [];

    protected $active = '';

    protected $id = '';

    protected $partial = false;

    protected $vertical = false;

    /**
     * 通过addFieldsContent添加的内容组列表
     *
     * @var FieldsContent[]
     */
    protected $__fields__ = [];

    /**
     * 渲染载体（模板 View）
     *
     * @var View|null
     */
    protected $content;

    /**
     * 获取tab容器id（无则生成）
     *
     * @return string
     */
    public function getId()
    {
        if (empty($this->id)) {
            $this->id = 'tab-' . mt_rand(1000, 9999);
        }

        return $this->id;
    }

    /**
     * 添加一个链接型tab（点击跳转href）
     *
     * @param string $label
     * @param string $href
     * @param boolean $isActive
     * @param string $name
     * @return $this
     */
    public function addLink($label, $href, $isActive = false, $name = '')
    {
        if (empty($name)) {
            $name = (count($this->labels) + 1);
        }

        if (empty($this->active) && count($this->labels) == 0) {
            $this->active = $name;
        }

        if ($isActive) {
            $this->active = $name;
        }

        $this->labels[$name] = ['content' => $label, 'active' => '', 'href' => $href, 'attr' => ''];

        return $this;
    }

    /**
     * 添加一个tab页并返回其内容行
     *
     * @param string $label
     * @param boolean $isActive
     * @param string $name
     * @return Row
     */
    public function add($label, $isActive = false, $name = '')
    {
        if (empty($name)) {
            $name = (count($this->labels) + 1);
        }

        if (empty($this->active) && count($this->labels) == 0) {
            $this->active = $name;
        }

        if ($isActive) {
            $this->active = $name;
        }

        $row = Row::make();

        $this->rows[$name] = ['content' => $row, 'active' => ''];
        $this->labels[$name] = ['content' => $label, 'active' => '', 'href' => '#' . $this->getId() . '-' . $name, 'attr' => 'data-toggle="tab"'];

        return $row;
    }

    /**
     * 添加一个tab页并在其中创建form
     *
     * @param string $label
     * @param boolean $isActive
     * @param string $name
     * @param integer $size
     * @return Form
     */
    public function form($label, $isActive = false, $name = '', $size = 12)
    {
        return $this->add($label, $isActive, $name)->form($size);
    }

    /**
     * 添加一个tab页并在其中创建table
     *
     * @param string $label
     * @param boolean $isActive
     * @param string $name
     * @param integer $size
     * @return Table
     */
    public function table($label, $isActive = false, $name = '', $size = 12)
    {
        return $this->add($label, $isActive, $name)->table($size);
    }

    /**
     * 添加一个tab页并在其中创建自定义内容
     *
     * @param string $label
     * @param boolean $isActive
     * @param string $name
     * @param integer $size
     * @return Content
     */
    public function content($label, $isActive = false, $name = '', $size = 12)
    {
        return $this->add($label, $isActive, $name)->content($size);
    }

    /**
     * 设置是否局部渲染（render时返回View对象）
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
     * 设置是否垂直布局
     *
     * @param boolean $val
     * @return $this
     */
    public function vertical($val = true)
    {
        $this->vertical = $val;
        return $this;
    }

    /**
     * 添加一个tab页并创建FieldsContent（供表单字段归入）
     *
     * @param string $label
     * @param boolean $isActive
     * @param string $name
     * @return FieldsContent
     */
    public function addFieldsContent($label, $isActive = false, $name = '')
    {
        if (empty($name)) {
            $name = (count($this->rows) + 1);
        }

        if (empty($this->active) && count($this->rows) == 0) {
            $this->active = $name;
        }

        if ($isActive) {
            $this->active = $name;
        }

        $content = new FieldsContent();
        $this->__fields__[] = $content;

        $this->rows[$name] = ['content' => $content, 'active' => ''];
        $this->labels[$name] = ['content' => $label, 'active' => '', 'href' => '#' . $this->getId() . '-' . $name, 'attr' => 'data-toggle="tab"'];

        return $content;
    }

    /**
     * 填充所有tab页内的表单数据
     *
     * @param array|Model|\ArrayAccess $data
     * @return $this
     */
    public function fill($data = [])
    {
        foreach ($this->__fields__ as $content) {
            $content->fill($data);
        }
        return $this;
    }

    /**
     * 设置所有tab页内的字段只读
     *
     * @param boolean $val
     * @return $this
     */
    public function readonly($val = true)
    {
        foreach ($this->__fields__ as $content) {
            $content->readonly($val);
        }
        return $this;
    }

    /**
     * 是否为字段容器组
     *
     * @return boolean
     */
    public function isFieldsGroup()
    {
        return true;
    }

    /**
     * 设置默认激活的tab（按name）
     *
     * @param string $val
     * @return $this
     */
    public function active($val)
    {
        $names = array_keys($this->labels);

        if (in_array($val, $names)) {
            $this->active = $val;
        }

        return $this;
    }

    /**
     * 获取所有tab内容行
     *
     * @return array
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * 渲染前的准备：递归准备各tab内容
     *
     * @return $this
     */
    public function beforRender()
    {
        foreach ($this->rows as $row) {
            $row['content']->beforRender();
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
     * 渲染为HTML（partial时返回View对象）
     *
     * @return string|View
     */
    public function render()
    {
        $template = Module::getInstance()->getViewsPath() . $this->view . '.html';

        $this->labels[$this->active]['active'] = 'active';

        if (isset($this->rows[$this->active])) {
            $this->rows[$this->active]['active'] = 'in active';
        }

        $vars = [
            'labels' => $this->labels,
            'rows' => $this->rows,
            'active' => $this->active,
            'id' => $this->getId(),
            'class' => $this->class . ($this->vertical ? ' tabs-vertical' : ' tabs-horizontal'),
            'attr' => $this->getAttrWithStyle(),
            'vertical' => $this->vertical, // 模板按此分支输出不同 DOM（垂直重排标签/面板两段）
        ];

        $customVars = $this->customVars();

        if (!empty($customVars)) {
            $vars = array_merge($vars, $customVars);
        }

        $viewshow = new View($template);

        if ($this->partial) {
            return $viewshow->assign($vars);
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
        $this->partial = false;
        return $this->render();
    }

    /**
     * 释放资源，销毁各tab内容
     *
     * @return void
     */
    public function destroy()
    {
        // 6.261 已销毁直接返回：同一组件可被多归属路径重复触达（契约见 traits\HasDestroyOnce）
        if ($this->__destroyed__) {
            return;
        }

        foreach ($this->rows as $row) {
            if (isset($row['content']) && $row['content'] instanceof ReleaseAble) {
                $row['content']->destroy();
            }
        }
        // 6.260 数组属性复位为空数组（保持类型恒定，二次 destroy 自然幂等）
        $this->rows = [];
        // 6.260 审计补：labels['content'] 与 rows 指向同一批组件，rows 置空后
        // 若不清 labels，FieldsContent 树仍被引用无法回收
        $this->labels = [];
        $this->__fields__ = [];
        $this->__destroyed__ = true;
    }
}
