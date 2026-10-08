<?php

namespace tpext\builder\traits;

use tpext\builder\traits\actions;

trait HasBuilder
{
    //基础
    use actions\HasBase;
    //按需加载，避免暴露不必要的action

    //列表
    use actions\HasIndex;
    //添加/修改
    use actions\HasAdd;
    use actions\HasEdit;

    //查看
    use actions\HasView;

    //字段编辑
    use actions\HasAutopost;
    //禁用/启用
    use actions\HasEnable;
    //删除
    use actions\HasDelete;
}
