<?php

namespace tpext\builder\common;

use think\Model;
use think\Collection;
use tpext\think\View;
use tpext\common\ExtLoader;
use tpext\builder\form\FRow;
use tpext\builder\form\Step;
use tpext\builder\form\When;
use tpext\builder\common\Module;
use tpext\builder\form\Fillable;
use tpext\builder\form\FWrapper;
use tpext\builder\traits\HasDom;
use tpext\builder\common\Builder;
use tpext\builder\displayer\Field;
use tpext\builder\displayer\Items;
use tpext\builder\displayer\Button;
use tpext\builder\displayer\Fields;
use tpext\builder\form\ItemsContent;
use tpext\builder\inface\Renderable;
use tpext\builder\inface\ReleaseAble;
use tpext\builder\traits\HasDestroyOnce;
use tpext\builder\form\FieldsContent;
use tpext\builder\displayer\MultipleFile;

/**
 * Form class
 */
class Form extends FWrapper implements Renderable, ReleaseAble
{
    use HasDom;
    use HasDestroyOnce;

    protected $view = '';

    protected $action = '';

    protected $id = 'the-form';

    protected $method = 'post';

    /**
     * 表单包含的所有行（普通行或tab/step容器）
     *
     * @var FRow[]|Tab[]|Step[]
     */
    protected $rows = [];

    protected $data = [];

    protected $botttomButtonsCalled = false;

    protected $bottomOffsetCalled = false;

    protected $ajax = true;

    protected $defaultDisplayerSize = null;

    protected $defaultDisplayerColSize = 12;

    protected $validator = [];

    protected $butonsSizeClass = 'btn-sm';

    protected $readonly = false;

    protected $partial = false;

    /**
     * tab容器（懒创建）
     *
     * @var Tab|null
     */
    protected $tab = null;

    /**
     * step容器（懒创建）
     *
     * @var Step|null
     */
    protected $step = null;

    /**
     * 当前tab/step内容区
     *
     * @var FieldsContent|null
     */
    protected $__tabs__ = null;

    /**
     * 当前fields分组
     *
     * @var FieldsContent|null
     */
    protected $__fields__ = null;

    /**
     * fields分组栈（嵌套fields时使用）
     *
     * @var FieldsContent[]
     */
    protected $__fields__bag__ = [];

    /**
     * 当前items分组
     *
     * @var ItemsContent|null
     */
    protected $__items__ = null;

    /**
     * 当前when条件组
     *
     * @var When|null
     */
    protected $__when__ = null;

    /**
     * 初始化默认样式类
     *
     * @return $this
     */
    public function created()
    {
        $this->class = 'form-horizontal';
        return $this;
    }

    /**
     * 添加一行
     *
     * @param FRow|Fillable $row
     * @return $this
     */
    public function addRow($row)
    {
        $this->rows[] = $row;
        return $this;
    }

    /**
     * 获取所有行
     *
     * @return array
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * 设置整个表单只读
     *
     * @param boolean $val
     * @return $this
     */
    public function readonly($val = true)
    {
        foreach ($this->rows as $row) {

            if ($row instanceof Tab || $row instanceof Step) {
                $row->readonly($val);
                continue;
            }

            if (!($row instanceof FRow)) {
                continue;
            }

            $row->getDisplayer()->readonly($val);
        }

        $this->readonly = $val;

        return $this;
    }

    /**
     * 设置是否ajax提交
     *
     * @param boolean $val
     * @return $this
     */
    public function ajax($val)
    {
        $this->ajax = $val;
        return $this;
    }

    /**
     * 设置表单id
     *
     * @param string $val
     * @return $this
     */
    public function formId($val)
    {
        $this->id = $val;
        return $this;
    }

    /**
     * 获取表单id
     *
     * @return string
     */
    public function getFormId()
    {
        return $this->id;
    }

    /**
     * 设置表单提交地址
     *
     * @param string $val
     * @return $this
     */
    public function action($val)
    {
        $this->action = (string)$val;
        return $this;
    }

    /**
     * 设置表单提交方式（get/post/put/delete等，非get/post时转为_method隐藏域）
     *
     * @param string $val
     * @return $this
     */
    public function method($val)
    {
        $this->method = $val;
        return $this;
    }

    /**
     * 设置底部按钮尺寸类
     * btn-lg btn-sm btn-xs
     * @param string $val
     * @return $this
     */
    public function butonsSizeClass($val)
    {
        $this->butonsSizeClass = $val;
        return $this;
    }

    /**
     * 获取底部按钮尺寸类
     *
     * @return string
     */
    public function getButonsSizeClass()
    {
        return $this->butonsSizeClass;
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
     * 获取tab容器（无则创建）
     *
     * @return Tab
     */
    public function getTab()
    {
        if (empty($this->tab)) {
            $this->tab = new Tab();
            $this->rows[] = $this->tab;
        }
        return $this->tab;
    }

    /**
     * 添加一个tab页，后续字段归入该页
     *
     * @param string $label
     * @param boolean $active
     * @param string $name
     * @return FieldsContent
     */
    public function tab($label, $active = false, $name = '')
    {
        $this->__fields__ = null;
        $this->__items__ = null;

        if (empty($this->tab)) {
            $this->tab = new Tab();
            $this->rows[] = $this->tab;
        }

        $this->__tabs__ = $this->tab->addFieldsContent($label, $active, $name);
        $this->__tabs__->setForm($this);
        return $this->__tabs__;
    }

    /**
     * 获取step容器（无则创建）
     *
     * @return Step
     */
    public function getStep()
    {
        if (empty($this->step)) {
            $this->step = new Step();
            $this->rows[] = $this->step;
        }
        return $this->step;
    }

    /**
     * 添加一个分步，后续字段归入该步
     *
     * @param string $label
     * @param string $description
     * @param boolean $active
     * @param string $name
     * @return FieldsContent
     */
    public function step($label, $description = '', $active = false, $name = '')
    {
        $this->__fields__ = null;
        $this->__items__ = null;

        if (empty($this->step)) {
            $this->step = new Step();
            $this->rows[] = $this->step;
        }

        $this->__tabs__ = $this->step->addFieldsContent($label, $description, $active, $name);
        $this->__tabs__->setForm($this);
        return $this->__tabs__;
    }

    /**
     * 创建一个新的fields分组（旧分组压栈）
     *
     * @return FieldsContent
     */
    public function createFields()
    {
        if ($this->__fields__) {
            $this->__fields__bag__[] = $this->__fields__;
        }
        $this->__fields__ = new FieldsContent();
        $this->__fields__->setForm($this);
        return $this->__fields__;
    }

    /**
     * 创建一个新的items分组
     *
     * @return ItemsContent
     */
    public function createItems()
    {
        $this->__items__ = new ItemsContent();
        $this->__items__->setForm($this);
        return $this->__items__;
    }

    /**
     * 创建一个when条件组（监视字段值变化切换字段显示）
     * @param Field $watchFor
     * @param string|int|array $cases
     * @return When
     */
    public function createWhen($watchFor, $cases)
    {
        $this->__when__ = new When();
        $this->__when__->watch($watchFor, $cases);
        $this->__when__->setForm($this);
        return $this->__when__;
    }

    /**
     * 结束当前fields分组，回到上一分组
     *
     * @return $this
     */
    public function fieldsEnd()
    {
        $this->__fields__ = array_pop($this->__fields__bag__);
        return $this;
    }

    /**
     * 结束当前items分组
     *
     * @return $this
     */
    public function itemsEnd()
    {
        $this->__items__ = null;
        return $this;
    }

    /**
     * 结束当前when条件组
     *
     * @return $this
     */
    public function whenEnd()
    {
        $this->__when__ = null;
        return $this;
    }


    /**
     * 结束当前tab内容区
     *
     * @return $this
     */
    public function tabEnd()
    {
        $this->__tabs__ = null;

        return $this;
    }

    /**
     * 结束当前step内容区
     *
     * @return $this
     */
    public function stepEnd()
    {
        $this->__tabs__ = null;

        return $this;
    }

    /**
     * 结束所有内容区（fields/tabs/items/when）
     *
     * @return $this
     */
    public function allContentsEnd()
    {
        $this->__fields__ = null;
        $this->__tabs__ = null;
        $this->__items__ = null;
        $this->__when__ = null;
        return $this;
    }

    /**
     * 获取当前tab/step内容区
     *
     * @return FieldsContent
     */
    public function getTabsContent()
    {
        return $this->__tabs__;
    }

    /**
     * 设置字段默认的label/元素宽度
     *
     * @param integer $label
     * @param integer $element
     * @return $this
     */
    public function defaultDisplayerSize($label = 2, $element = 8)
    {
        $this->defaultDisplayerSize = [$label, $element];
        return $this;
    }

    /**
     * 设置字段默认占列数
     *
     * @param integer $size
     * @return $this
     */
    public function defaultDisplayerColSize($size = 12)
    {
        $this->defaultDisplayerColSize = $size;
        return $this;
    }

    /**
     * 填充表单数据
     *
     * @param array|Model|\ArrayAccess $data
     * @return $this
     */
    public function fill($data = [])
    {
        $this->data = $data;

        return $this;
    }

    /**
     * 添加前端验证规则
     *
     * @param string $name
     * @param string $rule
     * @param boolean $val
     * @return $this
     */
    public function addJqValidatorRule($name, $rule, $val = true)
    {
        $this->validator[$name][$rule] = $val;

        return $this;
    }

    /**
     * 获取表单数据
     *
     * @return array|Model
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * 生成底部默认按钮（只读时为关闭按钮，否则提交/重置）
     *
     * @param boolean $create
     * @return $this
     */
    public function bottomButtons($create = true)
    {
        if ($create) {
            if ($this->readonly) {
                $this->btnLayerClose();
            } else {
                $this->btnSubmit();
                $this->btnReset();
            }
        }

        $this->fieldsEnd();

        $this->botttomButtonsCalled = true;

        return $this;
    }

    /**
     * 生成底部按钮的偏移布局（只在第一次调用时生效）
     *
     * @return $this
     */
    public function bottomOffset()
    {
        if ($this->bottomOffsetCalled) {
            return $this;
        }
        $this->allContentsEnd();
        $this->html('', '', '12 col-lg-12 col-sm-12 col-xs-12')->value('')->getWrapper()->addStyle('height: 20px;'); //这里时一个空行
        //此处开启了一个fields装载后面的操作按钮，不会调用fieldsEnd了，正常情况下，底部按钮后面不会有其他元素了。如果有，需要调用fieldsEnd结束按钮区域
        //col-lg 比例:      左(4) | 中部按钮组(4) | 右(4)
        //clo-md 比例:      左(4) | 中部按钮组(4) | 右(4)
        //col-sm 比例:      左(3) | 中部按钮组(6) | 右(3)
        //col-xs 比例:      左(2) | 中部按钮组(8) | 右(2)
        $this->html('', '', '4 col-lg-4 col-sm-3 col-xs-2')->showLabel(false); //左侧offset 4,4,3,2
        $this->fields('bottom_buttons', '', '4 col-lg-4 col-sm-6 col-xs-8 bottom-buttons') //中间按钮组 4,4,6,8
            ->size(0, '12 col-lg-12 col-sm-12 col-xs-12')->showLabel(false);

        $this->bottomOffsetCalled = true;
        return $this;
    }

    /**
     * 添加提交按钮
     *
     * @param string $label
     * @param integer|string $size
     * @param string $class
     * @return $this
     */
    public function btnSubmit($label = '提&nbsp;&nbsp;交', $size = '6 col-lg-6 col-sm-6 col-xs-6', $class = 'btn-primary')
    {
        if ($label == '提&nbsp;&nbsp;交') {
            $label = __blang('builder_button_submit');
        }
        $this->bottomOffset();
        $this->button('submit', '<i class="mdi mdi-check"></i> ' . $label, $size)->class($class . ' btn-bottom-submit ' . $this->butonsSizeClass);
        $this->botttomButtonsCalled = true;
        return $this;
    }

    /**
     * 添加重置按钮
     *
     * @param string $label
     * @param integer|string $size
     * @param string $class
     * @return $this
     */
    public function btnReset($label = '重&nbsp;&nbsp;置', $size = '6 col-lg-6 col-sm-6 col-xs-6', $class = 'btn-warning')
    {
        if ($label == '重&nbsp;&nbsp;置') {
            $label = __blang('builder_button_reset');
        }
        $this->bottomOffset();
        $this->button('reset', '<i class="mdi mdi-refresh"></i> ' . $label, $size)->class($class . ' btn-bottom-reset ' . $this->butonsSizeClass);
        $this->botttomButtonsCalled = true;
        return $this;
    }

    /**
     * 添加返回按钮
     *
     * @param string $label
     * @param integer|string $size
     * @param string $class
     * @param string $attr
     * @return $this
     */
    public function btnBack($label = '返&nbsp;&nbsp;回', $size = '6 col-lg-6 col-sm-6 col-xs-6', $class = 'btn-go-back', $attr = 'onclick="history.go(-1);')
    {
        if ($label == '返&nbsp;&nbsp;回') {
            $label = __blang('builder_button_go_back');
        }
        $this->bottomOffset();
        $this->button('button', '<i class="mdi mdi-arrow-left"></i> ' . $label, $size)->class($class . ' btn-bottom-back ' . $this->butonsSizeClass)->addAttr($attr);
        $this->botttomButtonsCalled = true;
        return $this;
    }

    /**
     * 添加关闭弹层按钮
     *
     * @param string $label
     * @param integer|string $size
     * @param string $class
     * @return $this
     */
    public function btnLayerClose($label = '返&nbsp;&nbsp;回', $size = '12 col-lg-12 col-sm-12 col-xs-12', $class = '')
    {
        if ($label == '返&nbsp;&nbsp;回') {
            $label = __blang('builder_button_go_back');
        }
        $this->bottomOffset();
        // 6.162 与 btnBack/btnReset 统一：底部按钮带图标
        $this->button('button', '<i class="mdi mdi-arrow-left"></i> ' . $label, $size)->class($class . ' btn-close-layer' . ' ' . $this->butonsSizeClass);
        $this->botttomButtonsCalled = true;
        return $this;
    }

    /**
     * 创建左侧字段区域（fields分组），可传入回调立即填充字段
     *
     * @param integer $colSize col大小
     * @param Closure|null $fieldsCall
     * @return Fields
     */
    public function left($colSize = 6, $fieldsCall = null)
    {
        $this->fieldsEnd(); //清理，避免被包含到其他fields中。因为fields可以包含fields的
        $displayer =  $this->fields('left' . mt_rand(10, 99), '', $colSize)->size(0, 12)->showLabel(false);

        if ($fieldsCall) {
            if (!($fieldsCall instanceof \Closure)) {
                throw new \InvalidArgumentException('Argument fieldsCall must be  `Closure` or `null` , if set to `null`, call `->with(...$fields) follow on .');
            }
            $fieldsCall($this);
            $this->fieldsEnd();
            //如果传入了fields，这里结束掉。如果未传，后面可以再使用->with(...$fields)
            // $form->left(6, function(){//...$fields}); 或者 $form->left(6)->with(...$fields);
        }

        return $displayer;
    }

    /**
     * 创建中间字段区域（fields分组），可传入回调立即填充字段
     *
     * @param integer $colSize col大小
     * @param Closure|null $fieldsCall
     * @return Fields
     */
    public function middle($colSize = 6, $fieldsCall = null)
    {
        $this->fieldsEnd(); //同上
        $displayer =  $this->fields('middle' . mt_rand(10, 99), '', $colSize)->size(0, 12)->showLabel(false);

        if ($fieldsCall) {
            if (!($fieldsCall instanceof \Closure)) {
                throw new \InvalidArgumentException('Argument fieldsCall must be  `Closure` or `null` , if set to `null`, call `->with(...$fields) follow on .');
            }
            $fieldsCall($this);
            $this->fieldsEnd();
        }

        return $displayer;
    }

    /**
     * 创建右侧字段区域（fields分组），可传入回调立即填充字段
     *
     * @param integer $colSize col大小
     * @param Closure|null $fieldsCall
     * @return Fields
     */
    public function right($colSize = 6, $fieldsCall = null)
    {
        $this->fieldsEnd(); //同上
        $displayer =  $this->fields('right' . mt_rand(10, 99), '', $colSize)->size(0, 12)->showLabel(false);

        if ($fieldsCall) {
            if (!($fieldsCall instanceof \Closure)) {
                throw new \InvalidArgumentException('fArgument fieldsCall must be  `Closure` or `null` , if set to `null`, call `->with(...$fields) follow on .');
            }
            $fieldsCall($this);
            $this->fieldsEnd();
        }

        return $displayer;
    }

    /**
     * 创建只读的日志展示区（items），可传入回调定义每行内容
     *
     * @param string $label
     * @param array|Collection|\IteratorAggregate $dataList
     * @param Closure|null $itemsCall
     * @param array $size 大小 [12, 12] 为上下结构，[2, 10]为左右结构
     * @return Items
     */
    public function logs($label, $dataList, $itemsCall = null, $size = [12, 12])
    {
        $this->itemsEnd();
        $displayer =  $this->items('logs' . mt_rand(10, 99), $label, 12)->size($size[0], $size[1])->readonly();

        if (is_array($dataList)) {
            $displayer->fill($dataList);
        } else if ($dataList instanceof Collection || $dataList instanceof \IteratorAggregate) {
            $displayer->dataWithId($dataList);
        }
        if ($itemsCall) {
            if (!($itemsCall instanceof \Closure)) {
                throw new \InvalidArgumentException('Argument itemsCall must be  `Closure` or `null` , if set to `null`, call `->with(...$fields) follow on .');
            }
            $itemsCall($this);
            $this->itemsEnd();
        }
        return $displayer;
    }

    /**
     * 渲染前的准备：触发事件、补默认底部按钮、填充数据、收集验证规则
     *
     * @return $this
     */
    public function beforRender()
    {
        ExtLoader::trigger('tpext_form_befor_render', $this);

        if (!$this->botttomButtonsCalled && empty($this->step)) {
            $this->bottomButtons(true);
        }

        if (!in_array(strtolower($this->method), ['get', 'post'])) {
            $this->hidden('_method')->value($this->method);
            $this->method = 'post';
        }

        foreach ($this->rows as $row) {
            $row->fill($this->data);

            if (!($row instanceof FRow)) {
                $row->beforRender();
                continue;
            }

            $displayer = $row->getDisplayer();

            if ($displayer->isRequired()) {
                $this->validator[$displayer->getName()]['required'] = true;
            }

            $row->beforRender();
        }

        $this->validatorScript();

        return $this;
    }

    /**
     * 生成表单快捷键脚本（Enter提交、Esc关闭弹窗确认）
     *
     * @return string
     */
    protected function validatorScript()
    {
        $form = $this->getFormId();

        $rules = json_encode($this->validator);

        $script = <<<EOT

        // validatorScript — 原生 JS 实现（替代原 jQuery + jquery-validate）
        // 验证已改用 Zod，这里只保留键盘快捷键 + layer 关闭
        window.focus();

        (function(formId) {
            if (window.__form_keyup_bound) return;
            window.__form_keyup_bound = true;

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.keyCode === 13) {
                    var form = document.querySelector('#' + formId + ' form');
                    if (!form) return;
                    // textarea 里不自动提交（除非是 input-tags 这种特殊标签）
                    if (event.target.tagName === 'TEXTAREA') return;
                    if (form.querySelector('.input-tags')) return;
                    // 多表单页面不自动提交
                    document.querySelectorAll('form').length > 1 && !form.querySelector('.form-search') && !form.closest('.form-search')
                        ? null : (window.__forms__ && window.__forms__[formId] ? window.__forms__[formId].formSubmit() : null);
                }
                if (event.key === 'Escape' || event.keyCode === 0x1B) {
                    if (parent && parent.layer) {
                        var index1 = parent.layer.getFrameIndex ? parent.layer.getFrameIndex(window.name) : 0;
                        if (index1) {
                            // 惰性解析：layui.use 异步就绪后 window.layer 才是真 layer，不能在脚本解析期捕获
                            var layerApi = window.layer || window.tpb;
                            layerApi.msg(__blang.builder_confirm_close_this_window, {
                                time: 2000,
                                btn: [__blang.builder_button_ok, __blang.builder_button_cancel],
                                yes: function () {
                                    parent.layer.close(index1);
                                }
                            });
                        }
                        event.preventDefault();
                    }
                }
            });
        })('{$form}');

EOT;
        Builder::getInstance()->addScript($script);

        return $script;
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
     * 获取视图模板路径
     *
     * @return string
     */
    public function getViewTemplate()
    {
        $template = Module::getInstance()->getViewsPath() . 'form.html';

        return $template;
    }

    /**
     * 渲染表单（partial时返回View对象，否则返回HTML字符串）
     *
     * @return string|View
     */
    public function render()
    {
        $viewshow = new View($this->getViewTemplate());

        $vars = [
            'rows' => $this->rows,
            'action' => $this->action,
            'method' => strtoupper($this->method),
            'class' => $this->class,
            'attr' => $this->getAttrWithStyle(),
            'id' => $this->getFormId(),
            'ajax' => $this->ajax ? 1 : 0,
        ];

        $customVars = $this->customVars();

        if (!empty($customVars)) {
            $vars = array_merge($vars, $customVars);
        }

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
     * 魔术方法：以displayer类名（小驼峰）创建字段行，如 ->text('name', '标签')
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $count = count($arguments);

        if ($count > 0 && static::isDisplayer($name)) {

            $row = FRow::make($arguments[0], $count > 1 ? $arguments[1] : '', $count > 2 ? $arguments[2] : ($name == 'button' ? 1 : $this->defaultDisplayerColSize));

            if ($this->__fields__) {
                $this->__fields__->addRow($row);
            } else if ($this->__items__) {
                if ($name == 'hidden') {
                    throw new \InvalidArgumentException(__blang('builder_not_allowed') . ' : ' . $name);
                }
                $row->class('text-center');
                $this->__items__->addCol($arguments[0], $row);
            } else if ($this->__tabs__) {

                $this->__tabs__->addRow($row);
            } else {

                $this->rows[] = $row;
            }

            $row->setForm($this);

            $displayer = $row->$name($arguments[0], $count > 1 ? $arguments[1] : '');

            $row->setLabel($displayer->getLabel());

            if ($this->__when__) {
                $this->__when__->toggle($displayer);
            }

            if ($this->defaultDisplayerSize && !($displayer instanceof Button)) {
                $displayer->size($this->defaultDisplayerSize[0], $this->defaultDisplayerSize[1]);
            }

            if ($name == 'button') {
                $displayer->extKey('-' . $this->id . mt_rand(10, 99));
            }

            if ($this->__items__) {
                if (!($displayer instanceof Items)) {
                    $displayer->showLabel(false);
                }
                if ($displayer instanceof MultipleFile) { //表格中默认禁止直接上传图片
                    $displayer->setIsInTable();
                } else if ($displayer instanceof Fields) { //items的Fields
                    $displayer->getContent()->hasWrapper(false);
                }
            }

            return $displayer;
        }

        throw new \InvalidArgumentException(__blang('builder_invalid_argument_exception') . ' : ' . $name);
    }

    /**
     * 创建自身
     *
     * @param mixed $arguments
     * @return static
     */
    public static function make(...$arguments)
    {
        return Widget::makeWidget('Form', $arguments);
    }

    /**
     * 释放资源，销毁所有行和容器
     *
     * @return void
     */
    public function destroy()
    {
        // 6.261 已销毁直接返回：同一组件可被多归属路径重复触达（契约见 traits\HasDestroyOnce）
        if ($this->__destroyed__) {
            return;
        }

        $this->allContentsEnd();
        foreach ($this->rows as $row) {
            if ($row instanceof ReleaseAble) {
                $row->destroy();
            }
        }
        // 6.260 审计补：tab/step 是 ReleaseAble（内部挂 FieldsContent 树），先销毁再置空
        if ($this->tab instanceof ReleaseAble) {
            $this->tab->destroy();
        }
        $this->tab = null;
        if ($this->step instanceof ReleaseAble) {
            $this->step->destroy();
        }
        $this->step = null;
        // 6.260 数组属性复位为空数组（保持类型恒定，二次 destroy 自然幂等）
        $this->rows = [];
        $this->__fields__ = null;
        $this->__items__ = null;
        $this->data = [];
        $this->__destroyed__ = true;
    }
}
