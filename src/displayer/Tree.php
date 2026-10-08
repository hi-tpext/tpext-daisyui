<?php

namespace tpext\builder\displayer;

use think\Collection;

/**
 * Tree — 树形复选框 (表单字段)
 *
 * 左侧树 (widget/tree/Tree.php) 用 Alpine.js 实现点击筛选;
 * 这里的 Tree 是 form 里的 checkbox tree, 同样用 Alpine.js 递归渲染。
 *
 * 数据格式: optionsData() 输出嵌套 children, 兼容 treeselectjs 和 Alpine.js
 *   [{ value, name, children: [{ value, name, children: [] }] }]
 */
class Tree extends Field
{
    protected $view = 'tree';
    protected $cssFamily = 'trees'; // 6.181 按需加载

    protected $minify = false;

    protected $js = [
        '/assets/tpextdaisyui/js/vendors/jquery.min.js',
        '/assets/tpextdaisyui/js/vendors/jstree/jstree.min.js',
    ];

    protected $css = [
        '/assets/tpextdaisyui/js/vendors/jstree/themes/default/style.min.css',
    ];

    /** @var array 嵌套树数据 */
    protected $options = [];

    protected $expandAll = true;

    protected $multiple = true;

    protected $maxHeight = 400;

    protected $minHeight = 200;

    protected $enableCheck = true;

    /** @var array|string 已选中的值 */
    protected $checked = [];

    /** @var array 禁用节点 id */
    protected $disabledOptions = [];

    /**
     * 点击父节点自动展开/收起
     *
     * @var bool
     */
    protected $expandNodeOnclick = false;

    /**
     * 多选时, 父子节点是否级联 (false = 级联, true = 独立)
     * Alpine.js 模板按需消费
     *
     * @var bool
     */
    protected $noCascaded = true;

    /**
     * 设置默认选中值
     *
     * @param array|string $val
     * @return $this
     */
    public function default($val = [])
    {
        $this->default = $val;
        return $this;
    }

    /**
     * 是否启用 checkbox
     *
     * @param boolean $val
     * @return $this
     */
    public function enableCheck($val = true)
    {
        $this->enableCheck = $val;
        return $this;
    }

    /**
     * 点击父节点自动展开/收起
     *
     * @param boolean $val
     * @return $this
     */
    public function expandNodeOnclick($val = true)
    {
        $this->expandNodeOnclick = $val;
        return $this;
    }

    /**
     * 直接设置嵌套树数据 (绕开 optionsData 整理)
     *
     * @param array $val
     * @return $this
     */
    public function data($val)
    {
        $this->data = $val;
        return $this;
    }

    /**
     * 多选 / 单选
     *
     * @param boolean $val
     * @return $this
     */
    public function multiple($val = true)
    {
        $this->multiple = $val;
        return $this;
    }

    /**
     * 多选时, 是否父子节点独立选中 (不级联)
     *
     * @param boolean $val
     * @return $this
     */
    public function noCascaded($val = true)
    {
        $this->noCascaded = $val;
        return $this;
    }

    /**
     * 树容器最大高度
     *
     * @param int $val
     * @return $this
     */
    public function maxHeight($val = 800)
    {
        $this->maxHeight = $val;
        return $this;
    }

    /**
     * 树容器最小高度
     *
     * @param int $val
     * @return $this
     */
    public function minHeight($val = 200)
    {
        $this->minHeight = $val;
        return $this;
    }

    /**
     * 获取嵌套树数据
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * 默认展开全部
     *
     * @param boolean $val
     * @return $this
     */
    public function expandAll($val = true)
    {
        $this->expandAll = $val;
        return $this;
    }

    /**
     * 禁用节点
     *
     * @param string|array $val
     * @return $this
     */
    public function disabledOptions($val)
    {
        $this->disabledOptions = $val;
        return $this;
    }

    /**
     * 快捷设置: 传入已嵌套的数据
     *
     * @param array|Collection|\IteratorAggregate $options
     * @return $this
     */
    public function options($options)
    {
        if ($options instanceof Collection || $options instanceof \IteratorAggregate) {
            return $this->optionsData($options);
        }
        $this->options = $options;
        return $this;
    }

    /**
     * 从扁平列表构建嵌套树
     *
     * 输出格式: [{ value, name, disabled, children: [{...}] }]
     *
     * @param array|Collection|\IteratorAggregate $treeData
     * @param string $textField  文本字段, 支持 {field} 和 {a.b} 占位
     * @param string $idField    主键字段
     * @param string $pidField   父级字段
     * @param string|false $rootText 根节点文本, false 不加根
     * @param int|string $rootId 根节点 id
     *
     * @return $this
     */
    public function optionsData($treeData, $textField = '', $idField = 'id', $pidField = 'parent_id', $rootText = '全部', $rootId = 0)
    {
        if ($rootText === '全部') {
            $rootText = __blang('builder_left_tree_text_all') . $this->getlabel();
        }

        // 先收集所有节点到扁平数组
        $flat = [];

        preg_match_all('/\{([\w\.]+)\}/', $textField, $matches);
        $needReplace = isset($matches[1]) && count($matches[1]) > 0;

        foreach ($treeData as $li) {
            if (empty($idField)) {
                $idField = $li->getPk();
            }
        if (empty($textField)) {
            $textField = isset($li['name']) ? 'name' : (isset($li['text']) ? 'text' : 'title');
        }

            $pid = $li[$pidField] ?? ($li['pid'] ?? 0);
            $value = $li[$idField];

            if ($needReplace) {
                $name = $this->resolveText($textField, $matches[1], $li);
            } else {
                $name = $li[$textField] ?? '-';
            }

            $flat[] = [
                'value' => $value,
                'name' => $name,
                '_pid' => $pid,
                'children' => [],
            ];
        }

        // 构建嵌套树; rootText 非空时包一层根节点 (与旧库一致, 旧库为 pId:-1 的根)
        $roots = $this->buildNested($flat, $rootId);

        if ($rootText) {
            $this->options = [
                [
                    'value' => $rootId,
                    'name' => $rootText,
                    'children' => $roots,
                ]
            ];
        } else {
            $this->options = $roots;
        }

        return $this;
    }

    /**
     * 递归构建嵌套 children
     *
     * @param array $flat
     * @param int|string $pid
     * @return array
     */
    protected function buildNested(array $flat, $pid)
    {
        $roots = [];
        foreach ($flat as $node) {
            if ((string)$node['_pid'] === (string)$pid) {
                $child = $node;
                unset($child['_pid']);
                $child['children'] = $this->buildNested($flat, $node['value']);
                $roots[] = $child;
            }
        }
        return $roots;
    }

    /**
     * 占位符替换
     *
     * @param string $textField
     * @param array $placeholders
     * @param array|\ArrayAccess $li
     * @return string
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
     * x-tree 元素配置：jstree 数据与行为开关（checked/disabled 已在
     * toJsTreeData 里烘焙成节点 state；expandAll 亦然）
     *
     * @return string
     */
    protected function elementCfg()
    {
        $jsTreeData = $this->toJsTreeData($this->options ?: [], $this->checked ?: [], $this->disabledOptions ?: []);

        $cfg = [
            'data' => $jsTreeData,
            'multiple' => $this->multiple,
            'cascade' => $this->multiple ? !$this->noCascaded : false,
            'enableCheck' => $this->enableCheck,
            'minHeight' => $this->minHeight,
        ];

        return htmlspecialchars(json_encode($cfg, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 模板变量：x-tree cfg配置
     *
     * @return array
     */
    public function customVars()
    {
        return [
            'cfg' => $this->elementCfg(),
        ];
    }

    /**
     * 渲染前处理：计算选中与禁用节点
     *
     * @return $this
     */
    public function beforRender()
    {
        // 计算 checked (值 → id 映射)
        if (!($this->value === '' || $this->value === null || $this->value === [])) {
            $this->checked = is_array($this->value) ? $this->value : explode(',', $this->value);
        } else if (!($this->default === '' || $this->default === null || $this->default === [])) {
            $this->checked = is_array($this->default) ? $this->default : explode(',', $this->default);
        }

        if ($this->disabledOptions && !is_array($this->disabledOptions)) {
            $this->disabledOptions = explode(',', $this->disabledOptions);
        }

        return parent::beforRender();
    }

    /**
     * 递归转换为 jstree 数据格式，并标记 checked/disabled
     *
     * @param array $options
     * @param array $checked
     * @param array $disabled
     * @return array
     */
    protected function toJsTreeData($options, $checked, $disabled)
    {
        $result = [];
        foreach ($options as $key => $opt) {
            if (is_array($opt)) {
                $id = isset($opt['value']) ? $opt['value'] : (string)$key;
                $node = [
                    'id' => $id,
                    'text' => $opt['name'] ?? $opt['text'] ?? $id,
                    'state' => [
                        'opened' => $this->expandAll,
                        'checked' => in_array($id, $checked),
                        'disabled' => in_array($id, $disabled),
                    ],
                ];
                if (!empty($opt['children'])) {
                    $node['children'] = $this->toJsTreeData($opt['children'], $checked, $disabled);
                } else {
                    // 6.194 官方主题观感：叶子用文件图标、父级用文件夹图标
                    // （jstree 把字符串 icon 当作 themeicon 附加类，jstree-file/jstree-folder
                    // 的雪碧图官方主题自带；对 treeselectjs 消费方是多余键，无影响）
                    $node['icon'] = 'jstree-file';
                }
                $result[] = $node;
            } else {
                $id = (string)$key;
                $result[] = [
                    'id' => $id,
                    'text' => (string)$opt,
                    'icon' => 'jstree-file',
                    'state' => [
                        'opened' => $this->expandAll,
                        'checked' => in_array($id, $checked),
                        'disabled' => in_array($id, $disabled),
                    ],
                ];
            }
        }
        return $result;
    }

    /**
     * 渲染为HTML
     *
     * @return mixed
     */
    public function render()
    {
        $vars = $this->commonVars();
        $vars['dataSelected'] = implode(',', $this->checked ?: []);
        $viewshow = $this->getViewInstance();
        return $viewshow->assign($vars)->getContent();
    }
}