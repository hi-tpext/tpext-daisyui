<?php

namespace tpext\builder\displayer;

use tpext\builder\common\Module;

/**
 * Map地图选点组件（支持高德/百度/腾讯/Yandex等）
 */
class Map extends Text
{
    protected $view = 'map';

    protected $type = 'amap';

    protected $minify = false;

    protected $jsOptions = [];

    protected $height = '450px';

    protected $width = '100%';

    /**
     * prepare{Type} 组装的 x-map 元素配置（elementCfg 序列化用）
     *
     * @var array
     */
    protected $mapCfg = [];

    /**
     * 设置地图高度
     *
     * @param string|int $val
     * @return $this
     */
    public function height($val)
    {
        if (is_numeric($val)) {
            $val .= 'px';
        }
        $this->height = $val;
        return $this;
    }

    /**
     * 设置地图宽度
     *
     * @param string|int $val
     * @return $this
     */
    public function width($val)
    {
        if (is_numeric($val)) {
            $val .= 'px';
        }
        $this->width = $val;
        return $this;
    }

    /**
     * 设置缩放级别
     *
     * @param int $val
     * @return $this
     */
    public function zoom($val)
    {
        $this->jsOptions['zoom'] = $val;
        return $this;
    }

    /**
     * 使用高德地图
     *
     * @return $this
     */
    public function amap()
    {
        $this->type = 'amap';
        return $this;
    }

    /**
     * 使用百度地图
     *
     * @return $this
     */
    public function baidu()
    {
        $this->type = 'baidu';
        return $this;
    }

    /**
     * 谷歌地图（未实现，保持兼容）
     *
     * @return $this
     */
    public function google()
    {
        return $this;
    }

    /**
     * 使用腾讯地图
     *
     * @return $this
     */
    public function tcent()
    {
        $this->type = 'tcent';
        return $this;
    }

    /**
     * 使用Yandex地图
     *
     * @return $this
     */
    public function yandex()
    {
        $this->type = 'yandex';
        return $this;
    }

    /**
     * 自己实现的地图，或者地图jsapi更新后已不适用需要重写js
     *
     * @return $this
     */
    public function other()
    {
        $this->type = 'other';
        return $this;
    }

    /**
     * 渲染前处理：按地图类型准备js与配置
     *
     * @return $this
     */
    public function beforRender()
    {
        $config = Module::getInstance()->getConfig();

        if ($this->type == 'amap') {
            $this->prepareAmap($config['amap_js_key']);
        } else if ($this->type == 'baidu') {
            $this->js[] = $config['baidu_map_js_key']; // jsapi 库仍走页面脚本加载
            $this->prepareBaidu();
        } else if ($this->type == 'tcent') {
            $this->prepareTcent($config['tcent_map_js_key']);
        } else if ($this->type == 'yandex') {
            $this->js[] = $config['yandex_map_js_key'];
            $this->prepareYandex();
        } else if ($this->type == 'other') {
            // 自己实现
        }

        $this->beforSymbol('<i class="mdi mdi-map"></i>');

        return parent::beforRender();
    }

    /**
     * x-map 元素配置（ENT_QUOTES 转义防属性截断，同 DateTime::elementCfg）
     *
     * @return string
     */
    protected function elementCfg()
    {
        return htmlspecialchars(json_encode($this->mapCfg, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 模板变量：地图类型与cfg配置
     *
     * @return array
     */
    public function customVars()
    {
        return [
            'maptype' => $this->type,
            'cfg' => $this->elementCfg(),
        ];
    }

    /**
     * 当前值是否有效坐标（"x,y" 且两段都非空）
     *
     * @param string $value
     * @return boolean
     */
    protected function hasPosition($value)
    {
        $position = array_filter(explode(',', $value), 'strlen');
        return !($value == ',' || count($position) != 2);
    }

    /**
     * 组装高德地图x-map配置
     *
     * @param string $jsKey
     * @return void
     */
    protected function prepareAmap($jsKey)
    {
        if (is_array($this->default)) {
            $this->default = implode(',', $this->default);
        }

        $value = $this->renderValue();

        if (!$this->hasPosition($value)) {
            $value = '102.709629,24.847463'; // 旧脚本的默认坐标（lng,lat）
        }

        $this->jsOptions = array_merge([
            'center' => explode(',', $value),
            'zoom' => 15,
        ], $this->jsOptions);

        $jscode = '';
        if (preg_match('/jscode=([^&]+)/i', $jsKey, $mch)) {
            $jscode = $mch[1]; //得到安全密钥
            $jsKey = str_replace(['&jscode=', $jscode], '', $jsKey); //替换url中的安全密钥
        }

        $this->mapCfg = [
            'type' => 'amap',
            'ro' => $this->isReadonly() || $this->isDisabled(),
            'opts' => $this->jsOptions, // center/zoom 及用户附加的 AMap options
            'value' => array_map('floatval', explode(',', $value)), // marker 初始位置 [lng,lat]
            'jsKey' => (string)$jsKey,
            'jscode' => $jscode,
            'searchable' => !$this->isReadonly() && !$this->isDisabled(),
        ];
    }

    /**
     * 组装腾讯地图x-map配置
     *
     * @param string $jsKey
     * @return void
     */
    protected function prepareTcent($jsKey)
    {
        $value = $this->renderValue();

        if (!$this->hasPosition($value)) {
            $value = '24.847463,102.709629'; // 旧脚本默认（lat,lng 存储序）
        } else {
            $position = explode(',', $value);
            $value = $position[1] . ',' . $position[0]; // 旧脚本反转后传给 LatLng
        }

        $this->jsOptions = array_merge([
            'zoom' => 15,
            'panControl' => true,
            'zoomControl' => true,
            'scaleControl' => true,
        ], $this->jsOptions);

        $this->mapCfg = [
            'type' => 'tcent',
            'ro' => $this->isReadonly() || $this->isDisabled(),
            'opts' => $this->jsOptions,
            'value' => array_map('floatval', explode(',', $value)), // LatLng 双参（旧脚本反转序）
            'jsKey' => (string)$jsKey,
            'searchable' => !$this->isReadonly() && !$this->isDisabled(),
        ];
    }

    /**
     * 组装百度地图x-map配置
     *
     * @return void
     */
    protected function prepareBaidu()
    {
        $value = $this->renderValue();

        if (!$this->hasPosition($value)) {
            $value = '102.709629,24.847463';
        }

        $this->jsOptions = array_merge([
            'zoom' => 14,
        ], $this->jsOptions);

        $this->mapCfg = [
            'type' => 'baidu',
            'ro' => $this->isReadonly() || $this->isDisabled(),
            'zoom' => $this->jsOptions['zoom'],
            'value' => array_map('floatval', explode(',', $value)), // Point 双参 [lng,lat]
            'searchable' => !$this->isReadonly() && !$this->isDisabled(),
        ];
    }

    /**
     * 组装Yandex地图x-map配置
     *
     * @return void
     */
    protected function prepareYandex()
    {
        $value = $this->renderValue();

        if (!$this->hasPosition($value)) {
            $position = [24.847463, 102.709629]; // 默认 [lat,lng]（旧脚本不反转）
        } else {
            $p = explode(',', $value);
            $position = [floatval($p[1]), floatval($p[0])]; // 旧脚本反转（[lng,lat]）
        }
        // 旧脚本 Placemark 用的是 $position 的再反转（两处顺序不一致，忠实复刻）：
        // 默认场景 Placemark=[lng,lat]、有效值场景 Placemark=[lat,lng]
        $placemark = [$position[1], $position[0]];

        $this->jsOptions = array_merge([
            'center' => $position,
            'zoom' => 14,
        ], $this->jsOptions);

        $this->mapCfg = [
            'type' => 'yandex',
            'ro' => $this->isReadonly() || $this->isDisabled(),
            'opts' => $this->jsOptions,
            'center' => $position, // Map center
            'value' => $placemark, // Placemark
            'searchable' => false, // 旧模板 yandex 不渲染搜索框
        ];
    }
}
