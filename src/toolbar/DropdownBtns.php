<?php

namespace tpext\builder\toolbar;

class DropdownBtns extends Bar
{
    protected $view = 'dropdownbtns';

    protected $items = [];

    protected $groupClass = '';

    protected $groupAttr = '';

    protected $groupStyle = '';

    /**
     * 获取下拉组元素ID
     *
     * @return string
     */
    public function getId()
    {
        return 'dropdown-' . $this->name . preg_replace('/[^\w\-]/', '', $this->extKey);
    }

    /**
     * 设置下拉项
     *
     * @param array $items
     * @return $this
     */
    public function items($items)
    {
        $this->items = $items;
        return $this;
    }

    /**
     * 获取下拉项
     *
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * 判断下拉项是否为空
     *
     * @return boolean
     */
    public function isEmpty()
    {
        return empty($this->items);
    }

    /**
     * 设置下拉组类名
     *
     * @param string $val
     * @return $this
     */
    public function groupClass($val)
    {
        $this->groupClass = $val;
        return $this;
    }

    /**
     * 设置下拉组属性
     *
     * @param string $val
     * @return $this
     */
    public function groupAttr($val)
    {
        $this->groupAttr = $val;
        return $this;
    }

    /**
     * 设置下拉组样式
     *
     * @param string $val
     * @return $this
     */
    public function groupStyle($val)
    {
        $this->groupStyle = $val;
        return $this;
    }

    /**
     * 追加下拉组类名
     *
     * @param string $val
     * @return $this
     */
    public function addGroupClass($val)
    {
        $this->groupClass .= ' ' . $val;
        return $this;
    }

    /**
     * 追加下拉组属性
     *
     * @param string $val
     * @return $this
     */
    public function addGroupAttr($val)
    {
        $this->groupAttr .= ' ' . $val;
        return $this;
    }

    /**
     * 追加下拉组样式
     *
     * @param string $val
     * @return $this
     */
    public function addGroupStyle($val)
    {
        $this->groupStyle .= $val;
        return $this;
    }

    /**
     * 获取含样式的下拉组属性
     *
     * @return string
     */
    public function getGroupAttrWithStyle()
    {
        return $this->groupAttr . (empty($this->groupStyle) ? '' : ' style="' . $this->groupStyle . '"');
    }

    /**
     * 渲染下拉按钮组
     *
     * @return mixed
     */
    public function render()
    {
        $vars = $this->commonVars();

        $actions = [];

        $items = $this->getItems();

        foreach ($items as $key => $it) {
            if (is_string($it)) {
                $it = ['label' => $it, 'url' => (string)url($key)];
            }
            $data = array_merge(
                [
                    'key' => $key,
                    'label' => '',
                    'icon' => '',
                    'url' => '',
                    'attr' => '',
                    'class' => '',
                ]
                , $it);

            $actions[$key] = $data;
        }

        $vars = array_merge($vars, [
            'items' => $actions,
            'groupAttr' => $this->getGroupAttrWithStyle(),
            'groupClass' => $this->groupClass,
            // 6.246 老版 Bootstrap 方向类 dropup 在 groupClass 里出现即向上弹出；
            // 模板属性内不能嵌 {if}（TP 编译器限制），方向在这里算好
            'dropdownDir' => false !== strpos($this->groupClass, 'dropup') ? 'dropdown-top' : 'dropdown-bottom',
        ]);

        $viewshow = $this->getViewInstance();

        return $viewshow->assign($vars)->getContent();
    }

    /**
     * 空实现，仅为兼容操作栏接口
     *
     * @param string $val
     * @return $this
     */
    public function dataId($val)
    {
        return $this;
    }

    /**
     * 空实现，仅为兼容操作栏接口
     *
     * @param array|\think\Model $data
     * @return $this
     */
    public function parseUrl($data)
    {
        return $this;
    }

    /**
     * 空实现，仅为兼容操作栏接口
     *
     * @param array $data
     * @return $this
     */
    public function parseMapClass($data)
    {
        return $this;
    }
}
