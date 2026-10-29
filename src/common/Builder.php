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

    /* 6.489n 资源版本号：JS/CSS 改动后须递增（URL 变了浏览器才会重新拉取）。
       注意 myadmin8 的 AppService 会 aver(time()) 覆盖为 worker 启动时刻——
       那意味着改前端资源也必须 touch 一个 app 文件触发 worker 重载，
       否则 aver 不变、浏览器继续吃旧缓存（本条注释即 6.489n 部署踩坑记录）。
       6.489ag：1.0.4 → 1.0.5（容器查询纵排 CSS；实测当前 myadmin8 worker
       并无 time() 覆盖、touch 后仍输出 1.0.4，纯 CSS 部署必须手动递增本值）
       1.0.5 → 1.0.6（label 换行行高压缩 16px/搜索 14px）
       1.0.6 → 1.0.7（区间日期 readonly 标记类：readonly-native/readonly-external）
       1.0.7 → 1.0.8（区间图标改 fp.open()+stopPropagation，focus 会被点外关闭立即合上）
       1.0.8 → 1.0.9（区间载体值契约：起止同日也强制两段「起,止」，onChange 统一自拼）
       1.0.9 → 1.0.10（输入框显示同步两段：onValueUpdate 改写，同日也显示「起,止」）
       1.0.10 → 1.0.11（BS3 按钮色类 CSS 兜底 btn-default/btn-dark；PHP 侧
         compatBsBtnClass 映射后经用户裁定撤回——raw/JS 注入场景终究要 CSS 兜底，
         双轨维护无意义，统一只走 CSS：markup 保留旧类由样式接管）
       1.0.11 → 1.0.12（btn-danger 兜底改软色形态并入 error 组，撤销 6.393 实心红）
       1.0.12 → 1.0.13（仅注释措辞：compatBsBtnClass 撤回后 CSS 兜底为旧类唯一接管层）
       1.0.13 → 1.0.14（--s 回填紫罗兰 + secondary 拆入彩色软色组；default 白底块
         删除归入灰组=旧 secondary 观感，二者分色）
       1.0.14 → 1.0.15（6.489ai 回摆：secondary 使用面大恢复灰组原观感、--s 回填
         撤销；default 换用户提供的 Neutral 灰蓝专属色 #617389）
       1.0.15 → 1.0.16（label-secondary 徽章白底白字不可读修复：并入 label-default
         浅灰规则——主题 --s/--sc 近白所致的存量遗留，app/旧库零使用）
       1.0.16 → 1.0.17（裸 .btn 无主题色类时兜底=btn-default 灰蓝，特异性低于
         彩色组自动让位；顺带清理灰组重复注释头）
       1.0.17 → 1.0.18（default/裸 .btn 边框降饱和提亮：--btn-border-color
         55.1% 0.028 257、透明度 0.6→0.4，公式组回落链兼容其余组不变；
         静态边框 daisyUI 原生公式不认该变量，基线规则补直接 border-color 声明）
       1.0.18 → 1.0.20（1.0.19 实测裸声明 border-color 压过 daisyUI (0,1,0)
         公式、污染全部彩色按钮静态边框——裸 .btn 规则补全主题色类排除表，
         只匹配真·无色按钮）
       1.0.20 → 1.0.21（整页表格卡撑满视口：body/main 弹性链 + 内容 card-body
         flex:1，layout.html 给 layer 弹窗页打 body.in-layer 排除）
       1.0.21 → 1.0.22（撑满卡内标题条被拉高：daisyUI 原生 .card-body flex:1 1 auto
         会平分多余高度——组内全部归零增长，仅内容区 flex:1 0 auto）
       1.0.22 → 1.0.23（表单/搜索表单输入组件左右内边距减半：input 1rem→0.5rem、
         input-sm 0.75→0.375、textarea 横向同半；select 箭头侧受 chevron 位置
         限制取 1.5rem/1.375rem，起始侧减半；仅 form-wrapper 作用域）
       1.0.23 → 1.0.24（1.0.23 实测左侧无变化：327 行 .form-wrapper input.input
         (0,2,1) 把文本框左 padding 锁 0.75rem、压过 (0,2,0) 新规则——同选择器
         靠后覆盖，按当前生效值取半：左 0.75→0.375、右 1rem→0.5）
       1.0.24 → 1.0.25（1.0.24 搜索区无变化：layout css 的 .search-form .input/.select
         !important 规则压过一切无重要值规则——就地改值减半：左 12→6、右 10→5、
         日期左 10→5；TomSelect 主表单 wrapper 左 8→6/右 8→4、内层 input 左 4px
         归零（wrapper 6px+1px 边框即文字起点）；treeselect 左 8→6。右侧含箭头
         功能性预留（vendor 强制 ts-control ≥0.75rem）不硬减）
       1.0.25 → 1.0.26（用户报 color/icon 仍无变化：二者的左 padding 12px 是组件
         自带 !important（trees/widgets），压过 6.489aq 无重要值规则——就地改 6px。
         连带修复 6.489aq 回归：date/map/pwd-eye/num-spinner/日期区间的右 padding
         是右缘图标/按钮的功能性让位但原本无重要值，被 .form-wrapper 通用右
         padding 压成 0.5rem 会文字压图标——全部补 !important；搜索/表格档在
         layout css 补 color/pwd/map/date 功能位重钉（generic 5px/4px 太小），
         清掉 trees 里 color 表格/搜索 left 23px/25px 死值）
       1.0.26 → 1.0.27（layer 弹窗标题条灰色：6.411 玻璃方案下 .layui-layer-title
         background transparent 透出 70% 半透明玻璃底、背后取样页面呈灰，
         与 iframe 内容区不透明白不一致——改为不透明 oklch(var(--b1))，
         dark 主题自动跟随）
       1.0.27 → 1.0.28（用户反馈液态玻璃页面发蓝、窗口越小越明显：色斑固定
         rem 尺寸在小视口占比失衡 + saturate 增益放大——色斑浓度减半
         （0.22/0.17/0.14/0.12→0.11/0.09/0.07/0.06）、尺寸 min(rem,vmin) 封顶、
         --glass-sat 1.8→1.3，modal/layer 硬编码 saturate(1.9) 并轨到令牌）
       1.0.28 → 1.0.29（用户指定图样「支付成功」：badge-success/-error/-warning/-info
         状态徽章软色化——底 0.1、同色细边 0.3、字 0.92，直接声明三属性压原生
         实心底公式（badge-danger 一并兜底）；按用户要求 label-* 系列旧徽章
         不动，实心底语义保留）
       1.0.29 → 1.0.30（用户再报裸词类：.the-field 上的 success/danger/error/
         warning/info 从 6.273 纯文字着色升级为软色徽章（badge-* 同语言：
         底 0.1/边 0.3/字 0.92 + 20px 徽章版式，边用 border 简写带宽度——
         tailwind preflight 归零了 border-width）；primary/secondary/default
         非状态色维持纯文字；6.273 纯文字决策作废并记录）
       1.0.30 → 1.0.31（用户反馈软色徽章边太重、角太方：边 0.3/0.35 → 0.2/0.25，
         圆角主题 --rounded-badge 0.125rem → 固定 0.25rem——badge-* 与裸词类
         两族同步）
       1.0.31 → 1.0.32（用户反馈按钮边框生硬、圆角再加大：彩色组静态边 0.6→0.4、
         default/裸 .btn 0.4→0.3，全组 hover 边 0.8→0.6（含 layout css 批量
         按钮 disabled hover 同步）；圆角在 .btn 上作用域覆盖 --rounded-btn
         令牌 0.125rem→0.25rem——输入框等其他消费者 2px 基准不动）
       1.0.32 → 1.0.33（用户报 btn-purple 不是紫色：LightYear 字面色类无 CSS
         规则、落进裸 .btn 灰蓝兜底。新增字面色组 purple/pink/brown/cyan
         （myadmin8 实际用量四色）——固定 oklch 分量串（类名即字面语义，
         不随主题 --p 漂移），软色公式复用，--btn-color (0,6,0) 压裸线
         (0,5,0)，hover 走通用公式线自动生效）
       1.0.33 → 1.0.34（用户对照旧库测试页 14 色清点：补齐最后一个缺口
         btn-yellow（字面黄 oklch 66%/0.14/85，黄相区别 warning 的橙、文字档
         L 压 66% 保白底可读，底 0.1 同 warning 档）——至此 default/primary/
         success/info/warning/danger/secondary/dark/purple/pink/cyan/yellow/
         brown/link 全覆盖）
       1.0.34 → 1.0.35（用户要求同查 label 覆盖面：对照 lightyear-admin style.css
         全 13 色，新库原有 8 色，补齐字面色 purple(#926dde)/pink(#f96197)/
         cyan(#57c7d4)/yellow(#fcc525)/brown(#8d6658)——hex 原样取自旧库，
         实心底白字形态与 label 族一致（6.489av 裁定 label 族不软色化），
         选择器三形态（独立/.label 复合/the-field 作用域）随族补全）
       1.0.35 → 1.0.36（用户反馈 divider 线偏重：daisyUI 原生 .divider:before/:after
         0.125rem（2px）→ 1px 发丝线，全局作用域统一观感）
       1.0.36 → 1.0.37（用户报 the-field.default/primary 没对齐原库：改回旧库
         字面色 default #8b95a5 / primary #33cabb——primary 旧库是恒定 teal，
         不随主题 --p 漂移；顺带补齐旧库裸词字面色 dark/purple/pink/cyan/
         yellow（纯文字形态，新库此前无定义），dark 在 dark 主题提亮一档保读；
         secondary 维持 6.273 可读性改色）
       1.0.37 → 1.0.38（用户报字面色组只有文字色没边框/背景：裸词全员徽章化
         ——default/primary/dark/purple/pink/cyan/yellow 以原库 hex 的 rgba
         tint（0.1 底/0.2 边，yellow 0.12/0.25）作底与边、原色作文字，版式
         随族 chrome；secondary 一并入组用主题自适应灰（bc 0.05/0.15/0.6）；
         dark 主题 dark 文字提亮规则保留）
       1.0.38 → 1.0.39（用户裁定 primary 跟随主题色：the-field.primary 收回
         6.489be 的字面 teal，改 oklch(var(--p)) 软色三件套——语义色随主题，
         字面色仅保留给 purple/pink 等无语义歧义类名）
       1.0.39 → 1.0.40（6.489bh items 容器吞页面滚轮：.form-wrapper
         .table-wrapper 的 overscroll-behavior:contain 只留横向，
         纵向回落 auto 恢复滚轮链）
       1.0.40 → 1.0.41（6.489bi badge-primary 并入软色徽章组：
         tint 底 0.1 / 同色边 0.2 / 主题色字 0.92，与其他 badge 一致）
       1.0.41 → 1.0.42（6.489bj badge-* 与裸词徽章统一：尺寸/字重/行高/
         留白对齐 20px/12px/600 配方；两族底/边再淡一档 0.07/0.14，
         warning 0.08/0.17）
       1.0.42 → 1.0.43（6.489bk 两族高度统一 24px：badge-* 与裸词同升
         1.5rem；表格里 .table td .the-field 的 height:100%/line-height:21px
         压过裸词组致 23px，加 (0,3,1) 覆盖规则收回定高）
       1.0.43 → 1.0.44（6.489bl 去掉两族 line-height:1：生产字体度量下
         行盒=字号挤压字形，垂直居中交由 flex+定高，行高回落上下文/
         daisyUI 原生值） */
    protected static $aver = '1.0.44';

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


