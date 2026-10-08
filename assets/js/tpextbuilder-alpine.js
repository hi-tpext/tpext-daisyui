/**
 * tpextbuilder-alpine.js — Alpine 组件注册（集中文件）
 *
 * 【规范】COMPONENT_SPEC.md 第 3 条：Alpine.data 注册依赖 alpine:init 事件，
 * 本文件必须在 alpine.min.js 之前加载（Builder $commonJs 中已排好顺序），
 * 模板里只输出 x-data="componentName(...)" 的 HTML 结构，禁止内联注册脚本。
 *
 * 包含组件：iconPicker / transferBox / monthPicker / yearPicker / switchBtn /
 * passwordEye / treeWidget(6.148 从 tree.html 收编) / treeCheckbox(legacy)
 */
(function (w, d) {
    'use strict';

    d.addEventListener('alpine:init', function () {

        /* ============ iconPicker — 图标选择器 ============ */
        // 从 materialdesignicons.json 动态加载全部 MDI 图标
        // JSON 路径和旧库 Icon.php 保持一致
        var MDI_JSON_URL = '/assets/tpextdaisyui/js/fontIconPicker/fontjson/materialdesignicons.json';
        var ICON_LOADED = null;

        function loadIcons() {
            if (ICON_LOADED) return ICON_LOADED;
            ICON_LOADED = fetch(MDI_JSON_URL)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.glyphs && data.glyphs.length) {
                        return data.glyphs.map(function (g) { return g.css; });
                    }
                    return [];
                })
                .catch(function (err) {
                    console.error('[iconPicker] MDI JSON load failed:', err);
                    return [];
                });
            return ICON_LOADED;
        }

        Alpine.data('iconPicker', function (id, current) {
            return {
                open: false,
                q: '',
                current: current || '',
                all: [],
                loaded: false,
                loading: true,

                // Alpine 生命周期钩子 — 初始化后 fetch 图标
                init() {
                    var self = this;
                    loadIcons().then(function (icons) {
                        self.all = icons;
                        self.loaded = true;
                        self.loading = false;
                    });
                },

                get filtered() {
                    var q = this.q.toLowerCase();
                    if (!q) return this.all.slice(0, 200);
                    return this.all.filter(function (ic) { return ic.toLowerCase().indexOf(q) >= 0; });
                },
                // 6.152 载体解析：优先 x-ref（items 克隆行会把 id 重写成 __new__N，
                // 构造参数里的旧 id 查不到元素，选图标会静默写不回值），
                // 回退 getElementById 兼容不带 x-ref 的旧结构。
                // 惰性取值而非 init 时缓存：$refs 在子节点遍历完成后才可用
                carrier() {
                    return (this.$refs && this.$refs.carrier) || d.getElementById(id);
                },

                // 6.152 编码输入框（模板里的可见 input）→ 隐藏载体。
                // 输入即同步 current/载体值（未失焦就提交也不丢），change（回车/失焦）
                // 才派发 change 事件，避免每个按键都惊动表单监听方。
                // 归一化目标 = materialdesignicons.json 的 css 原样（'mdi mdi-star'：
                // 基础类 + 字形类，历史数据 tp_admin_menu.icon 即此格式），
                // 用户无论输 'star'/'mdi-star'/'mdi mdi-star' 都落到同一形态
                applyCode(v, fireChange, srcEl) {
                    var raw = String(v == null ? '' : v).trim().replace(/\s+/g, ' ');
                    var glyph = raw.replace(/^(?:mdi[\s-]+)+/i, '').trim();
                    // 只剩/只输了 mdi 前缀（'mdi'、'mdi-'）视为清空，别生成 'mdi mdi-mdi'
                    if (/^mdi[\s-]*$/i.test(glyph)) {
                        glyph = '';
                    }
                    var code = glyph ? ('mdi mdi-' + glyph) : '';
                    // 输入中途的 'mdi'/'mdi-' 只是前缀还没输完，保持原选中避免图标预览闪空
                    if (!fireChange && !code) {
                        code = this.current;
                    }
                    this.current = code;
                    var el = this.carrier();
                    if (el) {
                        el.value = code;
                        if (fireChange) el.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    // 失焦时把输入框文本回写为归一化编码（'star' → 'mdi mdi-star'）。
                    // 只在 change 阶段回写：输入中回写会把光标顶到末尾
                    if (srcEl && srcEl.value !== code) {
                        srcEl.value = code;
                    }
                },
                pick(ic) {
                    this.current = ic;
                    var el = this.carrier();
                    if (el) { el.value = ic; el.dispatchEvent(new Event('change', { bubbles: true })); }
                    this.open = false;
                }
                // 6.157 clear() 已删：icon 不需要清除 ×，清空=清空编码输入框
                // （applyCode 归一化空值），month/year 的 clear 不受影响
            };
        });

        /* ============ transferBox — 穿梭框 ============ */
        // options 多形态归一化：树形数组 / {value:name} 关联对象 / 纯文本数组
        function normalizeTransferOptions(raw) {
            function toNode(n, key) {
                if (n === null || n === undefined) return null;
                if (typeof n === 'object') {
                    var val = String(n.value !== undefined ? n.value : (key !== undefined ? key : ''));
                    var name = n.name !== undefined ? String(n.name) : (n.label !== undefined ? String(n.label) : val);
                    return { value: val, name: name };
                }
                return { value: String(key !== undefined ? key : n), name: String(n) };
            }
            if (raw === null || raw === undefined) return [];
            return (Array.isArray(raw) ? raw : Object.keys(raw))
                .map(function(n, i) { return toNode(Array.isArray(raw) ? n : raw[n], Array.isArray(raw) ? undefined : n); })
                .filter(Boolean);
        }

        Alpine.data('transferBox', function (id, options, selectedStr, disabled) {
            return {
                qLeft: '',
                qRight: '',
                selected: String(selectedStr || '').split(',').filter(Boolean).map(String),
                locked: (disabled || []).map(function(v) { return String(v).replace(/^-/, ''); }),
                all: normalizeTransferOptions(options),
                get leftList() {
                    var q = this.qLeft.toLowerCase(), self = this;
                    return this.all.filter(function (o) {
                        return self.selected.indexOf(o.value) < 0 && o.name.toLowerCase().indexOf(q) >= 0;
                    });
                },
                get rightList() {
                    var q = this.qRight.toLowerCase(), self = this;
                    return this.all.filter(function (o) {
                        return self.selected.indexOf(o.value) >= 0 && o.name.toLowerCase().indexOf(q) >= 0;
                    });
                },
                isLocked(v) { return this.locked.indexOf(v) >= 0; },
                // 6.153 载体解析同 iconPicker：items 克隆行会重写 id 而 x-data 字符串不变
                carrier() {
                    return (this.$refs && this.$refs.carrier) || d.getElementById(id);
                },
                // 6.153 提交域名字取自「名字原型」元素（模板里 disabled 的那个 hidden）。
                // x-for 生成的 input 位于 <template> 内容中，items 克隆行的 name 重写
                // （querySelectorAll 穿不进 template 内容）够不到它们，行键 [__new__]
                // 不会被替换成 [__new__N]，值就落到幽灵行键上丢失。
                // 原型元素是行的普通子节点，重写会命中，这里只读它的最终 name
                get arrayName() {
                    var p = this.$refs && this.$refs.nameProto;
                    return p ? (p.getAttribute('name') || '') : '';
                },
                pick(v) {
                    if (this.selected.indexOf(v) < 0) { this.selected.push(v); this.sync(); }
                },
                remove(v) {
                    if (this.isLocked(v)) return;
                    this.selected = this.selected.filter(function (x) { return x !== v; });
                    this.sync();
                },
                moveAll() {
                    var self = this;
                    this.all.forEach(function (o) {
                        if (self.selected.indexOf(o.value) < 0) self.selected.push(o.value);
                    });
                    this.sync();
                },
                removeAll() {
                    var self = this;
                    this.selected = this.selected.filter(function (v) { return self.isLocked(v); });
                    this.sync();
                },
                sync() {
                    var el = this.carrier();
                    if (el) { el.value = this.selected.join(','); el.dispatchEvent(new Event('change', { bubbles: true })); }
                }
            };
        });

        /* ============ monthPicker — 月份选择器 ============ */
        Alpine.data('monthPicker', function (id, initial) {
            return {
                open: false,
                current: initial || '',
                months: Array.from({length: 12}, function(_, i) {
                    return { value: String(i + 1), label: String(i + 1).padStart(2, '0') };
                }),
                // 6.153 载体解析同 iconPicker（items 克隆行 id 被重写，x-data 里的旧 id 查不到）
                carrier() {
                    return (this.$refs && this.$refs.carrier) || d.getElementById(id);
                },
                pick(v) {
                    this.current = v;
                    var el = this.carrier();
                    if (el) { el.value = v; el.dispatchEvent(new Event('change', { bubbles: true })); }
                    this.open = false;
                },
                clear() {
                    this.current = '';
                    var el = this.carrier();
                    if (el) { el.value = ''; el.dispatchEvent(new Event('change', { bubbles: true })); }
                }
            };
        });

        /* ============ yearPicker — 年份选择器 ============ */
        Alpine.data('yearPicker', function (id, initial) {
            var now = new Date().getFullYear();
            var initYear = parseInt(initial) || 0;
            return {
                open: false,
                // 6.153 current 只反映真实值：原先空值时也取当前年，导致触发按钮显示
                // "2026" 且常驻清除 ×，而提交值其实是空的（UI 谎报已选）。
                // 十年面板的定位仍用当前年（decadeStart）
                current: initYear ? String(initYear) : '',
                decadeStart: (initYear || now) - 5,
                get rangeLabel() { return this.decadeStart + ' - ' + (this.decadeStart + 11); },
                get years() {
                    var arr = [];
                    for (var i = this.decadeStart; i < this.decadeStart + 12; i++) arr.push(i);
                    return arr;
                },
                prevDecade() { this.decadeStart -= 12; },
                nextDecade() { this.decadeStart += 12; },
                // 6.153 载体解析同 iconPicker（items 克隆行 id 被重写，x-data 里的旧 id 查不到）
                carrier() {
                    return (this.$refs && this.$refs.carrier) || d.getElementById(id);
                },
                pick(y) {
                    this.current = String(y);
                    var el = this.carrier();
                    if (el) { el.value = y; el.dispatchEvent(new Event('change', { bubbles: true })); }
                    this.open = false;
                },
                clear() {
                    this.current = '';
                    var el = this.carrier();
                    if (el) { el.value = ''; el.dispatchEvent(new Event('change', { bubbles: true })); }
                }
            };
        });

        /* ============ switchBtn — 开关 ============ */
        // 从 switchbtn.html 的内联 x-data 收编（规范 3：模板禁止内联逻辑）。
        // 值契约：开关值走隐藏 input（on/off 由 PHP pair() 传入）；
        // init 依复选框状态同步一次，克隆行（Alpine MutationObserver 重新初始化）同样成立。
        Alpine.data('switchBtn', function (onValue, offValue) {
            return {
                init() {
                    this.$refs.hidden.value = this.$refs.switchEl.checked ? onValue : offValue;
                },
                get checked() {
                    return this.$refs.switchEl.checked;
                },
                onToggle() {
                    this.$refs.hidden.value = this.$refs.switchEl.checked ? onValue : offValue;
                }
            };
        });

        /* ============ passwordEye — 密码显示/隐藏切换 ============ */
        // password.html 的右侧眼睛按钮：只翻转 shown，type/图标/aria 全由模板的
        // Alpine 绑定驱动（:type="shown ? 'text' : 'password'"）。提示文案由 PHP
        // 经 __blang 传入，克隆行由 Alpine MutationObserver 自动接管。
        Alpine.data('passwordEye', function (showText, hideText) {
            return {
                shown: false,
                get title() {
                    return this.shown ? hideText : showText;
                }
            };
        });

        /* ============ treeWidget — 左侧树（tree.html） ============ */
        // 从 tree.html 的内联 alpine:init 注册收编（规范 3：模板禁止内联注册脚本）。
        // PHP 层只负责数据传递，交互全在 Alpine；select() 的表单联动载体下钻见 6.146。
        // onClickScript 保留原契约位（Tree.php 可经模板赋值，当前无调用方）。
        Alpine.data('treeWidget', function (id, data, expandAll, trigger, expandNodeOnclick) {
            return {
                nodes: data,
                selected: null,
                opened: new Set(),

                init() {
                    if (expandAll) {
                        this.openAll(this.nodes);
                    }
                },

                openAll(nodes) {
                    for (const node of nodes) {
                        if (this.hasChildren(node)) {
                            this.opened.add(String(node.id));
                            this.openAll(node.children);
                        }
                    }
                },

                hasChildren(node) {
                    return Array.isArray(node.children) && node.children.length > 0;
                },

                isOpen(node) {
                    return this.opened.has(String(node.id));
                },

                // 6.287 左侧树改用表格树同款参考线（tline）：把嵌套树摊平成「当前可见行」
                // 一维数组，每行带上参考线信息（祖先 guide 数 / 自身 elbow / 是否末枝），
                // 模板单层 x-for 渲染——顺带修掉旧模板写死三层的递归缺口（四级以下渲染不出来）。
                // 末枝判定沿用表格树 6.281 规则：本行是末子项 且 父级无后续兄弟才用 └。
                // 缩进层级 = depth：depth 0 无前缀（同表格树根级），每层 24px 由 tline 槽位承担。
                get flatNodes() {
                    const rows = [];

                    const walk = (nodes, depth, parentHasNext) => {
                        nodes.forEach((node, i) => {
                            const isLast = i === nodes.length - 1;
                            const hasChildren = this.hasChildren(node);
                            const open = hasChildren && this.isOpen(node);

                            rows.push({
                                id: node.id,
                                text: node.text,
                                node: node,
                                hasChildren: hasChildren,
                                open: open,
                                guides: Math.max(depth - 1, 0),
                                elbow: depth > 0,
                                last: isLast && !parentHasNext,
                            });

                            if (open) {
                                walk(node.children, depth + 1, !isLast);
                            }
                        });
                    };

                    walk(this.nodes, 0, false);

                    return rows;
                },

                toggle(node) {
                    const key = String(node.id);
                    if (this.opened.has(key)) {
                        this.opened.delete(key);
                    } else {
                        this.opened.add(key);
                    }
                },

                select(node) {
                    this.selected = node;

                    if (expandNodeOnclick && this.hasChildren(node)) {
                        this.toggle(node);
                    }

                    // trigger() 绑定的表单元素联动 — 写入表单元素并刷新搜索
                    if (trigger) {
                        let $input = document.querySelector(`form.search-form ${trigger}`);
                        if (!$input) {
                            $input = document.createElement('input');
                            $input.type = 'hidden';
                            $input.name = trigger.replace(/^\.row-/, '');
                            $input.className = trigger.replace(/^\./, '');
                            document.querySelector('form.search-form').appendChild($input);
                        }
                        const val = node.id === '__all__' ? '' : node.id;
                        // 6.146 row-* 类已在 Web Components 化后移到宿主（x-select 等），
                        // 宿主上没有 tomselect，真实载体是内部 select/input——先下钻解析
                        let $carrier = $input;
                        if (!$carrier.tomselect && !/^(select|input|textarea)$/i.test($carrier.tagName)) {
                            let inner = $carrier.querySelector('select, input:not([type=hidden]), textarea');
                            if (inner) $carrier = inner;
                        }
                        // TomSelect：优先用实例 API 设置值，让 UI 同步
                        if ($carrier.tomselect) {
                            var ts = $carrier.tomselect;
                            if (val === '') {
                                // 6.296 树 All（空值）：clear() 回到占位清空选值。
                                // 不能走 addOption({value:'',text:'All'})——会把「All」
                                // 作为空值项永久塞进下拉选项里（用户：应清空而非加入选项）
                                ts.clear();
                            } else {
                                // 先确保该 value 对应的 option 在 TomSelect 的 options 里
                                // 远程 select 的 fetch 还没做，但设值后 open 会触发 load 并回填
                                var existingOption = ts.options[val];
                                if (!existingOption) {
                                    ts.addOption({ value: val, text: node.text });
                                }
                                ts.setValue(val);
                            }
                            // 关掉可能打开的下拉
                            ts.close();
                        } else {
                            $carrier.value = val;
                        }
                        $carrier.dispatchEvent(new Event('change', { bubbles: true }));
                        // 刷新表格（搜索栏的 refresh 按钮）
                        const $refresh = document.querySelector('form.search-form .row-refresh');
                        if ($refresh) $refresh.click();
                    }

                    // onClick 回调 — Tree.php 的 onClick() 方法可以传一段 JS
                    if (this.onClickScript) {
                        try { eval(this.onClickScript); } catch (e) {}
                    }
                },

                onClickScript: '' // 由外层 PHP 模板赋值
            };
        });
    });
})(window, document);
