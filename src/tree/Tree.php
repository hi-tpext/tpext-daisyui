<?php

namespace tpext\builder\tree;

use think\Collection;
use tpext\think\View;
use tpext\builder\common\Module;
use tpext\builder\common\Widget;
use tpext\builder\traits\HasDom;
use tpext\builder\inface\Renderable;
use tpext\builder\inface\ReleaseAble;
use tpext\builder\traits\HasDestroyOnce;

/**
 * Tree — 统一树形组件
 *
 * 原 JSTree / ZTree 的前端依赖 (jstree、zTree v3、jQuery) 已由 DaisyUI + Alpine.js 替代。
 * PHP 层只负责数据整理和属性配置, 不在 beforRender 里注入任何 JS/CSS。
 */
class Tree extends Widget implements Renderable, ReleaseAble
{
    use HasDom;
    use HasDestroyOnce;

    /** @var array 树数据, fill() 生成的嵌套结构 */
    protected $data;

    /** @var string|null 节点点击后要触发的前端脚本 (交由 view 模板消费) */
    protected $onClick = null;

    /** @var string trigger() 快捷方法绑定的表单元素选择器 */
    protected $trigger = '';

    /** @var string DOM id */
    protected $id = 'the-tree';

    /** @var bool partial 模式 — render() 返回 View 对象而非 HTML 字符串 */
    protected $partial = false;

    /** @var bool 默认全部展开 */
    protected $expandAll = false;

    /**
     * 点击父节点自动展开/收起 (原 ZTree 独有, Alpine.js 模板按需消费)
     *
     * @var bool
     */
    protected $expandNodeOnclick = false;

    public function __construct()
    {
        $this->addStyle('float:left;padding-left:5px;');
    }

    /**
     * Partial 模式
     */
    public function partial($val = true)
    {
        $this->partial = $val;
        return $this;
    }

    /**
     * 全部展开
     */
    public function expandAll($val = true)
    {
        $this->expandAll = $val;
        return $this;
    }

    /**
     * 点击父节点自动展开/收起
     */
    public function expandNodeOnclick($val = true)
    {
        $this->expandNodeOnclick = $val;
        return $this;
    }

    /**
     * 设置 DOM id
     */
    public function setId($val)
    {
        $this->id = $val;
        return $this;
    }

    /**
     * 直接设置树数据 (绕开 fill() 的整理逻辑)
     */
    public function data($val)
    {
        $this->data = $val;
        return $this;
    }

    /**
     * 填充树数据 — 统一输出嵌套结构
     *
     * 输出格式:
     *   [
     *     {id: 1, text: '根节点', state: {opened: true}, children: [
     *       {id: 2, text: '子节点', state: {opened: false}, children: []}
     *     ]}
     *   ]
     *
     * @param array|Collection|\IteratorAggregate $treeData  原始列表 (扁平)
     * @param string       $textField   文本字段名, 支持 {field} 和 {a.b} 占位
     * @param string       $idField     主键字段
     * @param string       $pidField    父级字段
     * @param string|false $rootText    根节点文本, false 表示不生成根节点
     */
    public function fill($treeData, $textField = 'name', $idField = 'id', $pidField = 'parent_id', $rootText = '全部')
    {
        if (empty($idField)) {
            $idField = 'id';
        }
        if (empty($pidField)) {
            $pidField = 'parent_id';
        }

        $tree = [];

        if ($rootText === '全部') {
            $rootText = __blang('builder_left_tree_text_all');
        }

        if ($rootText !== false && $rootText !== '') {
            $tree[] = [
                'id' => '__all__',
                'text' => $rootText,
                'state' => ['opened' => false],
                'children' => [],
            ];
        }

        preg_match_all('/\{([\w\.]+)\}/', $textField, $matches);
        $needReplace = isset($matches[1]) && count($matches[1]) > 0;

        $remaining = [];
        foreach ($treeData as $k => $li) {
            if (!isset($li[$pidField])) {
                $li[$pidField] = $li['pid'] ?? 0;
            }

            // 收集所有节点 (除根节点外全部进 remaining, 再按 pid 递归)
            $remaining[] = $li;
        }

        // 找出根节点 (pid == 0 或空)
        $roots = [];
        foreach ($remaining as $i => $li) {
            if ($li[$pidField] == 0 || $li[$pidField] === '' || $li[$pidField] === null) {
                $roots[] = $li;
                unset($remaining[$i]);
            }
        }

        foreach ($roots as $root) {
            $tree[] = $this->buildNode($root, $remaining, $textField, $idField, $pidField, $needReplace, $matches);
        }

        $this->data = $tree;
        return $this;
    }

    /**
     * 递归构建单个节点
     */
    protected function buildNode($li, &$remaining, $textField, $idField, $pidField, $needReplace, $matches)
    {
        $children = [];
        foreach ($remaining as $i => $child) {
            if ((string)$child[$pidField] === (string)$li[$idField]) {
                unset($remaining[$i]);
                $children[] = $this->buildNode($child, $remaining, $textField, $idField, $pidField, $needReplace, $matches);
            }
        }

        $text = $needReplace ? $this->resolveText($textField, $matches[1], $li) : (isset($li[$textField]) ? $li[$textField] : '');

        return [
            'id' => $li[$idField],
            'text' => $text,
            'state' => ['opened' => true],
            'children' => $children,
        ];
    }

    /**
     * 占位符替换: {field} 和 {a.b}
     */
    protected function resolveText($textField, $placeholders, $li)
    {
        $keys = [];
        $replace = [];

        foreach ($placeholders as $match) {
            $arr = explode('.', $match);
            $keys[] = '{' . $match . '}';

            if (count($arr) == 1) {
                $replace[] = isset($li[$arr[0]]) ? $li[$arr[0]] : '-';
            } elseif (count($arr) == 2) {
                $replace[] = isset($li[$arr[0]]) && isset($li[$arr[0]][$arr[1]]) ? $li[$arr[0]][$arr[1]] : '-';
            }
        }

        return str_replace($keys, $replace, $textField);
    }

    /**
     * 点击节点后要执行的前端脚本
     */
    public function onClick($script)
    {
        $this->onClick = $script;
        return $this;
    }

    /**
     * 快捷方式: 点击节点后, 将选中节点的 id 写入指定表单元素并触发搜索刷新
     *
     * 原 jQuery 逻辑已移除, Alpine.js 模板会读取 trigger 属性来绑定等价行为。
     * 该方法仍保留是为了 API 兼容。
     */
    public function trigger($element)
    {
        $this->trigger = $element;
        return $this;
    }

    /**
     * beforRender — 原 jstree/zTree 的 jQuery 初始化脚本已全部移除。
     *
     * Tree 组件不再注入任何前端资源, 由 Alpine.js + DaisyUI 在 view 模板中自行渲染。
     */
    public function beforRender()
    {
        return $this;
    }

    /**
     * 渲染 HTML
     *
     * 传递给模板的变量:
     *   class        — HasDom trait 的 CSS class
     *   attr         — HasDom trait 的属性字符串
     *   id           — DOM id
     *   data         — 树数据 (已 json_encode)
     *   expandAll    — 是否全部展开
     *   onClick      — 点击回调脚本 (可能为 null)
     *   trigger      — trigger() 绑定的表单选择器
     *   expandNodeOnclick — 点击父节点自动展开
     */
    public function render()
    {
        $template = Module::getInstance()->getViewsPath() . 'tree' . DIRECTORY_SEPARATOR . 'tree.html';

        $viewshow = new View($template);

        $vars = [
            'class' => $this->class,
            'attr' => $this->getAttrWithStyle(),
            'id' => $this->id,
            'data' => json_encode($this->data ?: [], JSON_UNESCAPED_UNICODE),
            'expandAll' => $this->expandAll,
            'onClick' => $this->onClick,
            'trigger' => $this->trigger,
            'expandNodeOnclick' => $this->expandNodeOnclick,
        ];

        if ($this->partial) {
            return $viewshow->assign($vars);
        }

        return $viewshow->assign($vars)->getContent();
    }

    public function destroy()
    {
        // 6.261 已销毁直接返回：同一组件可被多归属路径重复触达（契约见 traits\HasDestroyOnce）
        if ($this->__destroyed__) {
            return;
        }

        // 6.260 数组属性销毁后复位为空数组（保持类型恒定，二次 destroy 自然幂等）
        $this->data = [];
        $this->__destroyed__ = true;
    }
}
