<?php

namespace tpext\builder\traits\actions;

/**
 * 列表/list，添加/add，编辑/edit，删除/delete
 */

trait HasIAED
{
    use HasBase;
    use HasIndex;
    use HasAdd;
    use HasEdit;
    use HasDelete;
}
