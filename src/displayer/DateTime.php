<?php

namespace tpext\builder\displayer;

/**
 * DateTime日期时间选择组件（基于flatpickr）
 */
class DateTime extends Text
{
    protected $view = 'date';
    protected $cssFamily = 'flatpickr'; // 6.181 按需加载

    protected $js = [
        '/assets/tpextdaisyui/js/vendors/flatpickr.min.js',
    ];

    protected $css = [];

    protected $size = [2, 3];

    protected $format = 'YYYY-MM-DD HH:mm:ss';

    protected $befor = '';

    protected $timespan = 'Y-m-d H:i:s';

    protected $jsOptions = [
        'useCurrent' => false,
        'showTodayButton' => false,
        'showClear' => true,
        'showClose' => true,
        'sideBySide' => true,
        'inline' => false,
    ];

    /**
     * flatpickr 配置，子类（Date/Time/Month/Year）按控件类型覆盖。
     * locale 留空 = 跟随项目语言（builder_js_locale()），显式非空值优先（6.169）
     */
    protected $flatpickrOptions = [
        'locale' => '',
        'dateFormat' => 'Y-m-d H:i:S',
        'enableTime' => true,
        'enableSeconds' => true,
        'time_24hr' => true,
        // 6.123 允许直接在输入框键入日期时间，不必非在面板里点选
        'allowInput' => true,
    ];

    /** 额外的 plugins 原生 JS 数组表达式，如 '[new monthSelectPlugin({shorthand: true, dataFormat: "Y-m"})]' */
    protected $flatpickrPluginsJs = '';

    /**
     * 创建组件：DateTime类型设置默认viewDate
     *
     * @param string $fieldType
     * @return $this
     */
    public function created($fieldType = '')
    {
        parent::created($fieldType);
        if (class_basename($fieldType) == 'DateTime') {
            $this->jsOptions['viewDate'] = date('Y-m-d 00:00:00'); //默认值为空时，如果选择今天时间，会自带前的时分秒。通过此设置舍弃时分秒
        }
        return  $this;
    }

    /**
     * 6.156 flatpickr 官方发行版内建英文（l10ns.default），其余语言须先加载
     * l10n 文件（注册进 flatpickr.l10ns）、再由 $flatpickrOptions['locale'] 选用。
     * 6.169 locale 留空 = 跟随项目语言（builder_js_locale()）；显式非空值优先。
     * 本包 assets 只发行了 zh；en/default/未发行的语言不加载文件
     * （en 走内建；未发行的语言即使传了 locale，flatpickr 也会回落内建英文）。
     */
    protected function loadLocale()
    {
        $locale = !empty($this->flatpickrOptions['locale']) ? $this->flatpickrOptions['locale'] : builder_js_locale();

        if ($locale == '' || $locale == 'en' || $locale == 'default') {
            return;
        }

        // 只加载本包发行过的语言文件，防 404
        if (is_file(__DIR__ . '/../../assets/js/vendors/flatpickr.' . $locale . '.js')) {
            $this->js[] = '/assets/tpextdaisyui/js/vendors/flatpickr.' . $locale . '.js';
        }
    }

    /**
     * 渲染前处理：加载flatpickr语言文件
     *
     * @return $this
     */
    public function beforRender()
    {
        // 语言文件须经本方法在 $js 收集前追加：页面级 $js 块输出在 body 底部
        // common 栈（含 builder-elements.js）之前，保证先于 x-date 元素初始化执行
        $this->loadLocale();
        return parent::beforRender();
    }

    /**
     * Web Components 化（WEBCOMPONENTS_PLAN.md 批1）：
     * 初始化不再输出页面底部脚本文本，改由 x-date 元素（builder-elements.js）自动完成。
     * flatpickr options 经 elementCfg() 序列化进模板 cfg 属性（ENT_QUOTES 转义防属性截断）。
     */
    protected function elementCfg()
    {
        $options = $this->flatpickrOptions;
        // 6.169 空 locale 解析为项目语言，与 loadLocale() 加载的 l10n 文件一致：
        // 'zh' 选用刚注册的语言包；'en'/未发行语言回落 flatpickr 内建英文
        if (empty($options['locale'])) {
            $options['locale'] = builder_js_locale();
        }
        $options['defaultDate'] = $this->value ?: null;

        return htmlspecialchars(json_encode($options, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 设置显示格式
     * @param string $val YYYY-MM-DD HH:mm:ss
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
    public function timespan($val = 'Y-m-d H:i:s')
    {
        $this->timespan = $val;
        return $this;
    }

    /**
     * 根据子类类型返回合适的图标 class（MDI font icon）
     * - Time/TimeRange → mdi-clock
     * - DateTime/Date  → mdi-calendar
     * - Month          → mdi-calendar-month
     * - Year           → mdi-calendar
     */
    public function customVars()
    {
        $class = class_basename(get_class($this));
        $iconMap = [
            // lightyearadmin 的 materialdesignicons.min.css 是字形子集，无 clock-outline，用实心 mdi-clock
            'Time' => 'mdi-clock',
            'TimeRange' => 'mdi-clock',
            'DateTime' => 'mdi-calendar',
            'Date' => 'mdi-calendar',
            'Month' => 'mdi-calendar-month',
            'Year' => 'mdi-calendar',
        ];
        return [
            'fieldType' => $class,
            'dateIcon' => isset($iconMap[$class]) ? $iconMap[$class] : 'mdi-calendar',
            'cfg' => $this->elementCfg(),
        ];
    }

    /**
     * 6.164 日期时间是「选」不是「输」，占位措辞与选择类一致（原继承 Text 的「请输入」）
     *
     * @return string
     */
    protected function placeholderText()
    {
        return __blang('builder_please_select') . $this->label;
    }

    /**
     * 获取渲染用值：数字时间戳自动转为日期格式
     *
     * @return string|int|float|null
     */
    public function renderValue()
    {
        /**
         * 数字格式时间戳自动转为日期格式
         * 但要避免没有`-/`分割的时间格式被转换，如：20200630 => 1970-08-23 03:17:10
         * 解决办法，截取前字符串4位，如果大于2099或小于1900则认为是时间戳，否则认为是`-/`分割的时间
         * 如果值是数字但可以确定值不是时间戳，可主动使用->timespan('')清空格式避免自动转换。
         */
        if ($this->timespan && is_numeric($this->value) && $this->value > 0) {

            $char4 = substr((string)$this->value, 0, 4);

            if ($char4 < 1900 || $char4 > 2099) //1900~2099区间不会误判
            {
                $this->value = date($this->timespan, $this->value);
            }
        }

        return parent::renderValue();
    }
}

