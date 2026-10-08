<?php

namespace tpext\builder\form;

use tpext\builder\inface\ReleaseAble;
use tpext\builder\traits\HasDestroyOnce;

use think\Collection;
use tpext\builder\common\Form;
use tpext\builder\common\Module;
use tpext\builder\displayer\Field;
use tpext\builder\form\FRow;
use tpext\builder\table\Actionbar;
use tpext\builder\traits\HasDom;
use tpext\think\View;

class ItemsContent extends FWrapper implements ReleaseAble
{
    use HasDom;
    use HasDestroyOnce;

    protected $view = 'itemscontent';

    protected $headers = [];

    protected $cols = [];

    protected $data = [];

    protected $list = [];

    protected $script = [];

    protected $pk = 'id';

    protected $ids = [];

    protected $emptyText = '';

    protected $isInitData = false;

    protected $actionRowText = '';

    protected $canDelete = true;

    protected $canAdd = true;

    protected $name = '';

    protected $template = [];

    /**
     * 操作列按钮组
     *
     * @var Actionbar
     */
    protected $actionbar = null;

    /**
     * 所属表单对象
     *
     * @var Form
     */
    protected $form;

    /**
     * 模板行字段处理回调
     *
     * @var \Closure
     */
    protected $templateFieldCall = null;

    /**
     * 初始化items表格默认样式及文案
     */
    public function __construct()
    {
        $this->class = 'table-striped table-hover table-bordered table-condensed table-responsive';

        $this->actionRowText = __blang('builder_action_operation');
        $this->emptyText = '<span>' . __blang('builder_no_relevant_data') . '</span>';
    }

    /**
     * 设置组件名称
     *
     * @param string $val
     * @return void
     */
    public function name($val)
    {
        $this->name = $val;
    }

    /**
     * 设置操作列标题文字
     *
     * @param string $val
     * @return $this
     */
    public function actionRowText($val)
    {
        $this->actionRowText = $val;
        return $this;
    }

    /**
     * 添加列（表头及字段行）
     *
     * @param string $name
     * @param FRow|Field|Fillable $row
     * @return $this
     */
    public function addCol($name, $col)
    {
        $this->headers[$name] = [
            'label' => $col->getLabel(),
            'style' => $col->getStyle(),
        ];
        $this->cols[$name] = $col;
        return $this;
    }

    /**
     * 获取列列表
     *
     * @return FRow[]
     */
    public function getCols()
    {
        return $this->cols;
    }

    /**
     * 设置所属表单
     *
     * @param Form $val
     * @return $this
     */
    public function setForm($val)
    {
        $this->form = $val;
        return $this;
    }

    /**
     * 获取所属表单
     *
     * @return Form
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * 字段定义结束，可传入回调对表单做最后处理
     *
     * @param mixed ...$fields
     * @return $this
     */
    public function with(...$fields)
    {
        if (count($fields) && $fields[0] instanceof \Closure) {
            $fields[0]($this->form);
        }

        $this->form->fieldsEnd();
        return $this;
    }

    /**
     * 设置是否允许删除行
     *
     * @param boolean $val
     * @return $this
     */
    public function canDelete($val)
    {
        $this->canDelete = $val;
        return $this;
    }

    /**
     * 是否需要操作列（新增/删除）
     *
     * @return boolean
     */
    public function hasAction()
    {
        return $this->canDelete || $this->canAdd;
    }

    /**
     * 设置是否允许添加行
     *
     * @param boolean $val
     * @return $this
     */
    public function canAdd($val)
    {
        $this->canAdd = $val;
        return $this;
    }

    /**
     * 设置主键字段名
     * 主键, 默认 为 'id'
     * @param string $val
     * @return $this
     */
    public function pk($val)
    {
        $this->pk = $val;
        return $this;
    }

    /**
     * 填充多行数据
     *
     * @param array|Collection $data
     * @return $this
     */
    public function fill($data = [])
    {
        $this->data = $data;
        return $this;
    }

    /**
     * 获取多行数据
     *
     * @return array|Collection
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * 设置所有字段只读
     *
     * @param boolean $val
     * @return $this
     */
    public function readonly($val = true)
    {
        foreach ($this->cols as $col) {
            $col->getDisplayer()->readonly($val);
        }

        return $this;
    }

    /**
     * 清除各列字段已注册的脚本
     *
     * @return $this
     */
    public function clearScript()
    {
        foreach ($this->cols as $col) {
            if (!($col instanceof FRow)) {
                continue;
            }

            $col->getDisplayer()->clearScript();
        }
        return $this;
    }

    /**
     * 设置模板行字段处理回调
     *
     * @param \Closure $callback
     * @return $this
     */
    public function templateFieldCall($callback)
    {
        $this->templateFieldCall = $callback;
        return $this;
    }

    /**
     * 渲染前处理，初始化行数据及模板行
     *
     * @return $this
     */
    public function beforRender()
    {
        $this->initData();

        return $this;
    }

    /**
     * 初始化行数据及模板行
     *
     * @return void
     */
    protected function initData()
    {
        $this->list = [];

        $pk = $this->pk;

        $cols = array_keys($this->cols);

        foreach ($this->data as $key => $data) {

            if (isset($data[$pk])) {

                $this->ids[$key] = $data[$pk];
            } else {
                $this->ids[$key] = $key;
            }

            foreach ($cols as $col) {

                $colunm = $this->cols[$col];

                if (!($colunm instanceof FRow)) {
                    continue;
                }

                $displayer = $colunm->getDisplayer();

                $displayer->clearScript();

                $displayer
                    ->value('')
                    ->fill($data)
                    ->extKey($key . $this->name)
                    ->arrayName([$this->name . '[' . $this->ids[$key] . '][', ']'])
                    ->showLabel(false)
                    ->size('0', '12 col-lg-12 col-sm-12 col-xs-12')
                    ->addClass('item-field ' . ($displayer->isRequired() ? ' item-field-required' : ''))
                    ->addAttr('data-label="' . $colunm->getLabel() . '"')
                    ->beforRender();

                if (
                    !empty($data['__readonly__fields__'])
                    && (in_array($col, $data['__readonly__fields__']) || $data['__readonly__fields__'][0] == '*')
                ) {
                    $displayer->readonly();
                }

                if (
                    !empty($data['__disabled__fields__'])
                    && (in_array($col, $data['__disabled__fields__']) || $data['__disabled__fields__'][0] == '*')
                ) {
                    $displayer->disabled();
                }

                $this->list[$key][$col] = [
                    'displayer' => $displayer,
                    'value' => $displayer->render(),
                    'attr' => $displayer->getAttrWithStyle(),
                    'wrapper' => $colunm,
                    '__can_delete__' => isset($data['__can_delete__']) ? $data['__can_delete__'] : 1,
                ];
            }
        }

        foreach ($this->cols as $key => $colunm) {
            if (!($colunm instanceof FRow)) {
                continue;
            }
            $displayer = $colunm->getDisplayer();

            $displayer->clearScript();

            // 模板行的 beforRender 会通过 Field::beforRender 调用 Builder::addScript，
            // 但模板行的脚本只应输出到 items-xxx-script textarea 供"添加行"按钮使用，
            // 不应在页面加载时全局执行。暂时禁用 Field 的 Builder 注入标志。
            $displayer->builderScriptEnabled(false);

            $isRequired = $displayer->isRequired();

            $headerLabel = $displayer->getLabel();

            if ($isRequired) {
                $headerLabel .= '<strong title="' . __blang('builder_this_field_is_required') . '" class="field-required">*</strong>';
            }

            // items 表格是 table-layout:auto（与主数据表一致），td 的 width 虽是首选宽度，
            // 但列宽由整列所有行共同决定，th 上的 width/min-width 更直接，故同步一份到 th
            $this->headers[$key] = [
                'label' => $headerLabel,
                'style' => $colunm->getStyle(),
            ];

            $displayer->required(false);

            if ($this->templateFieldCall) {
                $this->templateFieldCall->call($this, $displayer);
            }

            $displayer
                ->extKey($this->name . '-no-init-script') // 先改 ID，确保 beforRender 生成的 script 里的 ID 带 -no-init-script 后缀
                ->arrayName([$this->name . '[' . '__new__' . '][', ']'])
                ->showLabel(false)
                ->value('')
                ->size(12, 12)
                ->addClass('item-field ' . ($isRequired ? ' item-field-required' : ''))
                ->addAttr('data-label="' . $colunm->getLabel() . '"')
                ->beforRender();

            $this->template[] = [
                'value' => $displayer->render(),
                'attr' => $displayer->getAttrWithStyle(),
                'wrapper' => $colunm,
            ];

            // 模板行脚本不再收集：items 内只用简单组件（x-date/x-select 等 Web Components），
            // 克隆插入 DOM 时由 connectedCallback 自动初始化，无需 textarea 脚本重放
        }

        $this->isInitData = true;
    }

    /**
     * 获取模板自定义变量
     *
     * @return array
     */
    public function customVars()
    {
        return [];
    }

    /**
     * 渲染items表格
     *
     * @return string
     */
    public function render()
    {
        $template = Module::getInstance()->getViewsPath() . 'form' . DIRECTORY_SEPARATOR . $this->view . '.html';

        $viewshow = new View($template);

        $vars = [
            'name' => $this->name,
            'class' => $this->class,
            'attr' => $this->getAttrWithStyle(),
            'headers' => $this->headers,
            'cols' => $this->cols,
            'list' => $this->list,
            'data' => $this->data,
            'emptyText' => $this->emptyText,
            'ids' => $this->ids,
            'canDelete' => $this->canDelete,
            'actionRowText' => $this->actionRowText,
            'canAdd' => $this->canAdd,
            'script' => '',
            'template' => $this->template,
        ];

        $customVars = $this->customVars();

        if (!empty($customVars)) {
            $vars = array_merge($vars, $customVars);
        }

        return $viewshow->assign($vars)->getContent();
    }

    /**
     * 魔术方法，将字段类型调用转换为列创建
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $count = count($arguments);

        if ($count > 0 && static::isDisplayer($name)) {

            $col = FRow::make($arguments[0], $count > 1 ? $arguments[1] : '', $count > 2 ? $arguments[2] : 1);

            $this->headers[$arguments[0]] = [
                'label' => $col->getLabel(),
                'style' => $col->getStyle(),
            ];
            $this->cols[$arguments[0]] = $col;

            return $col->$name($arguments[0], $col->getLabel());
        }

        throw new \InvalidArgumentException(__blang('builder_invalid_argument_exception') . ' : ' . $name);
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

        foreach ($this->cols as $col) {
            if ($col instanceof ReleaseAble) {
                $col->destroy();
            }
        }
        // 6.260 数组属性复位为空数组（保持类型恒定，二次 destroy 自然幂等）
        $this->cols = [];
        $this->list = [];
        $this->__destroyed__ = true;
    }
}
