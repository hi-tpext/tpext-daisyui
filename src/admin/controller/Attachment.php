<?php

namespace tpext\builder\admin\controller;

use think\Controller;
use think\facade\Session;
use tpext\builder\common\Module;
use tpext\builder\traits\actions\HasBase;
use tpext\builder\traits\actions\HasIndex;
use tpext\builder\traits\actions\HasAutopost;
use tpext\builder\common\model\Attachment as AttachmentModel;

/**
 * 附件管理控制器：列表、搜索、文件选择器
 * @title 文件管理
 */
class Attachment extends Controller
{
    use HasBase;
    use HasIndex;
    use HasAutopost;

    /**
     * 附件模型实例
     *
     * @var AttachmentModel
     */
    protected $dataModel;

    protected function initialize()
    {
        $this->dataModel = new AttachmentModel;

        $this->pageTitle = __blang('builder_attachment_manage');
        $this->postAllowFields = ['name'];
        $this->pagesize = 8;
    }

    /**
     * 根据搜索数据构建查询条件（非管理员只能看自己的附件）
     *
     * @return array
     */
    protected function filterWhere()
    {
        $searchData = request()->get();

        $where = [];

        $admin = Session::get('admin_user');

        if ($admin['role_id'] != 1) {
            $where[] = ['admin_id', '=', $admin['id']];
        }

        if (!empty($searchData['name'])) {
            $where[] = ['name', 'like', '%' . $searchData['name'] . '%'];
        }

        if (!empty($searchData['url'])) {
            $where[] = ['url', 'like', '%' . $searchData['url'] . '%'];
        }

        $ext = input('ext');

        if ($ext) {
            $where[] = ['suffix', 'in', explode(',', $ext)];
        }

        if (!empty($searchData['suffix'])) {
            $where[] = ['suffix', 'in', $searchData['suffix']];
        }

        return $where;
    }

    /**
     * 构建搜索
     *
     * @return void
     */
    protected function buildSearch()
    {
        $search = $this->search;

        $search->text('name', __blang('builder_attachment_name'), '6 col-xs-6')->size('4 col-xs-4', '8 col-xs-8')->maxlength(55);
        $search->text('url', __blang('builder_attachment_url'), '6 col-xs-6')->size('4 col-xs-4', '8 col-xs-8')->maxlength(200);

        $exts = [];
        $arr = [];

        $ext = input('ext');
        if ($ext) {
            $arr = explode(',', $ext);
        } else {
            $config = Module::getInstance()->getConfig();
            $arr = explode(',', $config['allow_suffix']);
        }

        foreach ($arr as $a) {
            $exts[$a] = $a;
        }

        $search->multipleSelect('suffix', __blang('builder_attachment_suffix'), '6 col-xs-6')->size('4 col-xs-4', '8 col-xs-8')->options($exts);
    }
    /**
     * 构建表格
     *
     * @return void
     */
    protected function buildTable(&$data = [], $isExporting = false)
    {
        $table = $this->table;

        $choose = input('choose', 0);
        $limit = input('limit', 1);

        $table->show('id', 'ID');
        $table->text('name', __blang('builder_attachment_name'))->autoPost();
        $table->file('file',  __blang('builder_attachment_file'))->thumbSize(50, 50);
        if (!$choose) {
            $table->show('mime', __blang('builder_attachment_mime'));
            $table->show('size', __blang('builder_attachment_size'))->to('{val}MB');
            $table->show('suffix', __blang('builder_attachment_suffix'))->getWrapper()->addStyle('width:80px');
            $table->show('storage', __blang('builder_attachment_storage'));
        }

        $table->raw('url', __blang('builder_attachment_url'))->to('<a href="{val}" target="_blank">{val}</a>');
        $table->show('create_time', __blang('builder_attachment_create_time'))->getWrapper()->addStyle('width:160px');

        $table->getToolbar()
            ->btnRefresh()
            ->btnImport(url('uploadSuccess'), '', ['280px', '235px'], 0, __blang('builder_upload_file_button'), 'btn-pink', 'mdi-cloud-upload', 'title="' . __blang('builder_upload_nwe_file') . '"', '')
            ->btnToggleSearch();

        foreach ($data as &$d) {
            $d['file'] = $d['url'];
        }

        unset($d);

        if ($choose) {
            $id = input('id');
            if ($limit > 1) {
                // 普通按钮跑内联 onclick 即可——此前误用 btnOpenChecked('#')：openChecked
                // 机制会再挂一层委托（勾选后 window.open('#') 弹出新页面，6.140 修复）
                $table->getToolbar()
                    ->btnLink('javascript:;', __blang('builder_choose_multiple_files_button'), 'btn-success', 'mdi-note-plus-outline', 'onclick="chooseMultipleUrlsAndClose()"');
            } else {
                $table->useCheckbox(false);
            }
            $table->getActionbar()
                ->btnLink('choose', '#', __blang('builder_choose_file_button'), 'btn-success', 'mdi-note-plus-outline', 'onclick="chooseUrlAndClose(this)"');

            $script = <<<EOT

            var chooseUrlLimit = {$limit};

            // 原生 DOM 回写父页面输入框（页面不再加载 jQuery，禁用 parent.\$）
            function getChooserInput() {
                return parent.document.getElementById('{$id}');
            }

            function setChooserValue(v) {
                var input = getChooserInput();
                if (!input) return;
                input.value = v;
                input.dispatchEvent(new Event('change', {bubbles: true}));
            }

            function getChooserValue() {
                var input = getChooserInput();
                return input ? String(input.value || '').trim(',') : '';
            }

            window.chooseUrlAndClose = function(e) {
                var tr = e.closest ? e.closest('tr') : null;
                var a = tr ? tr.querySelector('.row-url-td a') : null;
                var url = a ? a.getAttribute('href') : '';
                if (!url) return;

                if (chooseUrlLimit > 1) {
                    var cur = getChooserValue();
                    setChooserValue(cur ? cur + ',' + url : url);
                } else {
                    setChooserValue(url);
                }

                window.closeChoose();
            }

            window.chooseMultipleUrlsAndClose = function() {
                var urls = getChooserValue() ? getChooserValue().split(',') : [];
                var over = false;
                document.querySelectorAll('input.table-row-checkbox:checked').forEach(function (cb) {
                    if (urls.length >= chooseUrlLimit) { over = true; return; }
                    var tr = cb.closest('tr');
                    var a = tr ? tr.querySelector('.row-url-td a') : null;
                    if (a && a.getAttribute('href')) { urls.push(a.getAttribute('href')); }
                });

                if (over) {
                    (parent.lightyear || lightyear).notify(__blang.builder_maximum_upload_files_num_is + chooseUrlLimit, 'danger');
                }

                if (!urls.length) {
                    lightyear.notify(__blang.builder_data_not_found);
                    return;
                }

                setChooserValue(urls.join(','));
                window.closeChoose();
            }

            window.closeChoose = function() {
                parent.layer.close(parent.layer.getFrameIndex(window.name));
            }
EOT;

            $this->builder()->addScript($script);
        } else {
            $table->useCheckbox(false);
            $table->useActionbar(false);
        }
    }

    /**
     * 上传成功提示页（并通知父页面刷新列表）
     *
     * @title 上传成功
     * @return mixed
     */
    public function uploadSuccess()
    {
        $builder = $this->builder(__blang('builder_file_uploading_succeeded'));

        $builder->addScript('parent.lightyear.notify("' . __blang('builder_file_uploading_succeeded') . '","success");parent.$(".search-refresh").trigger("click");parent.layer.close(parent.layer.getFrameIndex(window.name));'); //刷新列表页

        return $builder->render();
    }
}
