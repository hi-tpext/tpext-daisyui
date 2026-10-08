# tpext-daisyui

把 `tpext-builder`（Bootstrap 3 + jQuery + jsTree）用 **Tailwind CSS + DaisyUI + Alpine.js** 重构后的 PHP 后台 CRUD UI 库。

**目标**：PHP 层 API 保持兼容（同一个 `tpext\builder\Builder` 类名、同一个 CRUD 生成器用法），前端 UI / JS 全换掉。

---

## 目录结构

```
tpext-daisyui/
├── src/                        # PHP 源码（PSR-4 → tpext\builder\）
│   ├── common/                 # 核心：Builder / Table / Form / Search / Toolbar / Row / Column / Layer
│   ├── displayer/              # 表单 & 表格单元格渲染器（Text / Select / Date / SwitchBtn / Tree / ...）
│   ├── form/                   # 表单子组件（Step / FieldsContent / ItemsContent / FRow / FWrapper）
│   ├── table/                  # 表格子组件（Paginator / SRow / SWrapper / TColumn / TWrapper）
│   ├── toolbar/                # 工具栏按钮（ActionBtn / LinkBtn / DropdownBtns / Bar / BWrapper）
│   ├── tree/                   # 树组件（Tree.php，原 JSTree / ZTree 已移除）
│   ├── traits/                 # 复用 trait：HasWhen / HasBuilder / HasAutopost / HasIAED / ...
│   ├── search/                 # 搜索组件 tab
│   ├── controller/             # 内置控制器（Attachment / Import / Upload）
│   ├── lang/                   # 语言包
│   ├── view/                   # ThinkPHP 模板（.html）
│   │   ├── layout.html         # 页面骨架
│   │   ├── table.html          # 表格主模板
│   │   ├── form.html           # 表单主模板
│   │   ├── toolbar.html
│   │   ├── displayer/          # 每个 input 控件的模板
│   │   ├── table/              # 表格子模板（search / tab / fieldscontent）
│   │   ├── form/               # 表单子模板
│   │   ├── toolbar/            # 工具栏子模板
│   │   ├── layer/              # 弹窗关闭按钮模板
│   │   └── tree/tree.html
│   ├── helper.php              # 全局辅助函数
│   ├── config.php              # 默认配置
│   ├── Install.php             # 扩展安装器
│   └── Service.php             # ThinkPHP Service，注册 HttpEnd 清理
│
├── view/                       # (无) 模板内嵌在 src/view/ 下
│
├── assets/                     # 发布到 /assets/tpextbuilder/ 的静态资源
│   ├── css/
│   │   ├── builder-tw.css        # Tailwind 入口（编译为 builder.css 的源文件）
│   │   ├── builder.css           # ✅ 编译产物，PHP 运行时实际引用的文件
│   │   ├── builder-core.css      # ⭐ 手写直载：全局/密度/布局/BS3 兼容
│   │   ├── builder-tomselect.css # ⭐ TomSelect 集成
│   │   ├── builder-trees.css     # ⭐ jstree / treeselectjs / pickr
│   │   ├── builder-flatpickr.css # ⭐ flatpickr 全族（日期/月份面板）
│   │   ├── builder-widgets.css   # ⭐ 上传/Transfer/Number/Password/编辑器
│   │   ├── builder-core-layout.css # ⭐ 搜索区/表格内缩减/行距/btn-hover
│   │   └── tom-select.min.css
│   └── js/
│       ├── tpb.js              # 兼容层：$.ajax → fetch / alert → tpbToast / layer → 兼容 API
│       ├── tpextbuilder.js     # 业务逻辑（原生 JS，无 jQuery）
│       └── vendors/            # alpine.min.js / axios.min.js / flatpickr / tom-select
│
├── tailwind.config.js          # Tailwind + DaisyUI 主题配置
├── postcss.config.js           # PostCSS → autoprefixer
├── package.json                # 前端构建脚本
├── composer.json               # PHP 包配置
└── TASK_LIST.md                # 迁移任务清单
```

---

## PHP 架构不变

tpext-builder 的 PHP 类名、方法签名、`new Builder()` 用法全部保留。以下是核心骨架：

```
Builder（根）
├── Table        → Table（列表页）
│   ├── Column[] → Column.php（列定义）
│   ├── Search   → Search.php（搜索表单）
│   └── Toolbar  → Toolbar.php（顶部按钮）
├── Form         → Form.php（表单页）
│   └── Row[]    → Row.php
│       └── Displayer[] → Text / Select / Date / SwitchBtn / Tree / ...
└── Layer.php    → 弹窗 / 抽屉
```

**改动点**全部在这三个地方：

| 层级 | 原实现 | 现实现 |
|------|--------|--------|
| 模板 | Bootstrap 3 `<div class="col-md-6">` | DaisyUI `<div class="join">` `<table class="table table-zebra">` |
| JS | jQuery `$('#id').on('click')` | 原生 `document.addEventListener` + Alpine.js `x-data` |
| CSS | bootstrap.min.css + 自定义 LESS | Tailwind + DaisyUI 编译产物 `builder.css` |

---

## 前端依赖

PHP 运行时会自动注入以下资源（见 `Builder::commonVars()` 第 840–870 行）：

| 资源 | 用途 | 来源 |
|------|------|------|
| `/assets/tpextbuilder/css/builder.css` | Tailwind + DaisyUI + 兼容层（**编译产物**） | `assets/css/builder-tw.css` 编译 |
| `/assets/tpextbuilder/js/vendors/alpine.min.js` | Alpine.js 响应式 | 从 CDN 下载后放到 vendors |
| `/assets/tpextbuilder/js/vendors/axios.min.js` | HTTP 请求 | 同上 |
| `/assets/tpextbuilder/js/vendors/tom-select.min.js` | 多选下拉 | 同上 |
| `/assets/tpextbuilder/js/vendors/flatpickr.min.js` + zh | 日期选择器 | 同上 |
| `/assets/tpextbuilder/js/tpb.js` | **兼容层**：把旧 jQuery API 翻译到原生实现 | 手写 |
| `/assets/tpextbuilder/js/tpextbuilder.js` | 业务逻辑（表格、表单、搜索） | 手写 |

> **没有 jQuery**，没有 Bootstrap，没有 jsTree / zTree。

---

## 如何构建 builder.css

Tailwind 的 JIT 模式——**扫描模板中的 class，只编译实际用到的**。所以新增/修改模板后都要重新 build。

### 1. 安装依赖（一次性）

```powershell
cd tpext-daisyui
npm install
```

### 2. 修改源码

- **主题色、组件变量** → `tailwind.config.js` → `daisyui.themes`
- **新增自定义 CSS** → 按族写入 `assets/css/builder-{core,tomselect,trees,flatpickr,widgets,core-layout}.css` 对应文件（见 `CSS_GOVERNANCE_PLAN.md` 族归属表），禁止用 @layer（6.148 已删@layer）
- **模板里写 DaisyUI class** → `src/view/**/*.html`（Tailwind 会自动扫到）

### 3. 编译

```powershell
# 一次性构建（生产环境，压缩）
npm run build

# 开发时监听（改模板自动重编译，不压缩）
npm run watch
```

等价于手动执行：

```powershell
npx tailwindcss -i assets/css/builder-tw.css -o assets/css/builder.css --minify
```

### 4. 发布到运行时

把整个 `assets/` 目录复制到项目的 `public/assets/tpextbuilder/` 下，让 `/assets/tpextbuilder/css/builder.css` 能被访问到。

ThinkPHP 项目里一般用 `composer8 dump-optimize` 或者手动 symlink。

---

## builder.css 里有什么

编译产物 `assets/css/builder.css`（约 90 KB / 压缩后更小）由以下几部分叠加：

```
tailwind base;                ← Tailwind reset + oklch 颜色变量（--p/--b1/--bc...）
tailwind components;          ← DaisyUI 所有组件 class（.btn / .input / .table / .join / .card / .modal...）
tailwind utilities;           ← Tailwind utility（.flex / .gap-2 / .rounded-box / .bg-base-200...）
@layer components {            ← builder-input.css 里自定义的
  .tpb-toast { ... }
}
/* 兼容层兜底 */
.panel / .form-group / .btn-default / .input-group-addon / ...
```

**注意**：DaisyUI 是 Tailwind 插件。只要模板里引用了 `.btn` 就会被编译进去，没用到的 `.hero` 不会出现。所以：

- 新增模板 class → 重新 build → CSS 变大
- 删掉模板 class → 重新 build → CSS 变小（JIT 特性）

---

## 主题切换

`layout.html` 里的 `<html data-theme="corporate">` 控制 DaisyUI 主题：

```html
<!-- 可用主题见 tailwind.config.js 的 daisyui.themes 数组 -->
<html data-theme="corporate">   <!-- 默认 -->
<html data-theme="dark">       <!-- 暗色 -->
<html data-theme="nord">       <!-- 北欧冷淡 -->
<html data-theme="admin">      <!-- 自定义品牌主题 -->
```

运行时切换（Alpine.js）：

```javascript
document.documentElement.setAttribute('data-theme', 'dark');
```

---

## 新增一个 Displayer

以 `Phone.php`（假设）为例：

1. 在 `src/displayer/Phone.php` 写 PHP 类，继承 `Field`
2. 在 `src/view/displayer/phone.html` 写模板，用 DaisyUI class：
   ```html
   {include file="$labeltempl" /}
   <div class="{$size[1]}">
       <input class="input input-bordered w-full {$class}" ...>
   </div>
   ```
3. `npm run build`（让 Tailwind 扫描到模板里的新 class）
4. 完成。PHP 用法和原来一样：`$form->phone('mobile', '手机号')`

---

## 和 tpext-builder 的差异

| 方面 | tpext-builder | tpext-daisyui |
|------|--------------|---------------|
| CSS 框架 | Bootstrap 3 | Tailwind CSS + DaisyUI |
| JS 框架 | jQuery | 原生 ES + Alpine.js |
| 树组件 | jsTree / zTree | Alpine.js 模板重写 |
| 表格样式 | `.table` + 自定义 | `.table .table-zebra .table-hover` |
| 弹窗 | layer.js | DaisyUI modal + 手写兼容层 |
| 文件上传 | WebUploader | axios + 原生 FormData |
| 表单校验 | jquery-validate | 原生 HTML5 约束校验（formSubmit 入口 reportValidity，规则由服务端以 HTML 属性驱动；经评估不引入 zod——v3 无浏览器构建且规则会与服务端重复） |
| CSS 入口 | LESS 多文件 | `builder-{core,tomselect,trees,flatpickr,widgets,core-layout}.css` 按族分文件 |

详细迁移进度见 `TASK_LIST.md`。
