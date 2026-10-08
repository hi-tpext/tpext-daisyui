<?php

namespace tpext\builder\displayer;

use tpext\builder\inface\ReleaseAble;
use tpext\builder\traits\HasDestroyOnce;

use think\Model;
use tpext\builder\common\Builder;
use tpext\builder\common\Module;
use tpext\builder\common\Wrapper;
use tpext\builder\common\SizeAdapter;
use tpext\builder\form\Fillable;
use tpext\think\View;
use tpext\builder\traits\HasDom;
use tpext\common\ExtLoader;
use think\facade\Lang;
use tpext\builder\form\FRow;
use tpext\builder\search\SRow;
use tpext\builder\table\TColumn;

/**
 * 字段组件基类：表单/搜索/表格共用的属性设置与渲染流程
 */
class Field implements Fillable, ReleaseAble
{
    use HasDom;
    use HasDestroyOnce;

    protected $id = '';
    protected $extKey = '';
    protected $extNameKey = '';
    protected $name = '';
    protected $innerName = '';
    protected $label = '';
    protected $js = [];
    protected $customJs = [];
    protected $customCss = [];
    protected $css = [];
    protected $stylesheet = '';
    protected $script = [];
    protected $builderScriptEnabled = true; // beforRender 时是否把 $script 注入 Builder 单例
    protected $cssFamily = null; // 6.181 按需加载：子类覆盖为 tomselect|trees|flatpickr|widgets

    protected $view = 'field';
    protected $isInput = true; //是否为可输入元素
    protected $isFieldsGroup = false;
    protected $isArrayValue = false;

    /**
     * @var string|array
     */
    protected $value = '';
    protected $lockValue = false;
    protected $default = '';
    protected $icon = '';
    protected $autoPost = '';
    protected $autoPostRefresh = false;
    protected $showLabel = true;
    protected $labelClass = '';
    protected $labelAttr = '';
    protected $errorClass = '';
    protected $error = '';
    protected $size = [2, 8];
    protected $help = '';
    protected $readonly = false;
    protected $disabled = false;
    /**
     * 所属包装行（表单行/搜索行/表格列）
     *
     * @var FRow|SRow|TColumn
     */
    protected $wrapper = null;
    protected static $helptempl;
    protected static $labeltempl;
    protected $mapClass = [];
    protected $required = false;
    protected $minify = true;
    protected $arrayName = false;
    protected $to = '';
    protected $data = [];
    protected $jsOptions = [];

    /**
     * 渲染前回调列表（beforRender 时依次执行）
     *
     * @var \Closure[]
     */
    protected $rendering = [];

    /**
     * 实例化字段组件
     *
     * @param string $name 字段名
     * @param string $label 标签
     */
    public function __construct($name, $label = '')
    {
        $this->name = trim($name);

        if (empty($label) && !empty($this->name)) {
            $label = Lang::get(ucfirst($this->name));
        }

        if (strstr($this->name, '.')) {
            $arr = explode('.', $this->name);
            $this->arrayName([$arr[0] . '[', ']']);
            $this->innerName = $arr[1];
            $this->extKey = '-' . $arr[0];
        }

        $this->label = $label;
    }

    /**
     * 创建完成：应用字段类型默认class并触发创建事件
     *
     * @param string $fieldType
     * @return $this
     */
    public function created($fieldType = '')
    {
        $fieldType = $fieldType ? $fieldType : get_called_class();

        $fieldType = lcfirst($fieldType);

        $defaultClass = Wrapper::hasDefaultFieldClass($fieldType);

        if (!empty($defaultClass)) {
            $this->class = $defaultClass;
        }

        ExtLoader::trigger('tpext_displayer_created', $this);

        return $this;
    }

    /**
     * 判断是否为某个类型的组件
     *
     * @param string $type
     * @return boolean
     */
    public function isDisplayerType($type)
    {
        $thisType = class_basename($this);

        return strtolower($thisType) === strtolower($type);
    }

    /**
     * 获取组件类型名（短类名）
     *
     * @return string
     */
    public function getDisplayerType()
    {
        return class_basename($this);
    }

    /**
     * 获取元素ID（由name与extKey生成）
     *
     * @return string
     */
    public function getId()
    {
        if ($this->id) {
            return $this->id;
        }
        $this->id = 'form-' . preg_replace('/[^\w\-]/', '-', $this->name . $this->extKey);
        return $this->id;
    }

    /**
     * 获取字段name（含数组式包装与扩展后缀）
     *
     * @return string
     */
    public function getName()
    {
        if ($this->arrayName) {

            if ($this->innerName) {
                return $this->arrayName[0] . $this->innerName . $this->arrayName[1] . $this->extNameKey;
            }

            return $this->arrayName[0] . $this->name . $this->arrayName[1] . $this->extNameKey;
        }

        return $this->name . $this->extNameKey;
    }

    /**
     * 获取包装器class名
     *
     * @return string
     */
    public function getClassName()
    {
        return $this->wrapper->getClassName();
    }

    /**
     * 获取包装器上的原始字段名
     *
     * @return string
     */
    public function getOriginName()
    {
        return $this->wrapper->getName();
    }

    /**
     * 设置数组式name包装，如['user[', ']']渲染为user[xxx]
     *
     * @param array|bool $val
     * @return $this
     */
    public function arrayName($val)
    {
        $this->arrayName = $val;
        $this->id = '';
        return $this;
    }

    /**
     * 获取字段标签
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * 设置扩展键（用于区分同名字段的元素ID）
     *
     * @param string $val
     * @return $this
     */
    public function extKey($val)
    {
        $this->extKey = $val;
        $this->id = '';
        return $this;
    }

    /**
     * 设置name扩展后缀
     *
     * @param string $val
     * @return $this
     */
    public function extNameKey($val)
    {
        $this->extNameKey = $val;
        return $this;
    }

    /**
     * 获取name扩展后缀
     *
     * @return string
     */
    public function getExtNameKey()
    {
        return $this->extNameKey;
    }

    /**
     * 合并设置js组件选项
     *
     * @param array $options
     * @return $this
     */
    public function jsOptions($options)
    {
        $this->jsOptions = array_merge($this->jsOptions, $options);
        return $this;
    }

    /**
     * 设置值变化时自动提交的URL
     *
     * @param string $url
     * @param boolean $refresh
     * @return $this
     */
    public function autoPost($url = '', $refresh = false)
    {
        if (empty($url)) {
            $url = (string) url('autopost');
        }
        $this->autoPost = $url;
        $this->autoPostRefresh = $refresh;
        return $this;
    }

    /**
     * 是否为可输入元素
     *
     * @return boolean
     */
    public function isInput()
    {
        return $this->isInput;
    }

    /**
     * 是否为字段组
     *
     * @return boolean
     */
    public function isFieldsGroup()
    {
        return $this->isFieldsGroup;
    }

    /**
     * 值是否为数组类型
     *
     * @return boolean
     */
    public function isArrayValue()
    {
        return $this->isArrayValue;
    }

    /**
     * 设置字段值
     *
     * @param string|int|float|bool|array $val 值
     * @return $this
     */
    public function value($val)
    {
        if ($this->lockValue) {
            return $this;
        }

        if (is_array($val)) {
            $val = implode(',', $val);
        }
        $this->value = $val;
        return $this;
    }

    /**
     * 锁定$value，不会被后续value()/fill()方法覆盖值
     * 
     * $form->text('field_a', 'A')->value('hello')->lockValue();
     * $form->fill(['field_a' => 'world']);//field_a不会覆被盖
     *
     * @param boolean $val
     * @return $this
     */
    public function lockValue($val = true)
    {
        $this->lockValue = $val;

        return $this;
    }

    /**
     * 设置值输出转换模板或回调
     *
     * @param string|\Closure $val
     * @return $this
     */
    public function to($val)
    {
        $this->to = $val;
        return $this;
    }

    /**
     * 设置默认值
     *
     * @param string|int|float|bool|array $val
     * @return $this
     */
    public function default($val = '')
    {
        $this->default = $val;
        return $this;
    }

    /**
     * 设置字段name
     *
     * @param string $val
     * @return $this
     */
    public function name($val)
    {
        $this->name = $val;
        $this->id = '';
        return $this;
    }

    /**
     * 设置字段标签
     *
     * @param string $val
     * @return $this
     */
    public function label($val)
    {
        $this->label = $val;
        return $this;
    }

    /**
     * 设置标签class
     *
     * @param string $val
     * @return $this
     */
    public function labelClass($val)
    {
        $this->labelClass = $val;
        return $this;
    }

    /**
     * 设置标签额外属性
     *
     * @param string $val
     * @return $this
     */
    public function labelAttr($val)
    {
        $this->labelAttr = $val;
        return $this;
    }

    /**
     * 设置标签/元素的栅格宽度
     *
     * @param integer|string $label
     * @param integer|string $element
     * @return $this
     */
    public function size($label = 2, $element = 8)
    {
        $this->size = [$label, $element];
        return $this;
    }

    /**
     * 设置所在列的栅格宽度
     * @example 1 [int] 4 => class="col-md-4"
     * @example 2 [string] '4 xls-4' => class="col-md-4 xls-4"
     *
     * @param int|string $val
     * @return $this
     */
    public function cloSize($val)
    {
        $this->wrapper->cloSize($val);
        return $this;
    }

    /**
     * 设置帮助文案
     *
     * @param string $val
     * @return $this
     */
    public function help($val)
    {
        $this->help = $val;
        return $this;
    }

    /**
     * 设置错误信息并标记错误样式
     *
     * @param string $val
     * @return $this
     */
    public function error($val)
    {
        $this->error = $val;
        $this->errorClass($val ? 'has-error' : '');
        return $this;
    }

    /**
     * 设置错误样式class（同步到包装行）
     *
     * @param string $val
     * @return $this
     */
    public function errorClass($val)
    {
        $this->errorClass = $val;
        $this->wrapper->errorClass($val);
        return $this;
    }

    /**
     * 设置只读
     *
     * @param boolean $val
     * @return $this
     */
    public function readonly($val = true)
    {
        $this->readonly = $val;
        return $this;
    }

    /**
     * 设置禁用
     *
     * @param boolean $val
     * @return $this
     */
    public function disabled($val = true)
    {
        $this->disabled = $val;
        return $this;
    }

    /**
     * 设置必填
     *
     * @param boolean $val
     * @return $this
     */
    public function required($val = true)
    {
        $this->required = $val;
        return $this;
    }

    /**
     * 设置是否显示标签
     *
     * @param boolean $val
     * @return $this
     */
    public function showLabel($val)
    {
        $this->showLabel = $val;
        return $this;
    }

    /**
     * 是否显示标签
     *
     * @return boolean
     */
    public function isShowLabel()
    {
        return $this->showLabel;
    }

    /**
     * 获取尺寸设置[标签宽, 元素宽]
     *
     * @return array
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * 是否必填
     *
     * @return boolean
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * 是否只读
     *
     * @return boolean
     */
    public function isReadonly()
    {
        return $this->readonly;
    }

    /**
     * 是否禁用
     *
     * @return boolean
     */
    public function isDisabled()
    {
        return $this->disabled;
    }

    /**
     * js/css是否参与minify合并
     *
     * @return boolean
     */
    public function canMinify()
    {
        return $this->minify;
    }

    /**
     * 添加js文件
     *
     * @param array|string $val
     * @return $this
     */
    public function addJs($val)
    {
        if (!is_array($val)) {
            $val = [$val];
        }
        $this->js = array_merge($this->js, $val);
        return $this;
    }

    /**
     * 移除js文件
     *
     * @param array|string $val
     * @return $this
     */
    public function removeJs($val)
    {
        if (!is_array($val)) {
            $val = [$val];
        }

        foreach ($this->js as $k => $j) {
            if (in_array($j, $val)) {
                unset($this->js[$k]);
            }
        }

        return $this;
    }

    /**
     * 移除css文件
     *
     * @param array|string $val
     * @return $this
     */
    public function removeCss($val)
    {
        if (!is_array($val)) {
            $val = [$val];
        }

        foreach ($this->css as $k => $c) {
            if (in_array($c, $val)) {
                unset($this->css[$k]);
            }
        }

        return $this;
    }

    /**
     * 替换js文件
     *
     * @param string $val
     * @param string $newVal
     * @return $this
     */
    public function replaceJs($val, $newVal)
    {
        foreach ($this->js as $k => $j) {
            if ($val == $j) {
                $this->js[$k] = $newVal;
                break;
            }
        }

        return $this;
    }

    /**
     * 替换css文件
     *
     * @param string $val
     * @param string $newVal
     * @return $this
     */
    public function replaceCss($val, $newVal)
    {
        foreach ($this->css as $k => $c) {
            if ($val == $c) {
                $this->css[$k] = $newVal;
                break;
            }
        }

        return $this;
    }

    /**
     * 添加自定义js，不会被minify
     *
     * @param array|string $val
     * @return $this
     */
    public function customJs($val)
    {
        if (!is_array($val)) {
            $val = [$val];
        }
        // 6.298 修正：原写成 array_merge($this->customCss, $val)（上游遗留）——既丢已加的
        // customJs，还会把 css 文件列表当 js 注入（beforRender 时 customJs 整体进 Builder::customJs）
        $this->customJs = array_merge($this->customJs, $val);
        return $this;
    }

    /**
     * 添加自定义css，不会被minify
     *
     * @param array|string $val
     * @return $this
     */
    public function customCss($val)
    {
        if (!is_array($val)) {
            $val = [$val];
        }
        $this->customCss = array_merge($this->customCss, $val);
        return $this;
    }

    /**
     * 添加css文件
     *
     * @param array|string $val
     * @return $this
     */
    public function addCss($val)
    {
        if (!is_array($val)) {
            $val = [$val];
        }
        $this->css = array_merge($this->css, $val);
        return $this;
    }

    /**
     * 设置所属包装行
     *
     * @param FRow|SRow|TColumn $wrapper
     * @return $this
     */
    public function setWrapper($wrapper)
    {
        $this->wrapper = $wrapper;
        return $this;
    }

    /**
     * 获取所属包装行
     *
     * @return FRow|SRow|TColumn
     */
    public function getWrapper()
    {
        return $this->wrapper;
    }

    /**
     * 获取标签class
     *
     * @return string
     */
    public function getLabelClass()
    {
        return $this->labelClass;
    }

    /**
     * 获取标签额外属性
     *
     * @return string
     */
    public function getLabelAttr()
    {
        return $this->labelAttr;
    }

    /**
     * 获取错误信息
     *
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * 获取字段值
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * 获取默认值
     *
     * @return string|array|bool
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * 获取帮助文案
     *
     * @return string
     */
    public function getHelp()
    {
        return $this->help;
    }

    /**
     * 获取js文件列表
     *
     * @return array
     */
    public function getJs()
    {
        return $this->js;
    }

    /**
     * 获取css文件列表
     *
     * @return array
     */
    public function getCss()
    {
        return $this->css;
    }

    /**
     * 获取脚本片段列表
     *
     * @return array
     */
    public function getScript()
    {
        return $this->script;
    }

    /**
     * 清空脚本片段
     *
     * @return $this
     */
    public function clearScript()
    {
        $this->script = [];
        return $this;
    }

    /**
     * 控制 beforRender() 是否把 $this->script 注入 Builder 单例。
     * 默认 true。ItemsContent 模板行处理时设置为 false，
     * 因为模板行脚本只应输出到 items-xxx-script textarea 供"添加行"按钮使用。
     *
     * @param bool $enabled
     * @return $this
     */
    public function builderScriptEnabled($enabled = true)
    {
        $this->builderScriptEnabled = $enabled;
        return $this;
    }

    /**
     * 设置字段占满整行
     *
     * @param integer $labelMin
     * @return $this
     */
    public function fullSize($labelMin = 3)
    {
        if (empty($this->size) || (is_numeric($this->size[0]) && is_numeric($this->size[1]))) {

            $this->size = [$labelMin, 12 - $labelMin];
        }

        return $this;
    }

    /**
     * 用数据填充字段值
     *
     * @param array|Model|\ArrayAccess $data
     * @return $this
     */
    public function fill($data = [])
    {
        if ($this->lockValue) {
            $this->data = $data;
            return $this;
        }

        if (!empty($this->name)) {

            $hasVal = false;
            $value = '';
            if (strstr($this->name, '.')) {

                $arr = explode('.', $this->name);

                // $form->field('b.name')
                // $form->fill($data);

                if (isset($data[$arr[0]])) {

                    // $data = ['name' => 'str1', 'b' => ['name' => 'str2']];
                    // 输出：'str2'
                    if (isset($data[$arr[0]][$arr[1]])) {
                        $value = $data[$arr[0]][$arr[1]];
                        $hasVal = true;
                    }
                    // 
                    //$data = ['name' => 'str1', 'b' => []];
                    // 输出：'str1'
                    else if (isset($data[$arr[1]])) { //尝试读取上一层级的值
                        $value = $data[$arr[1]];
                        $hasVal = true;
                    }
                } else {
                    if (isset($data[$arr[1]])) { //尝试读取上一层级的值
                        $value = $data[$arr[1]];
                        $hasVal = true;
                    }
                    // $data = ['name' => 'str1'];
                    // 输出：'str1'
                }
            } else if (isset($data[$this->name])) {

                $value = $data[$this->name];
                $hasVal = true;
            }

            if (is_array($value)) {
                $value = implode(',', $value);
            }

            if ($hasVal) {

                $this->value($value);
            }
        }

        $this->data = $data;

        return $this;
    }

    /**
     * 为字段元素派发DOM事件
     *
     * @param string $event
     * @return $this
     */
    public function trigger($event)
    {
        $fieldId = $this->getId();

        $script = <<<EOT

        // trigger — 原生 JS 实现（替代原 jQuery trigger）
        (function() {
            var el = document.getElementById('{$fieldId}');
            if (el) el.dispatchEvent(new Event('{$event}', {bubbles: true}));
        })();

EOT;
        $this->script[] = $script;
        return $this;
    }

    /**
     * 追加一段js脚本
     *
     * @param string $script
     * @return $this
     */
    public function addScript($script)
    {
        $this->script[] = $script;
        return $this;
    }

    /**
     * 按字段值条件为元素追加class
     *
     * @param array|string|int|\Closure $values
     * @param string $class
     * @param string $field default current field
     * @param string $logic in_array|not_in_array|eq|gt|lt|egt|elt|strstr|not_strstr
     * @return $this
     */
    public function mapClass($values, $class, $field = '', $logic = 'in_array')
    {
        if (empty($field)) {
            $field = $this->name;
        }

        if (!($values instanceof \Closure) && !is_array($values)) {
            $values = [$values];
        }

        $this->mapClass[] = [$values, $class, $field, $logic];
        return $this;
    }

    /**
     * 弃用，使用mapClass代替
     * @deprecated 1.8.93
     *
     * @param array|string|int|\Closure $values
     * @param string $class
     * @param string $field
     * @param string $logic
     * @return $this
     */
    public function mapClassWhen($values, $class, $field = '', $logic = 'in_array')
    {
        return $this->mapClass($values, $class, $field, $logic);
    }

    /**
     * 批量设置mapClass
     *
     * @param array $groupArr
     * @example location1 [[$values1, $class1, $field1, $logic1], [$values2, $class2, $field2, $logic2], ... ]
     * @example location2 ['class1' => [$values1, $field1, $logic1], 'class2'=> [$values2, $field2, $logic2], ... ]
     * @example location3 ['class1' => function closure1(){...}, 'class2'=> function closure2(){...}, ... ]
     * @return $this
     */
    public function mapClassGroup($groupArr)
    {
        foreach ($groupArr as $key => $g) {
            if (is_int($key)) { //  1
                $values = $g[0];
                $class = $g[1];
                $field = isset($g[2]) ? $g[2] : '';
                $logic = isset($g[3]) ? $g[3] : '';
                $this->mapClass($values, $class, $field, $logic);
            } else if (is_string($key)) { //  2 /  3
                if (is_array($g)) //2
                {
                    $values = $g[0];
                    $field = isset($g[1]) ? $g[1] : '';
                    $logic = isset($g[2]) ? $g[2] : '';
                    $this->mapClass($values, $key, $field, $logic);
                } else if ($g instanceof \Closure) {
                    $this->mapClass($g, $key);
                }
            }
        }

        return $this;
    }

    /**
     * 弃用，使用mapClassGroup代替
     *
     * @deprecated 1.8.93
     * @param array $groupArr
     * @return $this
     */
    public function mapClassWhenGroup($groupArr)
    {
        return $this->mapClassGroup($groupArr);
    }

    /**
     * 获取CSRF Token
     *
     * @return string
     */
    protected function getCsrfToken()
    {
        return Builder::getInstance()->getCsrfToken();
    }

    /**
     * 渲染为HTML
     *
     * @return mixed
     */
    public function render()
    {
        $vars = $this->commonVars();

        $viewshow = $this->getViewInstance();

        return $viewshow->assign($vars)->getContent();
    }

    /**
     * 渲染为HTML字符串
     *
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }

    /**
     * 获取视图实例
     *
     * @return \tpext\think\View
     */
    protected function getViewInstance()
    {
        $template = Module::getInstance()->getViewsPath() . 'displayer' . DIRECTORY_SEPARATOR . $this->view . '.html';

        $viewshow = new View($template);

        return $viewshow;
    }

    /**
     * 生成自动提交脚本并加入script列表
     *
     * @return string
     */
    protected function autoPostScript()
    {
        $class = 'row-' . $this->getClassName() . '-td';

        $refresh = $this->autoPostRefresh ? 1 : 0;

        $script = <<<EOT

        window.__auto_post_bind__ = window.__auto_post_bind__ || [];
        if(!window.__auto_post_bind__.includes('{$class}')){
            tpextbuilder.autoPost('{$class}', '{$this->autoPost}' ,{$refresh});
            window.__auto_post_bind__.push('{$class}');
        }

EOT;
        $this->script[] = $script;

        return $script;
    }

    /**
     * 渲染前处理：注册js/css、注入脚本、执行rendering回调
     *
     * @return $this
     */
    public function beforRender()
    {
        // 6.181 按需加载：子类覆盖 $cssFamily 即可注册所需 CSS 族
        if ($this->cssFamily) {
            Builder::getInstance()->needCss($this->cssFamily);
        }

        if ($this->minify) {
            Builder::getInstance()->addJs($this->js);
            Builder::getInstance()->addCss($this->css);
        } else {
            Builder::getInstance()->customJs($this->js);
            Builder::getInstance()->customCss($this->css);
        }

        Builder::getInstance()->customJs($this->customJs);
        Builder::getInstance()->customCss($this->customCss);

        if ($this->autoPost) {
            if (Builder::checkUrl($this->autoPost)) {
                $this->autoPostScript();
            } else {
                $this->readonly();
            }
        }

        if ($this->builderScriptEnabled && !empty($this->script)) {
            Builder::getInstance()->addScript($this->script);
        }

        if (!empty($this->stylesheet)) {
            Builder::getInstance()->addStyleSheet($this->stylesheet);
        }

        if (count($this->rendering)) {
            foreach ($this->rendering as $rd) {
                if ($rd instanceof \Closure) {
                    $rd->call($this, $this);
                }
            }
        }

        ExtLoader::trigger('tpext_displayer_befor_render', $this);

        return $this;
    }

    /**
     * 获取渲染用值：value优先、default兜底，并归一化布尔/数组
     *
     * @return string|int|float|null
     */
    public function renderValue()
    {
        if (is_array($this->default)) {
            $this->default = implode(',', $this->default);
        } else if ($this->default === true) {
            $this->default = 1;
        } else if ($this->default === false) {
            $this->default = 0;
        }

        if ($this->value === true) {
            $this->value = 1;
        } else if ($this->value === false) {
            $this->value = 0;
        }

        $value = !($this->value === '' || $this->value === null) ? $this->value : $this->default;

        if (!empty($this->to)) {
            $value = $this->parseToValue($value);
        }

        return $value;
    }

    /**
     * 按to模板/回调转换值
     *
     * @param string|int|float|null $value
     * @return string
     */
    protected function parseToValue($value)
    {
        $data = $this->data;

        $to = $this->to;

        if ($to instanceof \Closure) {
            //data 为空时可能引起错误
            if (empty($this->value) && empty($this->default) && empty($this->data)) {
                return '';
            }
            return $to($value, $data);
        }

        preg_match_all('/\{([\w\.]+)\}/', $this->to, $matches);

        $keys = ['{val}', '{__val__}'];
        $replace = [$value, $value];
        $arr = null;

        foreach ($matches[1] as $match) {
            $arr = explode('.', $match);

            if (count($arr) == 1) {

                $keys[] = '{' . $arr[0] . '}';
                $replace[] = isset($data[$arr[0]]) ? $data[$arr[0]] : '';
            } else if (count($arr) == 2) {

                $keys[] = '{' . $arr[0] . '.' . $arr[1] . '}';
                $replace[] = isset($data[$arr[0]]) && isset($data[$arr[0]][$arr[1]]) ? $data[$arr[0]][$arr[1]] : '-';
            } else {
                //最多支持两层 xx 或 xx.yy
            }
        }

        $val = str_replace($keys, $replace, $to);

        return $val;
    }

    /**
     * 计算mapClass命中的class
     *
     * @return string
     */
    protected function parseMapClass()
    {
        $matchClass = [];
        $values = $class = $field = $logic = $val = $match = null;
        if (!empty($this->mapClass)) {

            foreach ($this->mapClass as $mp) {
                $values = $mp[0];
                $class = $mp[1];
                $field = $mp[2];
                $logic = $mp[3]; //in_array|not_in_array|eq|gt|lt|egt|elt|strstr|not_strstr
                $val = '';
                if (strstr($field, '.')) {

                    $arr = explode('.', $field);

                    if (isset($this->data[$arr[0]]) && isset($this->data[$arr[0]][$arr[1]])) {

                        $val = $this->data[$arr[0]][$arr[1]];
                    } else {
                        continue;
                    }
                } else {

                    if (!isset($this->data[$field])) {
                        continue;
                    }

                    $val = $this->data[$field];
                }

                if ($values instanceof \Closure) {
                    $match = $values($val, $this->data);
                    if ($match) {
                        $matchClass[] = $class;
                    }
                    continue;
                }

                $match = false;
                if ($logic == 'not_in_array' || $logic == '!in_array') {
                    $match = !in_array($val, $values);
                } else if ($logic == 'eq' || $logic == '==') {
                    $match = $val == $values[0];
                } else if ($logic == 'gt' || $logic == '>') {
                    $match = is_numeric($values[0]) && $val > $values[0];
                } else if ($logic == 'lt' || $logic == '<') {
                    $match = is_numeric($values[0]) && $val < $values[0];
                } else if ($logic == 'egt' || $logic == '>=') {
                    $match = is_numeric($values[0]) && $val >= $values[0];
                } else if ($logic == 'elt' || $logic == '<=') {
                    $match = is_numeric($values[0]) && $val <= $values[0];
                } else if ($logic == 'strpos' || $logic == 'strstr') {
                    $match = strstr($val, $values[0]);
                } else if ($logic == 'not_strpos' || $logic == 'not_strstr' || $logic == '!strpos' || $logic == '!strstr') {
                    $match = !strstr($val, $values[0]);
                } else //default in_array
                {
                    $match = in_array($val, $values);
                }
                if ($match) {
                    $matchClass[] = $class;
                }
            }
        }

        if (count($matchClass)) {

            return ' ' . implode(' ', array_unique($matchClass));
        }

        return '';
    }

    /**
     * 获取模板公共变量
     *
     * @return array
     */
    public function commonVars()
    {
        if (empty(static::$helptempl)) {
            static::$helptempl = Module::getInstance()->getViewsPath() . 'displayer' . DIRECTORY_SEPARATOR . 'helptempl.html';
        }

        if (empty(static::$labeltempl)) {
            static::$labeltempl = Module::getInstance()->getViewsPath() . 'displayer' . DIRECTORY_SEPARATOR . 'labeltempl.html';
        }

        $mapClass = $this->parseMapClass();

        $value = $this->renderValue();

        $extendAttr = '';

        if ($this->isInput) {
            $extendAttr = ($this->isRequired() ? ' required="true"' : '') . ($this->disabled ? ' disabled' : '') . ($this->readonly ? ' readonly onclick="return false;"' : '');
        }

        $vars = [
            'id' => $this->getId(),
            'label' => $this->label,
            'name' => $this->getName(),
            'required' => $this->required,
            'requiredStyle' => $this->required ? '' : 'style="display: none;"',
            'befor' => property_exists($this, 'befor') ? $this->befor : '',
            'after' => property_exists($this, 'after') ? $this->after : '',
            'extKey' => $this->extKey,
            'extNameKey' => $this->extNameKey,
            'value' => $value,
            'class' => ' row-' . $this->getClassName() . $this->getClass() . $mapClass,
            'attr' => $this->getAttrWithStyle() . $extendAttr,
            'error' => $this->error,
            'size' => $this->adjustSize(),
            // 6.301 label 左右/上下布局分支：size[0] 可能是旧库风格的字符串（如 '4 col-xs-4'），
            // is_numeric 会误判成上下布局（full-label）——取前导数字判断（(int)'4 col-xs-4'==4），
            // 否则搜索表单 label 丢 justify-end 右对齐（attachment/index 实测）
            'labelClass' => (int) $this->size[0] > 0 && (int) $this->size[0] < 12 ? $this->labelClass . ' control-label' : $this->labelClass . ' full-label',
            'labelAttr' => empty($this->labelAttr) ? '' : ' ' . $this->labelAttr,
            'help' => $this->help,
            'showLabel' => is_numeric($this->size[0]) && $this->size[0] == 0 ? false : $this->showLabel,
            'helptempl' => static::$helptempl,
            'labeltempl' => static::$labeltempl,
            'readonly' => $this->readonly,
            'disabled' => $this->disabled,
        ];

        $customVars = $this->customVars();

        if (!empty($customVars)) {
            $vars = array_merge($vars, $customVars);
        }

        return $vars;
    }

    /**
     * 调整尺寸以适配当前布局
     *
     * @return array
     */
    public function adjustSize()
    {
        return SizeAdapter::make()->adjustDisplayerSize($this->size);
    }

    /**
     * 子类附加的模板变量，默认空
     *
     * @return array
     */
    public function customVars()
    {
        return [];
    }

    /**
     * @param \Closure $callback
     * @return $this
     */
    public function rendering($callback)
    {
        $this->rendering[] = $callback;
        return $this;
    }

    /**
     * 设置table列可排序
     *
     * @param boolean $val
     * @return $this
     */
    public function colSortable($val = true)
    {
        $this->wrapper->sortable($val);
        return $this;
    }

    /**
     * 设置table列默认隐藏
     *
     * @param boolean $val
     * @return $this
     */
    public function colHidden($val = true)
    {
        $this->wrapper->hidden($val);
        return $this;
    }

    /**
     * 销毁组件，释放引用
     *
     * @return void
     */
    public function destroy()
    {
        // 6.261 已销毁直接返回：同一组件可被多归属路径重复触达（契约见 traits\HasDestroyOnce）
        if ($this->__destroyed__) {
            return;
        }

        // 6.260 data 声明默认 []，销毁后复位为空数组（保持类型恒定）；wrapper 是对象引用置 null
        $this->data = [];
        $this->wrapper = null;
        $this->__destroyed__ = true;
    }

    /**
     * 组件 JS 库带多语言文件时的加载钩子（6.156）：需要的子类覆写本方法，
     * 并在自己的 beforRender() 里、parent::beforRender() 收集 $js 之前调用，
     * 把语言文件追加进 $this->js（页面级 $js 块先于 body 底部 common 栈输出，
     * 保证先于 Web Components 元素初始化执行）。参见 DateTime::loadLocale()。
     *
     * @return void
     */
    protected function loadLocale() {}
}
