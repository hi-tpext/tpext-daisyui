<?php

namespace tpext\builder\displayer;

class RangeSlider extends Text
{
    /**
     * 基于 daisyUI .range（原生 input[type=range]），不使用旧库的 ion-rangeslider（依赖 jQuery，新库已移除 jQuery）。
     * 提交契约与旧库一致：single -> from；double -> "from;to"（values 模式为值字符串）。
     * jsOptions 兼容旧库（ion）常用标量键：type/min/max/step/from/to/prefix/postfix/values/disable/from_fixed/to_fixed；
     * 不支持（差异见 TASK_LIST）：grid/grid_num/hide_min_max/hide_from_to/prettify 等装饰性键。
     */
    protected $view = 'rangeslider';

    protected $js = [];

    protected $css = [];

    protected $jsOptions = [
        'type' => 'single',
        'min' => 0,
        'max' => 100,
    ];

    protected $checked = [];

    protected $isInTable = false;

    /**
     * @var array|null [jsOptions, useValues] 缓存，beforRender 与 customVars 共用
     */
    protected $prepared = null;

    /**
     * 设置是否处于表格内
     *
     * @param boolean $val
     * @return $this
     */
    public function setIsInTable($val = true)
    {
        $this->isInTable = $val;
        return $this;
    }

    /**
     * 归一化 jsOptions：解析 value/default 到 from/to，values 模式统一为索引制
     *
     * @return array [$opts, $useValues]
     */
    protected function getPrepared()
    {
        if ($this->prepared !== null) {
            return $this->prepared;
        }

        $opts = $this->jsOptions;

        if (!isset($opts['step'])) {
            $opts['step'] = 1;
        }

        $useValues = isset($opts['values']) && is_array($opts['values']) && count($opts['values']) > 0;

        $checked = [];

        if (!empty($this->value)) {
            $checked = is_array($this->value) ? $this->value : explode(';', str_replace(',', ';', $this->value));
        } else if (!empty($this->default)) {
            $checked = is_array($this->default) ? $this->default : explode(';', str_replace(',', ';', $this->default));
        }

        if (count($checked) > 0) {
            $opts['from'] = $checked[0];

            if (count($checked) > 1) {
                $opts['to'] = $checked[1];
                $opts['type'] = 'double';
            }
        }

        $opts['type'] = $opts['type'] === 'double' ? 'double' : 'single';

        if ($useValues) {
            // ion 约定：values 模式下 from/to 是索引；容错直接传值字符串的写法
            foreach (['from', 'to'] as $key) {
                if (!isset($opts[$key])) {
                    continue;
                }

                if (is_numeric($opts[$key]) && $opts[$key] >= 0 && $opts[$key] < count($opts['values'])) {
                    $opts[$key] = (int)$opts[$key];
                } else {
                    $found = array_search(strval($opts[$key]), $opts['values']);
                    $opts[$key] = $found === false ? -1 : (int)$found;
                }
            }

            // values 模式下 range 以索引为值，min/max/step 统一改写为索引制
            $opts['min'] = 0;
            $opts['max'] = count($opts['values']) - 1;
            $opts['step'] = 1;

            if (!isset($opts['from']) || $opts['from'] < 0) {
                $opts['from'] = 0;
            }

            if ($opts['type'] === 'double' && (!isset($opts['to']) || $opts['to'] < 0)) {
                $opts['to'] = $opts['max'];
            }
        } else {
            if (!isset($opts['from'])) {
                $opts['from'] = $opts['min'];
            }

            if ($opts['type'] === 'double' && !isset($opts['to'])) {
                $opts['to'] = $opts['max'];
            }
        }

        if ($opts['type'] === 'double' && is_numeric($opts['from']) && is_numeric($opts['to']) && $opts['from'] > $opts['to']) {
            $tmp = $opts['from'];
            $opts['from'] = $opts['to'];
            $opts['to'] = $tmp;
        }

        if ($this->disabled) {
            $opts['disable'] = true;
        }

        $this->prepared = [$opts, $useValues];

        return $this->prepared;
    }

    /**
     * 当前值的展示文案（含 prefix/postfix 装饰），用于只读态与初始读数
     *
     * @return string
     */
    protected function buildLabel()
    {
        list($opts, $useValues) = $this->getPrepared();

        $prefix = isset($opts['prefix']) ? $opts['prefix'] : '';
        $postfix = isset($opts['postfix']) ? $opts['postfix'] : '';

        if ($useValues) {
            $one = function ($i) use ($opts, $prefix, $postfix) {
                $v = isset($opts['values'][$i]) ? strval($opts['values'][$i]) : '';
                return $prefix . $v . $postfix;
            };
        } else {
            $one = function ($i) use ($prefix, $postfix) {
                return $prefix . $i . $postfix;
            };
        }

        if ($opts['type'] === 'double') {
            return $one($opts['from']) . ' — ' . $one($opts['to']);
        }

        return $one($opts['from']);
    }

    /**
     * 提交载体的初始值（与表单提交格式一致：single 为单值，double 为 "from;to"）
     *
     * @return string
     */
    protected function buildInitValue()
    {
        list($opts, $useValues) = $this->getPrepared();

        if ($useValues) {
            $from = isset($opts['values'][$opts['from']]) ? strval($opts['values'][$opts['from']]) : '';
            $to = $opts['type'] === 'double' && isset($opts['values'][$opts['to']]) ? strval($opts['values'][$opts['to']]) : '';
        } else {
            $from = strval($opts['from']);
            $to = $opts['type'] === 'double' ? strval($opts['to']) : '';
        }

        if ($opts['type'] === 'double') {
            return $from . ';' . $to;
        }

        return $from;
    }

    /**
     * x-slider 元素配置：prepared 后的完整滑块配置（type/min/max/step/from/to/
     * values/prefix/postfix/disable/双 fixed）+ 初始值与读数文案。
     * 同步逻辑由元素内部闭包完成，旧的全局委托引擎与 __rsConfigs__ 注册表退役。
     *
     * @return string
     */
    protected function elementCfg()
    {
        list($opts, $useValues) = $this->getPrepared();

        $cfg = [
            'type' => $opts['type'],
            'min' => $opts['min'],
            'max' => $opts['max'],
            'step' => $opts['step'],
            'from' => $opts['from'],
            'to' => isset($opts['to']) ? $opts['to'] : null,
            'values' => $useValues ? array_map('strval', $opts['values']) : null,
            'prefix' => isset($opts['prefix']) ? $opts['prefix'] : '',
            'postfix' => isset($opts['postfix']) ? $opts['postfix'] : '',
            'disable' => !empty($opts['disable']),
            'fromFixed' => !empty($opts['from_fixed']),
            'toFixed' => !empty($opts['to_fixed']),
            'useValues' => $useValues,
            'initValue' => $this->buildInitValue(),
            'initLabel' => $this->buildLabel(),
        ];

        return htmlspecialchars(json_encode($cfg, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 模板变量：滑块配置与初始读数
     *
     * @return array
     */
    public function customVars()
    {
        list($opts, $useValues) = $this->getPrepared();

        return [
            'rs' => $opts,
            'rsUseValues' => $useValues,
            'rsInitValue' => $this->buildInitValue(),
            'rsInitLabel' => $this->buildLabel(),
            'rsReadout' => $this->buildLabel(),
            'isInTable' => $this->isInTable,
            'cfg' => $this->elementCfg(),
        ];
    }
}
