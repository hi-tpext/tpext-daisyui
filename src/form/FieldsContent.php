<?php

namespace tpext\builder\form;

use think\Model;
use tpext\builder\common\Form;
use tpext\builder\common\Search;
use tpext\builder\search\SRow;
use tpext\builder\common\Module;
use tpext\builder\displayer\Field;
use tpext\builder\inface\Renderable;
use tpext\builder\inface\ReleaseAble;
use tpext\builder\traits\HasDestroyOnce;
use tpext\think\View;

class FieldsContent extends FWrapper implements Renderable, ReleaseAble
{
    use HasDestroyOnce;

    protected $view = 'fieldscontent';

    protected $rows = [];

    protected $data = [];

    protected $readonly = false;

    protected $hasWrapper = true;

    /**
     * 所属表单/搜索对象
     *
     * @var Form|Search
     */
    protected $form;

    /**
     * 设置是否输出行包裹元素
     *
     * @param mixed $val
     * @return $this
     */
    public function hasWrapper($val = true)
    {
        $this->hasWrapper = $val;
        return $this;
    }

    /**
     * 渲染前处理，填充数据并注册必填验证规则
     *
     * @return $this
     */
    public function beforRender()
    {
        foreach ($this->rows as $row) {
            $row->fill($this->data);
            if (!($row instanceof FRow)) {
                $row->beforRender();
                continue;
            }

            $displayer = $row->getDisplayer();

            if ($displayer->isRequired()) {
                $this->form->addJqValidatorRule($displayer->getName(), 'required', true);
            }

            $row->beforRender();
        }
        return $this;
    }

    /**
     * 添加行
     *
     * @param FRow|SRow|Field|Fillable $row
     * @return $this
     */
    public function addRow($row)
    {
        $this->rows[] = $row;
        return $this;
    }

    /**
     * 获取行列表
     *
     * @return array
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * 设置所属表单/搜索对象
     *
     * @param Form|Search $val
     * @return $this
     */
    public function setForm($val)
    {
        $this->form = $val;
        return $this;
    }

    /**
     * 获取所属表单/搜索对象
     *
     * @return Form|Search
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
     * 设置所有字段只读
     *
     * @param boolean $val
     * @return $this
     */
    public function readonly($val = true)
    {
        foreach ($this->rows as $row) {
            $row->getDisplayer()->readonly($val);
        }
        $this->readonly = $val;
        return $this;
    }

    /**
     * 设置扩展标识
     *
     * @param string $val
     * @return $this
     */
    public function extKey($val)
    {
        foreach ($this->rows as $row) {
            if (!($row instanceof FRow)) {
                continue;
            }

            $row->getDisplayer()->extKey($val);
        }

        return $this;
    }

    /**
     * 清除各行字段已注册的脚本
     *
     * @return $this
     */
    public function clearScript()
    {
        foreach ($this->rows as $row) {
            if (!($row instanceof FRow)) {
                continue;
            }

            $row->getDisplayer()->clearScript();
        }
        return $this;
    }

    /**
     * 获取已填充的数据
     *
     * @return array|Model|\ArrayAccess
     */
    public function getData()
    {
        return $this->data;
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
     * 渲染表单行内容
     *
     * @return string
     */
    public function render()
    {
        $template = Module::getInstance()->getViewsPath() . 'form' . DIRECTORY_SEPARATOR . $this->view . '.html';

        $viewshow = new View($template);

        $vars = [
            'rows' => $this->rows,
            'hasWrapper' => $this->hasWrapper,
        ];

        $customVars = $this->customVars();

        if (!empty($customVars)) {
            $vars = array_merge($vars, $customVars);
        }

        return $viewshow->assign($vars)->getContent();
    }

    /**
     * 魔术方法，将字段类型调用转换为行创建
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $count = count($arguments);

        if ($count > 0 && static::isDisplayer($name)) {

            $row = FRow::make($arguments[0], $count > 1 ? $arguments[1] : '', $count > 2 ? $arguments[2] : ($name == 'button' ? 1 : 12));

            $this->rows[] = $row;

            return $row->$name($arguments[0], $row->getLabel());
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

        foreach ($this->rows as $row) {
            if ($row instanceof ReleaseAble) {
                $row->destroy();
            }
        }
        // 6.260 数组属性复位为空数组（保持类型恒定，二次 destroy 自然幂等）
        $this->rows = [];
        $this->__destroyed__ = true;
    }
}
