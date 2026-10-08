<?php

namespace tpext\builder\form;

use tpext\builder\common\Form;
use tpext\builder\inface\Renderable;
use tpext\builder\inface\ReleaseAble;
use tpext\builder\traits\HasDestroyOnce;
use tpext\builder\traits\HasDom;
use tpext\builder\traits\HasRow;

class FRow extends FWrapper implements Renderable, ReleaseAble
{
    use HasDom;
    use HasRow;
    use HasDestroyOnce;

    /**
     * 所属表单对象
     *
     * @var Form
     */
    protected $form;

    /**
     * 创建表单行
     *
     * @param string $name
     * @param string $label
     * @param integer $colSize
     */
    public function __construct($name, $label = '', $colSize = 12)
    {
        $this->name = trim($name);
        $this->label = $label;
        $this->cloSize = $colSize;
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
     * 填充表单数据
     *
     * @param array $data
     * @return $this
     */
    public function fill($data = [])
    {
        $this->displayer->fill($data);
        return $this;
    }

    /**
     * 魔术方法，按字段类型创建展示器
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (static::isDisplayer($name)) {

            $class = static::$displayersMap[$name];

            return $this->createDisplayer($class, $arguments);
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

        $this->form = null;
        // 6.260 判空保幂等：二次 destroy 时 displayer 已置 null
        if ($this->displayer) {
            $this->displayer->destroy();
            $this->displayer = null;
        }
        $this->__destroyed__ = true;
    }
}
