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
 daisyUI 原生值）