<?php

namespace tpext\builder\form;

use think\Model;
use tpext\builder\common\Builder;
use tpext\builder\common\Module;
use tpext\builder\form\FieldsContent;
use tpext\builder\inface\Renderable;
use tpext\builder\inface\ReleaseAble;
use tpext\builder\traits\HasDestroyOnce;
use tpext\builder\traits\HasDom;
use tpext\think\View;

class Step implements Renderable, ReleaseAble
{
    use HasDom;
    use HasDestroyOnce;

    protected $view = 'step';

    protected $navigateable = true;

    protected $size = [2, 8];

    protected $rows = [];

    protected $labels = [];

    protected $active = '';

    protected $id = '';

    protected $mode = 'dots';

    protected $readonly = false;

    /**
     * 各步骤的字段内容集合
     *
     * @var array
     */
    protected $__fields__ = [];

    /**
     * 获取步骤元素ID
     *
     * @return string
     */
    public function getId()
    {
        if (empty($this->id)) {
            $this->id = 'step-' . mt_rand(1000, 9999);
        }

        return $this->id;
    }

    /**
     * 添加一个步骤的字段内容
     *
     * @param string $label
     * @param string $description
     * @param boolean $isActive
     * @param string $name
     * @return FieldsContent
     */
    public function addFieldsContent($label, $description = '', $isActive = false, $name = '')
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

        $content = new FieldsContent();
        $this->__fields__[] = $content;

        $this->rows[$name] = ['content' => $content, 'description' => $description, 'active' => ''];
        $this->labels[$name] = ['content' => $label, 'active' => ''];

        return $content;
    }

    /**
     * 填充各步骤数据
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
     * 设置各步骤字段只读
     *
     * @param boolean $val
     * @return $this
     */
    public function readonly($val = true)
    {
        foreach ($this->__fields__ as $content) {
            $content->readonly($val);
        }
        $this->readonly = $val;
        return $this;
    }

    /**
     * 是否为字段分组
     *
     * @return boolean
     */
    public function isFieldsGroup()
    {
        return true;
    }

    /**
     * 设置步骤条是否可点击导航
     *
     * @param boolean $val
     * @return $this
     */
    public function navigateable($val)
    {
        $this->navigateable = $val;
        return $this;
    }

    /**
     * 设置步骤条布局尺寸（左侧占格、宽度占格）
     *
     * @param integer $left
     * @param integer $width
     * @return $this
     */
    public function size($left = 2, $width = 8)
    {
        $this->size = [$left, $width];
        return $this;
    }

    /**
     * 使用锚点模式显示步骤条
     *
     * @return $this
     */
    public function anchor()
    {
        $this->mode = 'anchor';
        return $this;
    }

    /**
     * 使用圆点模式显示步骤条
     *
     * @return $this
     */
    public function dots()
    {
        $this->mode = 'dots';
        return $this;
    }

    /**
     * 设置样式类名
     *
     * @param string $val
     * @return $this
     */
    function class($val)
    {
        $this->class = $val;
        return $this;
    }

    /**
     * 追加样式类名
     *
     * @param string $val
     * @return $this
     */
    public function addClass($val)
    {
        $this->class .= ' ' . $val;
        return $this;
    }

    /**
     * 设置当前激活的步骤
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
     * 获取样式类名
     *
     * @return string
     */
    public function getClass()
    {
        return empty($this->class) ? '' : ' ' . $this->class;
    }

    /**
     * 获取步骤行数据
     *
     * @return array
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * 渲染前处理
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
     * 渲染步骤条
     *
     * @param boolean $partial
     * @return mixed
     */
    public function render($partial = false)
    {
        $template = Module::getInstance()->getViewsPath() . 'form' . DIRECTORY_SEPARATOR . $this->view . '.html';

        $names = array_keys($this->labels);

        foreach ($names as $name) {
            if ($name == $this->active) {
                $this->labels[$name]['active'] = 'active';
                $this->rows[$name]['active'] = 'active';

                break;
            } else {
                $this->labels[$name]['active'] = 'complete';
                $this->rows[$name]['active'] = 'complete';
            }
        }

        $vars = [
            'labels' => $this->labels,
            'rows' => $this->rows,
            'active' => $this->active,
            'id' => $this->getId(),
            'class' => ($this->mode == 'anchor' ? 'step-anchor' : 'step-dots') . ' ' . $this->class,
            'mode' => $this->mode,
            'size' => $this->size,
            'attr' => $this->getAttrWithStyle(),
            'readonly' => $this->readonly,
        ];

        $viewshow = new View($template);

        if ($partial) {
            return $viewshow->assign($vars);
        }

        return $viewshow->assign($vars)->getContent();
    }

    /**
     * 销毁对象，释放资源
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
        // 6.260 审计补：labels['content'] 与 rows 指向同一批组件，需一并置空
        $this->labels = [];
        $this->__fields__ = [];
        $this->__destroyed__ = true;
    }
}
