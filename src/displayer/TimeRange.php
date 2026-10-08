<?php

namespace tpext\builder\displayer;

/**
 * TimeRange时间范围选择组件
 */
class TimeRange extends Time
{
    protected $view = 'timerange';

    protected $befor = '';

    protected $separator = ',';

    /**
     * 设置两个时间间的分隔符，默认','
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
     * 渲染前处理
     *
     * @return $this
     */
    public function beforRender()
    {
        return parent::beforRender();
    }

    /**
     * Web Components 化（WEBCOMPONENTS_PLAN.md 批1）：
     * 双时间输入 + 载体同步由 x-timerange 元素（builder-elements.js）自动完成。
     * cfg = 两组 flatpickr 实例共用的 options（noCalendar 纯时间），与原 timeRangeScript 一致。
     */
    protected function elementCfg()
    {
        $options = [
            // 6.156/6.169 与 DateTime::loadLocale() 同一开关与解析（继承自 Time
            // 的 flatpickrOptions['locale']）：留空 = 跟随项目语言，显式非空值优先
            'locale' => !empty($this->flatpickrOptions['locale']) ? $this->flatpickrOptions['locale'] : builder_js_locale(),
            'dateFormat' => 'H:i:S',
            'noCalendar' => true,
            'enableTime' => true,
            'enableSeconds' => true,
            'time_24hr' => true,
        ];

        return htmlspecialchars(json_encode($options, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 模板变量：cfg配置与分隔符
     *
     * @return array
     */
    public function customVars()
    {
        return [
            'cfg' => $this->elementCfg(),
            'separator' => $this->separator,
        ];
    }
}
