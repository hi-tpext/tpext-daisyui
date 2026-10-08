<?php

namespace tpext\builder\displayer;

/**
 * DateTimeRange日期时间范围选择组件
 */
class DateTimeRange extends DateRange
{
    protected $size = [2, 4];

    protected $format = 'YYYY-MM-DD HH:mm:ss';

    protected $timespan = 'Y-m-d H:i:s';

    /**
     * Flatpickr range 模式配置（覆盖 DateRange 基类的日期-only 配置）
     * 启用时间选择、24小时制、精确到秒（6.188：与 $timespan 'Y-m-d H:i:s'、
     * DB datetime 格式及单值 DateTime/TimeRange 对齐——flatpickr 的 'S' 是
     * 补零两位秒，'s' 是不补零秒，勿混用）
     */
    protected $flatpickrRangeOptions = [
        'dateFormat' => 'Y-m-d H:i:S',
        'enableTime' => true,
        'enableSeconds' => true,
        'time_24hr' => true,
    ];

    /**
     * startDate()/endDate() 的暂存（初始选中区间的起/止），getVars 时组装进 default
     */
    protected $defaultStart = '';
    protected $defaultEnd = '';

    /**
     * 限制可选的最早日期时间（6.303）。与基类 minDate() 同写 flatpickr minDate 键，
     * 差异：本方法按时间粒度归一化——传 10 位 'Y-m-d' 自动补 ' 00:00:00'，
     * 整天粒度可用；后调用者覆盖先调用者（与 minDate/maxDate 共用键）
     *
     * @param string $val 如 '2026-09-15'、'2026-09-15 08:00:00'
     * @return $this
     */
    public function minDatetime($val)
    {
        if (is_string($val) && strlen($val) === 10) {
            $val .= ' 00:00:00';
        }
        $this->flatpickrRangeOptions['minDate'] = $val;
        return $this;
    }

    /**
     * 限制可选的最晚日期时间（6.303）。与基类 maxDate() 同写 flatpickr maxDate 键，
     * 差异：传 10 位 'Y-m-d' 自动补 ' 23:59:59'，整天粒度可用；
     * 后调用者覆盖先调用者（与 minDate/maxDate 共用键）
     *
     * @param string $val 如 '2026-09-18'、'2026-09-18 20:00:00'
     * @return $this
     */
    public function maxDatetime($val)
    {
        if (is_string($val) && strlen($val) === 10) {
            $val .= ' 23:59:59';
        }
        $this->flatpickrRangeOptions['maxDate'] = $val;
        return $this;
    }

    /**
     * 6.302 语义修正：旧库 startDate/endDate 经 bootstrap-daterangepicker 配置原样
     * 透传，该插件里它们是「初始选中的起止日期」（限制范围是 minDate/maxDate）。
     * 故新实现不走 6.300 曾误接的 minDate/maxDate，改为组装 default（选中值），
     * 经载体值 → defaultDate 链路成为日历预选区间。限制可选范围请用基类
     * minDate()/maxDate()。传 10 位 'Y-m-d' 自动补 ' 00:00:00'
     *
     * @param string $val 如 '2026-09-15'、'2026-09-15 08:00:00'
     * @return $this
     */
    public function startDate($val)
    {
        if (is_string($val) && strlen($val) === 10) {
            $val .= ' 00:00:00';
        }
        $this->defaultStart = $val;
        return $this;
    }

    /**
     * 设置结束日期（6.302 起为「初始选中区间」的止点，见 startDate 说明）。
     * 传 10 位 'Y-m-d' 自动补 ' 23:59:59'
     *
     * @param string $val 如 '2026-09-18'、'2026-09-18 20:00:00'
     * @return $this
     */
    public function endDate($val)
    {
        if (is_string($val) && strlen($val) === 10) {
            $val .= ' 23:59:59';
        }
        $this->defaultEnd = $val;
        return $this;
    }

    /**
     * startDate/endDate 都设置且未显式给 value/default 时，按 separator 组装成
     * 初始选中区间（载体值经 x-daterange 拆分喂 flatpickr defaultDate）。
     * 注意 DateRange/Text 的渲染链没有 getVars，commonVars 直接调 renderValue——
     * 6.302 曾误覆写 getVars 成死代码，必须覆写这里
     *
     * @return string|int|float|null
     */
    public function renderValue()
    {
        if (
            ($this->value === '' || $this->value === null)
            && ($this->default === '' || $this->default === null || $this->default === [])
            && $this->defaultStart !== '' && $this->defaultEnd !== ''
        ) {
            $this->value = $this->defaultStart . $this->separator . $this->defaultEnd;
        }

        return parent::renderValue();
    }

    // customVars 继承 DateRange（rangePlaceholder + rangeIcon + cfg）
}
