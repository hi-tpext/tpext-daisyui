<?php

namespace tpext\builder\traits;

/**
 * destroy() 幂等标记（6.261，配合 inface\ReleaseAble 使用）
 *
 * 同一组件可能被多条归属路径触达（如 Tab 的 FieldsContent 同时挂在
 * Form rows 的 Fields 里），destroy() 因此可能被重复调用。实现方
 * destroy() 必须遵守：
 * 1. 入口检查 `$this->__destroyed__`，为 true 直接 return；
 * 2. 函数体末尾置 true（覆写类如 Select/Fields/Items 在
 *    `parent::destroy()` 之后置位，保证父类清理先执行）。
 *
 * 数组属性销毁后仍复位为 []（6.260 规则不变，负责释放内存），
 * 标记只是 O(1) 的短路守卫。实例为一次性对象（请求级），标记不会
 * 跨复用：Builder::destroyInstance() 销毁后置空单例。
 */
trait HasDestroyOnce
{
    /**
     * 是否已销毁
     * @var bool
     */
    protected $__destroyed__ = false;
}
