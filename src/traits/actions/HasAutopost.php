<?php

namespace tpext\builder\traits\actions;

/**
 * 字段编辑
 */

trait HasAutopost
{
    /**
     * 字段行内编辑保存入口
     *
     * @return mixed
     */
    public function autopost()
    {
        return $this->_autopost();
    }

    /**
     * 执行字段行内编辑保存（不触发模型事件）
     *
     * @return void
     */
    protected function _autopost()
    {
        $this->checkToken();

        $id = input('post.id/d', '');
        $name = input('post.name', '');
        $value = input('post.value', '');

        if (empty($id) || empty($name)) {
            $this->error(__blang('builder_parameter_error'));
        }

        if (!empty($this->postAllowFields) && !in_array($name, $this->postAllowFields)) {
            $this->error(__blang('builder_field_not_allowed'));
        }

        //单独修改一个字段，好多字段是未设置的，处理模型事件容易出错。不触发模型事件，不触发[update_time]修改
        $res = $this->dataModel->where($this->getPk(), $id)->update([$name => $value]);

        if ($res) {
            $this->success(__blang('builder_update_succeeded'));
        } else {
            $this->error(__blang('builder_update_failed_or_no_changes'));
        }
    }
}
