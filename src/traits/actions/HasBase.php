<?php

namespace tpext\builder\traits\actions;

use think\facade\Session;
use tpext\builder\common\Form;
use tpext\builder\common\Builder;

/**
 * 基础
 */

trait HasBase
{
    /**
     * 数据模型
     *
     * @var \think\Model|mixed
     */
    protected $dataModel;

    /**
     * 表单对象
     *
     * @var Form
     */
    protected $form;

    /**
     * 页面标题
     *
     * @var string
     */
    protected $pageTitle = 'Page';
    protected $addText = ''; //添加
    protected $editText = ''; //编辑
    protected $viewText = ''; //查看
    protected $enableField = 'enable';
    protected $pk = 'id';

    /**
     * 表单是否为编辑模式
     * 0:add,1:edit,2:view
     * @var boolean
     */
    protected $isEdit = false;

    /**
     * 构建表单（钩子，可在子类重写）
     *
     * @param boolean|int $isEdit 0:add,1:edit,2:view
     * @param array $data
     * @return void
     */
    protected function buildForm($isEdit, &$data = [])
    {
        $form = $this->form;
    }

    /**
     * 构建表格（钩子，可在子类重写）
     *
     * @param array $data
     * @param boolean $isExporting
     * @return void
     */
    protected function buildTable(&$data = [], $isExporting = false)
    {
        $table = $this->table;
    }

    /**
     * 构建搜索 范例
     *
     * @return void
     */
    protected function buildSearch()
    {
        $search = $this->search;
    }

    /**
     * 保存数据 范例
     *
     * @param integer $id
     * @return mixed
     */
    protected function save($id = 0)
    {
        $data = request()->post();

        return $this->doSave($data, $id);
    }

    /**
     * 执行保存数据
     *
     * @param array $data
     * @param integer $id
     * @return mixed
     */
    protected function doSave($data, $id = 0)
    {
        $res = 0;

        if ($id) {
            $exists = $this->dataModel->where([$this->getPk() => $id])->find();
            if ($exists) {
                $res = $exists->force()->save($data);
            }

        } else {
            $res = $this->dataModel->exists(false)->save($data);
        }

        if (!$res) {
            $this->error(__blang('builder_save_failed'));
        }

        return $this->builder()->layer()->closeRefresh(1, __blang('builder_save_succeeded'));
    }

    /**
     * 获取Builder实例
     *
     * @param string $title
     * @param string $desc
     * @param string $type index/add/edit/view
     * @return Builder
     */
    protected function builder($title = '', $desc = '', $type = '')
    {
        $builder = Builder::getInstance($title, $desc);

        $this->creating($builder, $type);

        return $builder;
    }

    /**
     * Builder创建时的自定义初始化（钩子，可在子类重写）
     *
     * @param Builder $builder
     * @param string $type index/add/edit/view
     * @return void
     */
    protected function creating($builder, $type = '')
    {
        //其他用户自定义初始化
    }

    /**
     * 获取主键字段名
     *
     * @return string
     */
    protected function getPk()
    {
        if (empty($this->pk)) {
            if ($this->dataModel) {
                $this->pk = $this->dataModel->getPk();
                $this->pk = !empty($this->pk) && is_string($this->pk) ? $this->pk : 'id';
            } else {
                $this->pk = 'id';
            }
        }

        return $this->pk;
    }

    /**
     * 校验CSRF token，失败直接报错
     *
     * @return void
     */
    protected function checkToken()
    {
        $token = Session::get('_csrf_token_');

        if (empty($token) || $token != input('__token__')) {
            $this->error('token error');
        }
    }

    /**
     * 销毁Builder相关属性，重置为默认值
     *
     * @return void
     */
    protected function _destroyBuilder()
    {
        $this->form = null;
        $this->dataModel = null;
        $this->addText = '';
        $this->editText = '';
        $this->viewText = '';
        $this->enableField = 'enable';
        $this->pk = 'id';
        $this->isEdit = false;

        if (isset($this->table)) {
            $this->table = null;
        }
        if (isset($this->search)) {
            $this->search = null;
        }
        if (isset($this->indexText)) {
            $this->indexText = '';
            $this->pagesize = 14;
            $this->sortOrder = 'id desc';
            $this->useSearch = true;
            $this->isExporting = false;

            $this->indexWith = [];
            $this->indexFieldsOnly = '*';
            $this->indexFieldsExcept = '';
            $this->postAllowFields = [];
            $this->delNotAllowed = [];
        }
        if (isset($this->treeModel) && isset($this->treeIdField)) {
            $this->treeScope = [];
            $this->treeRootText = '';
            $this->treeTextField = '';
            $this->treeIdField = '';
            $this->treeParentIdField = '';
            $this->treeKey = '';
            $this->treeExpandAll = true;
            $this->treeModel = null;
        }
        if (isset($this->selectIdField) && isset($this->selectTextField)) {
            $this->selectScope = [];
            $this->selectSearch = '';
            $this->selectTextField = '';
            $this->selectIdField = '';
            $this->selectFields = '*';
            $this->selectOrder = '';
            $this->selectPagesize = 20;
            $this->selectWith = [];
        }
        if (isset($this->exportOnly) || isset($this->exportExcept)) {
            $this->exportOnly = [];
            $this->exportExcept = [];
            $this->exportOnlyChoosedColumns = null;
        }
    }
}
