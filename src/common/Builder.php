<?php

namespace tpext\builder\common;

use Webman\Context;
use tpext\think\View;
use think\facade\Session;
use tpext\common\ExtLoader;
use tpext\builder\tree\Tree;
use tpext\builder\inface\Auth;
use tpext\builder\inface\Renderable;
use tpext\builder\inface\ReleaseAble;
use tpext\builder\traits\HasDestroyOnce;

class Builder implements Renderable, ReleaseAble
{
    use HasDestroyOnce;

    protected $view = '';

    protected $layout = '';

    protected $title = '';

    protected $desc = '';

    protected $csrf_token = '';

    /**
     * 页面包含的所有行
     *
     * @var Row[]
     */
    protected $rows = [];

    /**
     * 当前操作的行（新行创建后指向它）
     *
     * @var Row|null
     */
    protected $__row__ = null;

    protected $js = [];

    protected $customJs = [];

    protected $css = [];

    protected $customCss = [];

    protected $styleSheet = [];

    protected $script = [];

    protected $notify = [];

    protected $layer;

    // builder UI 依赖的全部静态资源，统一在此引入（页面级 $js/$css 经 content.html 渲染，
    // 不依赖宿主 layout 的 admin_js/admin_css——宿主自带 admin_layout 时后者不会注入）
    // 顺序敏感：alpine → axios → tom-select → flatpickr（中文包见 commonJs()，6.169 按语言插入）→ layui → bootstrap → 兼容层 → 业务逻辑
    // 校验=原生 HTML5 约束 + 自绘提示层（form.html novalidate + tpextbuilder.validateForm：
    // 红边框/按钮上方错误行/右上角通知，6.429 取代 reportValidity 浏览器原生气泡）；
    // zod 不引入：v3 npm 包是 CJS 构建浏览器不可用，且 schema 规则会与服务端属性驱动重复（6.428 已清残片）
    protected $commonJs = [
        '/assets/tpextdaisyui/js/vendors/jquery.min.js',
        // Collapse 插件、Alpine 组件注册文件都必须在 Alpine 核心之前加载
        // （均通过 alpine:init 事件注册，晚于核心加载则事件已过、注册无效）
        '/assets/tpextdaisyui/js/vendors/alpine-collapse.min.js',
        '/assets/tpextdaisyui/js/tpextbuilder-alpine.js',
        '/assets/tpextdaisyui/js/vendors/alpine.min.js',
        '/assets/tpextdaisyui/js/vendors/axios.min.js',
        '/assets/tpextdaisyui/js/vendors/tom-select.min.js',
        '/assets/tpextdaisyui/js/vendors/flatpickr.min.js',
        '/assets/tpextdaisyui/js/layui/layui.js',
        '/assets/tpextdaisyui/js/layui-bootstrap.js',
        '/assets/tpextdaisyui/js/tpb.js',
        '/assets/tpextdaisyui/js/tpextbuilder.js',
        // Web Components 定义（x-date 等）：必须在全部第三方库之后（元素 init 用到 flatpickr 等）
        '/assets/tpextdaisyui/js/builder-elements.js',
    ];

    protected $commonCss = [
        '/assets/tpextdaisyui/js/layui/css/layui.css',
        '/assets/tpextdaisyui/css/builder.css',
        '/assets/tpextdaisyui/css/flatpickr.min.css',
        '/assets/tpextdaisyui/css/tom-select.min.css',
        '/assets/tpextdaisyui/css/uploadfiles.css',
        // Material Design Icons — 模板里 <i class="mdi mdi-xxx"> 依赖它
        '/assets/lightyearadmin/css/materialdesignicons.min.css',
        // 6.178 手写覆盖层最后加载（CSS_GOVERNANCE_PLAN.md P1）：此前排在 vendor css
        // 之前，588 处 !important 纯属顺序税；移到最后同优先级天然赢 vendor，
        // !important 得以按 P3 的门逐步退役
        // 6.179 P2 拆分：原 builder-input.css 4600+ 行单文件按族拆成 6 个手写文件，
        // 宏顺序 core-base → tomselect → trees → flatpickr → widgets → core-layout；
        // 节内不改一字，行多重集一致性校验通过（scripts-csssplit.js）
        // 6.181 按需加载：core/core-layout 始终加载，4 个族文件由 displayer 通过
        // needCss() 注册，commonCss() 按需插入
        '/assets/tpextdaisyui/css/builder-core.css',
        // 族文件由 needCss() 按需注册，commonCss() 动态插入此处
        '/assets/tpextdaisyui/css/builder-core-layout.css',
    ];

    /**
     * 6.181 按需加载：displayer 在 beforRender() 中注册所需 CSS 族
     * @var string[]
     */
    protected $neededCss = [];

    /**
     * 注册一个 CSS 族文件（幂等）
     * @param string $family tomselect|trees|flatpickr|widgets
     */
    public function needCss($family)
    {
        if ($family && !in_array($family, $this->neededCss, true)) {
            $this->neededCss[] = $family;
        }
    }

    /**
     * 权限校验类（Auth接口实现，可为类名或实例）
     *
     * @var string|Auth
     */
    protected static $auth;

    protected static $minify = false;

    protected static $aver = '1.0.3';

    protected static $instance = null;

    protected static $isWebmanContext = null;

    protected function __construct($title, $desc)
    {
        $this->title = $title;
        $this->desc = $desc;
    }

    /**
     * 获取Builder单例（webman环境下从Context按类名取回）
     *
     * @param string $title
     * @param string $desc
     * @return static
     */
    public static function getInstance($title = '', $desc = '')
    {
        if (is_null(self::$isWebmanContext)) {
            self::$isWebmanContext = class_exists(Context::class);
        }
        if (self::$isWebmanContext) {
            self::$instance = Context::get(static::class);
        }
        if (self::$instance == null) {
            self::$instance = new static($title, $desc);
            self::$instance->created();

            if (self::$isWebmanContext) {
                Context::set(static::class, self::$instance);
            }

            ExtLoader::trigger('tpext_create_builder', self::$instance);
        } else {
            if ($title) {
                self::$instance->title($title);
            }
            if ($desc) {
                self::$instance->desc($desc);
            }
        }

        return self::$instance;
    }

    /**
     * 销毁实例
     *
     * @return void
     */
    public static function destroyInstance()
    {
        if (self::$isWebmanContext) {
            self::$instance = Context::get(static::class);
        }

        if (self::$instance) {
            self::$instance->destroy();
            self::$instance = null;

            if (self::$isWebmanContext) {
                Context::set(static::class, null);
            }
        }

        if (self::$isWebmanContext) {
            Context::set(static::class . '::auth', null);
        }
    }

    /**
     * 实例创建后的初始化钩子，子类可覆盖
     *
     * @return $this
     */
    protected function created()
    {
        return $this;
    }

    /**
     * 设置页面标题
     *
     * @param string $val
     * @return $this
     */
    public function title($val)
    {
        $this->title = $val;
        return $this;
    }

    /**
     * 设置页面描述
     *
     * @param string $val
     * @return $this
     */
    public function desc($val)
    {
        $this->desc = $val;
        return $this;
    }

    /**
     * 设置布局模板
     *
     * @param mixed $val
     * @return $this
     */
    public function layout($val)
    {
        $this->layout = $val;
        return $this;
    }

    /**
     * 设置视图模板路径，避免不同的应用中使用Builder时模板缓存冲突
     *
     * @param string $template
     * @return $this
     */
    public function setView($template)
    {
        $this->view = $template;

        return $this;
    }

    /**
     * 获取CSRF Token，不存在时生成并缓存到Session
     *
     * @return string
     */
    public function getCsrfToken()
    {
        if (!$this->csrf_token) {

            $token = Session::get('_csrf_token_');

            if (empty($token)) {
                $token = md5('_csrf_token_' . time() . uniqid());
                Session::set('_csrf_token_', $token);
            }

            $this->csrf_token = $token;
        }

        return $this->csrf_token;
    }

    /**
     * 添加js文件路径
     *
     * @param array|string $val
     * @return $this
     */
    public function addJs($val)
    {
        if (!is_array($val)) {
            $val = [$val];
        }
        $this->js = array_merge($this->js, $val);
        return $this;
    }

    /**
     * 添加自定义js，不会被minify
     *
     * @param array|string $val
     * @return $this
     */
    public function customJs($val)
    {
        if (!is_array($val)) {
            $val = [$val];
        }
        $this->customJs = array_merge($this->customJs, $val);
        return $this;
    }

    /**
     * 添加css文件路径
     *
     * @param array|string $val
     * @return $this
     */
    public function addCss($val)
    {
        if (!is_array($val)) {
            $val = [$val];
        }
        $this->css = array_merge($this->css, $val);
        return $this;
    }

    /**
     * 添加自定义css，不会被minify
     *
     * @param array|string $val
     * @return $this
     */
    public function customCss($val)
    {
        if (!is_array($val)) {
            $val = [$val];
        }
        $this->customCss = array_merge($this->customCss, $val);
        return $this;
    }

    /**
     * 移除js文件
     *
     * @param array|string $val
     * @return $this
     */
    public function removeJs($val)
    {
        if (!is_array($val)) {
            $val = [$val];
        }

        foreach ($this->js as $k => $j) {
            if (in_array($j, $val)) {
                unset($this->js[$k]);
            }
        }

        return $this;
    }

    /**
     * 移除css文件
     *
     * @param array|string $val
     * @return $this
     */
    public function removeCss($val)
    {
        if (!is_array($val)) {
            $val = [$val];
        }

        foreach ($this->css as $k => $c) {
            if (in_array($c, $val)) {
                unset($this->css[$k]);
            }
        }

        return $this;
    }

    /**
     * 替换js文件路径
     *
     * @param string $val
     * @param string $newVal
     * @return $this
     */
    public function replaceJs($val, $newVal)
    {
        foreach ($this->js as $k => $j) {
            if ($val == $j) {
                $this->js[$k] = $newVal;
                break;
            }
        }

        return $this;
    }

    /**
     * 替换css文件路径
     *
     * @param string $val
     * @param string $newVal
     * @return $this
     */
    public function replaceCss($val, $newVal)
    {
        foreach ($this->css as $k => $c) {
            if ($val == $c) {
                $this->css[$k] = $newVal;
                break;
            }
        }

        return $this;
    }

    /**
     * 添加内联js脚本
     *
     * @param array|string $val
     * @return $this
     */
    public function addScript($val)
    {
        if (!is_array($val)) {
            $val = [$val];
        }
        $this->script = array_merge($this->script, $val);
        return $this;
    }

    /**
     * 添加内联css样式
     *
     * @param array|string $val
     * @return $this
     */
    public function addStyleSheet($val)
    {
        if (!is_array($val)) {
            $val = [$val];
        }
        $this->styleSheet = array_merge($this->styleSheet, $val);
        return $this;
    }

    /**
     * 获取js文件列表
     *
     * @return array
     */
    public function getJs()
    {
        return $this->js;
    }

    /**
     * 获取内联js脚本列表
     *
     * @return array
     */
    public function getScript()
    {
        return $this->script;
    }

    /**
     * 获取内联css样式列表
     *
     * @return array
     */
    public function getStyleSheet()
    {
        return $this->styleSheet;
    }

    /**
     * 获取css文件列表
     *
     * @return array
     */
    public function getCss()
    {
        return $this->css;
    }

    /**
     * 获取所有行
     *
     * @return Row[]
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * 清空所有行
     *
     * @return $this
     */
    public function clearRows()
    {
        $this->rows = [];
        $this->__row__ = null;

        return $this;
    }

    /**
     * 设置页面顶部通知消息
     * lightyear.notify('修改成功，页面即将自动跳转~', 'success', 5000, 'mdi mdi-emoticon-happy', 'top', 'center');
     * @param string $msg
     * @param string $type
     * @param integer $delay
     * @param string $icon
     * @param string $from
     * @param string $align
     * @return $this
     */
    public function notify($msg, $type = 'info', $delay = 2000, $icon = '', $from = 'top', $align = 'center')
    {
        $this->notify = [$msg, $type, $delay, $icon, $from, $align];
        return $this;
    }

    /**
     * 获取通知消息配置
     *
     * @return array
     */
    public function getNotify()
    {
        return $this->notify;
    }

    /**
     * 新增一行并设为当前行
     *
     * @return Row
     */
    public function row()
    {
        $row = Row::make();
        $this->rows[] = $row;
        $this->__row__ = $row;
        return $row;
    }

    /**
     * 在当前行新增一列（无当前行时先建行）
     *
     * @param integer|string $size
     * @return Column
     */
    public function column($size = 12)
    {
        if (!$this->__row__) {
            $this->row();
        }

        return $this->__row__->column($size);
    }

    /**
     * 获取一个form
     *
     * @param integer|string $size col大小
     * @return Form
     */
    public function form($size = 12)
    {
        return $this->column($size)->form();
    }

    /**
     * 获取一个表格
     *
     * @param integer|string $size col大小
     * @return Table
     */
    public function table($size = 12)
    {
        return $this->column($size)->table();
    }

    /**
     * 获取一个工具栏
     *
     * @param integer|string $size col大小
     * @return Toolbar
     */
    public function toolbar($size = 12)
    {
        return $this->column($size)->toolbar();
    }

    /**
     * 获取一个 Tree 组件
     * 
     * @param integer|string $size col大小
     * @return Tree
     */
    public function tree($size = 12)
    {
        return $this->column($size)->tree();
    }

    /**
     * 获取一个 Tree 组件 (原 ZTree, 已废弃)
     *
     * @deprecated 请使用 tree()
     * @param integer|string $size col大小
     * @return Tree
     */
    public function zTree($size = 12)
    {
        return $this->column($size)->zTree();
    }

    /**
     * 获取一个 Tree 组件 (原 JSTree, 已废弃)
     *
     * @deprecated 请使用 tree()
     * @param integer|string $size col大小
     * @return Tree
     */
    public function jsTree($size = 12)
    {
        return $this->column($size)->jsTree();
    }

    /**
     * 获取一自定义内容
     *
     * @param integer|string $size col大小
     * @return Content
     */
    public function content($size = 12)
    {
        return $this->column($size)->content();
    }

    /**
     * 获取一tab内容
     *
     * @param integer|string $size col大小
     * @return Tab
     */
    public function tab($size = 12)
    {
        return $this->column($size)->tab();
    }

    /**
     * 获取一Swiper
     *
     * @param integer|string $size col大小
     * @return Swiper
     */
    public function swiper($size = 12)
    {
        return $this->column($size)->swiper();
    }

    /**
     * 获取layer
     *
     * @return Layer
     */
    public function layer(...$arguments)
    {
        if (!$this->layer) {
            $this->layer = Column::makeWidget('Layer', $arguments);
        }

        return $this->layer;
    }

    /**
     * 渲染指定模板内容
     *
     * @param string $template
     * @param array $vars
     * @param integer|string $size col大小
     * @return $this
     */
    public function fetch($template = '', $vars = [], $size = 12)
    {
        $this->content($size)->fetch($template, $vars);

        return $this;
    }

    /**
     * 直接输出自定义内容
     *
     * @param string $content
     * @param array $vars
     * @param integer|string $size col大小
     * @return $this
     */
    public function display($content = '', $vars = [], $size = 12)
    {
        $this->content($size)->display($content, $vars);

        return $this;
    }

    /**
     * 获取本库依赖的公共js文件列表（中文项目追加flatpickr中文包）
     *
     * @return array
     */
    public function commonJs()
    {
        $js = $this->commonJs;

        // 6.169 flatpickr 中文包跟随项目语言：中文项目才全局注册 l10ns.zh
        // （兜底未走 loadLocale 的直接用法），其余语言不多发一个请求；
        // 字段级语言文件一律由 loadLocale() 按需追加。
        // 必须插回 flatpickr.min.js 之后的原位置：早于 builder-elements.js
        // 执行，元素 init 时 l10ns.zh 才已可选中
        if (builder_js_locale() === 'zh') {
            $pos = array_search('/assets/tpextdaisyui/js/vendors/flatpickr.min.js', $js);
            array_splice($js, $pos === false ? count($js) : $pos + 1, 0, ['/assets/tpextdaisyui/js/vendors/flatpickr.zh.js']);
        }

        return $js;
    }

    /**
     * 获取本库依赖的公共css文件列表（按需插入displayer注册的族文件）
     *
     * @return array
     */
    public function commonCss()
    {
        $css = $this->commonCss;
        // 6.181 按需加载：在 builder-core.css 之后插入 displayer 注册的族文件
        // 宏顺序固定：tomselect → trees → flatpickr → widgets
        static $familyOrder = ['tomselect', 'trees', 'flatpickr', 'widgets'];
        if (!empty($this->neededCss)) {
            $pos = array_search('/assets/tpextdaisyui/css/builder-core.css', $css);
            if ($pos !== false) {
                $insert = [];
                foreach ($familyOrder as $fam) {
                    if (in_array($fam, $this->neededCss, true)) {
                        $insert[] = '/assets/tpextdaisyui/css/builder-' . $fam . '.css';
                    }
                }
                array_splice($css, $pos + 1, 0, $insert);
            }
        }
        return $css;
    }

    /**
     * 渲染前的准备：递归准备各行，并注册公共js/css
     *
     * @return void
     */
    public function beforRender()
    {
        foreach ($this->rows as $row) {
            $row->beforRender();
        }

        $this->addJs($this->commonJs());
        $this->addCss($this->commonCss());
    }

    /**
     * 设置是否压缩合并静态资源
     *
     * @param boolean $val
     * @return void
     */
    public static function minify($val)
    {
        static::$minify = $val;
    }

    /**
     * 是否压缩合并静态资源
     *
     * @return boolean
     */
    public static function isMinify()
    {
        return static::$minify;
    }

    /**
     * 设置静态资源版本号（用于js/css缓存刷新）
     *
     * @param string $val
     * @return void
     */
    public static function aver($val)
    {
        static::$aver = $val;
    }

    /**
     * 设置权限校验类（Auth接口实现）
     *
     * @param string|Auth $class
     * @return void
     */
    public static function auth($class)
    {
        static::$auth = $class;

        if (is_null(self::$isWebmanContext)) {
            self::$isWebmanContext = class_exists(Context::class);
        }
        if (self::$isWebmanContext) {
            Context::set(static::class . '::auth', $class);
        }
    }

    /**
     * 检查当前用户是否有权限访问指定URL
     *
     * @param string $url
     * @return boolean
     */
    public static function checkUrl($url)
    {
        if ($url === '' || $url === '#' || stripos($url, 'javascript:') === 0) {
            return true;
        }
        //如果不是完整的[moudle/controller/action]格式
        if (preg_match('/^\w+$/', $url) || preg_match('/^\w+(\.\w+)?\/\w+$/', $url)) {
            $url = url($url);
        }

        if (self::$isWebmanContext) {
            self::$auth = Context::get(static::class . '::auth');
        }

        if (!empty(static::$auth)) {

            return static::$auth::checkUrl($url);
        }

        return true;
    }

    /**
     * 子类可覆盖，返回要附加到视图的自定义变量
     *
     * @return array
     */
    public function customVars()
    {
        return [];
    }

    /**
     * 渲染整个页面，返回视图实例
     *
     * @return View
     */
    public function render()
    {
        if ($this->layer) {
            return $this->layer->getViewShow();
        }

        $this->beforRender();

        if (empty($this->view)) {
            $this->view = Module::getInstance()->getViewsPath() . 'content.html';
        }

        if (!empty($this->notify)) {

            $this->script[] = "lightyear.notify('{$this->notify[0]}', '{$this->notify[1]}', {$this->notify[2]}, '{$this->notify[3]}', '{$this->notify[4]}', '{$this->notify[5]}');";
        }

        if (static::$minify && !empty($this->customJs)) {
            $this->js = $this->customJs;
            $this->css = $this->customCss;
        } else {
            $this->js = array_merge($this->js, $this->customJs);
            $this->css = array_merge($this->css, $this->customCss);
        }

        foreach ($this->css as &$c) {
            if (strpos($c, '?') == false && strpos($c, 'http') == false) {
                $c .= '?aver=' . static::$aver;
            }
        }

        unset($c);

        foreach ($this->js as &$j) {
            if (strpos($j, '?') == false && strpos($j, 'http') == false) {
                $j .= '?aver=' . static::$aver;
            }
        }

        unset($j);

        $__blang = Module::getInstance()->getLang('common');

        if (empty($this->layout)) {
            $this->layout = Module::getInstance()->getViewsPath() . 'layout.html';
        }

        $vars = [
            'title' => $this->title ? $this->title : '',
            'desc' => $this->desc,
            'rows' => $this->rows,
            'js' => array_unique($this->js),
            'css' => array_unique($this->css),
            'stylesheet' => implode('', array_unique($this->styleSheet)),
            'script' => implode('', array_unique($this->script)),
            '__blang' => json_encode($__blang, JSON_UNESCAPED_UNICODE),
            // 6.169 项目语言传给前端：JS 侧据此决定注册哪套 JS 库语言
            // （registerFlatpickrZh）、html lang 属性等
            '__builder_lang' => builder_default_lang(),
            'builderLayout' => $this->layout,
        ];

        View::share([
            '__token__' => $this->getCsrfToken(),
            'admin_page_title' => $this->desc,
            'admin_page_position' => $this->title
        ]);

        $customVars = $this->customVars();

        if (!empty($customVars)) {
            $vars = array_merge($vars, $customVars);
        }

        $viewshow = new View($this->view);

        return $viewshow->assign($vars);
    }

    /**
     * 转为字符串时返回渲染后的页面内容
     *
     * @return string
     */
    public function __toString()
    {
        return $this->render()->getContent();
    }

    /**
     * 释放资源，销毁所有行
     *
     * @return void
     */
    public function destroy()
    {
        // 6.261 已销毁直接返回：同一组件可被多归属路径重复触达（契约见 traits\HasDestroyOnce）
        if ($this->__destroyed__) {
            return;
        }

        foreach ($this->rows as $row) {
            $row->destroy();
        }

        // 6.260 数组属性复位为空数组（保持类型恒定，二次 destroy 自然幂等）
        $this->rows = [];
        $this->__destroyed__ = true;
    }
}


