<?php

namespace tpext\builder\displayer;

use think\Model;
use think\Collection;
use tpext\builder\common\Form;
use tpext\builder\common\Search;
use tpext\builder\form\ItemsContent;

/**
 * Items组件：多行子表单，以模板行方式编辑一组关联数据
 */
class Items extends Field
{
    protected $view = 'items';

    protected $isInput = false;

    protected $isFieldsGroup = true;

    protected $canRecover = true;

    protected $data = [];

    /**
     * 所属表单/搜索容器
     *
     * @var Form|Search
     */
    protected $form;

    /**
     * items内容区对象
     *
     * @var ItemsContent
     */
    protected $__items__;

    /**
     * 创建items：绑定表单并生成内容区
     *
     * @param string $fieldType
     * @return $this
     */
    public function created($fieldType = '')
    {
        parent::created($fieldType);

        $this->form = $this->getWrapper()->getForm();
        $this->__items__ = $this->form->createItems();

        if (empty($this->name)) {
            $this->name = 'items' . mt_rand(100, 999);
            $this->getWrapper()->setName($this->name);
        }

        $this->__items__->name($this->name);

        return $this;
    }

    /**
     * 执行字段配置闭包并结束items字段定义
     *
     * @param \Closure|mixed ...$fields
     * @return $this
     */
    public function with(...$fields)
    {
        if (count($fields) && $fields[0] instanceof \Closure) {
            $fields[0]($this->form);
        }

        $this->form->itemsEnd();
        return $this;
    }

    /**
     * 设置items数据（等同fill）
     *
     * @param array|Model|Collection|\IteratorAggregate $val
     * @return $this
     */
    public function value($val)
    {
        return $this->fill($val);
    }

    /**
     * 获取items内容区对象
     *
     * @return ItemsContent
     */
    public function getContent()
    {
        return $this->__items__;
    }

    /**
     * 设置操作列文案
     *
     * @param string $val
     * @return $this
     */
    public function actionRowText($val)
    {
        $this->__items__->actionRowText($val);
        return $this;
    }

    /**
     * 设置是否可删除行
     *
     * @param boolean $val
     * @return $this
     */
    public function canDelete($val)
    {
        $this->__items__->canDelete($val);
        return $this;
    }

    /**
     * canDelete的错误写法，保留兼容
     * @deprecated  1.9.0044
     * @param boolean $val
     * @return $this
     */
    public function cnaDelete($val)
    {
        $this->__items__->canDelete($val);
        return $this;
    }

    /**
     * 设置是否可添加行
     *
     * @param boolean $val
     * @return $this
     */
    public function canAdd($val)
    {
        $this->__items__->canAdd($val);
        return $this;
    }

    /**
     * 设置删除的行是否可恢复
     *
     * @param boolean $val
     * @return $this
     */
    public function canRecover($val)
    {
        $this->canRecover = $val;
        return $this;
    }

    /**
     * 禁止添加和删除行
     *
     * @return $this
     */
    public function canNotAddOrDelete()
    {
        $this->__items__->canDelete(false);
        $this->__items__->canAdd(false);
        return $this;
    }

    /**
     * 填充items数据
     *
     * @param array|Model|\ArrayAccess|Collection|\IteratorAggregate $data
     * @param boolean $overWrite
     * @return $this
     */
    public function fill($data = [], $overWrite = false)
    {
        if ($data instanceof Collection || $data instanceof \IteratorAggregate) {
            return $this->dataWithId($data, '', $overWrite);
        }

        if (!$overWrite && !empty($this->data)) {
            return $this;
        }

        if (!empty($this->name) && isset($data[$this->name])) {
            if (is_array($data[$this->name])) {
                $this->data = $data[$this->name];
            } else if ($data[$this->name] instanceof Collection || $data instanceof \IteratorAggregate) {
                return $this->dataWithId($data[$this->name], '', $overWrite);
            } else {
                //
            }
        } else if (is_array($data)) {
            $this->data = $data;
        }

        $this->__items__->fill($this->data);

        return $this;
    }

    /**
     * 设置只读（同时禁用添加/删除行）
     *
     * @param boolean $val
     * @return $this
     */
    public function readonly($val = true)
    {
        $this->canDelete(!$val);
        $this->canAdd(!$val);
        $this->__items__->readonly($val);
        return $this;
    }

    /**
     * 以主键为键名填充数据
     *
     * @param array|Collection|\IteratorAggregate $data
     * @param string $idField
     * @param boolean $overWrite
     * @return $this
     */
    public function dataWithId($data, $idField = 'id', $overWrite = false)
    {
        if (!$overWrite && !empty($this->data)) {
            return $this;
        }

        $list = [];
        foreach ($data as $k => $d) {
            if (empty($idField)) {
                $idField = $this->getPk($d);
            }
            if ($idField != '_') {
                $list[$d[$idField]] = $d;
            } else {
                $list[$k] = $d;
            }
        }
        $this->data = $list;

        $this->__items__->fill($this->data);

        return $this;
    }

    /**
     * 获取数据对象的主键名
     *
     * @param mixed $d
     * @return string
     */
    protected function getPk($d)
    {
        $pk = is_object($d) && method_exists($d, 'getPk') ? $d->getPk() : '';
        $pk = !empty($pk) && is_string($pk) ? $pk : '_';

        return $pk;
    }

    /**
     * 获取items数据
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * 生成添加/删除行脚本
     *
     * @return $this
     */
    protected function actionScript()
    {
        $id = 'items-' . $this->name;
        $canRecover = $this->canRecover ? 'true' : 'false';

        $script = <<<EOT

        (function() {
            var table = document.getElementById('{$id}');
            if (!table || table.__itemsBound__) return;
            table.__itemsBound__ = true;

            var temple = document.getElementById('{$id}-temple');
            var canRecover = {$canRecover};
            var newIndex = 0;

            function blang(key) {
                return (window.__blang && window.__blang[key]) || '';
            }

            function confirmModal(msg, onOk) {
                var conf = (window.tpb && window.tpb.confirm) || (window.lightyear && window.lightyear.confirm);
                if (!conf) {
                    if (window.confirm(String(msg).replace(/<[^>]+>/g, ''))) onOk();
                    return;
                }
                try {
                    conf({ title: blang('builder_operation_tips'), msg: msg, onOk: onOk });
                } catch (e) {
                    if (window.confirm(String(msg).replace(/<[^>]+>/g, ''))) onOk();
                }
            }

            // 模板行准备：所有带 name 的控件把名字存进 data-name 再剥离，避免模板行参与提交。
            // 模板行在 <template> 内（惰性、不升级），querySelectorAll 走 content 片段。
            if (temple && temple.content) {
                temple.content.querySelectorAll('[name]').forEach(function(el) {
                    el.setAttribute('data-name', el.getAttribute('name'));
                    el.removeAttribute('name');
                });
            }

            // 克隆行字段重置：
            // 1. 恢复 name（[__new__] → [__new__{n}]）
            // 2. id 去掉 -no-init-script 换成 __new__{n}（确保每行元素 id 唯一）
            // Web Components（x-date/x-select 等）插入 DOM 时由 connectedCallback 自动初始化，
            // 不再需要隐藏 textarea 脚本重放。
            function resetField(obj) {
                var carriers = [obj].concat(Array.prototype.slice.call(obj.querySelectorAll('[data-name],[name]')));
                carriers.forEach(function(el) {
                    var dataName = el.getAttribute('data-name') || el.getAttribute('name');
                    if (!dataName) return;
                    el.setAttribute('name', dataName.replace('[__new__]', '[__new__' + newIndex + ']'));
                    el.removeAttribute('data-name');
                    if (el.classList.contains('item-field-required')) {
                        el.setAttribute('required', 'true');
                    }
                });

                var idEls = [obj].concat(Array.prototype.slice.call(obj.querySelectorAll('[id]')));
                idEls.forEach(function(el) {
                    var oldId = el.getAttribute('id');
                    if (!oldId || oldId.indexOf('-no-init-script') === -1) return;
                    var newId = oldId.replace('-no-init-script', '__new__' + newIndex);
                    el.setAttribute('id', newId);
                    if (window.uploadConfigs && window.uploadConfigs[oldId]) {
                        window.uploadConfigs[newId] = window.uploadConfigs[oldId];
                    }
                });
            }

            // 复制行里的字段：item-field 自身 + item-field 之外散落的带 name 控件
            function resetRow(node) {
                node.querySelectorAll('.item-field').forEach(function(f) {
                    resetField(f);
                });
                node.querySelectorAll('[data-name]').forEach(function(el) {
                    if (el.closest('.item-field')) return;
                    resetField(el);
                });
                // 兜底：无 name/data-name 但 id 带 -no-init-script 的散件也要重写 id
                node.querySelectorAll('[id*="-no-init-script"]').forEach(function(el) {
                    if (el.closest('.item-field')) return;
                    var oldId = el.getAttribute('id');
                    var newId = oldId.replace('-no-init-script', '__new__' + newIndex);
                    el.setAttribute('id', newId);
                    if (window.uploadConfigs && window.uploadConfigs[oldId]) {
                        window.uploadConfigs[newId] = window.uploadConfigs[oldId];
                    }
                });
                // Web Components 的 cfg 属性里引用着行内 id（级联 prev_id 等），克隆后需同步重写
                node.querySelectorAll('[cfg*="-no-init-script"]').forEach(function(el) {
                    el.setAttribute('cfg', el.getAttribute('cfg').split('-no-init-script').join('__new__' + newIndex));
                });
            }

            function markRequired(row, td, ignore) {
                while (td) {
                    td.querySelectorAll('.item-field-required').forEach(function(el) {
                        el.classList.toggle('ignore', ignore);
                    });
                    td = td.previousElementSibling;
                }
            }

            document.addEventListener('click', function(e) {
                var t = e.target;
                if (!t || !t.closest) return;

                // 删除 / 恢复
                var delBtn = t.closest('.action-delete');
                if (delBtn && table.contains(delBtn)) {
                    var hidden = delBtn.previousElementSibling;
                    var del = hidden ? hidden.value : null;
                    var row = delBtn.closest('tr');
                    var td = delBtn.closest('td');

                    if (del === '0') {
                        if (!canRecover) {
                            confirmModal(blang('builder_confirm_to_do_operation') + ' <strong>' + blang('builder_remove') + '</strong> ' + blang('builder_action_operation') + ' ?', function() {
                                if (hidden) hidden.value = 1;
                                markRequired(row, td, true);
                                row.classList.add('hidden');
                            });
                        } else {
                            if (hidden) hidden.value = 1;
                            delBtn.classList.remove('btn-error');
                            delBtn.classList.add('btn-success');
                            delBtn.title = blang('builder_recover');
                            var icon = delBtn.querySelector('i');
                            if (icon) {
                                icon.classList.remove('mdi-delete');
                                icon.classList.add('mdi-restart');
                            }
                            markRequired(row, td, true);
                        }
                    } else if (del === '1') {
                        if (hidden) hidden.value = 0;
                        delBtn.classList.add('btn-error');
                        delBtn.classList.remove('btn-success');
                        delBtn.title = blang('builder_remove');
                        var icon2 = delBtn.querySelector('i');
                        if (icon2) {
                            icon2.classList.remove('mdi-restart');
                            icon2.classList.add('mdi-delete');
                        }
                        markRequired(row, td, false);
                    } else {
                        row.remove();
                    }
                    return;
                }

                // 添加：克隆模板行 → 重置字段 → 插入 DOM
                // Web Components 在插入 DOM 时由 connectedCallback 自动初始化
                var addBtn = t.closest('#{$id}-add');
                if (addBtn && temple && temple.content) {
                    var templateRow = temple.content.querySelector('tr');
                    if (!templateRow) return;
                    var node = templateRow.cloneNode(true);
                    newIndex += 1;
                    resetRow(node);
                    addBtn.closest('tr').parentNode.insertBefore(node, addBtn.closest('tr'));
                    var emptyText = document.getElementById('{$id}-empty-text');
                    if (emptyText) emptyText.classList.add('hidden');
                }
            });
        })();

EOT;
        $this->script[] = $script;

        return $this;
    }

    /**
     * 渲染前处理：绑定wrapper样式、生成操作脚本并渲染内容区
     *
     * @return $this
     */
    public function beforRender()
    {
        $this->getWrapper()->addClass('items-wrapper');

        if ($this->__items__->hasAction()) {
            $this->actionScript();
        }

        $this->__items__->beforRender();

        parent::beforRender();

        return $this;
    }

    /**
     * 附加模板变量：items内容区
     *
     * @return array
     */
    public function customVars()
    {
        return [
            'items_content' => $this->__items__,
        ];
    }

    /**
     * 在每个模板字段上执行
     * 
     * @param \Closure $callback
     * @return $this
     */
    public function templateFieldCall($callback)
    {
        $this->__items__->templateFieldCall($callback);
        return $this;
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

        if ($this->__items__) {
            $this->__items__->destroy();
            $this->__items__ = null;
        }

        $this->form = null;

        parent::destroy();
        $this->__destroyed__ = true;
    }
}
