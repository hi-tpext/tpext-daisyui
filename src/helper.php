<?php

use tpext\common\ExtLoader;
use think\facade\Request;
use think\facade\Lang;

$classMap = [
    'tpext\\builder\\common\\Module'
];

ExtLoader::addClassMap($classMap);

if (!function_exists('csrf_token')) {
    /**
     * 生成表单令牌
     * @param string $name 令牌名称
     * @param mixed  $type 令牌生成方法
     * @return string
     */
    function csrf_token($name = '__token__', $type = 'md5')
    {
        $token = Request::token($name, $type);

        return $token;
    }
}

if (!function_exists('__blang')) {
    function __blang($name = null, $vars = [], $range = '')
    {
        $name = str_replace('bilder_', 'builder_', $name);
        return Lang::get($name, $vars, $range);
    }
}

if (!function_exists('builder_default_lang')) {
    /**
     * 项目配置的语言（6.169）：JS 库语言与翻译文件共用同一开关。
     * 优先 lang.default_lang（tpext 框架 loadLang 同一来源）；webman 下回退
     * translation.locale；都未配置时默认 zh-cn。
     * @return string
     */
    function builder_default_lang()
    {
        $lang = '';

        if (function_exists('config')) {
            $lang = (string) config('lang.default_lang');

            if ($lang === '') {
                $lang = (string) config('translation.locale');
            }
        }

        return $lang === '' ? 'zh-cn' : $lang;
    }
}

if (!function_exists('builder_js_locale')) {
    /**
     * JS 库 locale 键（6.169）：项目语言 → 库的语言键。
     * 'zh-cn'→'zh'；'en'/'en-us'→'en'（库内建英文，无需语言文件）；
     * 其余取 `-` 主子标签小写（如 'pt-br'→'pt'），未发行的库语言由
     * loadLocale() 的 is_file 兜底防 404。
     * @return string
     */
    function builder_js_locale()
    {
        $lang = strtolower(str_replace('_', '-', builder_default_lang()));

        if ($lang === '') {
            return 'en';
        }

        $primary = explode('-', $lang)[0];

        return $primary === '' ? 'en' : $primary;
    }
}

if (!function_exists('class_basename')) {
    /**
     * 获取类名(不包含命名空间)
     *
     * @param mixed $class 类名
     * @return string
     */
    function class_basename($class): string
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }
}
