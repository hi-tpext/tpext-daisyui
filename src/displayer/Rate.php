<?php

namespace tpext\builder\displayer;

/**
 * Rate百分比输入组件（0-100，带%后缀）
 */
class Rate extends Text
{
    protected $rules = 'number|regex:^([1-9]?\d|100)$';

    protected $after = '%';

    protected $size = [2, 3];
}
