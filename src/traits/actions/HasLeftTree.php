<?php

namespace tpext\builder\traits\actions;

/**
 * 左侧树形结构
 * 已适配 \tpext\builder\traits\TreeModel;
 */
trait HasLeftTree
{
    /**
     * 默认查询条件
     *
     * @var array
     */
    protected $treeScope = []; //如 [['enable', 'eq', 1]]

    /**
     * 树 Text 字段 如 'name'
     *
     * @var string
     */
    protected $treeTextField = '';

    /**
     * 树 id 字段
     *
     * @var string
     */
    protected $treeIdField = '';

    /**
     * 树 上级id字段 如 parend_id pid
     *
     * @var string
     */
    protected $treeParentIdField = '';

    /**
     * 排序字段　如 sort
     *
     * @var string
     */
    protected $treeSortField = '';

    /**
     * 树字段
     *
     * @var string
     */
    protected $treeKey = '';

    /**
     * "全部"文字
     *
     * @var string
     */
    protected $treeRootText = ''; //全部

    /**
     * 展开所有节点
     *
     * @var boolean
     */
    protected $treeExpandAll = true;

    /**
     * 树数据模型，如果$treeModel是\tpext\builder\traits\TreeModel实例，上面的大多数参数可以不用配置
     * 
     * @var \think\Model
     */
    protected $treeModel = null;

    /**
     * 初始化树，返回树数据
     *
     * @return array|mixed
     */
    protected function initLeftTree()
    {
        $data = [];
        if (method_exists($this->treeModel, 'getAllData')) { //(是\tpext\builder\traits\TreeModel实例)
            $options = [];
            if ($this->treeScope) {
                $options['treeScope'] = $this->treeScope;
            }
            if ($this->treeSortField) {
                $options['treeSortField'] = $this->treeSortField;
            }
            $this->treeModel->reInit($options);

            $data = $this->treeModel->getAllData();
        } else {
            $data = $this->treeModel->where($this->treeScope)->order($this->treeSortField)->select();
        }

        if (method_exists($this->treeModel, 'getTreeOption')) { //读取树模型里面参数(是\tpext\builder\traits\TreeModel实例)
            if (empty($this->treeTextField)) {
                $this->treeTextField = $this->treeModel->getTreeOption('treeTextField');
            }
            if (empty($this->treeIdField)) {
                $this->treeIdField = $this->treeModel->getTreeOption('treeIdField');
            }
            if (empty($this->treeParentIdField)) {
                $this->treeParentIdField = $this->treeModel->getTreeOption('treeParentIdField');
            }
            if (empty($this->treeSortField) && $this->treeSortField !== false) {
                $this->treeSortField = $this->treeModel->getTreeOption('treeSortField');
            }
        }

        return $data;
    }
}
