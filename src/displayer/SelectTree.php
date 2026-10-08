<?php

namespace tpext\builder\displayer;

/**
 * SelectTree — 树形下拉选择器
 *
 * 使用 treeselectjs 实现: https://treeselectjs.com/
 * 依赖 CDN (jsdelivr):
 *   https://cdn.jsdelivr.net/npm/treeselectjs/dist/treeselectjs.umd.js
 *   https://cdn.jsdelivr.net/npm/treeselectjs/dist/treeselectjs.css
 *
 * 数据格式继承自 Tree::optionsData(): [{ value, name, children: [...] }]
 */
class SelectTree extends Tree
{
    protected $view = 'selecttree';

    /** Select + Tree 组合，默认单选（跟 Select 组件一致，而非 Tree 的多选） */
    protected $multiple = false;

    protected $attr = 'size="1"';

    protected $js = [
        '/assets/tpextdaisyui/js/vendors/treeselectjs.umd.js',
    ];

    protected $css = [
        '/assets/tpextdaisyui/js/vendors/treeselectjs.css',
    ];

    /** @var array treeselectjs 配置 */
    protected $jsOptions = [
        'placeholder' => '',
        'allowClear' => true,
        'showCount' => true,
        'isSingleSelect' => true,  // 默认单选（Select 组件行为）
        'closeOnSelect' => true,   // 单选选完自动关
        'showValue' => true,
        'isGroupSelectable' => false,
        // 旧库 noCascaded 默认 true = 父子独立选中不级联, 级联模式 value 只回传叶子 id
        'isIndependentNodes' => true,
        // treeselectjs 分组默认全折叠 (openLevel:0), 展开到深层需显式配置
        'openLevel' => 10,
    ];

    /**
     * 多选时, 父子节点是否独立选中
     *
     * @param boolean $val
     * @return $this
     */
    public function noCascaded($val = true)
    {
        $this->noCascaded = $val;
        // treeselectjs 里叫 isIndependentNodes
        $this->jsOptions['isIndependentNodes'] = $val;
        return $this;
    }

    /**
     * 设置占位提示
     *
     * @param string $val
     * @return $this
     */
    public function placeholder($val)
    {
        $this->jsOptions['placeholder'] = $val;
        return $this;
    }

    /**
     * 是否允许清空
     *
     * @param boolean $val
     * @return $this
     */
    public function allowClear($val = true)
    {
        $this->jsOptions['allowClear'] = $val;
        return $this;
    }

    /**
     * 是否显示数量标签
     *
     * @param boolean $val
     * @return $this
     */
    public function showCount($val = true)
    {
        $this->jsOptions['showCount'] = $val;
        return $this;
    }

    /**
     * 下拉高度 (treeselectjs 叫 listMaxHeight)
     *
     * @param int $val
     * @return $this
     */
    public function maxHeight($val = 400)
    {
        $this->jsOptions['listMaxHeight'] = $val;
        return parent::maxHeight($val);
    }

    /**
     * 是否分组可选 (select 组节点时选中组还是仅叶子)
     *
     * @param boolean $val
     * @return $this
     */
    public function groupSelectable($val = false)
    {
        $this->jsOptions['isGroupSelectable'] = $val;
        return $this;
    }

    /**
     * 多选 / 单选
     *
     * @param boolean $val
     * @return $this
     */
    public function multiple($val = true)
    {
        parent::multiple($val);
        $this->jsOptions['isSingleSelect'] = !$val;
        return $this;
    }

    /**
     * 渲染前处理：计算选中值与占位
     *
     * @return $this
     */
    public function beforRender()
    {
        // 自己计算 checked — 复用 Tree 的 Tree::beforRender 的逻辑
        // 但不调 parent::beforRender()（= Tree::beforRender），因为 SelectTree 走 treeselectjs
        if (!($this->value === '' || $this->value === null || $this->value === [])) {
            $this->checked = is_array($this->value) ? $this->value : explode(',', $this->value);
        } else if (!($this->default === '' || $this->default === null || $this->default === [])) {
            $this->checked = is_array($this->default) ? $this->default : explode(',', $this->default);
        }

        // placeholder 默认值
        if (empty($this->jsOptions['placeholder'])) {
            $this->jsOptions['placeholder'] = __blang('builder_please_select') . $this->getlabel();
        }

        // expandAll 对应旧库 zTree open:true (treeselectjs 分组默认折叠)
        $this->jsOptions['openLevel'] = $this->expandAll ? 10 : 0;

        // 跳过 Tree，直接调 Field
        return Field::beforRender();
    }

    /**
     * x-selecttree 元素配置：多形态原始 options + 已选值 + treeselectjs 配置
     * （归一化在元素 init 内逐行等价移植）
     *
     * @return string
     */
    protected function elementCfg()
    {
        $cfg = [
            'options' => $this->options ?: [],
            'checked' => $this->checked ?: [],
            'multiple' => $this->multiple,
            'jsOptions' => $this->jsOptions ?: [],
        ];

        return htmlspecialchars(json_encode($cfg, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 渲染为HTML
     *
     * @return mixed
     */
    public function render()
    {
        $vars = $this->commonVars();

        $vars = array_merge($vars, [
            'cfg' => $this->elementCfg(),
        ]);

        $viewshow = $this->getViewInstance();

        return $viewshow->assign($vars)->getContent();
    }
}
