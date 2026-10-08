<?php

namespace tpext\builder\common;

use tpext\think\View;
use tpext\builder\toolbar\Bar;
use tpext\builder\table\TEmpty;
use tpext\builder\traits\HasDom;
use tpext\builder\toolbar\BWrapper;
use tpext\builder\inface\Renderable;
use tpext\builder\inface\ReleaseAble;
use tpext\builder\traits\HasDestroyOnce;

class Toolbar extends BWrapper implements Renderable, ReleaseAble
{
    use HasDom;
    use HasDestroyOnce;

    protected $view = '';

    /**
     * 工具栏内所有元素
     *
     * @var Bar[]
     */
    protected $elms = [];

    /**
     * 当前元素
     *
     * @var Bar
     */
    protected $__elm__;

    protected $extKey = '';

    protected $elmsRight = [];

    protected $elmsLeft = [];

    /**
     * 按钮行容器（6.238）：createBar 加入当前行，br() 开启新行。
     * 渲染时每行包一个 div.action-row，行内不自动折行，列宽由最宽行决定
     *
     * @var array<Bar[]>
     */
    protected $rows = [[]];

    protected $lockForExporting = false;

    /**
     * 导出时的空元素占位
     *
     * @var TEmpty|null
     */
    protected $tEmpty = null;

    /**
     * 设置是否锁定导出（导出时工具栏方法调用返回空元素）
     *
     * @param bool $val
     * @return $this
     */
    public function lockForExporting($val = true)
    {
        $this->lockForExporting = $val;

        return $this;
    }

    /**
     * 实例创建时的初始化（创建空元素占位）
     *
     * @return $this
     */
    public function created()
    {
        $this->tEmpty = new TEmpty;

        return $this;
    }

    /**
     * 设置扩展键（用于生成元素唯一id）
     *
     * @param string $val
     * @return $this
     */
    public function extKey($val)
    {
        $this->extKey = $val;
        return $this;
    }

    /**
     * 获取当前元素
     *
     * @return Bar
     */
    public function getCurrent()
    {
        return $this->__elm__;
    }

    /**
     * 获取工具栏内所有元素
     *
     * @return Bar[]
     */
    public function getElms()
    {
        return $this->elms;
    }

    /**
     * 工具栏是否为空
     *
     * @return boolean
     */
    public function isEmpty()
    {
        return empty($this->elms);
    }

    /**
     * 清空工具栏所有元素
     *
     * @return $this
     */
    public function clear()
    {
        $this->__elm__ = null;
        $this->elms = [];
        $this->elmsRight = [];
        $this->elmsLeft = [];
        $this->rows = [[]];

        return $this;
    }

    /**
     * 以下为代理方法，当前[$__elm__]生效
     *
     */

    /**
     * 设置当前元素是否靠右
     *
     * @param boolean $val
     * @return $this
     */
    public function pullRight($val = true)
    {
        if ($this->__elm__) {
            $this->__elm__->pullRight($val);
        }

        return $this;
    }

    /**
     * 设置当前元素是否靠右（pullRight别名）
     *
     * @param boolean $val
     * @return $this
     */
    public function barPullRight($val = true)
    {
        if ($this->__elm__) {
            $this->__elm__->pullRight($val);
        }

        return $this;
    }

    /**
     * 设置当前元素点击弹出弹层
     *
     * @param boolean $val
     * @param array|string $size
     * @return $this
     */
    public function barUseLayer($val, $size = [])
    {
        if ($this->__elm__) {
            $this->__elm__->useLayer($val, $size);
        }

        return $this;
    }

    /**
     * 设置当前元素弹层尺寸（同时启用弹层）
     *
     * @param boolean $val
     * @param array|string $size
     * @return $this
     */
    public function barLayerSize($size = [])
    {
        if ($this->__elm__) {
            $this->__elm__->useLayer(true, $size);
        }

        return $this;
    }

    /**
     * 设置当前元素文字标签
     *
     * @param string $val
     * @return $this
     */
    public function barLabel($val)
    {
        if ($this->__elm__) {
            $this->__elm__->label($val);
        }
        return $this;
    }

    /**
     * 设置当前元素图标
     *
     * @param string $val
     * @return $this
     */
    public function barIcon($val)
    {
        if ($this->__elm__) {
            $this->__elm__->icon($val);
        }
        return $this;
    }

    /**
     * 设置当前元素链接地址
     *
     * @param string $val
     * @return $this
     */
    public function barHref($val)
    {
        if ($this->__elm__) {
            $this->__elm__->href($val);
        }

        return $this;
    }

    /**
     * 设置当前元素name属性
     *
     * @param string $val
     * @return $this
     */
    public function barName($val)
    {
        if ($this->__elm__) {
            $this->__elm__->name($val);
        }

        return $this;
    }

    /**
     * 设置当前元素class（覆盖）
     *
     * @param string $val
     * @return $this
     */
    function barClass($val)
    {
        if ($this->__elm__) {
            $this->__elm__->class($val);
        }
        return $this;
    }

    /**
     * 设置当前元素attr属性（覆盖）
     *
     * @param string $val
     * @return $this
     */
    public function barAttr($val)
    {
        if ($this->__elm__) {
            $this->__elm__->attr($val);
        }
        return $this;
    }

    /**
     * 设置当前元素style样式（覆盖）
     *
     * @param string $val
     * @return $this
     */
    public function barStyle($val)
    {
        if ($this->__elm__) {
            $this->__elm__->style($val);
        }
        return $this;
    }

    /**
     * 为当前元素追加class
     *
     * @param string $val
     * @return $this
     */
    public function barAddClass($val)
    {
        if ($this->__elm__) {
            $this->__elm__->addClass($val);
        }
        return $this;
    }

    /**
     * 为当前元素追加attr属性
     *
     * @param string $val
     * @return $this
     */
    public function barAddAttr($val)
    {
        if ($this->__elm__) {
            $this->__elm__->addAttr($val);
        }
        return $this;
    }

    /**
     * 为当前元素追加style样式
     *
     * @param string $val
     * @return $this
     */
    public function barAddStyle($val)
    {
        if ($this->__elm__) {
            $this->__elm__->addStyle($val);
        }
        return $this;
    }

    /**
     * 创建一个bar元素并设为当前元素
     *
     * @param string $name
     * @param mixed $arguments
     *
     * @return mixed
     */
    protected function createBar($name, $arguments = [])
    {
        $this->__elm__ = BWrapper::makeBar($name, $arguments);
        $this->elms[] = $this->__elm__;
        $this->rows[count($this->rows) - 1][] = $this->__elm__;
        return $this->__elm__;
    }

    /**
     * 换行（6.238）：开启新行数组，渲染时每行包一个 div.action-row。
     * 行内不自动折行——用户没有明确调用 br() 就不换行
     *
     * @return $this
     */
    public function br()
    {
        $this->rows[] = [];
        return $this;
    }

    /**
     * 渲染前的准备：按靠右/靠左分组并递归准备元素
     *
     * @return $this
     */
    public function beforRender()
    {
        $this->elmsLeft = $this->elmsRight = [];

        foreach ($this->elms as $elm) {

            if ($this->extKey) {
                $elm->extKey($this->extKey);
            }

            if ($elm->isPullRight()) {
                $this->elmsRight[] = $elm;
            } else {
                $this->elmsLeft[] = $elm;
            }

            $elm->beforRender();
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
     * 按行渲染（6.238）：每行一个 div.action-row，由 br() 显式分行。
     * 无 br() 时只有一行，结构与旧版等价；空行（连续/尾部 br）不输出。
     * pull-right 的元素仍走 elmsRight 容器，不进行 div
     *
     * @return string
     */
    protected function rowsHtml()
    {
        $html = '';

        foreach ($this->rows as $row) {
            $items = '';
            foreach ($row as $elm) {
                if (!in_array($elm, $this->elmsLeft, true)) {
                    continue;
                }
                $items .= $elm->render();
            }
            if ('' === $items) {
                continue;
            }
            $html .= '<div class="action-row">' . $items . '</div>';
        }

        return $html;
    }

    /**
     * 渲染为HTML
     *
     * @return string
     */
    public function render()
    {
        $template = Module::getInstance()->getViewsPath() . 'toolbar.html';

        $viewshow = new View($template);

        $vars = [
            'elms' => $this->elms,
            'elmsLeft' => $this->elmsLeft,
            'elmsRight' => $this->elmsRight,
            'rows' => $this->rowsHtml(),
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
     * 魔术方法：以bar类名（小驼峰）创建工具栏元素，如 ->button('xxx')
     *
     * @param string $name
     * @param array $arguments
     * @return Bar|TEmpty
     */
    public function __call($name, $arguments)
    {
        if ($this->lockForExporting) {
            return $this->tEmpty;
        }

        $count = count($arguments);

        if ($count > 0 && self::isBar($name)) {

            $bar = $this->createBar($name, $arguments);

            return $bar;
        }

        throw new \InvalidArgumentException(__blang('builder_invalid_argument_exception') . ' : ' . $name);
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
     * 释放资源，销毁所有元素
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
        // 6.260 审计补：rows 是 elms 的分组成引用（createBar 双写），不清则 Bar 仍被持有
        $this->rows = [];
        $this->elmsRight = [];
        $this->elmsLeft = [];
        $this->__elm__ = null;
        $this->tEmpty = null;
        $this->__destroyed__ = true;
    }
}
