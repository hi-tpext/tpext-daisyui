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
            } else {
                $this->addClass('hidden disabled');
            }
        } else if ($this->openChecked) {

            if (Builder::checkUrl($this->openChecked)) {
                $this->openCheckedScript();
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
