<?php

namespace tpext\builder\displayer;

/**
 * MultipleSelect多选下拉组件（基于TomSelect）
 */
class MultipleSelect extends Select
{
    protected $view = 'multipleselect';

    protected $attr = 'size="1"';

    /** 多选：已选项带删除图标 + 清空按钮（TomSelect complete 构建插件） */
    protected $tomPlugins = ['remove_button', 'clear_button'];

    /**
     * 默认选中值
     *
     * @var array|string
     */
    protected $default = [];

    /**
     * 已选中的值
     *
     * @var array|string
     */
    protected $checked = [];

    protected $isArrayValue = true;

    /**
     * 创建组件：覆盖为多选配置
     *
     * @param string $fieldType
     * @return $this
     */
    public function created($fieldType = '')
    {
        parent::created($fieldType);
        $this->jsOptions['closeOnSelect'] = false;
        $this->jsOptions['maxItems'] = null; // 多选不限制数量（覆盖 Select 的单选默认 1）
    }

    /**
     * 设置默认选中值
     *
     * @param array|string $val
     * @return $this
     */
    public function default($val = [])
    {
        $this->default = $val;
        return $this;
    }

    /**
     * 渲染为HTML
     *
     * @return mixed
     */
    public function render()
    {
        $vars = $this->commonVars();

        // 6.214 过滤空串：模型存的多选值可能带首尾逗号（,HTKY,），
        // 不滤的话 dataSelected 拼回 ',HTKY,'，ajax 回显 fetch/setValue 全对不上
        if (!($this->value === '' || $this->value === null || $this->value === [])) {
            $this->checked = is_array($this->value) ? $this->value : array_values(array_filter(explode(',', $this->value), 'strlen'));
        } else if (!($this->default === '' || $this->default === null || $this->default === [])) {
            $this->checked = is_array($this->default) ? $this->default : array_values(array_filter(explode(',', $this->default), 'strlen'));
        }

        $this->isGroup();

        $dataSelected = $this->checked;

        foreach ($this->checked as &$ck) {
            $ck = '-' . $ck;
        }

        unset($ck);

        if ($this->disabledOptions && !is_array($this->disabledOptions)) {
            $this->disabledOptions = explode(',', $this->disabledOptions);
        }
        foreach ($this->disabledOptions as &$di) {
            $di = '-' . $di;
        }

        $vars = array_merge($vars, [
            'checked' => $this->checked,
            'dataSelected' => implode(',', $dataSelected), //已经手动在后端给了选项的，不再ajax加载默认值
            'dataDisabled' => implode(',', $this->disabledOptions),
            'group' => $this->group,
            'options' => $this->options,
            'disabledOptions' => $this->disabledOptions,
        ]);

        $viewshow = $this->getViewInstance();

        return $viewshow->assign($vars)->getContent();
    }
}
