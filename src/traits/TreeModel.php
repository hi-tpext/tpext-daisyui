<?php

namespace tpext\builder\traits;

trait TreeModel
{
    /**
     * 默认查询条件
     *
     * @var array
     */
    protected $treeScope = []; //如 [['enable', 'eq', 1]]

    /**
     * 根一级的id
     *
     * @var integer
     */
    protected $treeRootId = 0;

    /**
     * 树 Text 字段 如 'name'
     *
     * @var string
     */
    protected $treeTextField = 'name';

    /**
     * 树 id 字段
     *
     * @var string
     */
    protected $treeIdField = 'id';

    /**
     * 树 上级id字段 如 parend_id pid
     *
     * @var string
     */
    protected $treeParentIdField = 'parent_id';

    /**
     * 排序字段　如 sort
     *
     * @var string
     */
    protected $treeSortField = '';

    /**
     * 多维结构数据
     *
     * @var array
     */
    protected $treeData = [];

    /**
     * 一维结构数据
     *
     * @var array
     */
    protected $lineData = [];

    /**
     * 一维结构key:value数据
     *
     * @var array
     */
    protected $optionsData = [];

    protected $lineType = 0;

    protected $except = [];

    protected $allTreeData;

    protected $asTreeList = true;

    protected $treeCacheKey = ''; //避免和 thinkorm-model中的属性[cacheKey]冲突

    /**
     * 树数据缓存时间（秒），false不缓存；按需调整时间，并自行处理修改后清除缓存
     *
     * @var int|boolean
     */
    protected $chacheTime = false; //按需调整时间，并自行处理修改后清除缓存

    /**
     * 获取是否显示为树行
     *
     * @return boolean
     */
    public function asTreeList()
    {
        return $this->asTreeList;
    }

    /**
     * 获取树数据缓存键
     *
     * @return string
     */
    public function getTreeCacheKey()
    {
        return $this->treeCacheKey ?: 'tree_data_' . $this->getName();
    }

    /**
     * 设置是否显示为树行
     *
     * @param boolean $asTreeList
     * @return $this;
     */
    public function setAsTreeList($asTreeList = true)
    {
        $this->asTreeList = $asTreeList;

        return $this;
    }

    /**
     * 初始化
     *
     * @return void
     */
    protected function treeInit()
    {
        //如
        // $this->treeScope = [['enable', 'eq', 1]];
        // $this->treeTextField = 'title';
        // $this->treeIdField = 'id';
        // $this->treeParentIdField = 'pid';
        // $this->treeSortField ='sort';
    }

    /**
     * 重新初始化参数
     *
     * @param array $initData
     * @return $this
     */
    public function reInit($initData)
    {
        foreach ($initData as $key => $value) {
            $this->setTreeOption($key, $value);
        }

        return $this;
    }

    /**
     * 判断这个$key 是不是我的成员属性，如果是，则设置
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setTreeOption($key, $value)
    {
        //得到所有的成员属性
        $keys = array_keys(get_class_vars(__CLASS__));

        if (in_array($key, $keys)) {
            $this->$key = $value;
        }

        return $this;
    }

    /**
     * 获取树配置项
     *
     * @param string $key
     * @return array|string
     */
    public function getTreeOption($key = '')
    {
        return $this->$key ?? '';
    }

    /**
     * 获取1维结构数据 text当&nbsp;(适合放列表页面)
     *
     * @param array $except
     * @return array
     */
    public function getLineData($except = [])
    {
        $this->treeInit();
        $this->lineType = 1;
        $this->except = is_array($except) ? $except : [$except];

        if (!empty($this->lineData)) {
            return $this->lineData;
        }

        $this->lineData = [];
        $this->builderData();

        return $this->lineData;
    }

    /**
     * 获取1维结构数据 (适合放列select)
     *
     * @param array $except
     * @return array
     */
    public function getOptionsData($except = [])
    {
        $this->treeInit();
        $this->lineType = 2;
        $this->except = is_array($except) ? $except : [$except];

        if (!empty($this->optionsData)) {
            return $this->optionsData;
        }

        if (empty($this->lineData)) {
            $this->lineData = [];
            $this->builderData();
        }

        $this->optionsData = [];
        foreach ($this->lineData as $d) {
            $this->optionsData[$d['__id__']] = $d['__text__'];
        }

        return $this->optionsData;
    }

    /**
     * 获取多维结构数据
     *
     * @param array $except
     * @return array
     */
    public function getTreeData($except = [])
    {
        $this->treeInit();
        $this->lineType = 0;
        $this->except = is_array($except) ? $except : [$except];

        if (!empty($this->treeData)) {
            return $this->treeData;
        }

        $this->treeData = [];
        $this->builderData();

        return $this->treeData;
    }

    /**
     * 获取全部树数据（按treeScope条件查询）
     *
     * @return \think\Collection
     */
    public function getAllData()
    {
        $this->treeInit();

        if ($this->chacheTime !== false) {
            $this->allTreeData = $this->where($this->treeScope)->order($this->treeSortField)->cache($this->getTreeCacheKey(), $this->chacheTime)->select();
        } else {
            $this->allTreeData = $this->where($this->treeScope)->order($this->treeSortField)->select();
        }

        return $this->allTreeData;
    }

    /**
     * 构建树形/一维结构数据
     *
     * @return void
     */
    protected function builderData()
    {
        $this->getAllData();

        $roots = [];

        foreach ($this->allTreeData as $k => $d) {

            if ('' . $d[$this->treeParentIdField] !== '' . $this->treeRootId) {
                continue;
            }

            if (!empty($this->except) && in_array($d[$this->treeIdField], $this->except)) {
                continue;
            }

            $roots[] = $d;

            unset($this->allTreeData[$k]);
        }

        unset($d);

        $rootTotal = count($roots);

        foreach ($roots as $ri => $d) {

            $this->treeData[] = $d;
            $this->lineData[] = $d;

            $d['__deep__'] = 0;
            $d['__id__'] = $d[$this->treeIdField];
            // 6.278 根级也带 __plain__（Menu 表单父级下拉遍历全部行取该键）；6.280 根级回退为无前缀
            $d['__plain__'] = $d[$this->treeTextField];
            // 6.281 根级是否还有后续兄弟，决定其子孙的末枝用 └ 还是 ├（用户：L形只在父级后无兄弟时用）
            // 6.406 根级同 tree 命令一样带 ├/└ 标记（推翻 6.405 的 tline-stub 下探线方案）：
            // 有后续兄弟的根级 ├（竖线贯行高，向下与其子级的 │ guide 列、再向下与下一根级 ├ 对接），
            // 末根级 └（竖线只上半段）；子级前缀槽从根级列起步——├ 根级给 │ guide 列（兄弟子树贯通），
            // └ 根级给空白 spacer 列（该列无后续，不画线）
            $rootHasNext = $ri < $rootTotal - 1;
            if ($this->lineType == 1) {
                $elbow = $rootHasNext ? '<i class="tline-elbow"></i>' : '<i class="tline-elbow tline-elbow-last"></i>';
                $d['__text__'] = '<span class="tline">' . $elbow . $d[$this->treeTextField] . '</span>';
            } else {
                $d['__text__'] = $d[$this->treeTextField];
            }
            $childPrefix = $this->lineType == 1
                ? ($rootHasNext ? '<i class="tline-guide"></i>' : '<i class="tline-spacer"></i>')
                : '';
            $d['__children__'] = $this->getChildrenData($d[$this->treeIdField], 1, $rootHasNext, $childPrefix);
        }
    }

    /**
     * 递归获取指定父级的子级数据
     *
     * @param integer $pid
     * @param integer $deep
     * @param boolean $parentHasNext 父级后面是否还有同级兄弟（6.281：末枝 └ 只在父级无后续兄弟时用）
     * @param string  $prefixHtml    本级行首前缀槽 HTML（6.406：每个 ├ 祖先占一个 │ guide 槽、
     *                               └ 祖先占一个空白 spacer 槽，自己接在末尾）；仅 lineType=1 使用
     * @return array
     */
    protected function getChildrenData($pid, $deep = 1, $parentHasNext = false, $prefixHtml = '')
    {

        $data = [];
        $deep += 1;

        foreach ($this->allTreeData as $k => $d) {

            if (!empty($this->except) && in_array($d[$this->treeIdField], $this->except)) {
                continue;
            }

            if ('' . $d[$this->treeParentIdField] === '' . $pid) {

                $data[] = $d;

                unset($this->allTreeData[$k]);
            }
        }

        $children = [];

        $total = count($data);

        foreach ($data as $i => $d) {
            $d['__deep__'] = $deep;
            $d['__id__'] = $d[$this->treeIdField];

            if ($this->lineType) {
                $isLast= false;
                if ($this->lineType == 1) {
                    // 6.278 表格树形 HTML 化；6.281 末枝判定（用户：标签管理 用 T 形 ├ 而非 L 形 └，
                    // 因为父级「产品管理」后面还有同级「商城订单」——└ 只在父级后面没有同级兄弟时用）：
                    // 本行是末子项 且 父级无后续兄弟 → └（elbow-last，竖线只上半段），否则 ├。
                    // 6.406 前缀槽由根级/上层递归传入（$prefixHtml：├ 祖先=│ guide 槽、└ 祖先=空白
                    // spacer 槽），不再按 __deep__ 推算——└ 祖先列不画线，兄弟子树沿 ├ 列贯通。
                    // __plain__ 是 select/导出等非 HTML 场景的纯文本标记
                    $isLast = ($i === $total - 1);
                    $useL = ($isLast && !$parentHasNext);
                    $elbow = $useL ? '<i class="tline-elbow tline-elbow-last"></i>' : '<i class="tline-elbow"></i>';
                    $d['__text__'] = '<span class="tline">' . $prefixHtml . $elbow . $d[$this->treeTextField] . '</span>';
                    $d['__plain__'] = str_repeat('──', ($deep - 1)) . ($useL ? '└─' : '├─') . $d[$this->treeTextField];
                } else {
                    $d['__text__'] = str_repeat('──', ($deep - 1)) . '├─' . $d[$this->treeTextField];
                }

                $this->lineData[] = $d;
                // 递归传给子级：本行是否有后续兄弟（本行是末子项=false，其末子项才可能用 └）；
                // 6.406 前缀槽追加本行列——├ 追加 │ guide（分支继续下探），└ 追加空白 spacer
                $childSlot = $useL ? '<i class="tline-spacer"></i>' : '<i class="tline-guide"></i>';
                $this->getChildrenData($d[$this->treeIdField], $deep, !$isLast, $prefixHtml . $childSlot);
                continue;
            }

            $d['__text__'] = $d[$this->treeTextField];
            $d['__children__'] = $this->getChildrenData($d[$this->treeIdField], $deep);

            $children[] = $d;
        }

        return $children;
    }
}
