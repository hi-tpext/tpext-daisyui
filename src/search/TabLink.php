<?php

namespace tpext\builder\search;

use tpext\builder\common\Builder;
use tpext\builder\common\Module;
use tpext\builder\inface\Renderable;
use tpext\builder\traits\HasDom;
use tpext\builder\traits\HasOptions;
use tpext\think\View;

class TabLink implements Renderable
{
    use HasDom;
    use HasOptions;

    protected $view = 'tab';

    protected $active = null;
    protected $id = '';
    protected $key = '';
    protected $searchId = '';

    /**
     * 获取标签元素ID
     *
     * @return string
     */
    public function getId()
    {
        if (empty($this->id)) {
            $this->id = 'tab-' . mt_rand(1000, 9999);
        }

        return $this->id;
    }

    /**
     * 设置所属搜索栏ID
     *
     * @param string $id
     * @return $this
     */
    public function searchId($id)
    {
        $this->searchId = $id;

        return $this;
    }

    /**
     * 设置查询字段名
     *
     * @param string $val
     * @return $this
     */
    public function key($val)
    {
        $this->key = $val;

        return $this;
    }

    /**
     * 设置默认激活的标签值
     *
     * @param string $val
     * @return $this
     */
    public function active($val)
    {
        $this->active = $val;

        return $this;
    }

    /**
     * 获取当前激活的标签值
     *
     * @return string
     */
    public function getActive()
    {
        if (is_null($this->active) && count($this->options)) {
            return array_keys($this->options)[0];
        }

        return $this->active;
    }

    /**
     * 渲染前处理，生成标签切换脚本
     *
     * @return $this
     */
    public function beforRender()
    {
        if (is_null($this->active) && count($this->options)) {
            $this->active = array_keys($this->options)[0];
        }

        $id = $this->getId();
        $element = 'row-' . $this->key;
        $script = <<<EOT

    // TabLink beforRender — 原生 JS。6.320 修正：模板 tab.html 已是 daisyUI
    // `a.tab`（tab-active 为激活类），旧版按 bootstrap `.nav-item a` 查询永远
    // 匹配不上，导致点击切换/自动提交全部失效。隐藏域优先复用同名搜索字段
    // （.row-{key}，如配套的 select），不存在才创建 hidden。
    (function() {
        var sf = document.querySelector('#{$this->searchId} form.search-form');
        var field = sf ? sf.querySelector('.{$element}') : null;
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = '{$this->key}';
            field.className = '{$element}';
            field.value = '{$this->active}';
            if (sf) sf.appendChild(field);
        }

        var root = document.getElementById('{$id}');
        if (!root) return;

        function markActive() {
            var tabs = root.querySelectorAll('a.tab');
            var act = root.querySelector('a.tab.tab-active');
            if (!act && tabs.length) tabs[0].classList.add('tab-active');
        }
        markActive();

        root.addEventListener('click', function(e) {
            var link = e.target.closest('a.tab');
            if (!link || !root.contains(link)) return;
            e.preventDefault();
            var val = link.getAttribute('data-val');
            root.querySelectorAll('a.tab').forEach(function(a) { a.classList.remove('tab-active'); });
            link.classList.add('tab-active');
            // 同名字段可能是 x-select 宿主（包装原生 select）——解析到真实 select，
            // 优先走 TomSelect API（同步 UI + change），普通控件退回 change 事件
            var target = field.tagName === 'X-SELECT' ? (field.querySelector('select') || field) : field;
            if (target.tomselect) {
                target.tomselect.setValue(val);
            } else {
                target.value = val;
                target.dispatchEvent(new Event('change', { bubbles: true }));
            }
            var submitBtn = sf ? sf.querySelector('button[type="submit"]') : null;
            if (submitBtn) submitBtn.click();
        });
    })();

EOT;

        Builder::getInstance()->addScript($script);

        return $this;
    }

    /**
     * 渲染标签页
     *
     * @return string
     */
    public function render()
    {
        $template = Module::getInstance()->getViewsPath() . 'table' . DIRECTORY_SEPARATOR . $this->view . '.html';
        $vars = [
            'options' => $this->options,
            'active' => $this->active,
            'id' => $this->getId(),
            'class' => $this->class,
            'attr' => $this->getAttrWithStyle(),
        ];

        $viewshow = new View($template);
        return $viewshow->assign($vars)->getContent();
    }

    /**
     * 对象转字符串时渲染标签页
     *
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }
}
