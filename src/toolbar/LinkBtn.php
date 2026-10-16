<?php

namespace tpext\builder\toolbar;

use tpext\builder\common\Builder;

class LinkBtn extends Bar
{
    protected $view = 'linkbtn';

    protected $postChecked = '';

    protected $openChecked = '';

    protected $confirm = true;

    /**
     * 获取按钮元素ID
     *
     * @return string
     */
    public function getId()
    {
        return 'btn-' . $this->name . preg_replace('/[^\w\-]/', '', $this->extKey);
    }

    /**
     * 设置为对勾选行提交POST请求的按钮
     *
     * @param string $url
     * @param boolean|string $confirm
     * @return $this
     */
    public function postChecked($url, $confirm = true)
    {
        $this->postChecked = (string)$url;
        $this->confirm = $confirm ? $confirm : 0;

        return $this;
    }

    /**
     * 设置为打开勾选行数据页面（如编辑页）的按钮
     *
     * @param string $url
     * @return $this
     */
    public function openChecked($url)
    {
        $this->openChecked = $url;

        return $this;
    }

    /**
     * 生成对勾选行提交POST请求的脚本
     *
     * @return string
     */
    protected function postCheckedScript()
    {
        $script = '';
        $inputId = $this->getId();

        $script = <<<EOT

        tpextbuilder.postChecked('{$inputId}', '{$this->postChecked}', '{$this->confirm}');

EOT;
        $this->script[] = $script;

        return $script;
    }

    /**
     * 生成打开勾选行数据页面的脚本
     *
     * @return string
     */
    protected function openCheckedScript()
    {
        $script = '';
        $inputId = $this->getId();

        $script = <<<EOT

        tpextbuilder.openChecked('{$inputId}', '{$this->openChecked}');

EOT;
        $this->script[] = $script;

        return $script;
    }

    /**
     * 渲染前处理，注册脚本
     *
     * @return $this
     */
    public function beforRender()
    {
        if ($this->postChecked) {

            if (Builder::checkUrl($this->postChecked)) {
                $this->postCheckedScript();
                // 6.475 服务端预置批量禁用态：页面加载后不可能有行被选中，批量
                // 按钮初始必为禁用——此前等 JS 绑定时才打 batch-disabled，按钮会
                // 先以可用态绘制再变灰（短暂闪烁）。勾选行时 updateBatchBtnState
                // 自动摘除
                $this->addClass('batch-action-btn batch-disabled');
            } else {
                $this->addClass('hidden disabled');
            }
        } else if ($this->openChecked) {

            if (Builder::checkUrl($this->openChecked)) {
                $this->openCheckedScript();
                $this->addClass('batch-action-btn batch-disabled'); // 6.475 同上
            } else {
                $this->addClass('hidden disabled');
            }
        }

        return parent::beforRender();
    }

    /**
     * 渲染链接按钮
     *
     * @return mixed
     */
    public function render()
    {
        $vars = $this->commonVars();

        $viewshow = $this->getViewInstance();

        return $viewshow->assign($vars)->getContent();
    }
}
