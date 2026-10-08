<?php

namespace tpext\builder\traits\actions;

/**
 * 禁用/启用
 */

trait HasEnable
{
    /**
     * 启用/禁用数据（支持批量ids）
     *
     * @return void
     */
    public function enable()
    {
        $state = input('state');

        $ids = input('post.ids', '');
        $ids = array_filter(explode(',', $ids), 'strlen');
        if (empty($ids)) {
            $this->error(__blang('builder_parameter_error'));
        }
        $res = 0;
        foreach ($ids as $id) {

            //单独修改一个字段，好多字段是未设置的，处理模型事件容易出错。不触发模型事件，不触发[update_time]修改
            if ($this->dataModel->where($this->getPk(), $id)->update([$this->enableField => $state])) {
                $res += 1;
            }
        }
        if ($res) {
            $this->success(__blang('builder_update_{:num}_records_succeeded', ['num' => $res]));
        } else {
            $this->error(__blang('builder_update_failed'));
        }
    }
}
