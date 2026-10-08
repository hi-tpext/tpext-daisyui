<?php

namespace tpext\builder\table;

use tpext\builder\common\Toolbar;
use tpext\builder\toolbar\ActionBtn;

class Actionbar extends Toolbar
{
    /**
     * 主键字段名
     *
     * @var string
     */
    protected $pk;

    /**
     * 当前行主键值
     *
     * @var string|int
     */
    protected $rowId;

    /**
     * 当前行数据
     *
     * @var array
     */
    protected $rowData;

    protected $mapClass = [];

    /**
     * 6.331 下拉模式：操作列收敛为单个触发器（默认 [...]），悬停展开全部操作按钮
     *
     * @var boolean
     */
    protected $dropdown = false;

    /**
     * 下拉触发器文字
     *
     * @var string
     */
    protected $dropdownLabel = '...';

    /**
     * 6.331 操作列收敛为单个 [...] 触发器，鼠标悬停时展开全部操作按钮
     * （daisyUI dropdown-hover + dropdown-left：菜单从触发器左侧弹出，右缘操作列不溢出）
     *
     * @param string $label 触发器文字，默认 '...'
     * @return $this
     */
    public function dropdown($label = '...')
    {
        $this->dropdown = true;
        $this->dropdownLabel = (string)$label;
        return $this;
    }

    /**
     * 渲染为HTML——dropdown 模式下把全部按钮包进悬停菜单，菜单保持按钮原有形态
     * （rowsHtml 按 br() 分行、行内横排），其余场景照常
     *
     * @return string
     */
    public function render()
    {
        if (!$this->dropdown) {
            return parent::render();
        }

        $label = htmlspecialchars($this->dropdownLabel, ENT_QUOTES, 'UTF-8');

        return '<div class="action-row action-row-dropdown">'
            . '<div class="dropdown dropdown-hover dropdown-left action-dropdown">'
            . '<div tabindex="0" role="button" class="btn btn-xs btn-secondary action-dropdown-trigger">' . $label . '</div>'
            . '<div tabindex="0" class="dropdown-content action-dropdown-menu">' . $this->rowsHtml() . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * 设置所有按钮使用弹层方式打开
     *
     * @param boolean $val
     * @param array|string $size
     * @return $this
     */
    public function useLayerAll($val, $size = [])
    {
        foreach ($this->elms as $elm) {
            $elm->useLayer($val, $size);
        }

        return $this;
    }

    /**
     * 创建动作栏
     *
     * @param string $barType
     * @return $this
     */
    public function created($barType = '')
    {
        parent::created();
        return $this;
    }

    /**
     * 渲染前处理，为各按钮填充行主键、行数据、类名映射等信息
     *
     * @return $this
     */
    public function beforRender()
    {
        if (empty($this->elms)) {
            $this->buttons();
        }

        foreach ($this->elms as $elm) {

            if ($this->extKey) {
                $elm->extKey($this->extKey);
            }

            if (!($elm instanceof ActionBtn)) {
                continue;
            }

            if ($this->rowId) {
                $elm->dataId($this->rowId);
            }

            if ($this->rowData) {
                $elm->parseUrl($this->rowData);
            }

            if ($this->mapClass) {
                $elm->mapClass($this->mapClass);
            }
        }

        return parent::beforRender();
    }

    /**
     * 设置主键字段名
     *
     * @param string $val
     * @return $this
     */
    public function pk($val)
    {
        $this->pk = $val;
        return $this;
    }

    /**
     * 设置当前行数据
     *
     * @param array $data
     * @return $this
     */
    public function rowData($data)
    {
        if (isset($data[$this->pk])) {
            $this->rowId = $data[$this->pk];
        }

        $this->rowData = $data;

        return $this;
    }

    /**
     * 设置按钮类名映射（按钮名 => 附加类名）
     *
     * @param array $data
     * @return $this
     */
    public function mapClass($data)
    {
        $this->mapClass = $data;
        return $this;
    }

    /**
     * 添加默认的编辑、删除按钮
     *
     * @return $this
     */
    public function buttons()
    {
        $this->btnEdit();
        $this->btnDelete();

        return $this;
    }

    /**
     * 添加编辑按钮
     *
     * @param string $url
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @return $this
     */
    public function btnEdit($url = '', $label = '', $class = 'btn-primary', $icon = 'mdi-lead-pencil', $attr = 'title="编辑"')
    {
        if (empty($url)) {
            $url = url('edit', ['id' => '__data.pk__']);
        }
        if ($attr == 'title="编辑"') {
            $attr = 'title="' . __blang('builder_action_edit') . '"';
        }
        $this->actionBtn('edit', $label)->href($url)->icon($icon)->addClass($class)->addAttr($attr);
        return $this;
    }

    /**
     * 添加查看按钮
     *
     * @param string $url
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @return $this
     */
    public function btnView($url = '', $label = '', $class = 'btn-info', $icon = 'mdi-eye-outline', $attr = 'title="查看"')
    {
        if (empty($url)) {
            $url = url('view', ['id' => '__data.pk__']);
        }
        if ($attr == 'title="查看"') {
            $attr = 'title="' . __blang('builder_action_view') . '"';
        }
        $this->actionBtn('view', $label)->href($url)->icon($icon)->addClass($class)->addAttr($attr);
        return $this;
    }

    /**
     * 添加删除按钮
     *
     * @param string $postUrl
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @param boolean|string $confirm
     * @return $this
     */
    public function btnDelete($postUrl = '', $label = '', $class = 'btn-error', $icon = 'mdi-delete', $attr = 'title="删除"', $confirm = true)
    {
        if (empty($postUrl)) {
            $postUrl = url('delete');
        }
        if ($attr == 'title="删除"') {
            $attr = 'title="' . __blang('builder_action_delete') . '"';
        }
        $this->actionBtn('delete', $label)->postRowid($postUrl, $confirm)->icon($icon)->addClass($class)->addAttr($attr);
        return $this;
    }

    /**
     * 添加禁用按钮
     *
     * @param string $postUrl
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @param boolean|string $confirm
     * @return $this
     */
    public function btnDisable($postUrl = '', $label = '', $class = 'btn-warning', $icon = 'mdi-block-helper', $attr = 'title="禁用"', $confirm = true)
    {
        if (empty($postUrl)) {
            $postUrl = url('enable', ['state' => 0]);
        }
        if ($attr == 'title="禁用"') {
            $attr = 'title="' . __blang('builder_action_disable') . '"';
        }
        $this->actionBtn('disable', $label)->postRowid($postUrl, $confirm)->icon($icon)->addClass($class)->addAttr($attr);
        return $this;
    }

    /**
     * 添加启用按钮
     *
     * @param string $postUrl
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @param boolean|string $confirm
     * @return $this
     */
    public function btnEnable($postUrl = '', $label = '', $class = 'btn-success', $icon = 'mdi-check', $attr = 'title="启用"', $confirm = true)
    {
        if (empty($postUrl)) {
            $postUrl = url('enable', ['state' => 1]);
        }
        if ($attr == 'title="启用"') {
            $attr = 'title="' . __blang('builder_action_enable') . '"';
        }
        $this->actionBtn('enable', $label)->postRowid($postUrl, $confirm)->icon($icon)->addClass($class)->addAttr($attr);
        return $this;
    }

    /**
     * 添加启用/禁用一对按钮
     *
     * @param string $enableTitle
     * @param string $disableTitle
     * @return $this
     */
    public function btnEnableAndDisable($enableTitle = '启用', $disableTitle = '禁用')
    {
        if ($enableTitle == '启用') {
            $enableTitle = __blang('builder_action_enable');
        }
        if ($disableTitle == '禁用') {
            $disableTitle = __blang('builder_action_disable');
        }
        $this->btnEnable()->getCurrent()->attr('title="' . $enableTitle . '"');
        $this->btnDisable()->getCurrent()->attr('title="' . $disableTitle . '"');

        return $this;
    }

    /**
     * 添加链接按钮
     *
     * @param string $name
     * @param string $url
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @return $this
     */
    public function btnLink($name = '', $url = '', $label = '', $class = 'btn-secondary', $icon = '', $attr = '')
    {
        if (!$name) {
            $name = preg_replace('/.+?\/(\w+)(\.\w+)?$/', '$1', $url, -1, $count);

            if (!$count) {
                $name = preg_replace('/\W/', '_', $url);
            }
        }

        $this->actionBtn($name, $label)->href($url)->icon($icon)->addClass($class)->addAttr($attr);

        return $this;
    }

    /**
     * 添加按行提交 POST 请求的按钮
     *
     * @param string $name
     * @param string $postUrl
     * @param string $label
     * @param string $class
     * @param string $icon
     * @param string $attr
     * @param boolean|string $confirm
     * @return $this
     *
     */
    public function btnPostRowid($name = '', $postUrl = '', $label = '', $class = 'btn-secondary', $icon = 'mdi-checkbox-marked-outline', $attr = '', $confirm = true)
    {
        if (!$name) {
            $name = preg_replace('/.+?\/(\w+)(\.\w+)?$/', '$1', $postUrl, -1, $count);

            if (!$count) {
                $name = preg_replace('/\W/', '_', $postUrl);
            }
        }

        $this->actionBtn($name, $label)->postRowid($postUrl, $confirm)->icon($icon)->addClass($class)->addAttr($attr);

        return $this;
    }

    /**
     * 设置自定义 HTML 内容
     *
     * @param string $val
     * @return $this
     */
    public function html($val)
    {
        parent::html($val);
        return $this;
    }
}
