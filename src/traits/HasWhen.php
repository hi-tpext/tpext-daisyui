<?php

namespace tpext\builder\traits;

use tpext\builder\common\Form;
use tpext\builder\common\Search;
use tpext\builder\form\When;
use tpext\builder\common\Builder;

trait HasWhen
{
    /**
     * when条件集合
     *
     * @var array
     */
    protected $whens = [];

    /**
     * 当前进行中的when对象
     *
     * @var When
     */
    protected $__when__ = null;

    protected $whenWrapper = false;

    /**
     * 为字段添加when条件联动
     *
     * @param string|int|array $cases 如：'1' 或 '1 + 2' 或 ['1 + 2', '2 + 3']
     * @param array|\Closure|mixed ...$toggleFields
     * @return $this
     */
    public function when($cases, ...$toggleFields)
    {
        $form = $this->getForm();
        $when = $form->createWhen($this, $cases);

        $this->__when__ = $when;
        $this->whens[] = $when;

        if (count($toggleFields)) {

            if ($toggleFields[0] instanceof \Closure) { //如果是匿名回调
                //fields包围优化
                $this->makeWhenWrapper();
                $this->with(...$toggleFields);
                return $this;
            } else {
                //无法fields包围优化，多层次when嵌套时不推荐：
                //->when('1', $field1, $field2, ...)
                //或
                //->when('1', [$field1, $field2, ...])

                if (is_array($toggleFields[0])) {
                    $toggleFields = $toggleFields[0];
                }

                foreach ($toggleFields as $field) {
                    $this->__when__->toggle($field);
                }
            }

            $form->whenEnd();
            $this->whenEnd();
            //如果此处传入[toggleFields]参数，那么就结束，后面就不要再调用with($toggleFields)方法了。否则，后面可以继续调用with($toggleFields)方法;
        } else {
            //fields包围优化
            $this->makeWhenWrapper();
        }

        return $this;
    }

    /**
     * 创建一个fields包裹后续的toggleFields，解决多层when嵌套的一些问题
     *
     * @return void
     */
    protected function makeWhenWrapper()
    {
        //创建一个fields把后面的 toggleFields装进去，解决多层when的嵌套的一些问题
        $form = $this->getForm();
        $whenWrapper = $form->fields(preg_replace('/\W/', '', $this->getName()) . '_when_' . count($this->whens))
            ->showLabel(false)
            ->addClass('when-wrapper')
            ->size(0, 12);
        $this->__when__->toggle($whenWrapper);
        $this->whenWrapper = true;
    }

    /**
     * 判断是否没有when条件
     *
     * @return boolean
     */
    public function emptyWhens()
    {
        return empty($this->whens);
    }

    /**
     * when受控字段定义结束
     *
     * @param array|\Closure|mixed ...$toggleFields
     * @return $this
     */
    public function with(...$toggleFields)
    {
        if (!$this->__when__) {
            throw new \LogicException('when($cases, ...$toggleFields)第二个参数[toggleFields]已传入，后续不要继续调用with');
        }

        $form = $this->getForm();

        if (count($toggleFields)) {
            if ($toggleFields[0] instanceof \Closure) {
                $toggleFields[0]($form);
            }
        }

        $form->whenEnd();
        $this->whenEnd();

        if ($this->whenWrapper) {
            $form->fieldsEnd();
        }

        return $this;
    }

    /**
     * 结束当前when状态
     *
     * @return $this
     */
    public function whenEnd()
    {
        $this->__when__ = null;
        return $this;
    }

    /**
     * 获取所属表单/搜索对象
     *
     * @return Form|Search
     */
    protected function getForm()
    {
        return $this->getWrapper()->getForm();
    }

    /**
     * 生成when联动的js脚本
     * @param boolean $viewModel 是否为view模式
     * @return string
     */
    public function whenScript($viewModel = false)
    {
        if (count($this->whens) == 0) {
            return '';
        }

        $watchFor = $this->getId();

        $key = 'w_' . $this->getName();

        $key = preg_replace('/\W/', '', $key);

        // 6.410 turn()/whenScript(true) 在 view 模式下于渲染前运行，FieldsContent::beforRender
        // 还没把 fill 数据赋给字段，renderValue 只会拿到 default，judgeMatchCase 判定全错。
        // 显式从表单数据解析监听值（form 模式下 whenScript 由 watch 字段 beforRender 触发，
        // 行 fill 已先行完成、value 非空，此处跳过，行为不变）。
        if ($this->value === '' || $this->value === null) {
            $formData = $this->getForm()->getData();
            $watchName = $this->getName();
            if ((is_array($formData) || $formData instanceof \ArrayAccess) && isset($formData[$watchName])) {
                $watchVal = $formData[$watchName];
                if ($watchVal !== '' && $watchVal !== null) {
                    $this->value(is_array($watchVal) ? implode(',', $watchVal) : $watchVal);
                }
            }
        }

        $casesOptions = [];

        $i = 1;
        foreach ($this->whens as $when) {
            $when->setWrapperClass($key . ' ' . $key . '_' . $i);
            // 6.410 view 只读模式防首帧闪烁：受控字段被 turn() 换成 Show/Matche 后，
            // When::toggle 的 rendering 闭包不会触发（watch 字段本身不再渲染），服务端
            // 不会预置 hidden/match-case——所有段先全部渲染，等 whenScript 10ms 后才
            // 收敛，肉眼可见闪一下。此处按 judgeMatchCase 预置初始显隐类（调用时机在
            // fill 之后，判定值已就绪；form 模式闭包预置的是同一个类，重复添加无影响）。
            $when->setWrapperClass($when->judgeMatchCase() ? 'match-case' : 'hidden');
            $casesOptions[$key . '_' . $i] = $when->getCases();
            $i += 1;
        }

        $viewModel = $viewModel ? 1 : 0;
        $script = '';

        $casesOptions = json_encode($casesOptions);

        $fieldType = class_basename($this);

        $box = '';
        if ($viewModel) {
            $box = '__when__';
        } else {
            if ($fieldType == 'Checkbox') {
                $box = ' input:checkbox';
            } else if ($fieldType == 'Radio') {
                $box = ' input:radio';
            }
        }

        $script = <<<EOT

        // HasWhen::whenScript — 原生 JS 实现（替代原 jQuery）
        (function() {
            var casesOptions{$key} = {$casesOptions};
            var fieldType{$key} = '{$fieldType}';
            var viewModel{$key} = '{$viewModel}' == '1';

            // 获取触发 change 的源元素
            var sourceEl;
            if (viewModel{$key}) {
                sourceEl = document.getElementById('{$watchFor}__when__');
                if (!sourceEl) {
                    var __f__ = document.createElement('input');
                    __f__.type = 'hidden';
                    __f__.id = '{$watchFor}__when__';
                    var orig = document.getElementById('{$watchFor}');
                    __f__.value = orig ? (orig.getAttribute('data-selected') || '') : '';
                    var fw = document.querySelector('.form-wrapper form');
                    if (fw) fw.appendChild(__f__);
                    sourceEl = __f__;
                }
            } else {
                if ('{$fieldType}' === 'Radio') {
                    // 6.407 绑到包裹层而非首个 radio：兄弟 input 的 change 不会冒泡到首个 input，
                    // 选中第一个以外的选项时切换不响应（旧 jQuery 版对每个 radio 都绑定，原生化时回归）
                    sourceEl = document.getElementById('{$watchFor}') || document.querySelector('#{$watchFor} input[type=radio]');
                } else if ('{$fieldType}' === 'Checkbox') {
                    sourceEl = document.getElementById('{$watchFor}');
                } else {
                    sourceEl = document.getElementById('{$watchFor}');
                }
            }
            if (!sourceEl) return;

            // 注册 change 事件（绑定一次，后续 AJAX 刷新也生效）
            sourceEl.addEventListener('change', runWhenLogic{$key});

            function runWhenLogic{$key}() {
                var hbLabel = document.querySelector('#help-block .error-label');
                if (hbLabel) hbLabel.textContent = '';
                document.querySelectorAll('.{$key}.match-case').forEach(function(el) { el.classList.remove('match-case'); });

                var val = [];
                if (fieldType{$key} === 'Checkbox' || fieldType{$key} === 'Transfer' || fieldType{$key} === 'MultipleSelect') {
                    if (viewModel{$key}) {
                        val = (sourceEl.value || '').split(',').filter(Boolean);
                    } else if (fieldType{$key} === 'Checkbox') {
                        document.querySelectorAll('#{$watchFor} input[type=checkbox]:checked').forEach(function(cb) { val.push(cb.value); });
                    } else {
                        val = Array.isArray(sourceEl.value) ? sourceEl.value : (sourceEl.value || []);
                    }
                    // 匹配 cases
                    for (var c in casesOptions{$key}) {
                        for (var i in casesOptions{$key}[c]) {
                            var items = ('' + casesOptions{$key}[c][i]).split('+').map(function(s){ return s.trim(); });
                            if (items.length !== val.length) continue;
                            var m = 0;
                            for (var j in val) {
                                for (var k in items) {
                                    if (val[j] == items[k]) { m++; break; }
                                }
                            }
                            if (m > 0 && m === val.length) {
                                document.querySelectorAll('.' + c).forEach(function(el) { el.classList.add('match-case'); });
                                break;
                            }
                        }
                    }
                } else {
                    var sv = '';
                    if (viewModel{$key}) {
                        sv = sourceEl.value;
                    } else if (fieldType{$key} === 'Radio') {
                        var checked = document.querySelector('#{$watchFor} input[type=radio]:checked');
                        sv = checked ? checked.value : '';
                    } else {
                        sv = sourceEl.value;
                    }
                    for (var c in casesOptions{$key}) {
                        for (var i in casesOptions{$key}[c]) {
                            if (sv == ('' + casesOptions{$key}[c][i]).trim()) {
                                document.querySelectorAll('.' + c).forEach(function(el) { el.classList.add('match-case'); });
                                break;
                            }
                        }
                    }
                }

                // 显示/隐藏逻辑处理
                document.querySelectorAll('.{$key}.match-case').forEach(function(el) { el.classList.remove('hidden'); });

                var hideEls = document.querySelectorAll('.{$key}:not(.match-case)');
                hideEls.forEach(function(wrap) {
                    wrap.classList.add('hidden');
                    wrap.querySelectorAll('input,textarea,select').forEach(function(e) {
                        e.classList.add('ignore');
                        if (e.hasAttribute('name')) {
                            if (!e.dataset.name) e.dataset.name = e.getAttribute('name');
                            e.removeAttribute('name');
                        }
                    });
                });

                document.querySelectorAll('.{$key}.match-case').forEach(function(wrap) {
                    wrap.querySelectorAll('input,textarea,select').forEach(function(e) {
                        e.classList.remove('ignore');
                        if (e.dataset.name && !e.classList.contains('switch-box')) {
                            e.setAttribute('name', e.dataset.name);
                        }
                    });
                });
            }

            setTimeout(function() { runWhenLogic{$key}(); }, 10);
        })();

EOT;
        $this->script[] = $script;

        if ($viewModel) {
            Builder::getInstance()->addScript($script);
        }

        return $script;
    }
}
