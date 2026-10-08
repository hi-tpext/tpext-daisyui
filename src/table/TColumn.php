<?php

namespace tpext\builder\table;

use tpext\builder\common\Table;
use tpext\builder\displayer\Matche;
use tpext\builder\displayer\Matches;
use tpext\builder\inface\Renderable;
use tpext\builder\inface\ReleaseAble;
use tpext\builder\traits\HasDestroyOnce;
use tpext\builder\traits\HasDom;
use tpext\builder\traits\HasRow;

class TColumn extends TWrapper implements Renderable, ReleaseAble
{
    use HasDom;
    use HasRow;
    use HasDestroyOnce;

    /**
     * 所属表格对象
     *
     * @var Table
     */
    protected $table;

    protected $colAttr = [
        'sortable' => false,
        'hidden' => false,
    ];

    /**
     * 创建表格列
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
     * 填充行数据
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
     * 获取列宽度类名
     *
     * @return string
     */
    public function getColSizeClass()
    {
        return '0';
    }

    /**
     * 合并列属性
     *
     * @param array $arr
     * @return $this
     */
    public function colAttr($arr)
    {
        $this->colAttr = array_merge($this->colAttr, $arr);

        return $this;
    }

    /**
     * 获取列属性
     *
     * @return array
     */
    public function getColAttr()
    {
        return $this->colAttr;
    }

    /**
     * 设置列是否可排序
     *
     * @param boolean $val
     * @return $this
     */
    public function sortable($val = true)
    {
        $this->colAttr['sortable'] = $val;
        return $this;
    }

    /**
     * 设置列是否隐藏
     *
     * @param boolean $val
     * @return $this
     */
    public function hidden($val = true)
    {
        $this->colAttr['hidden'] = $val;
        return $this;
    }

    /**
     * 魔术方法，按字段类型创建列的展示器
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (static::isDisplayer($name)) {

            $class = static::$displayersMap[$name];

            $displayer = $this->createDisplayer($class, $arguments);

            // 6.267 匹配列（match/matches）内容水平居中：状态值列与勾选框列/操作列
            // 的 text-center 对齐节奏一致；用户覆写场景可在 ->getWrapper() 上再加类覆盖
            if ($displayer instanceof Matche || $displayer instanceof Matches) {
                $this->addClass('text-center');
            }

            return $displayer;
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

        $this->table = null;
        // 6.260 判空保幂等：二次 destroy 时 displayer 已置 null
        if ($this->displayer) {
            $this->displayer->destroy();
            $this->displayer = null;
        }
        $this->__destroyed__ = true;
    }
}
