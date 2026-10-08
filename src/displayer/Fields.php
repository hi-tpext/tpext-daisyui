<?php

namespace tpext\builder\displayer;

use think\Model;
use tpext\builder\common\Form;
use tpext\builder\common\Search;
use tpext\builder\common\Table;
use tpext\builder\form\FieldsContent as FormFileds;
use tpext\builder\table\FieldsContent as TableFileds;
use tpext\builder\table\TColumn;

/**
 * Fields字段分组组件：在一组容器内排列多个字段（表单/表格均可用）
 */
class Fields extends Field
{
    protected $view = 'fields';

    protected $isInput = false;

    protected $isFieldsGroup = true;

    protected $data = [];

    /**
     * 所属容器（表单/搜索/表格）
     *
     * @var Form|Search|Table
     */
    protected $widget;

    /**
     * 字段组内容区对象
     *
     * @var FormFileds|TableFileds
     */
    protected $__fields_content__;

    /**
     * 创建字段组：绑定容器并生成内容区
     *
     * @param string $fieldType
     * @return $this
     */
    public function created($fieldType = '')
    {
        parent::created($fieldType);

        if ($this->getWrapper() instanceof TColumn) {
            $this->widget = $this->getWrapper()->getTable();
        } else {
            $this->widget = $this->getWrapper()->getForm();
        }

        $this->__fields_content__ = $this->widget->createFields();

        if (empty($this->name)) {
            $this->name = 'fields' . mt_rand(100, 999);
            $this->getWrapper()->setName($this->name);
        }

        return $this;
    }

    /**
     * 执行字段配置闭包并结束字段组定义
     *
     * @param \Closure|mixed ...$fields
     * @return $this
     */
    public function with(...$fields)
    {
        if (count($fields) && $fields[0] instanceof \Closure) {
            $fields[0]($this->widget);
        }

        $this->widget->fieldsEnd();
        return $this;
    }

    /**
     * 获取字段组内容区对象
     *
     * @return FormFileds|TableFileds
     */
    public function getContent()
    {
        return $this->__fields_content__;
    }

    /**
     * 设置字段组数据（等同fill，覆盖写入）
     *
     * @param array|Model|\ArrayAccess $val
     * @return $this
     */
    public function value($val)
    {
        return $this->fill($val, true);
    }

    /**
     * 设置扩展键（同步到内容区）
     *
     * @param string $val
     * @return $this
     */
    public function extKey($val)
    {
        $this->__fields_content__->extKey($val);
        return parent::extKey($val);
    }

    /**
     * 填充字段组数据
     *
     * @param array|Model|\ArrayAccess $data
     * @param boolean $overWrite
     * @return $this
     */
    public function fill($data = [], $overWrite = false)
    {
        if (!$overWrite && !empty($this->data)) {
            return $this;
        }

        if (
            !empty($this->name) && isset($data[$this->name]) &&
            (is_array($data[$this->name]) || $data[$this->name] instanceof \ArrayAccess)
        ) {
            $fieldData = $data[$this->name];

            if (is_object($fieldData) && method_exists($fieldData, 'toArray')) {
                $fieldData = $fieldData->toArray();
            }

            if ($fieldData &&  (is_array($fieldData) || $fieldData instanceof \ArrayAccess)) {
                if (!$this->data) {
                    $this->data = [];
                }
                if (is_array($this->data) || $this->data instanceof \ArrayAccess) {
                    $this->data = array_merge($this->data, $fieldData);
                }
            }
        } else {
            $this->data = $data;
        }

        $this->__fields_content__->fill($this->data);

        return $this;
    }

    /**
     * 清空脚本片段（同步到内容区）
     *
     * @return $this
     */
    public function clearScript()
    {
        $this->__fields_content__->clearScript();
        return parent::clearScript();
    }

    /**
     * 设置只读（同步到内容区）
     *
     * @param boolean $val
     * @return $this
     */
    public function readonly($val = true)
    {
        $this->__fields_content__->readonly($val);
        return $this;
    }

    /**
     * 获取字段组数据
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * 渲染前处理：绑定wrapper样式并渲染内容区
     *
     * @return $this
     */
    public function beforRender()
    {
        $this->getWrapper()->addClass('fields-wrapper');
        $this->__fields_content__->beforRender();
        parent::beforRender();
        return $this;
    }

    /**
     * 附加模板变量：字段组内容区
     *
     * @return array
     */
    public function customVars()
    {
        return [
            'fields_content' => $this->__fields_content__,
        ];
    }

    /**
     * 销毁组件并释放内容区引用
     *
     * @return void
     */
    public function destroy()
    {
        // 6.261 已销毁直接返回：同一组件可被多归属路径重复触达（契约见 traits\HasDestroyOnce）
        if ($this->__destroyed__) {
            return;
        }

        if ($this->__fields_content__) {
            $this->__fields_content__->destroy();
            $this->__fields_content__ = null;
        }

        $this->widget = null;

        parent::destroy();
        $this->__destroyed__ = true;
    }
}
