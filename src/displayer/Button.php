<?php

namespace tpext\builder\displayer;

/**
 * Button按钮组件
 */
class Button extends Field
{
    protected $view = 'button';

    protected $isInput = false;

    protected $type = 'button';

    protected $bottom = false;

    protected $size = [0, '12 col-lg-12 col-sm-12 col-xs-12'];

    protected $showLabel = false;

    protected $loading = false;

    /**
     * 创建按钮：submit/reset名称自动映射类型
     *
     * @param string $fieldType
     * @return $this
     */
    public function created($fieldType = '')
    {
        parent::created($fieldType);

        if (in_array($this->name, ['submit', 'reset'])) {
            $this->type = $this->name;
        }
        
        return $this;
    }

    /**
     * 设置按钮类型（button/submit/reset）
     *
     * @param string $val
     * @return $this
     */
    public function type($val)
    {
        $this->type = $val;
        return $this;
    }

    /**
     * 设置是否显示加载中状态
     *
     * @param boolean $val
     * @return $this
     */
    public function loading($val = true)
    {
        $this->loading = $val;
        return $this;
    }

    /**
     * 渲染为HTML
     *
     * @return mixed
     */
    public function render()
    {
        if ($this->loading) {
            $this->class .= ' btn-loading';
        }

        // 旧 Bootstrap 3 类 → DaisyUI v4 兼容映射（6.393 起：包内源码已直接发出
        // 新 class（btn-xs/btn-sm/btn-secondary/btn-error），本表仅为用户控制器手传旧类兜底：
        //   btn-danger → btn-error（删除类按钮；builder-core.css 另有 .btn-danger 样式兜底）
        //   btn-default → btn-secondary（6.395：原「剥离回落基础 .btn」升级——无色按钮
        //     统一映射为次级色，与 Columns/Export 等既有 btn-secondary 一致）
        //   btn-xs → 不再改写 btn-sm：btn-xs 本就是 DaisyUI 原生紧凑档
        //     （工具条/搜索 24px 档），早期运行时改写会让 CSS 作用域规则
        //     必须两头兼顾，6.393 统一后各层直接发出声明档位
        $bsToDaisy = [
            'btn-danger'  => 'btn-error',
            'btn-default' => 'btn-secondary',
        ];
        foreach ($bsToDaisy as $bs => $daisy) {
            if (strpos($this->class, $bs) !== false) {
                $this->class = trim(str_replace($bs, $daisy, $this->class));
            }
        }

        $vars = $this->commonVars();

        $vars = array_merge($vars, [
            'type' => $this->type,
        ]);

        $viewshow = $this->getViewInstance();

        return $viewshow->assign($vars)->getContent();
    }
}
