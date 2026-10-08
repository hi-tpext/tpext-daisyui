/**
 * builder-elements.js — tpext-daisyui 表单组件 Web Components 定义
 *
 * 架构契约（WEBCOMPONENTS_PLAN.md §4，强制）：
 *  - light DOM，禁用 shadow DOM：内部 input 参与表单提交、daisyUI 全局样式照常生效
 *  - 内部 DOM 必须能从宿主属性完整重建（克隆安全：cloneNode 复制属性而非 property，
 *    items 复制行 / table AJAX 替换后插入即自动升级，无需任何重放脚本）
 *  - connectedCallback = 解析 cfg → 构建 DOM → 初始化第三方库；disconnectedCallback = 销毁实例
 *    （table 翻页/AJAX 刷新移除旧元素时不再泄漏）
 *  - 宿主元素位于 field-row 的包裹 div 内部，继承组件自身 class（如 date-input-wrap），
 *    不触碰 .field-row > div:nth-child(2) 族边界选择器（WEBCOMPONENTS_PLAN.md §9 修订）
 *  - 本文件由 Builder $commonJs 在全部第三方库之后引入；customElements.define 幂等，
 *    重复加载安全；已存在于页面中的标签在 define 时自动升级
 *
 * 多语言：读 window.__blang（与 tpextbuilder.js 同机制）。
 */
(function (w, d) {
    'use strict';

    var BE = w.BuilderElements = w.BuilderElements || {};

    // CKEditor 默认 autoInline 会把页面上其它富文本库（wangEditor 等）创建的
    // contenteditable 区误建为内联编辑器，聚焦时弹出 z-index 9999 的悬浮工具栏。
    // 本库只使用 CKEDITOR.replace（显式 textarea 替换），关闭自动内联无副作用。
    // 本文件在全部第三方库之后、DOMContentLoaded 之前执行，早于 CK 的可编辑区扫描。
    if (w.CKEDITOR) {
        w.CKEDITOR.disableAutoInline = true;
    }

    /** 读多语言 key（帮助函数，模板/库脚本也可用） */
    BE.blang = function (key, fallback) {
        return (w.__blang && w.__blang[key]) || fallback || key;
    };

    /** define 守卫：同名元素重复 define 会抛错，这里静默跳过 */
    BE.define = function (name, ctor) {
        if (w.customElements && !w.customElements.get(name)) {
            w.customElements.define(name, ctor);
        }
    };

    /**
     * 基类：cfg 解析 + 幂等守卫 + 属性搬运
     * 子类覆盖 build()（构建内部 DOM）/ init()（初始化库）/ deinit()（销毁库实例）
     */
    BE.Base = class extends HTMLElement {
        connectedCallback() {
            if (this.__ready__ || this.__pending__) return;
            this.__pending__ = true;

            var self = this;
            var start = function () {
                self.__pending__ = false;
                if (!self.isConnected || self.__ready__) return;
                self.__ready__ = true;

                var raw = self.getAttribute('cfg') || '';
                self.cfg = {};
                if (raw) {
                    try {
                        self.cfg = JSON.parse(raw);
                    } catch (e) {
                        self.cfg = {};
                    }
                }

                self.build();
                self.init();
            };

            // 服务端渲染内部控件的元素（x-select 等）：文档解析期 upgrade 时子元素
            // 可能尚未被解析（HTMLParser 是边解析边 upgrade 的），统一延迟到
            // 解析完成 / 当前任务结束后再 build+init
            if (d.readyState === 'loading') {
                d.addEventListener('DOMContentLoaded', start);
            } else {
                w.setTimeout(start, 0);
            }
        }

        disconnectedCallback() {
            if (!this.__ready__) return;
            this.__ready__ = false;
            this.deinit();
        }

        /* 子类钩子 */
        build() {}
        init() {}
        deinit() {}

        /**
         * 把宿主上的表单契约属性搬运到内部控件。
         * id 搬到控件后从宿主移除：id 全文档唯一，label[for] 需要指向可聚焦的 input；
         * items 复制行的 id 重写发生在插入前（宿主上），插入升级时再搬运，次序天然正确。
         */
        carryFormAttrs(input) {
            var self = this;
            ['placeholder', 'autofocus'].forEach(function (k) {
                var v = self.getAttribute(k);
                if (v !== null) {
                    input.setAttribute(k, v);
                    self.removeAttribute(k);
                }
            });
            ['required', 'readonly', 'disabled'].forEach(function (k) {
                if (self.hasAttribute(k)) {
                    input.setAttribute(k, '');
                    self.removeAttribute(k);
                }
            });

            var name = this.getAttribute('name');
            if (name !== null) {
                input.setAttribute('name', name);
                this.removeAttribute('name');
            }

            // 6.409 when() 受控字段的真名存放在宿主 data-name 上（When.php 用 extNameKey 把
            // 渲染 name 换成了防重名后缀），要一并带给内部 input：HasWhen::whenScript 显隐
            // 切换恢复的是内部 input 的 dataset.name，缺了它会按带后缀的怪名提交，服务端
            // 收不到真实字段名（x-date 实测 open_date 变成 open_date_pay_type<md5>）。
            // 宿主上的 data-name 副本保留：whenScript 只遍历 input/select/textarea，不冲突。
            var dataName = this.getAttribute('data-name');
            if (dataName !== null && !input.hasAttribute('data-name')) {
                input.setAttribute('data-name', dataName);
            }

            var val = this.getAttribute('value');
            if (val !== null) {
                input.value = val;
                this.removeAttribute('value');
            }

            var id = this.getAttribute('id');
            if (id) {
                input.setAttribute('id', id);
                this.removeAttribute('id');
            }
        }
    };

    /* ============================================================
     * x-date — Date / DateTime / Time（flatpickr 单输入框）
     *
     * cfg：flatpickr options JSON（服务端已并入 defaultDate）；
     *      Date = {dateFormat:'Y-m-d', enableTime:false, allowInput:true, ...}
     *      DateTime = {..., enableTime:true, enableSeconds:true, time_24hr:true}
     *      Time = {..., noCalendar:true}（不挂年份面板）
     * data-icon：图标 mdi class（Time=mdi-clock，其余 mdi-calendar）
     *
     * 内部 DOM 与旧 date.html 同构：.date-input-wrap（宿主自身）> .date-input + .date-icon-btn，
     * 既有日期 CSS 无需任何改动。
     * ============================================================ */
    /* 6.171 flatpickr 的时/分/秒/年输入框原为 <input type="number">：原生 spin
     * 按钮、校验态、焦点外观各浏览器不一致（用户反馈丑、不协调）。
     * flatpickr 的事件处理并不依赖 number 类型——取值 parseInt(...)||0 容错、
     * 方向键/滚轮自行绑定、step/min/max 属性照读，其 IE9 分支本就退化为
     * type=text + pattern=\d*。故实例化后统一换成 text+inputmode=numeric
     * （maxlength 时/分=2 在 text 下仍生效），各浏览器渲染完全一致。 */
    function slimNumInputs(fp) {
        if (!fp || !fp.calendarContainer) return;
        var nums = fp.calendarContainer.querySelectorAll('input[type="number"]');
        for (var i = 0; i < nums.length; i++) {
            nums[i].type = 'text';
            nums[i].setAttribute('inputmode', 'numeric');
            nums[i].setAttribute('pattern', '\\d*');
        }
    }

    var XDate = class extends BE.Base {
        build() {
            var input = d.createElement('input');
            input.type = 'text';
            input.className = 'input input-bordered w-full date-input';
            this.carryFormAttrs(input);

            var btn = d.createElement('button');
            btn.type = 'button';
            btn.className = 'date-icon-btn';
            var icon = d.createElement('i');
            icon.className = 'mdi ' + (this.getAttribute('data-icon') || 'mdi-calendar');
            btn.appendChild(icon);
            var self = this;
            btn.addEventListener('click', function () {
                self.__input.focus();
            });

            this.appendChild(input);
            this.appendChild(btn);
            this.__input = input;
        }

        init() {
            if (typeof w.flatpickr === 'undefined') return;

            var opts = {};
            for (var k in this.cfg) {
                opts[k] = this.cfg[k];
            }

            this.__fp = w.flatpickr(this.__input, opts);
            slimNumInputs(this.__fp);
            // 6.124 年份面板 / 6.174 月份面板：实例构造完成后再挂载（不能走 config.plugins，那时日历还没建好）
            if (this.__fp && !opts.noCalendar) {
                if (w.__fpYearPanel) w.__fpYearPanel(this.__fp);
                if (w.__fpMonthPanel) w.__fpMonthPanel(this.__fp);
            }
        }

        deinit() {
            if (this.__fp) {
                this.__fp.destroy();
                this.__fp = null;
            }
            this.__input = null;
        }
    };
    BE.define('x-date', XDate);

    /* ============================================================
     * x-daterange — DateRange / DateTimeRange（flatpickr range 模式）
     *
     * DOM 契约与旧 daterange.html 同构：隐藏载体（id/name/value 落在这）
     * + 可见只读输入框（id = 载体id + '-range'）+ 图标按钮。
     * 提交契约：hidden 载体值 = flatpickr dateStr（zh locale 区间分隔为「至」）。
     *
     * cfg：{locale:'zh', mode:'range', dateFormat, enableTime?...}（PHP 端组装）
     * data-icon：图标 mdi class
     * 注意：flatpickr 钩子按数组遍历，onChange 必须包成数组（6.124 的坑）。
     * ============================================================ */
    var XDateRange = class extends BE.Base {
        build() {
            var carrier = d.createElement('input');
            carrier.type = 'hidden';

            var id = this.getAttribute('id');
            if (id) {
                carrier.setAttribute('id', id);
                this.removeAttribute('id');
            }
            var name = this.getAttribute('name');
            if (name !== null) {
                carrier.setAttribute('name', name);
                this.removeAttribute('name');
            }
            var val = this.getAttribute('value');
            if (val !== null) {
                carrier.value = val;
                this.removeAttribute('value');
            }
            this.__carrier = carrier;

            var input = d.createElement('input');
            input.type = 'text';
            input.className = 'input input-bordered w-full date-input';
            if (id) input.setAttribute('id', id + '-range'); // 旧模板可见框 id 派生形态，CSS/脚本按它定位
            input.readOnly = true; // 旧模板可见框固定 readonly，区间模式不支持手输
            var placeholder = this.getAttribute('placeholder');
            if (placeholder !== null) {
                input.setAttribute('placeholder', placeholder);
                this.removeAttribute('placeholder');
            }
            ['required', 'disabled'].forEach(function (k) {
                if (this.hasAttribute(k)) {
                    input.setAttribute(k, '');
                    this.removeAttribute(k);
                }
            }, this);

            var btn = d.createElement('button');
            btn.type = 'button';
            btn.className = 'date-icon-btn';
            var icon = d.createElement('i');
            icon.className = 'mdi ' + (this.getAttribute('data-icon') || 'mdi-calendar-range');
            btn.appendChild(icon);
            var self = this;
            btn.addEventListener('click', function () {
                self.__input.focus();
            });

            this.appendChild(carrier);
            this.appendChild(input);
            this.appendChild(btn);
            this.__input = input;
        }

        init() {
            if (typeof w.flatpickr === 'undefined') return;
            var carrier = this.__carrier;

            var opts = {};
            for (var k in this.cfg) {
                opts[k] = this.cfg[k];
            }

            // 6.167 显示/提交契约统一走 separator（默认 ','）：
            // zh 语言包 rangeSeparator=「至」会让输入框回显与 onChange 的 dateStr 都变成「至」拼接，
            // 覆盖实例 l10n 后 dateStr 即按 separator 拼接，载体直接采用。
            var sep = this.cfg.separator || ',';
            // 初始值按 separator 拆成起止喂给日历（拆不出两段则忽略，如脏数据单值）
            if (carrier.value) {
                var parts = carrier.value.split(sep);
                if (parts.length === 2 && parts[0] && parts[1]) {
                    opts.defaultDate = parts;
                }
            }

            opts.onChange = [function (selectedDates, dateStr) {
                carrier.value = dateStr;
            }];

            this.__fp = w.flatpickr(this.__input, opts);
            slimNumInputs(this.__fp);
            if (this.__fp) {
                this.__fp.l10n.rangeSeparator = sep;
                // defaultDate 在构造期已按语言包的「至」写进输入框，重刷为 separator 拼接
                if (this.__fp.selectedDates.length) {
                    this.__fp.setDate(this.__fp.selectedDates, false);
                }
            }
            if (this.__fp && w.__fpYearPanel) {
                w.__fpYearPanel(this.__fp);
            }
            if (this.__fp && w.__fpMonthPanel) {
                w.__fpMonthPanel(this.__fp);
            }
            if (this.__fp && this.cfg.ranges && this.cfg.ranges.length) {
                this.__buildQuickRanges(this.__fp, this.cfg.ranges);
            }
        }

        /* 6.308 快捷范围；6.319 改为面板右侧竖排预设列（daterangepicker 式：
         * 左日历 280px、右预设列，容器加 fp-has-ranges 由 CSS 撑宽+定位）。
         * 点击即按 token 换算日期并 fp.setDate——from 取当日 00:00:00、to 取 23:59:59
         * （纯日期格式下时间被 dateFormat 丢弃，DateTimeRange 则完整生效）；
         * change 走 flatpickr 标准 onChange → 载体回写（separator 拼接）后关面板。
         * token：today、today±Nd（天）/Nm（月）/Ny（年）；其余字符串走 Date.parse 兜底。
         * 6.319 每次打开高亮与当前选中区间完全一致的预设（onOpen 里比对 selectedDates）。 */
        __buildQuickRanges(fp, presets) {
            var cal = fp.calendarContainer;
            if (!cal || cal.querySelector('.fp-quick-ranges')) return; // 幂等
            cal.classList.add('fp-has-ranges');

            function parseToken(tok, endOfDay) {
                var m = /^today(?:([+-])(\d+)([dmy]))?$/.exec(String(tok).trim());
                var dt;
                if (m) {
                    dt = new Date();
                    if (m[1]) {
                        var n = parseInt(m[2], 10) * (m[1] === '-' ? -1 : 1);
                        var u = m[3] || 'd';
                        if (u === 'd') dt.setDate(dt.getDate() + n);
                        else if (u === 'm') dt.setMonth(dt.getMonth() + n);
                        else dt.setFullYear(dt.getFullYear() + n);
                    }
                } else {
                    var p = Date.parse(tok);
                    if (isNaN(p)) return null;
                    dt = new Date(p);
                }
                dt.setHours(endOfDay ? 23 : 0, endOfDay ? 59 : 0, endOfDay ? 59 : 0, 0);
                return dt;
            }

            var row = d.createElement('div');
            row.className = 'fp-quick-ranges';
            presets.forEach(function (p) {
                if (!p || !p.label || !p.from || !p.to) return;
                var b = d.createElement('button');
                b.type = 'button';
                b.className = 'fp-quick-range';
                b.textContent = p.label; // textContent 防注入（规范 §10）
                b.dataset.from = p.from;
                b.dataset.to = p.to;
                b.addEventListener('click', function () {
                    var from = parseToken(p.from, false);
                    var to = parseToken(p.to, true);
                    if (!from || !to) return;
                    if (from > to) { var t = from; from = to; to = t; }
                    fp.setDate([from, to], true);
                    fp.close();
                });
                row.appendChild(b);
            });

            var months = cal.querySelector('.flatpickr-months');
            if (months) months.insertAdjacentElement('beforebegin', row);
            else cal.insertBefore(row, cal.firstChild);

            // 打开时高亮命中当前区间的预设（平比较到「日」即可）
            var markActive = function () {
                var sd = fp.selectedDates || [];
                var day = function (dt) { return dt ? new Date(dt.getFullYear(), dt.getMonth(), dt.getDate()).getTime() : null; };
                var s0 = day(sd[0]), s1 = day(sd[1]);
                row.querySelectorAll('.fp-quick-range').forEach(function (b) {
                    var from = parseToken(b.dataset.from, false);
                    var to = parseToken(b.dataset.to, true);
                    var hit = !!(from && to && s0 !== null && s1 !== null &&
                        day(from) === s0 && day(to) === s1);
                    b.classList.toggle('is-active', hit);
                });
            };
            fp.config.onOpen = [markActive].concat(fp.config.onOpen || []);
        }

        deinit() {
            if (this.__fp) {
                this.__fp.destroy();
                this.__fp = null;
            }
            this.__input = null;
            this.__carrier = null;
        }
    };
    BE.define('x-daterange', XDateRange);

    /* ============================================================
     * x-timerange — TimeRange（双时间输入 + 分隔符同步到隐藏载体）
     *
     * DOM 契约与旧 timerange.html 同构：date-range-row 里两个
     * date-range-cell（各自独立 flatpickr noCalendar 实例），中间 — 分隔。
     * 提交契约：载体值 = start + data-separator + end（默认 ','），空端省略。
     *
     * cfg：两组 flatpickr 共用的 options（noCalendar/enableTime/...，PHP 端组装）
     * data-separator：载体值分隔符
     * ============================================================ */
    var XTimeRange = class extends BE.Base {
        build() {
            var carrier = d.createElement('input');
            carrier.type = 'hidden';

            var id = this.getAttribute('id');
            if (id) {
                carrier.setAttribute('id', id);
                this.removeAttribute('id');
            }
            var name = this.getAttribute('name');
            if (name !== null) {
                carrier.setAttribute('name', name);
                this.removeAttribute('name');
            }
            var val = this.getAttribute('value');
            if (val !== null) {
                carrier.value = val;
                this.removeAttribute('value');
            }
            this.__carrier = carrier;

            // 6.164 占位带字段文字：宿主 placeholder（请选择xx）+ 方向后缀，缺省退回纯 From/To
            var phBase = this.getAttribute('placeholder');
            if (phBase !== null) this.removeAttribute('placeholder');
            var phFrom = BE.blang('builder_date_range_from', 'From');
            var phTo = BE.blang('builder_date_range_to', 'To');

            var row = d.createElement('div');
            row.className = 'date-range-row';
            var self = this;
            this.__inputs = [];
            this.__fps = [];
            ['start', 'end'].forEach(function (pos) {
                var cell = d.createElement('div');
                cell.className = 'date-input-wrap date-range-cell';

                var input = d.createElement('input');
                input.type = 'text';
                input.className = 'input input-bordered w-full date-input';
                if (carrier.id) input.setAttribute('id', carrier.id + '-' + pos);
                input.setAttribute('placeholder', pos === 'start'
                    ? (phBase !== null ? phBase + ' (' + phFrom + ')' : phFrom)
                    : (phBase !== null ? phBase + ' (' + phTo + ')' : phTo));

                var btn = d.createElement('button');
                btn.type = 'button';
                btn.className = 'date-icon-btn';
                var icon = d.createElement('i');
                icon.className = 'mdi mdi-clock';
                btn.appendChild(icon);
                btn.addEventListener('click', function () {
                    input.focus();
                });

                cell.appendChild(input);
                cell.appendChild(btn);
                row.appendChild(cell);
                if (pos === 'start') {
                    var sep = d.createElement('span');
                    sep.className = 'date-range-sep';
                    sep.textContent = '—';
                    row.appendChild(sep);
                }
                self.__inputs.push(input);
            });

            ['required', 'disabled'].forEach(function (k) {
                if (this.hasAttribute(k)) {
                    this.__inputs.forEach(function (i) {
                        i.setAttribute(k, '');
                    });
                    this.removeAttribute(k);
                }
            }, this);

            this.appendChild(carrier);
            this.appendChild(row);
        }

        init() {
            if (typeof w.flatpickr === 'undefined') return;
            var carrier = this.__carrier;
            var sep = this.getAttribute('data-separator') || ',';
            var startEl = this.__inputs[0];
            var endEl = this.__inputs[1];

            function makeOpts() {
                var opts = {};
                for (var k in this.cfg) {
                    opts[k] = this.cfg[k];
                }
                return opts;
            }
            var startOpts = makeOpts.call(this);
            startOpts.defaultDate = startEl.value || null;
            var endOpts = makeOpts.call(this);
            endOpts.defaultDate = endEl.value || null;

            function syncMainValue() {
                var sv = startEl.value.trim();
                var ev = endEl.value.trim();
                carrier.value = (sv || '') + sep + (ev || '');
                if (carrier.value === sep) {
                    carrier.value = '';
                }
            }
            this.__sync = syncMainValue;

            this.__fps.push(w.flatpickr(startEl, startOpts));
            this.__fps.push(w.flatpickr(endEl, endOpts));
            this.__fps.forEach(slimNumInputs);

            startEl.addEventListener('change', syncMainValue);
            endEl.addEventListener('change', syncMainValue);

            // 初始化：载体有值时拆到两端，否则同步一次（与旧脚本一致）
            if (carrier.value) {
                var arr = carrier.value.split(sep);
                startEl.value = arr[0] || '';
                endEl.value = arr.length > 1 ? arr[1] : '';
            } else {
                syncMainValue();
            }
        }

        deinit() {
            this.__fps.forEach(function (fp) {
                fp.destroy();
            });
            this.__fps = [];
            this.__inputs = [];
            this.__carrier = null;
        }
    };
    BE.define('x-timerange', XTimeRange);

    /* ============================================================
     * x-select — Select / MultipleSelect / Tags（TomSelect 系选择框）
     *
     * 与日期家族不同：内部 <select> 是服务端渲染的（options/selected/disabled
     * 全在 PHP 端组装），宿主只是包裹层 —— 模板把原 select 原样放进 <x-select>，
     * 元素初始化时对子 select 挂 TomSelect。克隆安全天然成立：temple 里的
     * select 是惰性原件，插入升级后由元素重新 init。
     *
     * cfg：
     *  {ts:false}                          —— select2(false)/只读 tags：保持原生 select
     *  {ts:true, placeholder, plugins:[], maxItems, create?, delimiter?}
     *  {..., ajax:{url,id:'_'|字段,text:'_'|字段,delay,loadmore},  —— 远程懒加载
     *       separator:'、', prev_id?, withParams:[]}
     *
     * 迁移自 Select::tomSelectScript + withPrevScript（附录 B「TomSelect 包装补丁」
     * 挂在 window.TomSelect 上，元素经 new window.TomSelect 自动继承）。
     * 级联改进：旧版 withPrevScript 在独立脚本里拿不到 TomSelect 实例（input 上
     * 只有小写 tomselect 属性），清空实际未生效；现元素持有实例，prev change 时
     * clear+clearOptions 并重置 fetched 标记，下次点开按新 prev_val 重新拉取。
     * ============================================================ */
    /* ------------------------------------------------------------
     * 6.284 树形下拉：识别 TreeModel __plain__ 的 ──├─/└─ 前缀标记，
     * 下拉选项用表格树同款 tline 参考线渲染（样式见 builder-core-layout.css，
     * 选项行内衔接补法见 builder-tomselect.css）。
     *
     * 原生 option 的标记文本在 TomSelect 接管前被改写成「干净标题 +
     * data-guides/data-elbow」：TomSelect 解析 option 时把整个 dataset
     * 合入渲染数据、data.text 取 textContent——搜索、选中徽章、readonly
     * 回显因此都不含标记。render.option 对普通选项输出与默认模板等价的
     * <div>text</div>，所以无标记的 select 走此渲染零差异。
     * 克隆行（items temple）安全：克隆件的 options 已是干净文本 + data
     * 属性，重解析零命中，data 属性原样保留、tline 照常渲染。
     * ------------------------------------------------------------ */
    var TREE_TEXT_RE = /^((?:──)*)(├─|└─)?/;
    var TREE_HTML_RE = /^<span class="tline">([\s\S]*?)<\/span>/i;
    function parseTreeOption(text) {
        // 分支一：控制器误把 lineType=1 的 tline HTML（ajax __text__ 等）当文本传入——
        // 参考线元素个数即层级数据，标题在 span 内，剥标签取纯文本
        var hm = TREE_HTML_RE.exec(text);
        if (hm) {
            var inner = hm[1];
            return {
                guides: (inner.match(/tline-guide/g) || []).length,
                elbow: /tline-elbow-last/.test(inner) ? 'l' : (/tline-elbow/.test(inner) ? 't' : ''),
                title: inner.replace(/<[^>]*>/g, ''),
            };
        }
        // 分支二：TreeModel __plain__/getOptionsData 的 ──├─/└─ 纯文本标记
        var m = TREE_TEXT_RE.exec(text);
        var pairs = m[1].length / 2;
        if (!pairs && !m[2]) return null; // 无标记：普通选项
        // 与表格树同构：标记里 (deep-1) 对 ── 的最后一对代表根级缩进位，
        // 表格树无此列（一级子项只有 elbow）→ 参考线数 = pairs - 1
        return {
            guides: Math.max(pairs - 1, 0),
            elbow: m[2] === '└─' ? 'l' : 't',
            title: text.slice(m[0].length),
        };
    }
    function prepTreeOptions(select) {
        for (var i = 0; i < select.options.length; i++) {
            var opt = select.options[i];
            var info = parseTreeOption(opt.textContent);
            if (!info) continue;
            opt.textContent = info.title;
            if (info.guides) opt.setAttribute('data-guides', info.guides);
            if (info.elbow) opt.setAttribute('data-elbow', info.elbow);
        }
    }
    function treeOptionHtml(data, escape) {
        var guides = parseInt(data.guides, 10) || 0;
        if (!guides && !data.elbow) return '<div>' + escape(data.text) + '</div>';
        var html = '<div><span class="tline">';
        for (var i = 0; i < guides; i++) html += '<i class="tline-guide"></i>';
        html += data.elbow === 'l'
            ? '<i class="tline-elbow tline-elbow-last"></i>'
            : '<i class="tline-elbow"></i>';
        return html + '</span>' + escape(data.text) + '</div>';
    }

    var XSelect = class extends BE.Base {
        init() {
            var select = this.querySelector('select');
            if (!select) return;
            this.__select = select;

            var cfg = this.cfg || {};
            if (!cfg.ts) return; // select2(false) / 只读 tags：原生 select，不初始化

            if (typeof w.TomSelect === 'undefined') {
                if (w.console) w.console.warn('[x-select] TomSelect is not loaded');
                return;
            }

            var self = this;
            var selected = select.getAttribute('data-selected') || '';

            // 6.215 PHP 侧对 ajax select 预置 display:none 防首帧跳变；
            // TomSelect 接管后由 ts-hidden-accessible（clip 1px、可聚焦）隐藏原生 select，
            // 内联 display:none 必须移除——否则 required 校验失败时控件不可聚焦，
            // 浏览器报「无法聚焦于无效的表单控件」、无气泡提示，提交被静默拦截
            function clearPrehide() {
                select.style.display = '';
            }

            /* 公共设置（placeholder/plugins/maxItems/no_results 与旧脚本一致） */
            function baseSettings() {
                var s = {
                    placeholder: cfg.placeholder || '',
                    plugins: cfg.plugins || [],
                };
                if (cfg.maxItems === null) s.maxItems = null;
                else if (cfg.maxItems) s.maxItems = cfg.maxItems;
                if (cfg.create) s.create = true;
                if (cfg.delimiter) s.delimiter = cfg.delimiter;
                s.render = {
                    // 6.284 树形选项（data-guides/data-elbow）用 tline 参考线，
                    // 普通选项输出与默认模板等价
                    option: treeOptionHtml,
                    no_results: function (data, escape) {
                        return '<div class="no-results p-2 text-center text-base-content/40 text-sm">'
                            + escape(BE.blang('builder_no_relevant_data', 'No relevant data')) + '</div>';
                    },
                };
                return s;
            }

            if (!cfg.ajax) {
                prepTreeOptions(select); // 6.284 树形标记 → 干净文本 + data 属性
                this.__ts = new w.TomSelect(select, baseSettings());
                clearPrehide();
                return;
            }

            /* ---------- 远程懒加载（原 tomSelectScript ajax 分支） ---------- */
            var ajax = cfg.ajax;
            var url = ajax.url;
            var idField = ajax.id === '_' ? null : ajax.id;
            var textField = ajax.text === '_' ? null : ajax.text;
            var separator = cfg.separator || '、';
            var prevId = cfg.prev_id || '';
            var withParams = cfg.withParams || [];
            var fetched = false;

            // items 复制行：宿主 id 被重写后 cfg.prev_id 仍是原 id，优先在本行内找
            function findPrev() {
                var esc = (w.CSS && w.CSS.escape) ? w.CSS.escape(prevId) : prevId;
                var row = self.closest('tr');
                var scoped = row && row.querySelector('select#' + esc);
                return scoped || d.getElementById(prevId);
            }

            function getPrevVal() {
                var el = prevId ? findPrev() : null;
                return (el && el.value) || '';
            }

            function getExtraParams() {
                var params = {};
                for (var i = 0; i < withParams.length; i++) {
                    var el = d.querySelector("input[name='" + withParams[i] + "'], select[name='" + withParams[i] + "']");
                    if (el) params[withParams[i]] = el.value;
                }
                return params;
            }

            function fetchList(extra) {
                var params = new URLSearchParams(Object.assign({
                    q: '', page: 1,
                    prev_val: getPrevVal(),
                    ele_id: select.id,
                    prev_ele_id: prevId,
                    idField: idField,
                    textField: textField,
                }, extra || {}));
                var ep = getExtraParams();
                for (var k in ep) params.set(k, ep[k]);
                return fetch(url + '?' + params.toString()).then(function (r) {
                    return r.json();
                });
            }

            function mapOptions(list) {
                return (list || []).map(function (dd) {
                    var o = {
                        value: dd.__id__ || dd[idField] || dd.id,
                        text: dd.__text__ || dd[textField] || dd.text,
                    };
                    // 6.284 远程返回的 __plain__ 标记文本同样转为 tline 数据；
                    // __text__ 为 tline HTML（lineType=1）时解析零命中、原样透传
                    var info = parseTreeOption(o.text);
                    if (info) {
                        o.text = info.title;
                        if (info.guides) o.guides = info.guides;
                        if (info.elbow) o.elbow = info.elbow;
                    }
                    return o;
                });
            }

            function createTomSelect() {
                var settings = baseSettings();
                // null = 不自动 load，全部自己控制；openOnFocus=false 阻止自动开下拉
                settings.preload = null;
                settings.openOnFocus = false;
                settings.loadThrottle = 0;
                settings.load = function (query, callback) {
                    fetchList({ q: query || '' })
                        .then(function (data) {
                            callback(mapOptions(data.data ? data.data : data));
                        })
                        .catch(function () { callback(); });
                };

                var ts = new w.TomSelect(select, settings);
                // 6.213 必须在此处落到 self.__ts：回显分支（selected!=='')之前只接住返回值，
                // 级联监听里 self.__ts 为 undefined → 换省后 clear/clearOptions 被跳过，
                // 旧徽章残留 + 新省城市追加进旧选项（新旧两省城市混列）
                self.__ts = ts;
                clearPrehide();

                // 自己接管：第一次点下拉才 fetch，fetch 完才 open → 不闪
                var control = ts.wrapper.querySelector('.ts-control');
                control.addEventListener('click', function () {
                    if (fetched) {
                        ts.open();
                        return;
                    }
                    fetched = true;
                    fetchList({ q: '' })
                        .then(function (data) {
                            ts.addOptions(mapOptions(data.data ? data.data : data));
                            ts.open();
                        })
                        .catch(function () { ts.open(); });
                });
                // open() 内部会覆盖 dropdown 内容 → 每次 dropdown_open 之后检查并塞 no_results
                ts.on('dropdown_open', function () {
                    setTimeout(function () {
                        if (Object.keys(ts.options).length === 0) {
                            var content = ts.dropdown.querySelector('.ts-dropdown-content');
                            if (content) {
                                content.innerHTML = '';
                                var el = ts.render('no_results', { query: '' });
                                content.appendChild(typeof el === 'string'
                                    ? new DOMParser().parseFromString(el, 'text/html').body.firstChild
                                    : el);
                            }
                        }
                    }, 0);
                });
                return ts;
            }

            // 级联：prev 变化 → 清空自己（实例层面真正生效，旧版清不掉），
            // 重置 fetched 让下次点开按新 prev_val 重新拉取。
            // 6.214：prev 回显 setValue 也会触发 change——那不是用户切换，
            // prev 宿主 __echoBusy 期间必须忽略，否则下级刚回显的值被随机清掉
            if (prevId) {
                var prev = findPrev();
                if (prev) {
                    prev.addEventListener('change', function () {
                        var prevHost = prev.closest('x-select');
                        if (prevHost && prevHost.__echoBusy) return;
                        fetched = false;
                        if (self.__ts) {
                            self.__ts.clear();
                            self.__ts.clearOptions();
                        }
                        select.innerHTML = '<option value=""></option>';
                        select.dispatchEvent(new Event('change'));
                    });
                }
            }

            if (select.hasAttribute('readonly')) {
                // readonly：fetch selected 后用纯文本 span 替换 select（原 ajax readonly 分支）
                fetchList(selected ? { selected: selected } : {})
                    .then(function (data) {
                        var options = mapOptions((data.data ? data.data : data) || []);
                        var texts = options.map(function (o) { return o.text; });
                        var span = d.createElement('span');
                        span.style.lineHeight = '33px';
                        span.textContent = texts.length ? texts.join(separator) : '-';
                        if (select.parentNode) {
                            select.parentNode.replaceChild(span, select);
                            var parent = span.closest('.field');
                            if (parent) parent.classList.add('field-show');
                        }
                    })
                    .catch(function () {
                        var span = d.createElement('span');
                        span.style.lineHeight = '33px';
                        span.textContent = BE.blang('builder_loading_error', '加载失败');
                        if (select.parentNode) select.parentNode.replaceChild(span, select);
                    });
            } else if (selected !== '') {
                // 有选中值：eager fetch 让 TomSelect 能显示已选项（完整列表仍等点开再拉）。
                // 6.214：回显期间置 __echoBusy——setValue 触发的 change 会让下级级联
                // 误判为用户切换、清掉刚回显的值（时序随机 → 「回显加载不全」）
                self.__echoBusy = true;
                fetchList({ selected: selected })
                    .then(function (data) {
                        var options = mapOptions((data.data ? data.data : data) || []);
                        var ts = createTomSelect();
                        if (ts) {
                            ts.addOptions(options);
                            // 6.216 setValue 的 addItems 里非数组参数被当作单个值：
                            // 逗号串 'HTKY,STO,...' 匹配不到任何 option → 多选回显全空（单值恰好能过）
                            ts.setValue(select.multiple
                                ? selected.split(',').filter(function (v) { return v !== ''; })
                                : selected);
                        }
                        self.__echoBusy = false;
                    })
                    .catch(function () { createTomSelect(); self.__echoBusy = false; });
            } else {
                // 无选中值：纯 lazy，init 后什么都不做
                this.__ts = createTomSelect();
            }
        }

        deinit() {
            if (this.__ts) {
                try { this.__ts.destroy(); } catch (e) { /* 行已被移除等场景容错 */ }
                this.__ts = null;
            }
            this.__select = null;
        }
    };
    BE.define('x-select', XSelect);

    /* ============================================================
     * x-loadtext — Load / Loads（纯展示：按已选值远程拉文本）
     *
     * 宿主即内容本体（旧模板的 span），无内部控件：服务端渲染初始文案
     * 「加载中...」，init 时按 data-selected fetch 并以 separator 连接回填。
     * .the-field 类保留在宿主上 —— CSS 的 :has(> .the-field) 与
     * .field-row .the-field{display:inline} 全部照旧生效。
     *
     * cfg：{url, text:'_'|字段, separator:'、'}
     * ============================================================ */
    var XLoadText = class extends BE.Base {
        init() {
            var cfg = this.cfg || {};
            // 与旧脚本一致：不因 url 为空而跳过（fetch 当前页 → json 解析失败 → 显示加载失败）

            var self = this;
            var selected = this.getAttribute('data-selected') || '';
            var textField = cfg.text === '_' ? '' : cfg.text;
            var separator = cfg.separator || '、';

            if (!selected) {
                self.textContent = BE.blang('builder_value_is_empty', '-');
                return;
            }

            var params = new URLSearchParams({
                q: '', page: 1, selected: selected,
                ele_id: this.id, prev_ele_id: '', idField: '',
                textField: textField, load: 1,
            });
            fetch(cfg.url + '?' + params.toString())
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var list = (data.data ? data.data : data) || [];
                    var texts = [];
                    for (var i = 0; i < list.length; i++) {
                        var dd = list[i];
                        texts.push(dd.__text__ || dd[textField] || dd.text);
                    }
                    self.textContent = texts.length ? texts.join(separator) : BE.blang('builder_value_is_empty', '-');
                })
                .catch(function () {
                    self.setAttribute('data-selected', '');
                    self.textContent = BE.blang('builder_loading_error', '加载失败');
                });
        }
    };
    BE.define('x-loadtext', XLoadText);

    /* ============================================================
     * x-number — Number（原生 number input + 前后缀 + 增减按钮）
     *
     * cfg：{decimals, prefix, postfix}；min/max/step/placeholder 走宿主属性
     * （搬运到内部 input，契约与旧模板一致）。init 做两件旧全局脚本的事：
     * ① decimals>0 时 change/blur 格式化并收拢到 [min,max]；
     * ② 按实测宽度给 input 设 padding 让位 prefix/postfix（!important 内联，
     *    覆盖表格内带 important 的 CSS 兜底）。
     * ============================================================ */
    var XNumber = class extends BE.Base {
        build() {
            var cfg = this.cfg || {};
            var self = this;

            var input = d.createElement('input');
            input.type = 'number';
            input.className = 'input input-bordered w-full num-spinner-input';
            this.carryFormAttrs(input);
            ['min', 'max', 'step'].forEach(function (k) {
                var v = self.getAttribute(k);
                if (v !== null) {
                    input.setAttribute(k, v);
                    self.removeAttribute(k);
                }
            });
            if (cfg.decimals > 0) input.setAttribute('data-decimals', cfg.decimals);

            var self = this;
            var btns = d.createElement('div');
            btns.className = 'num-spinner-btns';
            [['up', '+', 'stepUp'], ['down', '−', 'stepDown']].forEach(function (p) {
                var b = d.createElement('button');
                b.type = 'button';
                b.className = 'num-spinner-btn num-spinner-btn-' + p[0];
                b.textContent = p[1];
                b.addEventListener('click', function () {
                    input[p[2]]();
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
                btns.appendChild(b);
            });

            this.appendChild(input);
            if (cfg.prefix) {
                var pre = d.createElement('span');
                pre.className = 'num-spinner-prefix';
                pre.textContent = cfg.prefix;
                this.insertBefore(pre, input);
            }
            if (cfg.postfix) {
                var post = d.createElement('span');
                post.className = 'num-spinner-postfix';
                post.textContent = cfg.postfix;
                this.appendChild(post);
            }
            this.appendChild(btns);
            this.__input = input;
        }

        init() {
            var input = this.__input;
            var self = this;

            function formatNum() {
                var dec = parseInt(input.getAttribute('data-decimals') || '0', 10);
                if (!(dec > 0) || input.value === '' || isNaN(input.value)) return;
                var n = Number(input.value);
                var mi = input.getAttribute('min'), ma = input.getAttribute('max');
                if (mi !== null && mi !== '' && n < Number(mi)) n = Number(mi);
                if (ma !== null && ma !== '' && n > Number(ma)) n = Number(ma);
                input.value = n.toFixed(dec);
            }

            function sizeAddon() {
                var sr = self.getBoundingClientRect();
                if (!sr.width) return; // 隐藏中（未激活 tab 等），保留 CSS 兜底
                var pre = self.querySelector('.num-spinner-prefix');
                var post = self.querySelector('.num-spinner-postfix');
                if (pre) {
                    input.style.setProperty('padding-left', (Math.ceil(pre.getBoundingClientRect().right - sr.left) + 6) + 'px', 'important');
                } else {
                    input.style.removeProperty('padding-left');
                }
                if (post) {
                    input.style.setProperty('padding-right', (Math.ceil(sr.right - post.getBoundingClientRect().left) + 6) + 'px', 'important');
                } else {
                    input.style.removeProperty('padding-right');
                }
            }
            this.__sizeAddon = sizeAddon;

            input.addEventListener('change', function () { formatNum(); sizeAddon(); });
            input.addEventListener('blur', function () { formatNum(); sizeAddon(); });
            input.addEventListener('focus', sizeAddon);

            formatNum();
            sizeAddon();
        }

        deinit() {
            this.__input = null;
            this.__sizeAddon = null;
        }
    };
    BE.define('x-number', XNumber);

    /* ============================================================
     * x-color — Color（Pickr 取色器 + 文本框双向同步）
     *
     * 宿主即 .color-input-wrap（同 x-date 的 date-input-wrap 模式）：
     * 色块 div（id = 宿主id + '-pickr' 派生形态，Pickr create 时会替换它）
     * + 文本输入框。cfg：{swatches:[]}；按钮/提示文案走 BE.blang。
     * change 即生效：手动 applyColor（save:false，6.66/6.110 的坑），
     * change 参数 (color, origin, instance) 三参。
     * ============================================================ */
    var XColor = class extends BE.Base {
        build() {
            var input = d.createElement('input');
            input.type = 'text';
            input.className = 'input input-bordered w-full font-mono text-sm color-input';
            this.carryFormAttrs(input);

            var swatch = d.createElement('div');
            var id = input.id;
            if (id) swatch.setAttribute('id', id + '-pickr'); // 旧模板触发器派生 id
            swatch.className = 'color-swatch';

            this.appendChild(swatch);
            this.appendChild(input);
            this.__input = input;
            this.__swatch = swatch;
        }

        init() {
            if (typeof w.Pickr === 'undefined') return;
            var input = this.__input;
            var self = this;

            var pickr = w.Pickr.create({
                el: this.__swatch,
                theme: 'monolith',
                default: input.value || '#000000',
                swatches: (this.cfg && this.cfg.swatches) || [],
                components: {
                    preview: true,
                    opacity: true,
                    hue: true,
                    interaction: {
                        hex: true,
                        rgba: true,
                        hsla: true,
                        hsva: true,
                        cmyk: false,
                        input: true,
                        clear: true,
                        save: false // 即时生效, 不需要保存按钮
                    }
                },
                i18n: {
                    'btn:open': BE.blang('builder_color_open', 'Open'),
                    'btn:close': BE.blang('builder_color_close', 'Close'),
                    'btn:clear': BE.blang('builder_color_clear', 'Clear'),
                    'btn:save': BE.blang('builder_color_save', 'Save')
                }
            });

            pickr.on('change', function (color, origin, instance) {
                var hex = color ? color.toHEXA().toString() : '';
                input.value = hex;
                instance.applyColor();
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
            pickr.on('clear', function () {
                input.value = '';
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
            input.addEventListener('focus', this.__onFocus = function () {
                pickr.show();
            });

            this.__pickr = pickr;
        }

        deinit() {
            if (this.__pickr) {
                try { this.__pickr.destroy(); } catch (e) { /* 行已移除等场景容错 */ }
                this.__pickr = null;
            }
            this.__input = null;
            this.__swatch = null;
        }
    };
    BE.define('x-color', XColor);

    /* ============================================================
     * x-slider — RangeSlider（daisyUI .range 单/双柄，values 离散模式）
     *
     * DOM 与旧 rangeslider.html 可编辑分支同构：double/values 态 = 隐藏载体
     * （name/id 落这）+ rs-from + [rs-to] + rs-output；single 态 = rs-from
     * 自带 name/id（载体即它）。cfg：{type,min,max,step,from,to,values?,
     * prefix?,postfix?,disable?,fromFixed?,toFixed?,useValues,initValue,initLabel}。
     * 同步逻辑逐行等价移植旧 tpbRangeSync（拖动越界互推 + 载体 "from;to"
     * 契约 + change 转发触发 autoPost），配置在闭包里、不再走全局注册表。
     * 只读/表格列仍是服务端渲染的静态展示，不经此元素。
     * ============================================================ */
    var XSlider = class extends BE.Base {
        build() {
            var cfg = this.cfg || {};
            var self = this;
            var double = cfg.type === 'double';
            var carrier = null;

            var from = d.createElement('input');
            from.type = 'range';
            from.className = 'range range-primary rs-from flex-1';
            from.setAttribute('aria-label', double || cfg.useValues ? 'from' : 'range');
            from.min = cfg.min;
            from.max = cfg.max;
            from.step = cfg.step;
            from.value = cfg.from;

            if (double || cfg.useValues) {
                carrier = d.createElement('input');
                carrier.type = 'hidden';
                carrier.className = 'ignore rs-hidden';
                carrier.value = cfg.initValue || '';
                this.carryTo(carrier, 'name');
                this.carryTo(carrier, 'id');
                this.carryTo(carrier, 'value');
                this.appendChild(carrier);
                this.appendChild(from);
                if (carrier.id) from.setAttribute('id', carrier.id + '-from');
            } else {
                this.carryTo(from, 'name');
                this.carryTo(from, 'id');
                this.appendChild(from);
            }

            if (cfg.disable || cfg.fromFixed) from.disabled = true;

            if (double) {
                var sep = d.createElement('span');
                sep.className = 'rs-sep opacity-60';
                sep.textContent = '—';
                this.appendChild(sep);

                var to = d.createElement('input');
                to.type = 'range';
                to.className = 'range range-primary rs-to flex-1';
                to.setAttribute('aria-label', 'to');
                to.min = cfg.min;
                to.max = cfg.max;
                to.step = cfg.step;
                to.value = cfg.to;
                if (carrier && carrier.id) to.setAttribute('id', carrier.id + '-to');
                if (cfg.disable || cfg.toFixed) to.disabled = true;
                this.appendChild(to);
                this.__to = to;
            }

            var out = d.createElement('span');
            out.className = 'rs-output badge badge-ghost font-mono text-xs whitespace-nowrap';
            out.textContent = cfg.initLabel || '';
            this.appendChild(out);

            ['required', 'readonly', 'disabled'].forEach(function (k) {
                if (self.hasAttribute(k)) {
                    // required 落到载体/可见柄，供 reportValidity
                    (carrier || from).setAttribute(k, '');
                    self.removeAttribute(k);
                }
            });

            this.__carrier = carrier;
            this.__from = from;
            this.__out = out;
        }

        /* carryFormAttrs 的单属性版本（供 slider 内部定向搬运） */
        carryTo(target, key) {
            var v = this.getAttribute(key);
            if (v !== null) {
                target.setAttribute(key, v);
                this.removeAttribute(key);
            }
        }

        init() {
            var cfg = this.cfg || {};
            var from = this.__from;
            var to = this.__to;
            var out = this.__out;
            var carrier = this.__carrier;
            var vals = (cfg.useValues && cfg.values) ? cfg.values : null;
            var pre = cfg.prefix || '', post = cfg.postfix || '';

            function label(i) { return vals ? pre + String(vals[i]) + post : pre + i + post; }

            function sync(src) {
                var a = from.value;
                if (!to) {
                    if (carrier) carrier.value = vals ? String(vals[a]) : a;
                    else from.value = a; // single 数值态无载体
                    if (out) out.textContent = label(a);
                    return;
                }
                var b = to.value;
                // 拖动越过另一柄时把另一柄推走（与 ion 行为一致）
                if (src === from && Number(a) > Number(b)) { to.value = a; b = a; }
                if (src === to && Number(b) < Number(a)) { from.value = b; a = b; }
                if (carrier) carrier.value = vals ? (vals[a] + ';' + vals[b]) : (a + ';' + b);
                if (out) out.textContent = label(a) + ' — ' + label(b);
            }

            from.addEventListener('input', function () { sync(from); });
            if (to) to.addEventListener('input', function () { sync(to); });

            // 拖动结束(change)转发到提交载体，触发 autoPost 等。
            // single 模式无载体（载体即 from），原生 change 已落在 from 上，
            // 再向 from 派发会重新命中本监听 → 无限递归（too much recursion）
            function forward() {
                if (!carrier) return;
                carrier.dispatchEvent(new Event('change', { bubbles: true }));
            }
            from.addEventListener('change', forward);
            if (to) to.addEventListener('change', forward);

            sync(null);
        }

        deinit() {
            this.__from = null;
            this.__to = null;
            this.__out = null;
            this.__carrier = null;
        }
    };
    BE.define('x-slider', XSlider);

    /* ============================================================
     * x-map — Map（amap/baidu/tcent/yandex 取点地图）
     *
     * DOM 与旧 map.html 同构：.map-input-wrap（input+按钮）+ 搜索框 +
     * #map-{id} 容器（保留旧模板实际渲染出的 height:300px——旧模板
     * style 属性重复，mapStyle 的 width/height 配置从未生效，属既有缺陷，
     * 此处按渲染实况复刻）。id 派生形态不变：search-{id} / map-{id}。
     *
     * cfg：{type, ro, zoom, center:[..], value:'x,y', searchable,
     *       jsKey?, jscode?, baidu:{zoom,acValue?}, tcent:{...}}
     * 移植要点：① 全局回调 amapInit/tcentInit 改为唯一名（多实例不互踩）；
     * ② baidu/tcent/yandex 旧脚本的 jQuery $() 全部换原生（与 6.x amap
     * 的原生化同型）；③ API 已在页面上时直接 init 不再重复加载 JSONP。
     * ============================================================ */
    var XMap = class extends BE.Base {
        build() {
            var cfg = this.cfg || {};
            var self = this;

            var wrap = d.createElement('div');
            wrap.className = 'map-input-wrap';

            var input = d.createElement('input');
            input.type = 'text';
            input.className = 'input input-bordered w-full map-input';
            this.carryFormAttrs(input);

            var btn = d.createElement('button');
            btn.type = 'button';
            btn.className = 'map-icon-btn';
            btn.innerHTML = '<i class="mdi mdi-map"></i>';
            btn.addEventListener('click', function () {
                input.focus();
            });

            wrap.appendChild(input);
            wrap.appendChild(btn);
            this.appendChild(wrap);
            this.__input = input;

            var id = input.id;
            if (cfg.searchable) {
                var searchWrap = d.createElement('div');
                searchWrap.className = 'mt-2';
                var search = d.createElement('input');
                search.type = 'text';
                search.title = '若无法搜索，检查是否填写了正确的js apikey';
                search.className = 'input input-bordered w-full';
                search.setAttribute('placeholder', BE.blang('builder_map_search_place', '搜索地点'));
                if (id) search.setAttribute('id', 'search-' + id);
                searchWrap.appendChild(search);
                this.appendChild(searchWrap);
                this.__search = search;
            }

            var mapDiv = d.createElement('div');
            mapDiv.className = 'mt-2 rounded-box border border-base-300';
            mapDiv.style.height = '300px'; // 旧模板 style 属性重复，实际渲染恒 300px
            if (id) mapDiv.setAttribute('id', 'map-' + id);
            this.appendChild(mapDiv);
            this.__mapDiv = mapDiv;
        }

        init() {
            var cfg = this.cfg || {};
            if (!cfg.type || cfg.type === 'other') return;
            var self = this;
            var input = this.__input;
            var mapDiv = this.__mapDiv;
            var search = this.__search;
            var uid = (input.id || 'x').replace(/\W/g, '_');
            var ro = !!cfg.ro;

            /* ---------- amap ---------- */
            if (cfg.type === 'amap') {
                w._AMapSecurityConfig = { securityJsCode: cfg.jscode || '' };

                function amapInit() {
                    var map = new w.AMap.Map(mapDiv, cfg.opts);
                    self.__amap = map;
                    var marker = new w.AMap.Marker({
                        draggable: true,
                        position: new w.AMap.LngLat(cfg.value[0], cfg.value[1]),
                    });
                    map.add(marker);
                    if (ro) return;

                    map.on('click', function (e) {
                        marker.setPosition(e.lnglat);
                        input.value = e.lnglat.getLng() + ',' + e.lnglat.getLat();
                    });
                    marker.on('dragend', function () {
                        input.value = marker.getPosition().getLng() + ',' + marker.getPosition().getLat();
                    });

                    if (!input.value) {
                        map.plugin('AMap.Geolocation', function () {
                            var geolocation = new w.AMap.Geolocation();
                            map.addControl(geolocation);
                            geolocation.getCurrentPosition();
                            w.AMap.event.addListener(geolocation, 'complete', function (data) {
                                marker.setPosition(data.position);
                                input.value = data.position.getLng() + ',' + data.position.getLat();
                            });
                        });
                    }

                    if (search) {
                        w.AMap.plugin('AMap.Autocomplete', function () {
                            var autocomplete = new w.AMap.Autocomplete({ input: search.id });
                            w.AMap.event.addListener(autocomplete, 'select', function (data) {
                                map.setZoomAndCenter(cfg.zoom, data.poi.location);
                                marker.setPosition(data.poi.location);
                                input.value = data.poi.location.lng + ',' + data.poi.location.lat;
                            });
                        });
                    }
                }

                if (typeof w.AMap !== 'undefined') { amapInit(); return; }
                var cb = '__amapInit_' + uid;
                w[cb] = amapInit;
                var jsapi = d.createElement('script');
                jsapi.charset = 'utf-8';
                jsapi.src = cfg.jsKey + '&callback=' + cb;
                d.body.appendChild(jsapi);
                return;
            }

            /* ---------- tcent ---------- */
            if (cfg.type === 'tcent') {
                function tcentInit() {
                    var map = new w.qq.maps.Map(mapDiv, Object.assign({ center: new w.qq.maps.LatLng(cfg.value[0], cfg.value[1]) }, cfg.opts));
                    var marker = new w.qq.maps.Marker({ position: new w.qq.maps.LatLng(cfg.value[0], cfg.value[1]), draggable: true, map: map });
                    if (ro) return;

                    if (!input.value) {
                        var citylocation = new w.qq.maps.CityService();
                        citylocation.setComplete(function (result) {
                            map.setCenter(result.detail.latLng);
                            marker.setPosition(result.detail.latLng);
                            input.value = result.detail.latLng.getLng() + ',' + result.detail.latLng.getLat();
                        });
                        citylocation.searchLocalCity();
                    }
                    w.qq.maps.event.addListener(map, 'click', function (event) {
                        marker.setPosition(event.latLng);
                        input.value = event.latLng.getLng() + ',' + event.latLng.getLat();
                    });
                    w.qq.maps.event.addListener(marker, 'dragend', function () {
                        var pp = marker.getPosition();
                        input.value = pp.getLng() + ',' + pp.getLat();
                    });
                    if (search) {
                        var ap = new w.qq.maps.place.Autocomplete(search);
                        var searchService = new w.qq.maps.SearchService({ map: map });
                        w.qq.maps.event.addListener(ap, 'confirm', function (res) {
                            searchService.search(res.value);
                        });
                    }
                }
                if (typeof w.qq !== 'undefined' && w.qq.maps) { tcentInit(); return; }
                var cb2 = '__tcentInit_' + uid;
                w[cb2] = tcentInit;
                var s2 = d.createElement('script');
                s2.type = 'text/javascript';
                s2.src = cfg.jsKey + '&callback=' + cb2;
                d.body.appendChild(s2);
                return;
            }

            /* ---------- baidu ---------- */
            if (cfg.type === 'baidu' && typeof w.BMap !== 'undefined') {
                var map = new w.BMap.Map(mapDiv);
                var point = new w.BMap.Point(cfg.value[0], cfg.value[1]);
                map.centerAndZoom(point, cfg.zoom);
                var marker = new w.BMap.Marker(point);
                map.addOverlay(marker);
                if (!ro) {
                    if (!input.value) {
                        var geolocation = new w.BMap.Geolocation();
                        geolocation.getCurrentPosition(function (r) {
                            if (this.getStatus() == w.BMAP_STATUS_SUCCESS) {
                                marker.setPosition(r.point);
                                map.panTo(r.point);
                                input.value = r.point.lng + ',' + r.point.lat;
                            } else if (w.console) {
                                w.console.log('failed' + this.getStatus());
                            }
                        });
                    }
                    marker.enableDragging();
                    marker.addEventListener('dragend', function (e) {
                        input.value = e.point.lng + ',' + e.point.lat;
                    });
                    map.addEventListener('click', function (e) {
                        marker.setPosition(e.point);
                        input.value = e.point.lng + ',' + e.point.lat;
                    });
                    if (search) {
                        var ac = new w.BMap.Autocomplete({ input: search.id, location: map });
                        var myValue;
                        ac.addEventListener('onconfirm', function (e) {
                            var _value = e.item.value;
                            myValue = _value.province + _value.city + _value.district + _value.street + _value.business;
                            setPlace();
                        });
                        function setPlace() {
                            function myFun() {
                                var pp = local.getResults().getPoi(0).point;
                                map.centerAndZoom(pp, cfg.zoom);
                                marker.setPosition(pp);
                                input.value = pp.lng + ',' + pp.lat;
                            }
                            var local = new w.BMap.LocalSearch(map, { onSearchComplete: myFun });
                            local.search(myValue);
                        }
                    }
                }
                map.addControl(new w.BMap.NavigationControl());
                map.addControl(new w.BMap.ScaleControl());
                map.addControl(new w.BMap.OverviewMapControl());
                return;
            }

            /* ---------- yandex ---------- */
            if (cfg.type === 'yandex' && typeof w.ymaps !== 'undefined') {
                w.ymaps.ready(function () {
                    var myMap = new w.ymaps.Map(mapDiv, cfg.opts);
                    var myPlacemark = new w.ymaps.Placemark(cfg.value, {}, {
                        preset: 'islands#redDotIcon',
                        draggable: true
                    });
                    if (!ro) {
                        myPlacemark.events.add(['dragend'], function () {
                            input.value = myPlacemark.geometry.getCoordinates()[1] + ',' + myPlacemark.geometry.getCoordinates()[0];
                        });
                    }
                    myMap.geoObjects.add(myPlacemark);
                });
            }
        }

        deinit() {
            if (this.__amap) {
                try { this.__amap.destroy(); } catch (e) { /* 容错 */ }
                this.__amap = null;
            }
            this.__input = null;
            this.__search = null;
            this.__mapDiv = null;
        }
    };
    BE.define('x-map', XMap);

    /* ============================================================
     * x-tree — Tree（jstree 3.3 复选树，表单字段）
     *
     * cfg：{data:[jstree 节点], multiple, cascade(three_state), enableCheck}
     * DOM 与旧 tree.html 同构：隐藏载体（name/id/value 落这，class ignore）
     * + .jstree-wrap（id = 载体id + '-tree' 派生形态，min/max-height 内联）。
     * changed.jstree → 载体值 + change 派发（触发 autoPost 等）；
     * 无 checkbox 模式（enableCheck=false）走 select_node 单选回写。
     * deinit 时 jstree('destroy')，行删除不再泄漏。
     * ============================================================ */
    var XTree = class extends BE.Base {
        build() {
            var cfg = this.cfg || {};
            var self = this;

            var carrier = d.createElement('input');
            carrier.type = 'hidden';
            carrier.className = 'ignore';
            this.carryFormAttrs(carrier);
            this.appendChild(carrier);
            this.__carrier = carrier;

            var wrap = d.createElement('div');
            wrap.className = 'jstree-wrap rounded-box border border-base-300 p-2' + (cfg.multiple ? '' : ' tree-single');
            if (carrier.id) wrap.setAttribute('id', carrier.id + '-tree'); // 旧模板容器派生 id
            wrap.style.minHeight = (cfg.minHeight || 200) + 'px';
            wrap.style.maxHeight = '400px';
            wrap.style.overflow = 'auto';
            this.appendChild(wrap);
            this.__wrap = wrap;
        }

        init() {
            var cfg = this.cfg || {};
            var wrap = this.__wrap;
            var carrier = this.__carrier;
            if (typeof w.jQuery === 'undefined' || !w.jQuery.fn.jstree) {
                if (w.console) w.console.warn('[x-tree] jQuery/jstree is not loaded');
                return;
            }

            // 6.191 checkbox 插件只对「多选 + 勾选」树加载；单选树点节点即选中，
            // 选择框无意义（Tree->multiple(false) 但 enableCheck 未关时不再显示勾选框）
            var withCheck = !!cfg.enableCheck && !!cfg.multiple;

            w.jQuery(wrap).jstree({
                core: {
                    data: cfg.data || [],
                    multiple: !!cfg.multiple,
                    // 6.194 回归官方 default 主题：dots（点状辅助线）+ 雪碧图箭头/勾选框/
                    // 选中色全由 vendor style.min.css 提供；6.197 依用户要求
                    // icons:false 去掉文件/文件夹图标（vendor 的 .jstree-no-icons 隐藏 themeicon）
                    themes: { name: 'default', dots: true, icons: false },
                    check_callback: true,
                },
                checkbox: { three_state: !!cfg.cascade },
                plugins: withCheck ? ['checkbox'] : [],
            });

            // core.multiple=false 时，点击即使被插件强制带上 ctrlKey，
            // 也走核心 activate_node 的单选分支（清空旧选再选新节点），无需额外拦截
            w.jQuery(wrap).on(withCheck ? 'changed.jstree' : 'select_node.jstree', function (e, data) {
                carrier.value = withCheck ? data.selected.join(',') : data.node.id;
                carrier.dispatchEvent(new Event('change', { bubbles: true }));
            });

            this.__jq = w.jQuery;
        }

        deinit() {
            if (this.__jq && this.__wrap) {
                try { this.__jq(this.__wrap).jstree('destroy'); } catch (e) { /* 行已移除等场景容错 */ }
            }
            this.__jq = null;
            this.__wrap = null;
            this.__carrier = null;
        }
    };
    BE.define('x-tree', XTree);

    /* ============================================================
     * x-selecttree — SelectTree（treeselectjs 树形下拉）
     *
     * 宿主即 .treeselect-wrap（旧模板的容器 div，{$class}/{$attr} 落宿主）；
     * 隐藏载体（id = 宿主id + '-hidden' 派生形态，multiple 时 name 加 []）。
     * cfg：{options(多形态原始 JSON), checked, multiple, jsOptions:{...}}。
     * options 多形态归一化（树形数组 / {value:name} 关联对象 / 纯文本数组）
     * 逐行等价移植；init 后显式同步一次载体（修复编辑页初值不回写的隐患，
     * Treeselect 构造期 input 事件是否派发无保证）。
     * ============================================================ */
    var XSelectTree = class extends BE.Base {
        build() {
            var cfg = this.cfg || {};
            var self = this;

            var carrier = d.createElement('input');
            carrier.type = 'hidden';
            if (cfg.multiple) carrier.setAttribute('name', '[]');
            this.carryFormAttrs(carrier);
            if (cfg.multiple && carrier.name && carrier.name.slice(-2) !== '[]') {
                carrier.setAttribute('name', carrier.name + '[]');
            }
            var id = carrier.id;
            if (id) carrier.setAttribute('id', id + '-hidden'); // 旧模板载体派生 id
            this.appendChild(carrier);
            this.__carrier = carrier;
        }

        init() {
            if (typeof w.Treeselect === 'undefined') {
                if (w.console) w.console.warn('[x-selecttree] Treeselect is not loaded');
                return;
            }
            var cfg = this.cfg || {};
            var carrier = this.__carrier;
            var self = this;

            // options 多形态归一化：树形数组 / {value:name} 关联对象 / 纯文本数组
            var raw = cfg.options || [];
            function toNode(n, key) {
                if (n === null || n === undefined) return null;
                if (typeof n === 'object') {
                    var val = String(n.value !== undefined ? n.value : (key !== undefined ? key : ''));
                    var name = n.name !== undefined ? String(n.name) : val;
                    var children = (n.children && n.children.length)
                        ? n.children.map(function (c) { return toNode(c); }).filter(Boolean)
                        : [];
                    return { name: name, value: val, children: children };
                }
                return { name: String(n), value: String(key !== undefined ? key : n), children: [] };
            }
            var treeOptions = (Array.isArray(raw) ? raw : Object.keys(raw))
                .map(function (n, i) { return toNode(Array.isArray(raw) ? n : raw[n], Array.isArray(raw) ? undefined : n); })
                .filter(Boolean);

            var jso = cfg.jsOptions || {};
            var isSingle = jso.isSingleSelect === true;
            var rawValue = cfg.checked || [];
            var initValue = isSingle
                ? (rawValue && rawValue.length ? rawValue[0] : null)
                : (rawValue && rawValue.length ? rawValue : []);

            var treeselect = new w.Treeselect({
                parentHtmlContainer: self,
                options: treeOptions,
                value: initValue,
                placeholder: jso.placeholder || BE.blang('builder_please_select', 'Please select'),
                isSingleSelect: isSingle,
                allowClear: jso.allowClear !== false,
                showCount: jso.showCount !== false,
                closeOnSelect: isSingle || jso.closeOnSelect === true,
                showValue: jso.showValue !== false,
                isGroupSelectable: !!jso.isGroupSelectable,
                isIndependentNodes: !!jso.isIndependentNodes,
                listMaxHeight: jso.listMaxHeight || 400,
                openLevel: jso.openLevel || 0,
            });

            function syncHidden(val) {
                if (Array.isArray(val)) carrier.value = val.join(',');
                else if (val === null || val === undefined) carrier.value = '';
                else carrier.value = val;
            }
            treeselect.srcElement.addEventListener('input', function (e) {
                syncHidden(e.detail);
            });
            syncHidden(treeselect.value); // 构造期显式同步（编辑页初值回写）

            this.__treeselect = treeselect;
        }

        deinit() {
            if (this.__treeselect) {
                try { this.__treeselect.destroy(); } catch (e) { /* 行已移除等场景容错 */ }
                this.__treeselect = null;
            }
            this.__carrier = null;
        }
    };
    BE.define('x-selecttree', XSelectTree);

    /* ============================================================
     * x-upload — MultipleFile/File/Image/MultipleImage（上传家族）
     *
     * 与 tpext-uploader.js 引擎协作：引擎完全由 window.uploadConfigs[inputId]
     * 注册表 + id 约定驱动（renderFiles=initUploaders 绑 picker+渲染预览），
     * 元素职责 = ①重建 DOM（preview_{id} + .upload-input-wrap 内嵌按钮 +
     * picker_{id}）②把 cfg 写进注册表 ③调 w.renderFiles()。
     * 旧模板的内联 uploadConfigs script（规范违例）与 rewriteId 的配置复制
     * （克隆行元素自注册，天然覆盖新 id）就此退役。
     * 按钮从内联 onclick（chooseFile({$id},{$id}) 裸标识符，id 含 - 时语法
     * 错误的隐患）改为实例监听。
     * 只读/表格列/搜索区分支仍是服务端静态渲染，不经此元素。
     *
     * cfg：MultipleFile::render 组装的全量 jsOptions（含 upload_url/chooseUrl/
     * ext/限制/缩略图/canUpload/showInput/showChooseBtn/showUploadBtn/isInTable/cover）
     * ============================================================ */
    var XUpload = class extends BE.Base {
        build() {
            var cfg = this.cfg || {};
            var self = this;

            var input = d.createElement('input');
            input.type = cfg.showInput === false ? 'hidden' : 'text';
            input.className = 'input input-bordered w-full file-url-input';
            this.carryFormAttrs(input);
            var inputId = input.id;

            var wrap = d.createElement('div');
            wrap.className = 'upload-input-wrap w-full';
            wrap.appendChild(input);

            var btns = d.createElement('div');
            btns.className = 'upload-input-btns';

            // 6.200 与占位块遮罩按钮统一图标（tpext-uploader.js uploadSvg/chooseSvg 同一对
            // SVG，stroke=currentcolor：这里继承按钮文字色，遮罩侧按钮 color:#fff 保持白描边）
            var uploadIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4m0 0 4 4m-4-4-4 4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>';
            var chooseIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>';

            if (cfg.showUploadBtn && !cfg.isInTable) {
                var up = d.createElement('button');
                up.type = 'button';
                up.title = BE.blang('builder_upload_nwe_file', 'Upload');
                up.className = 'btn btn-ghost opt-btn upload-file';
                up.setAttribute('data-id', inputId);
                up.setAttribute('data-name', input.name || '');
                up.innerHTML = uploadIcon + '<span class="opt-btn-text">'
                    + BE.blang('builder_upload_file_button', 'Upload') + '</span>';
                up.addEventListener('click', function () {
                    var picker = d.getElementById('picker_' + inputId);
                    if (picker) picker.click();
                });
                btns.appendChild(up);
            }

            if (cfg.showChooseBtn) {
                var ch = d.createElement('button');
                ch.type = 'button';
                ch.title = BE.blang('builder_choose_uploaded_file', 'Choose');
                ch.className = 'btn btn-ghost opt-btn choose-file';
                ch.setAttribute('data-id', inputId);
                ch.setAttribute('data-name', input.name || '');
                ch.innerHTML = chooseIcon + '<span class="opt-btn-text">'
                    + BE.blang('builder_choose_file_button', 'Choose') + '</span>';
                ch.addEventListener('click', function () {
                    if (typeof w.chooseFile === 'function') w.chooseFile(inputId, inputId);
                });
                btns.appendChild(ch);
            }

            // 6.196 showInput=false 时输入行按钮一并隐藏——上传/选择仍可经
            // 占位块遮罩按钮完成（tpext-uploader.js 的 maskUpload/maskChoose）
            if (btns.children.length && cfg.showInput !== false) wrap.appendChild(btns);

            var preview = d.createElement('div');
            if (inputId) preview.setAttribute('id', 'preview_' + inputId); // 引擎按此 id 找插入点
            preview.className = 'upload-preview-list';

            var picker = d.createElement('input');
            picker.type = 'file';
            picker.className = 'hidden';
            if (inputId) picker.setAttribute('id', 'picker_' + inputId);

            this.appendChild(preview);
            this.appendChild(wrap);
            this.appendChild(picker);
            this.__input = input;
        }

        init() {
            var input = this.__input;
            if (!input.id) return;
            var self = this;

            // 注册表条目（引擎驱动源）；克隆行 init 时天然以新 id 覆盖注册
            w.uploadConfigs = w.uploadConfigs || [];
            w.uploadConfigs[input.id] = this.cfg;

            // 引擎可能晚于本文件就绪（Field $js 与 commonJs 顺序不保证），轮询兜底
            var tries = 0;
            var t = setInterval(function () {
                var stillThere = self.isConnected && self.__input === input;
                if (!stillThere || typeof w.renderFiles === 'function' || ++tries > 50) {
                    clearInterval(t);
                }
                if (typeof w.renderFiles === 'function') {
                    w.renderFiles(); // = initUploaders()：绑 picker + 渲染预览（幂等）
                }
            }, 100);
            this.__regTimer = t;
        }

        deinit() {
            if (this.__regTimer) {
                clearInterval(this.__regTimer);
                this.__regTimer = null;
            }
            this.__input = null;
        }
    };
    BE.define('x-upload', XUpload);

    /* ============================================================
     * x-editor — 编辑器家族（MDEditor/MDReader/UEditor/AceEditor/CKEditor/WangEditor）
     *
     * cfg.driver 分发，各驱动逐行等价移植原 editorScript：
     *  mde       —— EasyMDE(element=textarea)：工具栏/上传回调/titles 覆盖；
     *               值契约 = textarea 本体（EasyMDE 原生回写）
     *  ueditor   —— UE.getEditor(script[text/plain])；window.uploadUrl 全局
     *               （多实例后者覆盖，旧内联 script 同款行为）
     *  ace       —— ace.edit(mount)：主题/模式/字号/补全，change 回写 textarea
     *  ckeditor  —— CKEDITOR.replace(textarea.name, configs)（提交由库自身同步）
     *  wang      —— wangEditor(mount p)：customConfig/onchange 回写 textarea
     *  mdpreview —— marked.parse(textContent)（MDEditor 只读态与 MDReader 共用，
     *               __mdRendered 幂等语义由元素 __ready__ 天然保证）
     * 资源包缺失（UE/CK 的 layer.alert）仍由 PHP 侧脚本推送，元素只管
     * window 上有库时的初始化，缺失时 console.warn 并保留载体。
     * ============================================================ */
    var XEditor = class extends BE.Base {
        build() {
            var cfg = this.cfg || {};
            var driver = cfg.driver;
            var self = this;

            if (driver === 'mdpreview') {
                var pv = d.createElement('div');
                pv.className = cfg.pvClass || 'field-show md-body';
                // 6.210 先取走 value 再 carryFormAttrs——后者会把 value 属性
                // 搬到 input.value 并从宿主移除，顺序反了预览永远拿到空串
                pv.textContent = this.getAttribute('value') || '';
                this.removeAttribute('value');
                if (this.getAttribute('id')) {
                    this.carryFormAttrs(pv); // id 落到预览容器（旧 #id-preview）
                }
                this.appendChild(pv);
                this.__mount = pv;
                return;
            }

            // 通用载体：textarea（mde/ace/ckeditor/wang），ueditor 用 script[text/plain]
            var carrier;
            if (driver === 'ueditor') {
                carrier = d.createElement('script');
                carrier.setAttribute('type', 'text/plain');
                carrier.textContent = this.getAttribute('value') || '';
                this.removeAttribute('value');
                this.carryFormAttrs(carrier);
                this.appendChild(carrier);
                this.__carrier = carrier;
                return;
            }

            carrier = d.createElement('textarea');
            carrier.className = 'input input-bordered w-full hidden';
            this.carryFormAttrs(carrier);
            this.__carrier = carrier;

            if (driver === 'ace') {
                var box = d.createElement('div');
                box.className = 'rounded-box border border-base-300 overflow-hidden';
                var mount = d.createElement('div');
                if (carrier.id) mount.setAttribute('id', carrier.id + '-editor'); // 旧挂载派生 id
                box.appendChild(mount);
                box.appendChild(carrier);
                this.appendChild(box);
                this.__mount = mount;
                return;
            }

            if (driver === 'wang') {
                var p = d.createElement('p');
                p.className = 'min-h-[120px]';
                if (carrier.id) p.setAttribute('id', carrier.id + '-div'); // 旧挂载派生 id
                p.innerHTML = this.getAttribute('value') || '';
                this.removeAttribute('value');
                this.appendChild(p);
                this.appendChild(carrier);
                this.__mount = p;
                return;
            }

            // mde / ckeditor：textarea 即挂载
            this.appendChild(carrier);
        }

        init() {
            var cfg = this.cfg || {};
            var driver = cfg.driver;
            this.__driver = driver;
            var carrier = this.__carrier;
            var mount = this.__mount;
            var self = this;

            if (driver === 'mdpreview') {
                if (typeof w.marked === 'undefined') return;
                mount.innerHTML = w.marked.parse(mount.textContent, Object.assign({ breaks: true }, cfg.configs || {}));
                return;
            }

            if (driver === 'mde') {
                if (typeof w.EasyMDE === 'undefined') { if (w.console) w.console.warn('[x-editor] EasyMDE is not loaded'); return; }
                var mde = new w.EasyMDE(Object.assign({
                    element: carrier,
                    minHeight: '300px',
                    autosave: { enabled: false },
                    toolbar: ['bold', 'italic', 'heading', '|', 'unordered-list', 'ordered-list', 'quote', 'code', 'table', 'link', 'upload-image', 'image', '|', 'preview', 'side-by-side', 'fullscreen', '|', 'guide'],
                    uploadImage: true,
                    imageUploadFunction: function (file, onSuccess, onError) {
                        var fd = new FormData();
                        fd.append('editormd-image-file', file);
                        w.axios.post(cfg.imageUploadUrl, fd)
                            .then(function (res) {
                                var dd = res.data || {};
                                if (dd.success == 1 && dd.url) { onSuccess(dd.url); }
                                else { onError(dd.message || dd.info || BE.blang('builder_upload_failed', 'Upload failed')); }
                            })
                            .catch(function () { onError(BE.blang('builder_md_network_error', 'Network error')); });
                    },
                }, cfg.options || {}));

                // EasyMDE 的 title 是英文硬编码且无文案选项，初始化后按按钮 name 覆盖成当前语言
                var titles = cfg.titles || {};
                var btns = mde.toolbarElements || {};
                for (var k in titles) {
                    if (!btns[k]) { continue; }
                    // 保留原 title 末尾的快捷键提示，如 "Toggle Preview (Ctrl-P)" → "预览 (Ctrl-P)"
                    var shortcut = (btns[k].title || '').match(/\(([^()]*)\)\s*$/);
                    var text = titles[k] + (shortcut ? ' (' + shortcut[1] + ')' : '');
                    btns[k].title = text;
                    btns[k].setAttribute('aria-label', text);
                }
                this.__inst = mde;
                return;
            }

            if (driver === 'ueditor') {
                if (typeof w.UE === 'undefined') { if (w.console) w.console.warn('[x-editor] UE is not loaded'); return; }
                w.uploadUrl = cfg.uploadUrl || '';
                this.__inst = w.UE.getEditor(carrier.id, { initialFrameWidth: '100%', initialFrameHeight: 320 });
                return;
            }

            if (driver === 'ace') {
                if (typeof w.ace === 'undefined') { if (w.console) w.console.warn('[x-editor] ace is not loaded'); return; }
                var configs = cfg.configs || {};
                mount.style.position = 'relative';
                mount.style.width = configs.width;
                mount.style.height = configs.height;

                var ed = w.ace.edit(mount.id);
                ed.setTheme('ace/theme/' + (configs.dark ? 'one_dark' : 'textmate'));
                ed.session.setMode('ace/mode/' + configs.mode);
                ed.setFontSize(configs.fontSize);
                ed.setOptions({
                    enableBasicAutocompletion: configs.enableBasicAutocompletion,
                    enableSnippets: configs.enableSnippets,
                    enableLiveAutocompletion: configs.enableLiveAutocompletion,
                });
                ed.resize();
                ed.setReadOnly(!!cfg.ro);
                ed.getSession().setUseWrapMode(true);
                ed.setShowPrintMargin(false);

                ed.setValue(carrier.value);
                ed.moveCursorTo(0, 0);
                ed.session.on('change', function () {
                    carrier.value = ed.getValue();
                });
                this.__inst = ed;
                return;
            }

            if (driver === 'ckeditor') {
                if (typeof w.CKEDITOR === 'undefined') { if (w.console) w.console.warn('[x-editor] CKEDITOR is not loaded'); return; }
                w.CKEDITOR.disableAutoInline = true; // 兜底：防扫描时序在本次执行之前
                this.__inst = w.CKEDITOR.replace(carrier.name, cfg.configs || {});
                return;
            }

            if (driver === 'tinymce') {
                if (typeof w.tinymce === 'undefined') { if (w.console) w.console.warn('[x-editor] tinymce is not loaded'); return; }
                // init 返回 thenable（v6+）或编辑器数组（旧版），两种形态都接住
                var editors = w.tinymce.init(cfg.configs || {});
                if (editors && editors.length) {
                    this.__inst = editors[0];
                } else if (editors && editors.then) {
                    var slf = this;
                    editors.then(function (arr) {
                        if (slf.isConnected && arr && arr.length) slf.__inst = arr[0];
                    });
                }
                return;
            }

            if (driver === 'wang') {
                if (typeof w.wangEditor === 'undefined') { if (w.console) w.console.warn('[x-editor] wangEditor is not loaded'); return; }
                var E = w.wangEditor;
                var editor = new E('#' + mount.id);
                editor.customConfig = cfg.configs || {};
                editor.customConfig.uploadImgHooks = {
                    customInsert: function (insertImg, result) {
                        var url = result.url;
                        insertImg(url);
                    },
                };
                editor.customConfig.onchange = function (html) {
                    carrier.value = html;
                };
                editor.create();
                this.__inst = editor;
            }
        }

        deinit() {
            if (this.__inst) {
                try {
                    if (this.__driver === 'tinymce') this.__inst.remove();
                    else if (this.__driver === 'mde' || this.__driver === 'ueditor' || this.__driver === 'ckeditor' || this.__driver === 'wang') this.__inst.destroy();
                } catch (e) { /* 行已移除等场景容错 */ }
            }
            this.__inst = null;
            this.__carrier = null;
            this.__mount = null;
        }
    };
    BE.define('x-editor', XEditor);

    /* ============================================================
     * x-text / x-textarea — Text / Textarea（6.427 Web Components 化）
     *
     * 原生控件继续承担输入与提交（light DOM），组件宿主只做两件事：
     * ① 表单契约搬运（Base.carryFormAttrs：name/id/value/placeholder/状态属性；
     *    items 复制行「模板行剥名→克隆重写→插入升级」的次序天然正确，maxlength
     *    在 items 行内由内部原生控件直接生效——这是本次组件化的动机）；
     * ② maxlength 计数：「N/M」悬浮在控件右缘，用到上限 ~80% 才出现，超限
     *    控件与计数转红。组件内部自洽（input 事件即刷），取代 6.426 的
     *    全局扫描 + document 委托方案（那版计数行在控件下方，被否——打破
     *    32px 控件高度节奏）。
     * text 前后缀（.text-suffix-wrap 的 .text-prefix/.text-suffix 浮层）由模板
     * 保持不变；宿主以 flex-1 参与该 flex 行。有后缀浮层时计数右移让位。
     * ============================================================ */
    var XTextBase = class extends BE.Base {
        setupCount(input) {
            var max = parseInt(this.getAttribute('maxlength'), 10);
            if (!(max > 0)) return;
            if (!input.getAttribute('maxlength')) input.setAttribute('maxlength', String(max));

            var counter = d.createElement('span');
            counter.className = 'builder-char-count';
            var post = this.nextElementSibling;
            if (post && post.classList && post.classList.contains('text-suffix')) {
                counter.style.right = '2.25rem'; // 让位后缀浮层（与输入框 padding 同步）
            }
            this.appendChild(counter);

            var shown = false;
            function update() {
                var len = String(input.value).length;
                var over = len > max;
                var near = len >= Math.ceil(max * 0.8);
                input.classList.toggle('builder-char-over', over);
                if (!near && !over) {
                    if (shown) {
                        shown = false;
                        counter.classList.remove('builder-char-show');
                        input.classList.remove('char-counted');
                    }
                    return;
                }
                shown = true;
                counter.textContent = len + '/' + max;
                counter.classList.add('builder-char-show');
                counter.classList.toggle('builder-char-over', over);
                input.classList.add('char-counted');
                counter.title = over
                    ? BE.blang('builder_chars_over', '{:num} characters over').replace('{:num}', String(len - max))
                    : BE.blang('builder_chars_left', '{:num} characters left').replace('{:num}', String(max - len));
            }
            input.addEventListener('input', update);
            update();
        }
    };

    var XText = class extends XTextBase {
        build() {
            var input = d.createElement('input');
            input.type = 'text';
            input.className = 'input input-bordered w-full';
            this.carryFormAttrs(input);
            this.appendChild(input);
            this.__input = input;
            this.setupCount(input);
        }
        deinit() { this.__input = null; }
    };
    BE.define('x-text', XText);

    var XTextarea = class extends XTextBase {
        build() {
            var ta = d.createElement('textarea');
            ta.className = 'textarea textarea-bordered w-full';
            var rows = this.getAttribute('rows');
            if (rows !== null) {
                ta.setAttribute('rows', rows);
                this.removeAttribute('rows');
            }
            this.carryFormAttrs(ta);
            this.appendChild(ta);
            this.__input = ta;
            this.setupCount(ta);
        }
        deinit() { this.__input = null; }
    };
    BE.define('x-textarea', XTextarea);

})(window, document);
