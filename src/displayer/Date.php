<?php

namespace tpext\builder\displayer;

/**
 * Date日期选择组件（flatpickr）
 */
class Date extends DateTime
{
    protected $format = 'YYYY-MM-DD';


    protected $timespan = 'Y-m-d';
    protected $flatpickrOptions = [
        // 6.169 留空 = 跟随项目语言（builder_js_locale()），显式非空值优先
        'locale' => '',
        'dateFormat' => 'Y-m-d',
        'enableTime' => false,
        // 6.123 允许直接在输入框键入日期，不必非在面板里点选
        'allowInput' => true,
    ];


    /**
     * 设置显示格式
     * @param string $val YYYY-MM-DD
     * @return $this
     */
    public function format($val)
    {
        $this->format = $val;
        return $this;
    }

    /**
     * 设置时间戳自动转换的日期格式
     *
     * @param string $val
     * @return $this
     */
    public function timespan($val = 'Y-m-d')
    {
        $this->timespan = $val;
        return $this;
    }
}
