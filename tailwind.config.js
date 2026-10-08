/** @type {import('tailwindcss').Config} */

// DaisyUI 官方主题定义表（用于以官方主题为「结构基座」扩展）
const daisyuiThemes = require('daisyui/src/theming/themes');

// 以官方 corporate（现代蓝）为基座：圆角 / 边框 / 按钮动画等结构变量全部沿用，
// 保证所有自定义主题「只变颜色，不变形状大小」。
const structuralBase = daisyuiThemes['corporate'];

const WHITE = '#ffffff';

// 「现代简洁」结构微调：在 corporate 的锐利结构基座上，只放大圆角 + 恢复轻微按压动画。
// 这些 token 被 daisyUI 的 btn/input/card/badge/table(rounded-box) 等统一消费，
// 改一处即全站形状/手感一致，且不动任何颜色，深浅色主题共用。
const modernStructure = {
  '--rounded-box': '0.25rem',   // 卡片 / 模态 / 表格容器圆角（近直角，仅留 4px 软边）
  '--rounded-btn': '0.125rem',  // 按钮 / 输入框 / 复选框圆角（2px 微圆角）
  '--rounded-badge': '0.125rem', // 徽章 / 标签圆角
  '--tab-radius': '0.125rem',   // 选项卡圆角
  '--animation-btn': '0.25s',   // 按钮按压过渡（corporate 原为 0，无反馈）
  '--animation-input': '0.2s',  // 输入框聚焦过渡
  '--btn-focus-scale': '0.975', // 按钮点击轻微缩放
};

// 各主题共用的语义色，取值全部来自原库 LightYearAdmin（assets/css/lightyearadmin 的
// style.min.css 按钮色板）。切主题时「只有 primary 变化」，这里所有颜色保持不变。
// 所有实底按钮一律使用白色文字（深底白字），避免官方 corporate 自动推导出「深底黑字」。
// 特殊：原库 btn-secondary 是浅灰底 + 深灰字（#4d5259），故 secondary-content 用深色。
const sharedColors = {
  'secondary': '#e4e7ea', 'secondary-content': '#4d5259',
  'accent': '#33cabb', 'accent-content': WHITE,
  'neutral': '#465161', 'neutral-content': WHITE,
  'info': '#48b0f7', 'info-content': WHITE,
  'success': '#15c377', 'success-content': WHITE,
  'warning': '#faa64b', 'warning-content': WHITE,
  'error': '#f96868', 'error-content': WHITE,
};

// 浅色主题工厂：结构沿用 corporate，只换 primary
const lightTheme = (primary) => ({
  ...structuralBase,
  ...modernStructure,
  ...sharedColors,
  'primary': primary,
  'primary-content': WHITE,
});

// 夜间主题：结构与浅色完全一致，只换底色 / 文字色 / primary
const darkTheme = {
  ...structuralBase,
  ...modernStructure,
  ...sharedColors,
  'color-scheme': 'dark',
  'primary': structuralBase['primary'],
  'primary-content': WHITE,
  'base-100': '#1d232a',
  'base-200': '#191e24',
  'base-300': '#15191e',
  'base-content': '#a6adbb',
};

module.exports = {
  content: [
    // 扫描 tpext-daisyui 库内所有模板和 PHP（包含可能内联追加的 class）
    './src/view/**/*.html',
    './src/**/*.php',
    // 仅扫描自己写的前端 JS（第三方库里不会有 Tailwind 类名, 扫了白费时间）
    './assets/js/tpextbuilder.js',
    './assets/js/tpextbuilder-alpine.js',
    './assets/js/tpext-uploader.js',
    './assets/js/tpb.js',   // 6.148 补扫：tpb.js 弹窗/toast 的工具类此前从未被编译（w-[calc]/max-w-full/mb-4 缺失）
    './assets/js/builder-elements.js', // Web Components 化改造新增：运行时 className 字面量里的工具类必须被扫到（如 min-h-[120px]）
    // DaisyUI safelist — 冗余 HTML 文件，确保 Tailwind 能扫到所有 DaisyUI class
    // （Tailwind 3 无法解析 DaisyUI 4 postcss-js Object 格式的 selector token）
    './src/view/safelist.html',
  ],
  // PHP 运行时动态拼接的 class（Tailwind JIT 只看字面量，看不见字符串拼接）
  // SizeAdapter::adjustColSize() / adjustDisplayerSize() 动态生成 col-span-* / md:col-span-*
  safelist: [
    'col-span-1','col-span-2','col-span-3','col-span-4','col-span-5','col-span-6',
    'col-span-7','col-span-8','col-span-9','col-span-10','col-span-11','col-span-12',
    'sm:col-span-1','sm:col-span-2','sm:col-span-3','sm:col-span-4','sm:col-span-5','sm:col-span-6',
    'sm:col-span-7','sm:col-span-8','sm:col-span-9','sm:col-span-10','sm:col-span-11','sm:col-span-12',
    'md:col-span-1','md:col-span-2','md:col-span-3','md:col-span-4','md:col-span-5','md:col-span-6',
    'md:col-span-7','md:col-span-8','md:col-span-9','md:col-span-10','md:col-span-11','md:col-span-12',
    'lg:col-span-1','lg:col-span-2','lg:col-span-3','lg:col-span-4','lg:col-span-5','lg:col-span-6',
    'lg:col-span-7','lg:col-span-8','lg:col-span-9','lg:col-span-10','lg:col-span-11','lg:col-span-12',
    'xl:col-span-1','xl:col-span-2','xl:col-span-3','xl:col-span-4','xl:col-span-5','xl:col-span-6',
    'xl:col-span-7','xl:col-span-8','xl:col-span-9','xl:col-span-10','xl:col-span-11','xl:col-span-12',
    // 常用宽度类（模板里动态拼接/或 Tailwind 漏扫时兜底）
    'w-40','w-48','w-56','w-64','w-72','w-80','w-96','w-112',
  ],
  theme: {
    extend: {
      // 自定义断点（默认已有 sm/md/lg/xl/2xl，一般够用）
    },
  },
  plugins: [
    require('daisyui'),
  ],
  daisyui: {
    // 多主题支持：通过 <html data-theme="xxx"> 切换，layout.html 有主题选择器
    // 全部基于 corporate 结构扩展，只变颜色；key 必须与 layout.html 的清单一致
    themes: [
      { blue:   lightTheme(structuralBase['primary']) }, // 现代蓝（默认，沿用 corporate 蓝）
      { gray:   lightTheme('#475569') },                 // 简约灰
      { green:  lightTheme('#16a34a') },                 // 清新绿
      { orange: lightTheme('#fa541c') },                 // 火山橙（6.431 对齐主流：Ant Design volcano-6，原 #ea580c 偏深）
      { purple: lightTheme('#7c3aed') },                 // 优雅紫
      { dark:   darkTheme },                             // 夜间
    ],
    darkTheme: 'dark',
    base: true,
    styled: true,
    utils: true,
    prefix: '',
    logs: false,
    themeRoot: ':root',
  },
};
