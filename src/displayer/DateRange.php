<?php

namespace tpext\builder\displayer;

/**
 * DateRange日期范围选择组件（flatpickr range模式）
 */
class DateRange extends Text
{
    protected $view = 'daterange';
    protected $cssFamily = 'flatpickr'; // 6.181 按需加载

    protected $js = [
        '/assets/tpextdaisyui/js/vendors/flatpickr.min.js',
    ];

    protected $css = [];

    protected $size = [2, 4];

    protected $format = 'yyyy-mm-dd';

    protected $befor = '';

    protected $separator = ',';

    protected $timespan = 'Y-m-d';

    protected $jsOptions = [
        'weekStart' => 1,
        'autoclose' => false,
        // 6.169 留空 = 跟随项目语言（builder_js_locale()），显式非空值优先
        'locale' => ''
    ];

    /**
     * Flatpickr range 模式的配置数组。
     * 子类（如 DateTimeRange）只需覆盖此属性，即可变更日期格式/时间开关等。
     * 共享：locale、mode、onChange（同步 hidden input）由 dateRangeScript 统一注入
     */
    protected $flatpickrRangeOptions = [
        'dateFormat' => 'Y-m-d',
    ];

    /**
     * 6.308 快捷范围预设；6.318 起默认开启——null=内置默认预设（标签走 __blang）；
     * false=关闭（纯日历）；true=内置默认预设；array=自定义
     * （label => 'today-6d~today' token 串 或 [from, to] 具体日期）
     */
    protected $ranges = null;

    /**
     * Web Components 化（WEBCOMPONENTS_PLAN.md 批1）：
     * 初始化不再输出页面底部脚本文本，改由 x-daterange 元素（builder-elements.js）自动完成。
     * cfg = range 基础配置 + 子类 flatpickrRangeOptions 覆盖，ENT_QUOTES 转义进 cfg 属性。
     * 注意：hidden 载体同步（onChange 写回）由元素内部闭包完成，不再需要 getElementById。
     */
    protected function elementCfg()
    {
        $options = array_merge([
            // 6.156/6.169 与 loadLocale() 同一开关与解析：jsOptions['locale']
            // 既决定加载哪个 l10n 文件，也决定传给 x-daterange 的 cfg.locale；
            // 留空 = 跟随项目语言（builder_js_locale()），显式非空值优先
            'locale' => !empty($this->jsOptions['locale']) ? $this->jsOptions['locale'] : builder_js_locale(),
            'mode' => 'range',
            // 6.167 提交/回显契约：载体值 = start + separator + end；
            // 元素据此覆盖 flatpickr 实例的 l10n.rangeSeparator（zh 语言包默认「至」）
            'separator' => $this->separator,
        ], $this->flatpickrRangeOptions);

        // 6.308 快捷范围预设；6.318 起默认开启（false 显式关闭）
        if ($this->ranges !== false) {
            $ranges = $this->buildRanges();
            if ($ranges) {
                $options['ranges'] = $ranges;
            }
        }

        return htmlspecialchars(json_encode($options, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 6.156 flatpickr l10n 文件动态加载（语言开关 $jsOptions['locale']）：
     * 内建英文（en/default/空）不加载；只加载本包发行过的文件，防 404。
     * 须在 $js 收集前追加——页面级 $js 块先于 body 底部 common 栈（含
     * builder-elements.js）输出，保证先于 x-daterange 元素初始化执行。
     */
    protected function loadLocale()
    {
        $locale = !empty($this->jsOptions['locale']) ? $this->jsOptions['locale'] : builder_js_locale();

        if ($locale == '' || $locale == 'en' || $locale == 'default') {
            return;
        }

        if (is_file(__DIR__ . '/../../assets/js/vendors/flatpickr.' . $locale . '.js')) {
            $this->js[] = '/assets/tpextdaisyui/js/vendors/flatpickr.' . $locale . '.js';
        }
    }

    /**
     * 6.308 快捷范围预设（日历面板顶部一排快捷按钮，DateTimeRange 继承可用）
     * 日期 token：today、today±Nd（天）、today±Nm（月）、today±Ny（年），JS 端按点击时刻换算；
     * 也支持具体日期串 'YYYY-MM-DD'。from 恒取当日 00:00:00、to 恒取 23:59:59（纯日期格式下时间被格式丢弃）。
     * 6.318 起默认开启，ranges(false) 可关闭。
     *
     * @param bool|array $val true/null=内置默认预设（今天/近7天/近30天/近3月/近半年/近一年，标签走 __blang）
     *   false=关闭；array=自定义：['近一周' => 'today-6d~today', ...]（label => token 串，~ 分隔）
     *   或 ['某季度' => ['2026-01-01', '2026-03-31']]（label => [from, to] 具体日期）
     * @return $this
     */
    public function ranges($val = true)
    {
        $this->ranges = $val;
        return $this;
    }

    /**
     * 归一化快捷预设为 [{label, from, to}] 列表（cfg 透传给 x-daterange）
     *
     * @return array
     */
    protected function buildRanges()
    {
        if ($this->ranges === true || $this->ranges === null) {
            return [
                ['label' => __blang('builder_date_range_today'), 'from' => 'today', 'to' => 'today'],
                ['label' => __blang('builder_date_range_last7'), 'from' => 'today-6d', 'to' => 'today'],
                ['label' => __blang('builder_date_range_last30'), 'from' => 'today-29d', 'to' => 'today'],
                ['label' => __blang('builder_date_range_last3m'), 'from' => 'today-3m', 'to' => 'today'],
                ['label' => __blang('builder_date_range_last6m'), 'from' => 'today-6m', 'to' => 'today'],
                ['label' => __blang('builder_date_range_last1y'), 'from' => 'today-1y', 'to' => 'today'],
            ];
        }

        $list = [];

        foreach ((array)$this->ranges as $label => $val) {
            if (is_array($val)) {
                if (count($val) < 2) {
                    continue;
                }
                $list[] = ['label' => (string)$label, 'from' => (string)$val[0], 'to' => (string)$val[1]];
            } else {
                $pair = explode('~', (string)$val);
                if (count($pair) !== 2 || trim($pair[0]) === '' || trim($pair[1]) === '') {
                    continue;
                }
                $list[] = ['label' => (string)$label, 'from' => trim($pair[0]), 'to' => trim($pair[1])];
            }
        }

        return $list;
    }

    /**
     * 渲染前处理：加载flatpickr语言文件
     *
     * @return $this
     */
    public function beforRender()
    {
        $this->loadLocale();
        return parent::beforRender();
    }

    /**
     * 设置显示格式
     * @param string $val yyyy-mm-dd
     * @return $this
     */
    public function format($val)
    {
        $this->format = $val;
        return $this;
    }

    /**
     * 限制可选的最早日期（flatpickr 原生 minDate，cfg 整包透传给 x-daterange）
     * 支持 'Y-m-d' 字符串、'today'；DateTimeRange 子类传 'Y-m-d' 时自动补 00:00:00
     *
     * @param string $val 如 '2026-01-01'、'today'
     * @return $this
     */
    public function minDate($val)
    {
        $this->flatpickrRangeOptions['minDate'] = $val;
        return $this;
    }

    /**
     * 限制可选的最晚日期（flatpickr 原生 maxDate，cfg 整包透传给 x-daterange）
     * 支持 'Y-m-d' 字符串、'today'；DateTimeRange 子类传 'Y-m-d' 时自动补 23:59:59
     *
     * @param string $val 如 '2026-12-31'、'today'
     * @return $this
     */
    public function maxDate($val)
    {
        $this->flatpickrRangeOptions['maxDate'] = $val;
        return $this;
    }

    /**
     * 设置两个日期间的分隔符，默认','
     *
     * @param string $val
     * @return $this
     */
    public function separator($val = ',')
    {
        $this->separator = $val;
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

    /**
     * 获取渲染用值：数字时间戳自动转为日期格式
     *
     * @return string|int|float|null
     */
    public function renderValue()
    {
        $arr = explode($this->separator, $this->value);

        /**
         * 数字格式时间戳自动转为日期格式
         * 但要避免没有`-/`分割的时间格式被转换，如：20200630 => 1970-08-23 03:17:10
         * 解决办法，截取前字符串4位，如果大于2099或小于1900则认为是时间戳，否则认为是`-/`分割的时间
         * 如果值是数字但可以确定值不是时间戳，可主动使用->timespan('')清空格式避免自动转换。
         */
        if ($this->timespan && isset($arr[0]) && is_numeric($arr[0]) && $arr[0] > 0) {
            $char4 = substr((string)$arr[0], 0, 4);

            if ($char4 < 1900 || $char4 > 2099) //1900~2099区间不会误判
            {
                $arr[0] = date($this->timespan, $arr[0]);
            }
        }

        if ($this->timespan && isset($arr[1]) && is_numeric($arr[1]) && $arr[1] > 0) {
            $char4 = substr((string)$arr[1], 0, 4);

            if ($char4 < 1900 || $char4 > 2099) //1900~2099区间不会误判
            {
                $arr[1] = date($this->timespan, $arr[1]);
            }
        }

        $this->value = implode($this->separator, $arr);

        return parent::renderValue();
    }

    /**
     * 模板变量：范围占位、图标与cfg配置
     *
     * @return array
     */
    public function customVars()
    {
        return [
            // 6.164 与选择类统一：占位接字段文字（原 From ~ To 不带 label）
            'rangePlaceholder' => __blang('builder_please_select') . $this->label,
            'rangeIcon' => 'mdi-calendar-range',
            'cfg' => $this->elementCfg(),
        ];
    }
}

