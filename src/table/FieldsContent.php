<?php

namespace tpext\builder\table;

use think\Model;
use tpext\builder\common\Module;
use tpext\builder\common\Table;
use tpext\builder\displayer\Field;
use tpext\builder\inface\Renderable;
use tpext\builder\inface\ReleaseAble;
use tpext\builder\traits\HasDestroyOnce;
use tpext\think\View;

class FieldsContent extends TWrapper implements Renderable, ReleaseAble
{
    use HasDestroyOnce;

    protected $view = 'fieldscontent';

    protected $cols = [];

    protected $data = [];

    /**
     * 关联的表格对象
     *
     * @var Table
     */
    protected $table;

    /**
     * 渲染前处理，清空并填充各列数据
     *
     * @return $this
     */
    public function beforRender()
    {
        foreach ($this->cols as $col) {

            if (!($col instanceof TColumn)) {
                $col->fill($this->data);
                $col->beforRender();
                continue;
            }

            $displayer = $col->getDisplayer();

            $displayer->clearScript();

            $displayer
                ->value('')
                ->fill($this->data)
                ->showLabel(false)
                ->size('0', '0 col-lg-0 col-sm-0 col-xs-0 row-' . $displayer->getName() . '-td')
                ->beforRender();
        }
        return $this;
    }

    /**
     * 添加列
     *
     * @param TColumn|Field $col
     * @return $this
     */
    public function addCol($col)
    {
        $this->cols[] = $col;
        return $this;
    }

    /**
     * 获取列列表
     *
     * @return TColumn[]
     */
    public function getCols()
    {
        return $this->cols;
    }

    /**
     * 设置所属表格
     *
     * @param Table $val
     * @return $this
     */
    public function setTable($val)
    {
        $this->table = $val;
        return $this;
    }

    /**
     * 获取所属表格
     *
     * @return Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * 字段定义结束，可传入回调对表格做最后处理
     *
     * @param mixed ...$fields
     * @return $this
     */
    public function with(...$fields)
    {
        if (count($fields) && $fields[0] instanceof \Closure) {
            $fields[0]($this->table);
        }

        $this->table->fieldsEnd();
        return $this;
    }

    /**
     * 填充行数据
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
     * 设置行数据（非数组值将被忽略）
     *
     * @param array|string $val
     * @return $this
     */
    public function value($val)
    {
        if (is_array($val)) {
            $this->data = $val;
        } else {
            $this->data = [];
        }
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
        foreach ($this->cols as $col) {
            if (!($col instanceof TColumn)) {
                continue;
            }

            $col->getDisplayer()->extKey($val);
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
            if (!($col instanceof TColumn)) {
                continue;
            }

            $col->getDisplayer()->clearScript();
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
     * 渲染表格列内容
     *
     * @return string
     */
    public function render()
    {
        $template = Module::getInstance()->getViewsPath() . 'table' . DIRECTORY_SEPARATOR . $this->view . '.html';

        $viewshow = new View($template);

        $vars = [
            'cols' => $this->cols,
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

        if ($name == 'fields') {
            throw new \InvalidArgumentException('[fields] could not include sub-fields');
        }

        if ($count > 0 && static::isDisplayer($name)) {

            $col = TColumn::make($arguments[0], $count > 1 ? $arguments[1] : '', $count > 2 ? $arguments[2] : 0);

            $this->cols[] = $col;

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

        // 6.260 数组属性复位为空数组（保持类型恒定，二次 destroy 自然幂等）；对象引用置 null
        $this->cols = [];
        $this->data = [];
        $this->table = null;
        $this->__destroyed__ = true;
    }
}
