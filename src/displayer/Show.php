<?php

namespace tpext\builder\displayer;

/**
 * Show文本展示组件（支持截断展开）
 */
class Show extends Field
{
    protected $view = 'show';

    protected $isInput = false;
    
    protected $sublen = 0;

    protected $more = '...';

    protected $subed = false;

    protected $full = '';

    protected $inline = false;

    /**
     * 设置截断长度与省略符
     *
     * @param integer $len
     * @param string $more
     * @return $this
     */
    public function cut($len = 0, $more = '...')
    {
        $this->sublen = $len;
        $this->more = $more;

        return $this;
    }

    /**
     * 设置是否行内显示
     *
     * @param boolean $val
     * @return $this
     */
    public function inline($val = true)
    {
        $this->inline = $val;
        return $this;
    }

    /**
     * 获取渲染用值：超长截断并保存全文供展开
     *
     * @return string
     */
    public function renderValue()
    {
        $value = parent::renderValue();

        $this->full = '';
        $this->subed = false;
        if ($this->sublen > 0 && $value) {
            if (mb_strlen($value) > $this->sublen) {
                $this->full = $value;
                $value = mb_substr($value, 0, $this->sublen);
                $this->subed = true;
            }
        }

        return $value;
    }

    /**
     * 模板变量：截断状态与全文
     *
     * @return array
     */
    public function customVars()
    {
        return [
            'subed' => $this->subed,
            'more' => $this->more,
            'full' => $this->full,
            'inline' => $this->inline,
        ];
    }

    /**
     * 展示组件无只读概念，占位保持兼容
     *
     * @param boolean $val
     * @return $this
     */
    public function readonly($val = false)
    {
        return $this;
    }

    /**
     * 展示组件无禁用概念，占位保持兼容
     *
     * @param boolean $val
     * @return $this
     */
    public function disabled($val = false)
    {
        return $this;
    }
}
