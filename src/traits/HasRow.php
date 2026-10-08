<?php

namespace tpext\builder\traits;

use tpext\builder\common\Widget;
use tpext\common\ExtLoader;
use tpext\builder\displayer\Field;

trait HasRow
{
    protected $name = '';

    protected $label = '';

    protected $cloSize = 12;

    protected $errorClass = '';

    /**
     * Displayer
     *
     * @var \tpext\builder\displayer\Field
     */
    protected $displayer;

    /**
     * 设置列占格数
     * @example 1 [int] 4 => class="col-md-4"
     * @example 2 [string] '4 xls-4' => class="col-md-4 col-xls-4"
     *
     * @param int|string $val
     * @return $this
     */
    public function cloSize($val)
    {
        $this->cloSize = $val;
        return $this;
    }

    /**
     * 设置字段名
     * @param string $val
     * @return $this
     */
    public function setName($val)
    {
        $this->name = $val;
        return  $this;
    }

    /**
     * 设置标签文字
     * @param string $val
     * @return $this
     */
    public function setLabel($val)
    {
        $this->label = $val;
        return  $this;
    }

    /**
     * 获取列占格数
     *
     * @return int|string
     */
    public function getColSize()
    {
        return $this->cloSize;
    }

    /**
     * 获取列占格样式类名
     *
     * @return int|string
     */
    public function getColSizeClass()
    {
        return Widget::getSizeAdapter()->adjustColSize($this->cloSize);
    }

    /**
     * 获取字段名
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * 获取字段对应的类名（特殊字符替换为-）
     *
     * @return string
     */
    public function getClassName()
    {
        return preg_replace('/\W/', '-', $this->name);
    }

    /**
     * 获取标签文字
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * 设置验证错误时附加的类名
     *
     * @param string $val
     * @return $this
     */
    public function errorClass($val)
    {
        $this->errorClass = $val;
        return $this;
    }

    /**
     * 获取验证错误时附加的类名
     *
     * @return string
     */
    public function getErrorClass()
    {
        return $this->errorClass;
    }

    /**
     * 获取字段展示器
     *
     * @return Field
     */
    public function getDisplayer()
    {
        return $this->displayer;
    }

    /**
     * 渲染字段
     *
     * @return mixed
     */
    public function render()
    {
        return $this->displayer->render();
    }

    /**
     * 对象转字符串时渲染字段
     *
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }

    /**
     * 创建字段展示器
     *
     * @param string $class
     * @param array $arguments
     * @return \tpext\builder\displayer\Field
     */
    public function createDisplayer($class, $arguments)
    {
        $displayer = new $class($arguments[0], $arguments[1]);
        $displayer->setWrapper($this);
        $displayer->created($class);

        $this->displayer = $displayer;

        static::addUsing($displayer);

        return $displayer;
    }

    /**
     * 创建完成后的处理
     *
     * @return $this
     */
    public function created()
    {
        return $this;
    }

    /**
     * 创建自身
     *
     * @param mixed $arguments
     * @return static
     */
    public static function make(...$arguments)
    {
        $row = Widget::makeWidget(class_basename(get_called_class()), $arguments);

        ExtLoader::trigger('tpext_row_created', $row);

        return $row;
    }

    /**
     * 渲染前处理
     *
     * @return $this
     */
    public function beforRender()
    {
        $this->displayer->beforRender();
        return $this;
    }
}
