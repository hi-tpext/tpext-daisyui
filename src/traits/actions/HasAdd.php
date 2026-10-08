<?php

namespace tpext\builder\traits\actions;

define('FORM_ADD', 0);
/**
 * 添加
 */

trait HasAdd
{
    /**
     * 添加页（GET显示表单，POST保存数据）
     *
     * @return mixed
     */
    public function add()
    {
        if (request()->isGet()) {

            $builder = $this->builder($this->pageTitle, $this->addText ?: __blang('builder_page_add_text'), 'add');
            $form = $builder->form();
            $data = [];
            $this->form = $form;
            $this->isEdit = 0;
            $this->buildForm($this->isEdit, $data);
            $form->fill($data);
            $form->method('post');

            return $builder->render();
        }

        $this->checkToken();

        return $this->save();
    }
}
