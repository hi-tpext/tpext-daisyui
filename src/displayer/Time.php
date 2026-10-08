<?php

namespace tpext\builder\displayer;

/**
 * Time时间选择组件（flatpickr纯时间模式）
 */
class Time extends DateTime
{
    protected $format = 'HH:mm:ss';

    protected $timespan = '';

    protected $flatpickrOptions = [
        // 6.169 留空 = 跟随项目语言（builder_js_locale()），显式非空值优先
        'locale' => '',
        'dateFormat' => 'H:i:S',
        'enableTime' => true,
        'enableSeconds' => true,
        'time_24hr' => true,
        'noCalendar' => true,
        // 6.123 允许直接在输入框键入时间，不必非在面板里点选
        'allowInput' => true,
    ];


    /**
     * 设置显示格式
     * HH:mm:ss
     * @param string $val
     * @return $this
     */
    public function format($val)
    {
        $this->format = $val;
        return $this;
    }

    /**
     * 设置时间戳自动转换的时间格式
     *
     * @param string $val
     * @return $this
     */
    public function timespan($val = 'H:i:s')
    {
        $this->timespan = $val;
        return $this;
    }
}
