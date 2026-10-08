<?php

namespace tpext\builder\displayer;
use tpext\builder\inface\ReleaseAble;


use tpext\builder\traits\HasOptions;
use tpext\builder\traits\HasWhen;
use tpext\builder\common\Search;

/**
 * Select下拉选择组件（基于TomSelect，支持ajax远程数据与级联）
 */
class Select extends Field implements ReleaseAble
{
    use HasOptions;
    use HasWhen;

    protected $view = 'select';
    protected $cssFamily = 'tomselect'; // 6.181 按需加载

    protected $js = [
        '/assets/tpextdaisyui/js/vendors/tom-select.min.js',
    ];

    protected $css = [
        '/assets/tpextdaisyui/css/tom-select.min.css',
    ];

    protected $attr = 'size="1"';

    protected $group = false;

    /**
     * 级联时的上级Select
     *
     * @var Select
     */
    protected $prevSelect = null;

    protected $withParams = [];

    protected $checked = '';

    protected $disabledOptions = [];

    protected $jsOptions = [
        'placeholder' => '',
        'create' => false,
        'loadMore' => true,
        'maxItems' => 1,
    ];

    /**
     * TomSelect 插件（complete 构建内置 remove_button/clear_button 等）
     * 单选：仅清空按钮；多选（MultipleSelect 覆盖）：已选项删除图标 + 清空按钮
     */
    protected $tomPlugins = ['clear_button'];

    /**
     * 已弃用（6.207）：新 UI 单选统一走 TomSelect（x-select 元素），
     * 不再支持切回原生 select。保留方法仅为兼容旧调用（myadmin8 Form.php 等），
     * 调用无任何作用。
     *
     * @deprecated 无实际作用
     * @param boolean $use
     * @return $this
     */
    public function select2($use)
    {
        return $this;
    }

    /**
     * 设置远程数据源（ajax懒加载）
     *
     * @param string $url
     * @param string $textField text|name
     * @param string $idField
     * @param integer $delay
     * @param boolean $loadmore
     * @return $this
     */
    public function dataUrl($url, $textField = '', $idField = '', $delay = 250, $loadmore = true)
    {
        $this->jsOptions['ajax'] = [
            'url' => (string)$url,
            'id' => $idField,
            'text' => $textField,
            'delay' => $delay,
            'loadmore' => $loadmore,
        ];

        return $this;
    }

    /**
     * 设置禁用的选项
     *
     * @param string|array $val
     * @return $this
     */
    public function disabledOptions($val)
    {
        $this->disabledOptions = is_array($val) ? $val : explode(',', $val);
        return $this;
    }

    /**
     * 是否为ajax远程数据
     *
     * @return boolean
     */
    public function isAjax()
    {
        return isset($this->jsOptions['ajax']);
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
     * 获取ajax配置
     *
     * @return array
     */
    public function getAjax()
    {
        return $this->jsOptions['ajax'] ?? ['url' => '', 'text' => ''];
    }

    /**
     * x-select 元素配置：TomSelect 设置 + 远程懒加载参数，序列化进模板 cfg 属性。
     * ENT_QUOTES 转义防属性截断（同 DateTime::elementCfg）
     *
     * @return string
     */
    protected function elementCfg()
    {
        $cfg = [
            'ts' => true,
            'placeholder' => empty($this->jsOptions['placeholder']) ? '' : $this->jsOptions['placeholder'],
            'plugins' => array_values($this->tomPlugins),
            'maxItems' => $this->jsOptions['maxItems'],
        ];

        if (isset($this->jsOptions['ajax'])) {
            $ajax = $this->jsOptions['ajax'];
            $cfg['ajax'] = [
                'url' => (string)$ajax['url'],
                'id' => empty($ajax['id']) ? '_' : $ajax['id'],
                'text' => empty($ajax['text']) ? '_' : $ajax['text'],
                'delay' => empty($ajax['delay']) ? 250 : $ajax['delay'],
                'loadmore' => !empty($ajax['loadmore']),
            ];
            $cfg['separator'] = '、';
        }

        if (isset($this->jsOptions['prev_id'])) {
            $cfg['prev_id'] = $this->jsOptions['prev_id'];
        }

        if (!empty($this->withParams)) {
            $cfg['withParams'] = array_values($this->withParams);
        }

        return htmlspecialchars(json_encode($cfg, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 附加模板变量：x-select元素cfg配置
     *
     * @return array
     */
    public function customVars()
    {
        return [
            'cfg' => $this->elementCfg(),
        ];
    }

    /*
    $form->select('province', '省份', 4)->dataUrl(url('province'))->withNext(
    $form->select('city', '城市', 4)->dataUrl(url('city'))->withNext(
    $form->select('area', '区域', 4)->dataUrl(url('area'))
    )
    );
     */

    /**
     * 设置级联的下一个下拉
     *
     * @param Select $nextSelect
     * @return $this
     */
    public function withNext($nextSelect)
    {
        $nextSelect->withPrev($this);
        return $this;
    }

    /**
     * 设置级联的上一个下拉
     *
     * @param Select $prevSelect
     * @return $this
     */
    public function withPrev($prevSelect)
    {
        $this->prevSelect = $prevSelect;

        return $this;
    }

    /**
     * ajax 时，附带其他字段的值。
     * 与级联有所不同，级联时上级改变会清空下级，并重新加载。
     * 附带字段的值改变不会触发此控件的重新加载，只是在此控件重新加载的时候附加参数。
     * @param array|string $val
     * @return $this
     */
    public function withParams($val)
    {
        $this->withParams = is_array($val) ? $val : explode(',', $val);

        return $this;
    }

    /**
     * 判断options是否为分组结构
     *
     * @return boolean
     */
    protected function isGroup()
    {
        foreach ($this->options as $option) {

            if (isset($option['options']) && isset($option['label'])) {
                $this->group = true;
                break;
            }
        }

        return $this->group;
    }

    /**
     * 渲染前处理：设置占位、级联绑定与when脚本
     *
     * @return $this
     */
    public function beforRender()
    {
        if (empty($this->jsOptions['placeholder'])) {
            $this->jsOptions['placeholder'] = __blang('builder_please_select') . $this->label;
        }

        // 远程 select：原生 select 先 display:none，浏览器首帧不画它，
        // 避免 TomSelect init 前后的高度跳变
        if ($this->isAjax()) {
            $this->addStyle('display:none');
        }

        // 级联：上级 id 进 cfg，x-select 元素初始化时自行绑定 prev change 清空
        if ($this->prevSelect) {
            $this->jsOptions['prev_id'] = $this->prevSelect->getId();
        }

        $this->whenScript();

        return parent::beforRender();
    }

    /**
     * 渲染为HTML
     *
     * @return mixed
     */
    public function render()
    {
        $vars = $this->commonVars();

        if (!($this->value === '' || $this->value === null)) {
            $this->checked = $this->value;
        } else {
            $this->checked = $this->default;
        }

        $this->isGroup();

        if (!$this->group && !isset($this->options[''])) {
            $this->options = ['' => $this->jsOptions['placeholder']] + $this->options;
        }

        foreach ($this->disabledOptions as &$di) {
            $di = '-' . $di;
        }

        $vars = array_merge($vars, [
            'checked' => '-' . $this->checked,
            'dataSelected' => $this->checked,
            'group' => $this->group,
            'options' => $this->options,
            'disabledOptions' => $this->disabledOptions,
        ]);

        $viewshow = $this->getViewInstance();

        return $viewshow->assign($vars)->getContent();
    }

    /**
     * 销毁组件，释放上级引用
     *
     * @return void
     */
    public function destroy()
    {
        // 6.261 已销毁直接返回：同一组件可被多归属路径重复触达（契约见 traits\HasDestroyOnce）
        if ($this->__destroyed__) {
            return;
        }

        $this->prevSelect = null;
        parent::destroy();
        $this->__destroyed__ = true;
    }
}
