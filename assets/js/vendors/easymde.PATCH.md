# easymde.min.js 补丁说明

**适用版本**：EasyMDE v2.21.0（文件头 `/*! easymde v2.21.0 */`）
**补丁数量**：2 处（均为纯字符串替换，可用 `patch-easymde.py` 一键重打）

## 为什么还要打补丁

| 需求 | 官方选项 | 结论 |
|---|---|---|
| 按钮 class 撞 daisyUI `.table` | `toolbarButtonClassPrefix` | ✅ 官方解决，见 `MDEditor::mdeDefaults()` |
| 不自动注入 FontAwesome | `autoDownloadFontAwesome: false` | ✅ 官方解决 |
| 图标换 MDI | `iconClassMap` | ❌ **2.21.0 里是死选项**：源码只在 `options` 合并处出现 `e.iconClassMap=J({},te,e.iconClassMap||{})`，全文再无任何读取点，内置工具栏按钮的 className 直接取自内置常量表 `te` |

## 补丁点

### P1 — 图标映射表 `te` 换成 MDI

```js
// 原
var te={bold:"fa fa-bold",italic:"fa fa-italic", ... }
// 改后
var te={bold:"mdi mdi-format-bold",italic:"mdi mdi-format-italic", ... }
```

MDI 字体用的是全局 `materialdesignicons.min.css`（**v2.0.46，2060 字形**）。
`mdi-format-list-numbered`、`mdi-image-plus`、`mdi-view-split-vertical` 等较新图标在该版本不存在，
已替换为 `format-list-numbers` / `upload` / `page-layout-sidebar-right`。
**新增图标前先 grep 确认字形存在**：

```bash
grep -oE "\.mdi-xxx:before" <站点目录>/public/assets/lightyearadmin/css/materialdesignicons.min.css
```

### P2 — 图标 token 识别正则放宽（**最关键，容易漏**）

```js
// 原
/^fa([srlb]|(-[\w-]*)|$)/
// 改后
/^(fa|mdi)([srlb]|(-[\w-]*)|$)/
```

EasyMDE 的 `createToolbar()` 用这个正则从按钮 className 中挑出"图标 token"来创建 `<i>` 元素，
**其余 token 才会加到 button 上**。只改 P1 不 改 P2，`mdi`/`mdi-xxx` 匹配不上 → **根本不会生成 `<i>`，图标全部消失**。

## 升级 EasyMDE 的步骤

1. 下载新版本 `dist/easymde.min.js` 覆盖本文件（CSS 同理）。
2. 运行 `python patch-easymde.py`（幂等，重复运行无副作用）。
3. 脚本会逐个校验补丁点；若目标字符串因上游改动而找不到，会明确报 `MISS` 并退出（不会写入半改状态）。
4. 出现 `MISS` 时：人工在新文件里定位对应逻辑，确认官方是否已提供替代选项
   （重点看 `iconClassMap` 是否开始被消费——若已修复，可去掉 P1），更新本文件与脚本。
5. 跑一次 DOM 级验证（jsdom，检查图标/类名前缀/FA 注入/多语言 title）：

   ```bash
   cd assets/js/vendors
   node verify-easymde.js
   ```

   > 依赖 jsdom（`npm i jsdom`）。若未装在全局，把 `NODE_PATH` 指向 jsdom 所在的 node_modules 目录再跑。
6. 同步资源到测试站：`sync-assets.ps1`。

## 历史

- 早期版本会注入 `maxcdn.bootstrapcdn.com/font-awesome/`（CDN 已停服）。
  现在由 `autoDownloadFontAwesome: false` 官方关闭，不再需要改动注入块。
- 曾在 `builder-input.css` 里用 CSS 覆盖修 `.table` 污染，已由 `toolbarButtonClassPrefix` 取代并删除。
