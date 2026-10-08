/**
 * tpextbuilder.js — tpext-daisyui 运行时 JS
 *
 * 职责：
 * 1. 全局语言包 window.__blang
 * 2. 表单提交拦截（调用各 form.__forms__[id].formSubmit）
 * 3. 批量操作 checkbox 全选 / 计数
 * 4. Table 批量/行内操作（confirm 弹窗确认 + autoSendData AJAX 提交，带 __token__ 与 _method 伪装）
 * 5. Flatpickr locale 注册（CDN 版 zh.js 是 AMD，需要手动挂）
 * 6. Tom-Select 默认配置
 * 7. Items 行操作（复制 / 删除 / 上下移动）
 *
 * 依赖（加载顺序）：alpine.min.js → axios.min.js → flatpickr.min.js → tom-select.min.js
 *                  → tpb.js（lightyear/layer 兼容层）→ tpextbuilder.js（本文件）
 */

(function (w, d) {
    'use strict';

    /* ============================================================
     * 1. 全局语言包占位（PHP 通过 View::share 注入后会覆盖此默认值）
     * ============================================================ */
    w.__blang = w.__blang || {
        builder_save_succeeded: '保存成功',
        builder_save_failed: '保存失败',
        builder_network_error: '网络错误，请重试',
        builder_loading: '加载中...',
        builder_value_is_empty: '未选择',
        builder_loading_error: '加载失败',
        builder_please_select: '请选择',
        builder_confirm: '确认操作？',
        builder_delete_confirm: '确认删除选中项？',
        builder_batch_ok: '批量操作成功',
        builder_batch_fail: '批量操作失败',
    };

    /* ============================================================
     * 2. Flatpickr 中文 locale 注册（兼容 CDN 版 zh.js 的 AMD 格式）
     * 6.169 仅中文项目注册：英文项目不再全局注入中文月份/星期。
     * __builder_lang 由 content.html 注入（PHP builder_default_lang()）；
     * 变量缺失时（宿主页面直接引本文件的旧用法）按中文处理，保持兼容。
     * ============================================================ */
    function registerFlatpickrZh() {
        if (typeof flatpickr === 'undefined') return;
        var lang = (w.__builder_lang || 'zh').toLowerCase();
        if (lang.indexOf('zh') !== 0) return;
        if (flatpickr.l10ns && flatpickr.l10ns.zh) return; // 已经注册过

        // 如果 AMD 加载的 zh.js 没挂到 flatpickr.l10ns 上，手动注册一个简化版
        if (!flatpickr.l10ns) flatpickr.l10ns = {};
        flatpickr.l10ns.zh = flatpickr.l10ns['zh-cn'] || {
            firstDayOfWeek: 1,
            lastDayOfWeek: 7,
            weekdays: {
                shorthand: ['日', '一', '二', '三', '四', '五', '六'],
                longhand: ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'],
            },
            months: {
                shorthand: ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'],
                longhand: ['一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '十一月', '十二月'],
            },
            rangeSeparator: ' 至 ',
            weekAbbreviation: '周',
            scrollTitle: '滚动切换',
            toggleTitle: '点击切换',
            amPM: ['上午', '下午'],
            yearAriaLabel: '年',
            monthAriaLabel: '月',
            hourAriaLabel: '小时',
            minuteAriaLabel: '分钟',
        };
    }

    /* ============================================================
     * 2.1 Flatpickr 年份面板（window.__fpYearPanel）
     * 在日期/日期时间的日历里点「年」，展开 12 年网格 + 前后十年翻页（与 Month/Year
     * 组件的 decade 面板同款），解决 flatpickr 原生只能靠上下箭头 / 手输年份、
     * 跨年跨度大时极难切换的问题。
     *
     * 用法（必须在 flatpickr() 返回实例之后再调用，不能走 config.plugins）：
     *     var fp = flatpickr(el, opts);
     *     window.__fpYearPanel(fp);
     * 原因：flatpickr 是在构造函数早期就去调用 config.plugins 里的各插件（此时
     *      calendarContainer 尚未创建），插件内部访问 fp.calendarContainer 会抛
     *      “Cannot read properties of undefined”。所以只能等实例构造完成后再挂载。
     *
     * 注意：必须是模块级定义（不能只放在 boot 里）——页面底部的初始化脚本在
     *      DOMContentLoaded 内执行，要保证届时已可用。
     * ============================================================ */
    function yearPanelPlugin(fp) {
        if (fp.config.noCalendar) return; // 纯时间选择器没有月份头，不处理

        var cal = fp.calendarContainer;
        var months = cal.querySelector('.flatpickr-months');
        var curYear = cal.querySelector('.flatpickr-current-month .cur-year');
        if (!months || !curYear) return;

        var panel = d.createElement('div');
        panel.className = 'fp-year-panel';
        panel.innerHTML =
            '<div class="fp-year-head">' +
            '<button type="button" class="fp-year-nav" data-step="-12">&laquo;</button>' +
            '<span class="fp-year-range"></span>' +
            '<button type="button" class="fp-year-nav" data-step="12">&raquo;</button>' +
            '</div>' +
            '<div class="fp-year-grid"></div>';
        months.insertAdjacentElement('afterend', panel);

        var grid = panel.querySelector('.fp-year-grid');
        var rangeEl = panel.querySelector('.fp-year-range');
        var decadeStart = fp.currentYear - 5;

        function render() {
            rangeEl.textContent = decadeStart + ' - ' + (decadeStart + 11);
            var html = '';
            for (var i = decadeStart; i < decadeStart + 12; i++) {
                html += '<button type="button" class="fp-year-cell' +
                    (i === fp.currentYear ? ' is-current' : '') +
                    '" data-year="' + i + '">' + i + '</button>';
            }
            grid.innerHTML = html;
        }

        function openPanel() {
            decadeStart = fp.currentYear - 5;
            render();
            cal.classList.add('fp-year-open');
        }

        function closePanel() {
            cal.classList.remove('fp-year-open');
        }

        function pick(year) {
            fp.changeYear(year); // 与日历内置「年」上下箭头同一条路径，视图随之处刷新
            closePanel();
        }

        // 「年」改为面板触发器：只读（改由面板选），加 .fp-year-trigger 给指针样式
        curYear.readOnly = true;
        curYear.classList.add('fp-year-trigger');

        // 委托绑定在 calendarContainer 上（捕获阶段），对 items 复制行/表格每行都成立
        cal.addEventListener('click', function (e) {
            // 年份上下箭头：交回 flatpickr 原生逻辑（逐 1 步进），不拦
            if (e.target.closest('.arrowUp') || e.target.closest('.arrowDown')) return;

            var nav = e.target.closest('.fp-year-nav');
            if (nav) {
                e.preventDefault();
                e.stopPropagation();
                decadeStart += parseInt(nav.getAttribute('data-step'), 10);
                render();
                return;
            }

            var cell = e.target.closest('.fp-year-cell');
            if (cell) {
                e.preventDefault();
                e.stopPropagation();
                pick(parseInt(cell.getAttribute('data-year'), 10));
                return;
            }

            // 只把「年」区域当触发器；月份下拉框不在此列，不受影响
            if (e.target.closest('.flatpickr-current-month .numInputWrapper')) {
                e.preventDefault();
                e.stopPropagation();
                if (cal.classList.contains('fp-year-open')) {
                    closePanel();
                } else {
                    openPanel();
                }
            }
        }, true);

        // 6.161 面板打开时头部已隐藏：点日历内其他区域（时间行等）也收起面板，
        // 给「不选年退出」留途径（触发器本身已随头部隐藏，无法再点）
        cal.addEventListener('click', function (e) {
            if (!cal.classList.contains('fp-year-open')) return;
            if (e.target.closest('.fp-year-panel') || e.target.closest('.flatpickr-current-month')) return;
            closePanel();
        });

        // 每次重新打开日历时收起面板。
        // 注意：flatpickr 触发钩子时是按数组遍历的（源码 triggerEvent: config[e].length），
        // 所以必须赋成「函数数组」；直接赋一个函数会因 t[0] === undefined 而完全不触发。
        fp.config.onOpen = [closePanel].concat(fp.config.onOpen || []);
    }

    w.__fpYearPanel = yearPanelPlugin;

    /* ============================================================
     * 2.2 Flatpickr 月份面板（window.__fpMonthPanel，6.174）
     * 原生月份 <select> 的弹层由浏览器自绘不受控，且 appearance:base-select
     * 仅 Chromium 135+ 支持——用户用 Firefox（6.174 反馈）完全无效果。
     * 改为纯 JS 自绘 12 月网格面板，全引擎一致：
     * - mousedown 阶段 preventDefault：所有引擎（含 Firefox）都不再弹原生
     *   下拉。这正是 6.160 自绘面板「点击闪烁、无法选月」的根治点——当年
     *   闪烁即原生弹层与自绘面板同屏所致，此前未查明真因。
     * - 月名取 fp.l10n.months.longhand，自动跟随项目语言（6.169 locale 链）。
     * - 选月 = 给 select 赋值 + 派发 change：flatpickr 内部监听该 select 的
     *   change → changeMonth(目标月-当前月) + onMonthChange，与原生选月走
     *   同一条内部路径。
     * - 开关途径：点 trigger / Enter / Space / 上下箭头；选月、点日历其他
     *   区域收起；每次重新打开日历时复位（onOpen 前插，须为函数数组）。
     * 6.175 面板打开时连月份/年份标题一起隐藏（CSS，同 6.161 年份面板策略），
     * 面板即整个组件；与年份面板（2.1）的互斥由 CSS 双向隐藏兜底。
     * ============================================================ */
    function monthPanelPlugin(fp) {
        if (fp.config.noCalendar) return; // 纯时间选择器没有月份头，不处理

        var cal = fp.calendarContainer;
        var months = cal.querySelector('.flatpickr-months');
        var ms = cal.querySelector('.flatpickr-monthDropdown-months');
        if (!months || !ms || ms.dataset.fpMonthPanel) return;
        ms.dataset.fpMonthPanel = '1'; // 幂等：items 复制行/重复 init 不重复挂
        ms.classList.add('fp-month-trigger');

        var panel = d.createElement('div');
        panel.className = 'fp-month-panel';
        panel.innerHTML = '<div class="fp-month-grid"></div>';
        months.insertAdjacentElement('afterend', panel);
        var grid = panel.querySelector('.fp-month-grid');

        function isOpen() {
            return cal.classList.contains('fp-month-open');
        }

        function render() {
            var names = fp.l10n.months.longhand;
            var html = '';
            for (var i = 0; i < 12; i++) {
                html += '<button type="button" class="fp-month-cell' +
                    (i === fp.currentMonth ? ' is-current' : '') +
                    '" data-month="' + i + '">' + names[i] + '</button>';
            }
            grid.innerHTML = html;
        }

        function openPanel() {
            render();
            cal.classList.remove('fp-year-open'); // 与年份面板互斥
            cal.classList.add('fp-month-open');
        }

        function closePanel() {
            cal.classList.remove('fp-month-open');
        }

        function pick(month) {
            // 原生 select 的 12 个 option（value 0-11）由 flatpickr 维护，直接赋值
            ms.value = String(month);
            ms.dispatchEvent(new Event('change', { bubbles: true }));
            closePanel();
        }

        // 拦原生下拉：mousedown preventDefault 后所有引擎都不弹原生弹层
        cal.addEventListener('mousedown', function (e) {
            if (e.target.closest('.flatpickr-monthDropdown-months')) e.preventDefault();
        }, true);

        // select 聚焦时 Enter/Space/箭头也会弹原生下拉，一并拦下改为开关面板
        cal.addEventListener('keydown', function (e) {
            if (e.target !== ms) return;
            if (['Enter', ' ', 'ArrowDown', 'ArrowUp'].indexOf(e.key) === -1) return;
            e.preventDefault();
            e.stopPropagation();
            if (isOpen()) closePanel(); else openPanel();
        });

        cal.addEventListener('click', function (e) {
            var cell = e.target.closest('.fp-month-cell');
            if (cell) {
                e.preventDefault();
                e.stopPropagation();
                pick(parseInt(cell.getAttribute('data-month'), 10));
                return;
            }
            if (e.target.closest('.flatpickr-monthDropdown-months')) {
                e.preventDefault();
                e.stopPropagation();
                if (isOpen()) closePanel(); else openPanel();
            }
        }, true);

        // 面板打开时点日历内其他区域（时间行等）也收起，留「不选月退出」途径
        cal.addEventListener('click', function (e) {
            if (!isOpen()) return;
            if (e.target.closest('.fp-month-panel') || e.target.closest('.flatpickr-current-month')) return;
            closePanel();
        });

        // 每次重新打开日历时收起面板（flatpickr 触发钩子按数组遍历，必须赋函数数组）。
        // 6.316：月份 select 是 width:auto（取最宽 option），Firefox 对面板首次显示的
        // select 内在宽度走「首帧临时值→次帧重测量」两段式，居中基线随宽度增长左移
        // （用户观察到的「弹出瞬间月份-年份往左移动」）；onOpen 在面板显示后、首帧
        // 绘制前的同一同步任务里触发，此处强制布局并当场钉死宽度，首帧即最终几何。
        // 年份 numInputWrapper（6ch）同理一并钉住
        fp.config.onOpen = [function () {
            if (!ms.style.width) {
                ms.style.width = ms.offsetWidth + 'px';
            }
            var yw = cal.querySelector('.numInputWrapper');
            if (yw && !yw.style.width) {
                yw.style.width = yw.offsetWidth + 'px';
            }
            closePanel();
        }].concat(fp.config.onOpen || []);
    }

    w.__fpMonthPanel = monthPanelPlugin;

    /* ============================================================
     * 3. Tom-Select 默认配置（封装 constructor，在实例化时注入）
     * ============================================================ */
    function registerTomSelectDefaults() {
        if (typeof TomSelect === 'undefined') return;
        var _Orig = TomSelect;
        var _Wrapped = function (el, opt) {
            opt = opt || {};
            if (opt.allowClear === undefined) opt.allowClear = true;
            if (opt.placeholder === undefined) opt.placeholder = '';
            var ts = new _Orig(el, opt);
            // 移除 wrapper 上复制的 DaisyUI select 类，防止与 TomSelect 自带样式冲突
            // （双层边框、高度叠加、padding 覆盖等问题均由此引起）
            if (ts.wrapper) {
                // 用 .input .input-bordered 替换 .select .select-bordered
                // wrapper 直接继承单行输入框样式（高度/边框/圆角/背景），无需额外覆盖
                ts.wrapper.classList.remove('select', 'select-bordered');
                ts.wrapper.classList.add('input', 'input-bordered');
            }
            return ts;
        };
        // 复制静态属性（TomSelect.define / TomSelect.prototype 等）
        for (var k in _Orig) {
            if (Object.prototype.hasOwnProperty.call(_Orig, k)) {
                _Wrapped[k] = _Orig[k];
            }
        }
        // prototype 也要指向同一个，否则 instanceof TomSelect 会失败
        _Wrapped.prototype = _Orig.prototype;
        w.TomSelect = _Wrapped;
    }

    /* ============================================================
     * 4. 表单提交拦截：调用 window.__forms__[id].formSubmit()
     * ============================================================ */
    function initFormSubmit() {
        d.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || form.tagName !== 'FORM') return;

            var wrapper = form.closest('.form-wrapper');
            if (!wrapper || !wrapper.id) return;

            var formId = wrapper.id;
            if (!w.__forms__ || !w.__forms__[formId]) return;

            e.preventDefault();
            return w.__forms__[formId].formSubmit();
        });
    }

    /* ============================================================
     * 4.5 表单校验提示层（6.429）
     *
     * form.html 已加 novalidate——浏览器原生气泡（跟随浏览器 UI 语言、
     * 各家样式/位置不一、且只在控件旁一闪即逝）整体让位，改自绘三件套：
     * ① 无效控件挂 .builder-invalid（CSS 红边框 + focus 红光环，含
     *    TomSelect .ts-wrapper / x-editor 宿主路径）；
     * ② .builder-form-error 错误行插在提交按钮行上方（按钮常居中，
     *    错误行同宽居中）；
     * ③ 右上角 tpb.notify('danger') 通知（toast-top toast-end 容器）。
     * 约束规则仍由服务端输出的 HTML 属性驱动（required/type/min/max/
     * maxlength/pattern——checkValidity 与原 reportValidity 同一套引擎），
     * 不引入任何校验库（6.428 zod 已定调不引入）。
     * 消息文案走 __blang（builder_field_invalid 等），首错滚动+聚焦。
     * 6.430 起错误行/通知精确到首错字段名（fieldDisplayName：label[for] →
     * items 列头 → 行 label → name），格式 builder_field_error_line。
     * ============================================================ */
    function validityMessage(el) {
        var v = el.validity;
        if (!v || v.valid) return '';
        if (v.valueMissing) return blang('builder_this_field_is_required') || 'This field is required';
        if (v.typeMismatch || v.badInput) return blang('builder_field_invalid') || 'Invalid field value';
        if (v.rangeOverflow || v.rangeUnderflow || v.stepMismatch) {
            return blang('builder_value_out_of_range') || 'Value out of allowed range';
        }
        if (v.tooLong || v.tooShort || v.patternMismatch) {
            return blang('builder_field_invalid') || 'Invalid field value';
        }
        return blang('builder_field_invalid') || 'Invalid field value';
    }

    /* 6.430 首错字段显示名：label[for] → items 表格列头 → 所在行 label → name。
     * labeltempl 的文本是 "*标签"，剥掉星号；items 克隆行没有 label[for]，
     * 但都在真表 tbody 里，按 td 序号取 thead 同列 th 的列名 */
    function fieldDisplayName(el) {
        var txt = '', lb = null;
        if (el.id) {
            try {
                lb = d.querySelector('label[for="' + el.id.replace(/(["\\])/g, '\\$1') + '"]');
            } catch (e) { lb = null; }
        }
        if (lb) txt = lb.textContent;
        if (!txt && el.closest) {
            var td = el.closest('td');
            if (td) {
                var tr = td.parentElement;
                var table = tr ? tr.closest('table') : null;
                var head = table ? table.querySelector('thead') : null;
                var idx = tr ? Array.prototype.indexOf.call(tr.children, td) : -1;
                var th = (head && head.rows.length && idx > -1 && idx < head.rows[0].cells.length)
                    ? head.rows[0].cells[idx] : null;
                if (th) txt = th.textContent;
            }
        }
        if (!txt && el.closest) {
            var row = el.closest('.field-row');
            var rlb = row ? row.querySelector('label') : null;
            if (rlb) txt = rlb.textContent;
        }
        if (!txt && el.name) txt = el.name;
        return String(txt).replace(/^\s*\*+\s*/, '').replace(/\s*\*+\s*$/, '').trim();
    }

    function markInvalid(el, on) {
        el.classList.toggle('builder-invalid', on);
        // TomSelect：宿主 select 藏在 .ts-wrapper 里 display:none，红边画在 ts-control
        var ts = el.closest ? el.closest('.ts-wrapper') : null;
        if (ts) ts.classList.toggle('builder-invalid', on);
        // x-editor 等宿主元素内的隐藏载体：给可见宿主轮廓圈红（CSS :has 兜底）
        var host = el.closest ? el.closest('x-editor') : null;
        if (host) host.classList.toggle('builder-invalid', on);
    }

    function clearFormInvalid(formEl) {
        formEl.querySelectorAll('.builder-invalid').forEach(function (el) {
            el.classList.remove('builder-invalid');
        });
        var line = formEl.querySelector('.builder-form-error');
        if (line && line.parentNode) line.parentNode.removeChild(line);
    }

    function showFormError(formEl, msg) {
        var box = d.createElement('div');
        box.className = 'builder-form-error';
        var span = d.createElement('span');
        span.className = 'builder-form-error-text';
        span.textContent = msg;
        box.appendChild(span);
        // 错误行插在提交按钮所在行上方；按钮不在行容器里时兜底插在按钮前
        var submit = formEl.querySelector('[type=submit]');
        var row = submit ? (submit.closest('.field-row') || submit) : null;
        if (row && row.parentNode) {
            row.parentNode.insertBefore(box, row);
        } else {
            formEl.appendChild(box);
        }
    }

    function validateForm(formEl) {
        clearFormInvalid(formEl);

        var firstBad = null;
        var controls = formEl.querySelectorAll('input, select, textarea');
        Array.prototype.forEach.call(controls, function (el) {
            if (el.disabled || el.readOnly || el.type === 'hidden' || typeof el.checkValidity !== 'function') return;
            if (!el.checkValidity()) {
                markInvalid(el, true);
                if (!firstBad) firstBad = el;
            }
        });

        if (!firstBad) return true;

        // 6.430 提示精确到首错字段名：错误行与通知统一为「字段名：原因」；
        // 取不到字段名时回退整表提示（builder_fix_errors_before_submit）
        var reason = validityMessage(firstBad);
        var fname = fieldDisplayName(firstBad);
        var msg = fname
            ? (blang('builder_field_error_line') || '{:field}: {:reason}')
                .replace('{:field}', fname).replace('{:reason}', reason || blang('builder_field_invalid') || 'Invalid field value')
            : (blang('builder_fix_errors_before_submit') || 'Please fix the highlighted fields');
        showFormError(formEl, msg);
        if (w.tpb && typeof w.tpb.notify === 'function') {
            w.tpb.notify(
                msg,
                'danger',
                3000
            );
        }
        try {
            firstBad.scrollIntoView({ block: 'center' });
        } catch (e) { /* 老浏览器无 options */ }
        try {
            firstBad.focus({ preventScroll: true });
        } catch (e) {
            firstBad.focus();
        }
        return false;
    }

    // 修正即摘红：委托 input/change（capture），控件恢复合法就摘掉红边
    function initInvalidClear() {
        var clearIfFixed = function (e) {
            var el = e.target;
            if (!el || !el.classList || !el.classList.contains('builder-invalid')) return;
            if (typeof el.checkValidity === 'function' && el.checkValidity()) {
                markInvalid(el, false);
                // 全表已无无效项时摘掉错误行
                var form = el.closest('form');
                if (form && !form.querySelector('.builder-invalid')) {
                    var line = form.querySelector('.builder-form-error');
                    if (line && line.parentNode) line.parentNode.removeChild(line);
                }
            }
        };
        d.addEventListener('input', clearIfFixed, true);
        d.addEventListener('change', clearIfFixed, true);
    }

    /* ============================================================
     * 5. 批量选择 + Table 全选 checkbox
     * ============================================================ */
    function initBatchSelect() {
        // 全选 checkbox
        d.querySelectorAll('[data-toggle="check-all"]').forEach(function (master) {
            master.addEventListener('change', function () {
                var target = master.getAttribute('data-target') || '.check-item';
                d.querySelectorAll(target).forEach(function (cb) {
                    cb.checked = master.checked;
                });
                updateBatchBtnState();
            });
        });

        // 单项 checkbox 变化
        d.querySelectorAll('.check-item').forEach(function (cb) {
            cb.addEventListener('change', updateBatchBtnState);
        });

        // 表格行复选框变化 → 切换批量按钮可用态（委托，AJAX 刷新后新 DOM 仍生效；
        // checkall 批量勾选时 tableScript Handler 1 会对每行派发 change，同样命中）
        d.addEventListener('change', function (e) {
            if (e.target && e.target.classList && e.target.classList.contains('table-row-checkbox')) {
                updateBatchBtnState();
            }
        });

        // 批量操作按钮
        d.querySelectorAll('[data-batch-action]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var action = btn.getAttribute('data-batch-action');
                var url = btn.getAttribute('data-url') || btn.href;
                var field = btn.getAttribute('data-field') || 'ids';

                var ids = [];
                d.querySelectorAll('.check-item:checked').forEach(function (cb) {
                    ids.push(cb.value);
                });

                if (!ids.length) {
                    tpb.notify('请先选择要操作的项', 'warning');
                    return;
                }

                var confirmMsg = btn.getAttribute('data-confirm') || w.__blang.builder_confirm;
                confirmModal(confirmMsg, function () {
                    axios.post(url, { field: field, ids: ids, _token: getCsrf() })
                        .then(function (r) {
                            var d = r.data || {};
                            tpb.notify(d.msg || d.message || w.__blang.builder_batch_ok, d.status ? 'success' : 'danger');
                            if (d.url) {
                                setTimeout(function () { location.replace(d.url); }, 1500);
                            } else {
                                setTimeout(function () { location.reload(); }, 1200);
                            }
                        })
                        .catch(function () {
                            // 6.257 网络错误提示由全局 axios 拦截器（initAxiosDefaults）统一出口，
                        });
                });
            });
        });
    }

    function updateBatchBtnState() {
        var checked = d.querySelectorAll('.check-item:checked').length;
        d.querySelectorAll('[data-batch-action]').forEach(function (btn) {
            btn.disabled = checked === 0;
        });

        // 表格批量按钮（postChecked/openChecked 绑定时打 batch-action-btn 标记）：
        // 6.155 起不用 daisyUI btn-disabled——它会把按钮统一刷成中性灰、丢掉各自颜色；
        // 改用专用类 batch-disabled（builder-input.css：原色降透明度 + not-allowed 光标），
        // 点击拦截由下方各绑定函数的守卫承担
        var anyRow = d.querySelectorAll('.table-row-checkbox:checked').length > 0;
        d.querySelectorAll('.batch-action-btn').forEach(function (el) {
            el.classList.toggle('batch-disabled', !anyRow);
            el.setAttribute('aria-disabled', anyRow ? 'false' : 'true');
        });
    }

    /* ============================================================
     * 5.1 表单 checkbox/radio 的全选（input.checkall[data-check]，旧库 checkall 绑定的迁移）
     * 委托 document：items 动态添加的行同样生效；boot 幂等保证不重复绑定
     * 子项更新全选态时跳过 disabled/readonly 项（只统计可操作项）
     * ============================================================ */
    function initFormCheckall() {
        // 6.153 查询一律限定在元素自身的容器内。原因：子项类名 check-{id} 由字段 id 派生，
        // 而 items 克隆行的 id 重写（Items.php rewriteId）只改 id 属性与重放脚本，
        // 不碰 class / data-check → 同一 items 的多个克隆行共用同一个类名，
        // 点某行的「全选」会把其它行的子项一起勾上（实测：两行各 3 项全中）。
        // 容器取最近的带 id 祖先：表单字段是 checkbox.html 的根 div（带 {$id}，克隆行会被重写）；
        // 表格表头全选（table.html 的 .checkall[data-check]）master 在 thead、子项在 tbody，
        // 二者最近的带 id 祖先同为表格包裹 div #{$id}，跨行/跨表语义与原先一致。
        function checkScope(el) {
            return (el && el.closest && el.closest('div[id]')) || d;
        }
        d.addEventListener('change', function (e) {
            var input = e.target;
            if (!input || input.tagName !== 'INPUT' || input.type !== 'checkbox') return;

            // 全选开关：data-check 指向子项类名（check-{id}）
            var target = input.getAttribute('data-check');
            if (target && input.classList.contains('checkall')) {
                checkScope(input).querySelectorAll('.' + target).forEach(function (box) {
                    if (box.disabled || box.hasAttribute('readonly')) return;
                    box.checked = input.checked;
                });
                return;
            }

            // 子项变化回写全选态：全选中→勾上，否则取消
            var cls = null;
            input.classList.forEach(function (c) {
                if (cls === null && c.lastIndexOf('check-', 0) === 0 && c.length > 6) cls = c;
            });
            if (!cls) return;
            var scope = checkScope(input);
            scope.querySelectorAll('input.checkall[data-check="' + cls + '"]').forEach(function (master) {
                var boxes = scope.querySelectorAll('.' + cls);
                var total = 0, checked = 0;
                boxes.forEach(function (box) {
                    if (box.disabled || box.hasAttribute('readonly')) return;
                    total++;
                    if (box.checked) checked++;
                });
                master.checked = total > 0 && checked === total;
            });
        });
    }

    /* ============================================================
     * 5.2 垂直标签页面板切换（tab.html 垂直分支）
     * 垂直模式 DOM 重排（标签头全部在前、面板全部在后）后 daisyUI 的
     * 相邻选择器 (input:checked+.tab-content) 显隐失效，由这里接管：
     * radio checked → 隐藏同容器全部面板、显示 data-pane 指向的面板
     * 初始激活态由模板直接输出 .pane-active，无 JS 时初始面板也正确
     * ============================================================ */
    /* 6.400 面板最矮高度 = 左侧按钮列实测占地（末按钮底缘 + 下边距），右侧面板盒不矮于按钮列。
       容器是 flow-root，浮动按钮列不参与面板盒高度，按钮多时按钮列比激活面板高——
       用 CSS 变量下发（CSS 端 min-height: var(--vtabs-pane-min-h, 200px)，无 JS 时回落 200px） */
    function syncVerticalTabsMinH() {
        d.querySelectorAll('.tabs-vertical').forEach(function (tabs) {
            var btns = tabs.querySelectorAll(':scope > input.tab');
            if (!btns.length) return;
            var last = btns[btns.length - 1];
            var mb = parseFloat(w.getComputedStyle(last).marginBottom) || 0;
            var colH = Math.ceil(last.getBoundingClientRect().bottom - tabs.getBoundingClientRect().top) + mb;
            tabs.style.setProperty('--vtabs-pane-min-h', Math.max(200, colH) + 'px');
        });
    }

    function initVerticalTabs() {
        syncVerticalTabsMinH();
        var rTimer;
        w.addEventListener('resize', function () {
            clearTimeout(rTimer);
            rTimer = setTimeout(syncVerticalTabsMinH, 120);
        });
        d.addEventListener('change', function (e) {
            var radio = e.target;
            if (!radio.matches || !radio.matches('.tabs-vertical > input.tab')) return;
            var tabs = radio.closest('.tabs-vertical');
            var paneId = radio.getAttribute('data-pane');
            if (!tabs || !paneId) return;
            var pane = document.getElementById(paneId);
            if (!pane) return;
            tabs.querySelectorAll(':scope > .tab-content').forEach(function (p) {
                p.classList.remove('pane-active');
            });
            pane.classList.add('pane-active');
        });
    }

    function getCsrf() {
        var meta = d.querySelector('meta[name="__token__"]');
        if (meta) return meta.content;
        var input = d.querySelector('input[name="__token__"]');
        if (input) return input.value;
        // layout.html 注入的 window.__token__（列表页没有 meta/input，主要靠它）
        return w.__token__ || '';
    }

    function blang(key) {
        return (w.__blang && w.__blang[key]) || '';
    }

    /** DaisyUI 弹窗确认（替代原生 confirm），弹窗创建失败或 tpb.js 未加载时兜底回原生 */
    function confirmModal(msg, onOk) {
        msg = msg || blang('builder_confirm') || '确认操作？';
        var conf = (w.tpb && w.tpb.confirm) || (w.lightyear && w.lightyear.confirm);
        if (!conf) {
            if (w.confirm(String(msg).replace(/<[^>]+>/g, ''))) onOk();
            return;
        }
        try {
            conf({
                title: blang('builder_operation_tips'),
                msg: msg,
                onOk: onOk
            });
        } catch (e) {
            console.error('[tpextbuilder] confirm 弹窗异常，回退原生确认: ', e);
            if (w.confirm(String(msg).replace(/<[^>]+>/g, ''))) onOk();
        }
    }

    /** confirm 参数语义（与原库一致）：'0'/'false'/'' 不弹窗，'1' 默认文案，其他为自定义文案（可含 HTML） */
    function needConfirm(confirmOpt) {
        return !!confirmOpt && confirmOpt !== '0' && confirmOpt !== 'false';
    }

    function buildConfirmMsg(confirmOpt, text, isBatch) {
        if (confirmOpt === '1') {
            var key = isBatch ? 'builder_confirm_to_do_batch_operation' : 'builder_confirm_to_do_operation';
            return blang(key) + ' <strong>' + (text || blang('builder_this')) + '</strong> ' + blang('builder_action_operation') + ' ?';
        }
        return confirmOpt;
    }

    /* ============================================================
     * 6. Items 行操作（复制 / 删除 / 上下移动）
     * ============================================================ */
    function initItemsActions() {
        // Items 行删除
        d.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-item-action="remove"]');
            if (!btn) return;
            var row = btn.closest('[data-item-row]');
            if (!row) return;
            confirmModal(btn.getAttribute('data-confirm') || '确认删除？', function () {
                var container = row.parentElement;
                row.remove();
                reindexItems(container);
            });
        });

        // Items 行复制
        d.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-item-action="copy"]');
            if (!btn) return;
            var row = btn.closest('[data-item-row]');
            if (row) {
                var clone = row.cloneNode(true);
                // 清空值
                clone.querySelectorAll('input, textarea, select').forEach(function (el) {
                    if (el.tagName === 'SELECT') {
                        el.selectedIndex = 0;
                    } else if (el.type !== 'hidden') {
                        el.value = '';
                    }
                });
                row.parentElement.insertBefore(clone, row.nextSibling);
                reindexItems(row.parentElement);
                // 派发 input 让组件刷新复制行（如 rangeslider 的读数徽章与 hidden 载体）
                // 必须在插入文档后派发，事件才能冒泡到 document 级委托监听
                clone.querySelectorAll('input, textarea, select').forEach(function (el) {
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                });
            }
        });

        // Items 上移
        d.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-item-action="move-up"]');
            if (!btn) return;
            var row = btn.closest('[data-item-row]');
            var prev = row && row.previousElementSibling;
            if (prev) {
                row.parentElement.insertBefore(row, prev);
                reindexItems(row.parentElement);
            }
        });

        // Items 下移
        d.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-item-action="move-down"]');
            if (!btn) return;
            var row = btn.closest('[data-item-row]');
            var next = row && row.nextElementSibling;
            if (next) {
                row.parentElement.insertBefore(next, row);
                reindexItems(row.parentElement);
            }
        });
    }

    function reindexItems(container) {
        if (!container) return;
        var rows = container.querySelectorAll('[data-item-row]');
        rows.forEach(function (row, idx) {
            row.setAttribute('data-index', idx);
            // 更新 name 索引：items[oldIdx][field] → items[idx][field]
            row.querySelectorAll('[name]').forEach(function (el) {
                el.name = el.name.replace(/items\[\d+\]/, 'items[' + idx + ']');
            });
        });
    }

    /* ============================================================
     * 7. AJAX 全局错误拦截
     * ============================================================ */
    function initAxiosDefaults() {
        if (typeof axios === 'undefined') return;

        axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

        // 自动注入 CSRF token
        var token = getCsrf();
        if (token) {
            axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        }

        // 全局拦截器
        axios.interceptors.response.use(
            function (response) { return response; },
            function (error) {
                if (error.response && error.response.status === 419) {
                    // CSRF token 过期
                    tpb.notify('页面已过期，请刷新重试', 'warning');
                } else if (error.response && error.response.status === 422) {
                    // 表单验证错误
                    var errors = error.response.data && error.response.data.errors;
                    if (errors) {
                        var firstMsg = '';
                        for (var key in errors) {
                            firstMsg = errors[key][0];
                            break;
                        }
                        tpb.notify(firstMsg || '表单数据有误', 'danger');
                    }
                } else if (error.code !== 'ERR_CANCELED') {
                    tpb.notify(w.__blang.builder_network_error, 'danger');
                }
                return Promise.reject(error);
            }
        );
    }

    /* ============================================================
     * 8. window.tpextbuilder 全局 API — displayer/toolbar 依赖的公共接口
     *    原 jQuery 版已重写为原生 JS, 保持方法签名不变
     * ============================================================ */
    w.tpextbuilder = w.tpextbuilder || {};

    // 6.429 表单校验提示层 — form.html novalidate 闸门调用（无效控件红边框 +
    // 提交按钮上方错误行 + 右上角通知；修正即摘红）。挂载语句必须在此命名空间
    // 初始化之后——此前放文件前部曾因提前引用 undefined 炸掉整个 IIFE（本文件
    // 注释里就记录过这一坑，6.462 行的误放曾全局缺失 + 无required 红边全灭）
    w.tpextbuilder.validateForm = validateForm;

    // 批量按钮可用态重算 — 供 LinkBtn 绑定脚本（打标后同步初始态）与 search.html 刷新回调使用；
    // 注意必须挂在 w.tpextbuilder 挂载语句之后（此前放文件前部曾因提前引用 undefined 而炸掉整个 IIFE）
    w.tpextbuilder.updateBatchBtnState = updateBatchBtnState;

    // 防止 displayer autoPost 重复绑定的标记
    w.__auto_post_bind__ = w.__auto_post_bind__ || [];

    /** autoPost — table 行内组件自动保存
     *  PHP 端 Field::autoPost() 注册, classname = 'row-{field}-td'
     *  监听 checkbox/radio/text/textarea/select change → 提取 {id, name, value} → autoSendData
     *  —— 事件委托: change 事件冒泡到 document, TomSelect change 也会冒泡
     */
    w.tpextbuilder.autoPost = function (className, url, refresh) {
        if (w.__auto_post_bind__.includes(className)) return;
        w.__auto_post_bind__.push(className);

        // 防抖 timer（text/textarea 共用）
        var timer = null;

        function getData(el, scope) {
            var tr = el.closest('tr.table-row-id');
            if (!tr) return null;
            var dataid = tr.getAttribute('data-id');
            // name 去掉后缀: 'category_id-0' → 'category_id'
            var name = (el.getAttribute('name') || '').split('-')[0];
            if (!name) {
                // switch 场景: checkbox 自身没 name, 旁边 hidden input 有
                var hidden = findHidden(el, scope);
                if (hidden) name = (hidden.getAttribute('name') || '').split('-')[0];
            }
            if (!name || !dataid) return null;
            var val = el.value;
            return { id: dataid, name: name, value: val };
        }

        // 新版 switchBtn hidden 在 Alpine div 里、和 label 是兄弟；旧版 hidden 在 label 内
        // 优先直接父级 → 找不到再从 scope (通常是 td) 往上找
        function findHidden(el, scope) {
            if (el.parentElement) {
                var h1 = el.parentElement.querySelector('input[type="hidden"]');
                if (h1) return h1;
            }
            if (scope) {
                var h2 = scope.querySelector('input[type="hidden"]');
                if (h2) return h2;
            }
            // 最后兜底：整个 td 范围
            var td = el.closest ? el.closest('td') : null;
            if (td) {
                var h3 = td.querySelector('input[type="hidden"]');
                if (h3) return h3;
            }
            return null;
        }

        d.addEventListener('change', function (e) {
            var el = e.target;
            if (!el || !el.closest) return;
            // 只处理属于 .{className} td 内的元素
            var td = el.closest('.' + className);
            if (!td) return;

            var tag = (el.tagName || '').toLowerCase();
            var type = el.type || '';

            if (tag === 'input') {
                if (type === 'checkbox') {
                    // switch 开关标记：有 data-on + data-off 属性（旧版额外有 switch-box class, 新版用 DaisyUI toggle class）
                    var isSwitch = el.hasAttribute('data-on') && el.hasAttribute('data-off');
                    if (isSwitch) {
                        // switch: checkbox 带 data-on / data-off, hidden input 有真正的 name
                        var hidden = findHidden(el, td);
                        var name = hidden ? (hidden.getAttribute('name') || '').split('-')[0] : '';
                        var tr = el.closest('tr.table-row-id');
                        if (name && tr) {
                            // 6.151 已提交态：首次变更前记录初始状态，之后每次提交成功刷新；
                            // 修改失败（code=0）时复位到它，避免 UI 与数据不一致
                            if (el.__apCommitted === undefined) {
                                el.__apCommitted = !el.checked;
                            }
                            clearTimeout(timer);
                            timer = setTimeout(function () {
                                tpextbuilder.autoSendData({
                                    id: tr.getAttribute('data-id'),
                                    name: name,
                                    value: el.checked ? el.getAttribute('data-on') : el.getAttribute('data-off')
                                }, url, refresh, function () {
                                    // onFail：返回 code=0（或请求失败）→ 开关与隐藏值一起复位
                                    if (!el.isConnected) return;
                                    el.checked = !!el.__apCommitted;
                                    var h = hidden && hidden.isConnected ? hidden : findHidden(el, td);
                                    if (h) {
                                        h.value = el.checked ? el.getAttribute('data-on') : el.getAttribute('data-off');
                                    }
                                }, function () {
                                    // onOK：新状态成为已提交态
                                    el.__apCommitted = el.checked;
                                });
                            }, 500);
                        }
                    } else {
                        // 普通 checkbox: 可能有多个同名, 收集所有 checked 的 value
                        var tr2 = el.closest('tr.table-row-id');
                        var name2 = (el.getAttribute('name') || '').split('-')[0];
                        if (name2 && tr2) {
                            clearTimeout(timer);
                            timer = setTimeout(function () {
                                var values = [];
                                td.querySelectorAll("input[type='checkbox'][name='" + el.getAttribute('name') + "']:checked").forEach(function (c) {
                                    values.push(c.value);
                                });
                                tpextbuilder.autoSendData({
                                    id: tr2.getAttribute('data-id'),
                                    name: name2,
                                    value: values.join(',')
                                }, url, refresh);
                            }, 500);
                        }
                    }
                } else if (type === 'radio') {
                    clearTimeout(timer);
                    var radioEl = el;
                    timer = setTimeout(function () {
                        var data = getData(radioEl, td);
                        if (data) tpextbuilder.autoSendData(data, url, refresh);
                    }, 500);
                }
                // type=text / number / date 等 input 不监听 change（change 是失焦触发, 太迟钝）
                // 但旧版监听了 input[type=text] change, 保持一致 — 500ms 防抖
                else if (type !== 'file' && type !== 'password') {
                    clearTimeout(timer);
                    var inputEl = el;
                    timer = setTimeout(function () {
                        var data2 = getData(inputEl);
                        if (data2) tpextbuilder.autoSendData(data2, url, refresh);
                    }, 500);
                }
            } else if (tag === 'textarea') {
                clearTimeout(timer);
                var taEl = el;
                timer = setTimeout(function () {
                    var data3 = getData(taEl);
                    if (data3) tpextbuilder.autoSendData(data3, url, refresh);
                }, 500);
            } else if (tag === 'select') {
                // TomSelect change 也会冒泡到原生 select, 这里统一处理 — 500ms 防抖
                clearTimeout(timer);
                var selEl = el;
                timer = setTimeout(function () {
                    var data4 = getData(selEl, td);
                    if (data4) tpextbuilder.autoSendData(data4, url, refresh);
                }, 500);
            }
        });
    };

    /** autoSendData — 批量/行内操作统一发送入口（对齐原库契约）
     *  POST ids（逗号串）+ __token__ + _method(delete|patch 伪装)，form-urlencoded 编码；
     *  响应处理：刷新 token、注入执行 script、notify、触发 .search-refresh 刷新表格 */
    w.tpextbuilder.autoSendData = function (data, url, refresh, onFail, onOK) {
        if (typeof axios === 'undefined') {
            // 静默 return 会表现为「点了确认没任何反应」，必须显式暴露
            console.error('[tpextbuilder] axios 未加载，无法发送请求: ' + url);
            tpb.notify('axios 未加载，请检查 admin_js 资源配置', 'danger');
            return;
        }
        try {
            var token = getCsrf();
            data.__token__ = token;
            data._method = /.+?\/(?:destroy|delete|remove|del)(?:\.\w+)?$/.test(url) ? 'delete' : 'patch';
            tpb.loading('show');
            // form-urlencoded：与原库 $.ajax 一致，保证后端 $_POST['_method'] 方法伪装可用
            var pairs = [];
            Object.keys(data).forEach(function (k) {
                pairs.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k]));
            });
            var bodyStr = pairs.join('&');
            axios.post(url, bodyStr, {
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            })
            .then(function (res) {
                tpb.loading('hide');
                var resData = res.data || {};
                if (resData.__token__) {
                    w.__token__ = resData.__token__;
                }
                if (resData.status || resData.code) {
                    if (typeof onOK === 'function') { try { onOK(); } catch (e) { console.error(e); } }
                    tpb.notify(resData.msg || resData.message || blang('builder_operation_succeeded'), 'success');
                    if (refresh) {
                        var refreshBtn = d.querySelector('.search-refresh, .row-refresh');
                        if (refreshBtn) refreshBtn.click();
                    }
                } else {
                    // 6.151 修改失败（code=0/status=0）：先复位调用方控件再提示，UI 不谎报成功
                    if (typeof onFail === 'function') { try { onFail(); } catch (e) { console.error(e); } }
                    tpb.notify(resData.msg || resData.message || blang('builder_operation_failed'), 'warning');
                }
                if (resData.script || (resData.data && resData.data.script)) {
                    var script = resData.script || resData.data.script;
                    var holder = d.getElementById('script-div');
                    if (!holder) {
                        holder = d.createElement('div');
                        holder.id = 'script-div';
                        holder.className = 'hidden';
                        d.body.appendChild(holder);
                    }
                    holder.innerHTML = script;
                    // innerHTML 插入的 <script> 不会执行，需重建节点
                    holder.querySelectorAll('script').forEach(function (old) {
                        var s = d.createElement('script');
                        s.textContent = old.textContent;
                        old.parentNode.replaceChild(s, old);
                    });
                }
            })
            .catch(function () {
                tpb.loading('hide');
                // 请求失败同样未确认修改成功，复位调用方控件（同 code=0 分支语义）
                if (typeof onFail === 'function') { try { onFail(); } catch (e) { console.error(e); } }
                // 6.257 通知交给全局拦截器，勿在此重复 notify（开关切换失败弹双通知的根因）
            });
        } catch (e) {
            tpb.loading('hide');
            console.error('[tpextbuilder] autoSendData 异常: ', e);
            tpb.notify('请求发送失败: ' + e.message, 'danger');
        }
    };

    /** postRowid — ActionBtn 行内单条操作, 提交该行 data-id
     *  事件委托绑定：表格 AJAX 刷新后行按钮重建仍有效（对齐原库 $('body').on 委托） */
    w.tpextbuilder.postRowid = function (className, url, confirmMsg) {
        d.addEventListener('click', function (e) {
            var btn = e.target.closest('.row-__action__ .' + className);
            if (!btn) return;
            e.preventDefault();
            var id = btn.getAttribute('data-id');
            if (!id) return;
            var doSend = function () { w.tpextbuilder.autoSendData({ ids: id }, url, 1); };
            if (!needConfirm(confirmMsg)) return doSend();
            var text = btn.textContent.trim() || btn.getAttribute('title') || blang('builder_this');
            confirmModal(buildConfirmMsg(confirmMsg, text, false), doSend);
        });
    };

    /** postChecked — LinkBtn 批量操作, 收集选中项 ids（逗号串）提交 */
    w.tpextbuilder.postChecked = function (inputId, url, confirmMsg) {
        // 打批量标记并同步初始禁用态（无选中时专用类 batch-disabled：原色变浅+禁止光标）
        var batchBtn = d.getElementById(inputId);
        if (batchBtn && !batchBtn.classList.contains('batch-action-btn')) {
            batchBtn.classList.add('batch-action-btn');
            updateBatchBtnState();
        }
        d.addEventListener('click', function (e) {
            var btn = e.target.closest('#' + inputId);
            if (!btn) return;
            e.preventDefault();
            if (btn.classList.contains('batch-disabled')) return;
            var checked = d.querySelectorAll('.table-row-checkbox:checked');
            if (!checked.length) {
                tpb.notify(blang('builder_no_data_was_selected'), 'warning');
                return;
            }
            var ids = [];
            checked.forEach(function (c) { ids.push(c.value); });
            var doSend = function () { w.tpextbuilder.autoSendData({ ids: ids.join(',') }, url, 1); };
            if (!needConfirm(confirmMsg)) return doSend();
            var text = btn.textContent.trim() || btn.getAttribute('title') || blang('builder_this');
            confirmModal(buildConfirmMsg(confirmMsg, text, true), doSend);
        });
    };

    /** openChecked — LinkBtn 打开选中项：对齐原库契约，layer 弹层打开 url?ids=逗号串
     *  （旧版为 layerOpen，勿用 window.open——Attachment 曾因 url='#' 弹出新标签页） */
    w.tpextbuilder.openChecked = function (inputId, url) {
        var batchBtn = d.getElementById(inputId);
        if (batchBtn && !batchBtn.classList.contains('batch-action-btn')) {
            batchBtn.classList.add('batch-action-btn');
            updateBatchBtnState();
        }
        d.addEventListener('click', function (e) {
            var btn = e.target.closest('#' + inputId);
            if (!btn) return;
            e.preventDefault();
            if (btn.classList.contains('batch-disabled')) return;
            var checked = d.querySelectorAll('.table-row-checkbox:checked');
            if (!checked.length) {
                tpb.notify(blang('builder_no_data_was_selected'), 'warning');
                return;
            }
            var ids = [];
            checked.forEach(function (c) { ids.push(c.value); });
            var size = btn.getAttribute('data-layer-size');
            size = size ? size.split(',') : null;
            var target = url + (/.+\?.*/.test(url) ? '&ids=' : '?ids=') + ids.join(',');
            if (w.layerOpen) {
                w.layerOpen(btn, size, target);
            } else {
                console.error('[tpextbuilder] layerOpen 不可用，无法打开选中项');
            }
        });
    };

    /** layerOpen — 打开 iframe 弹窗（actionbtn/linkbtn 模板的 layer 分支经 onclick 调用）
     *  基于 layer v3.1.1（layer/layer.js，支持拖拽/最小化/最大化/关闭按钮）；
     *  url 依次取显式参数、元素 data-url / url / href；标题取 data-title / title / 按钮文字；
     *  data-layer-size="宽,高" 指定尺寸，高为 auto/0/空 时按 iframe 内容自适应（内容+标题 ~ 窗口高-顶部偏移） */
    w.layerOpen = function (obj, size, url) {
        if (!w.layer) { console.error('[tpextbuilder] layer 未加载，无法打开弹窗'); return 0; }
        obj = obj || {};
        function attr(n) { return obj.getAttribute ? obj.getAttribute(n) : ''; }
        var href = url || attr('data-url') || attr('url') || attr('href');
        if (!href) { console.error('[tpextbuilder] layerOpen 缺少 url'); return 0; }
        var text = attr('data-title') || attr('title') || (obj.textContent || '').trim() || ' ';
        var ls = attr('data-layer-size');
        if (ls) { size = ls.split(','); }
        // 弹窗顶部固定偏移 7px，可占用的最大总高相应扣减（底部不留空隙）
        var winheight = w.innerHeight - 7;
        var area = size || ['90%', '400px'];
        if (typeof area === 'string') { area = [area, '']; }
        var autoH = !size || !area[1] || area[1] === 'auto' || area[1] === '0';
        if (!area[1]) { area[1] = '400px'; }
        // 原库行为：弹窗顶部贴近页顶（7px），高度 100% 时贴顶为 0；layer 默认垂直居中，需显式指定
        var offset = (size && size.length > 1 && size[1] == '100%') ? '0' : '7px';

        return w.layer.open({
            type: 2,
            title: text,
            content: href,
            area: area,
            offset: offset,
            maxmin: true,
            shadeClose: false,
            success: function (layero, idx) {
                if (!autoH) return;
                var fit = function () {
                    try {
                        var node = document.getElementById('layui-layer' + idx) || (layero && layero[0]);
                        var iframe = node && node.querySelector('iframe');
                        if (!iframe) return;
                        // style() 设的是弹窗总高：内容高之外只补一次标题栏；iframe 视口 = 总高 - 标题，正好容纳内容
                        var title = node.querySelector('.layui-layer-title');
                        var titleH = title ? title.offsetHeight : 42;
                        var h = iframe.contentDocument.body.scrollHeight + titleH + 2;
                        h = Math.max(400 + titleH, Math.min(h, winheight));
                        if (w.layer.style) { w.layer.style(idx, { height: h + 'px' }); }
                        else if (node) { node.style.height = h + 'px'; }
                    } catch (e) { /* 跨域 iframe 内容不可读，保持默认高度 */ }
                };
                // 找到 layer 节点里的 iframe 后挂 load 监听（layer 内部创建 iframe，时机不定，轮询定位）
                var tries = 0;
                var timer = setInterval(function () {
                    tries++;
                    var node = document.getElementById('layui-layer' + idx) || (layero && layero[0]);
                    var iframe = node && node.querySelector('iframe');
                    if (iframe) {
                        clearInterval(timer);
                        iframe.addEventListener('load', fit);
                        fit();
                    } else if (tries > 10) {
                        clearInterval(timer);
                    }
                }, 300);
            }
        });
    };

    /* ============================================================
     * 7. 左侧树 展开/隐藏（列表页 left-tree / right-table 布局）
     *  按钮 .hide-left（树列右上角）/ .show-left（表格列左上角，仅收起时可见）
     *  状态挂在 body.left-tree-collapsed 上，CSS 负责隐藏树列、表格列占满整行
     *  —— 事件委托到 document，幂等（boot 只跑一次）
     * ============================================================ */
    function initLeftTreeToggle() {
        d.addEventListener('click', function (e) {
            var hide = e.target.closest('.hide-left');
            if (hide) {
                e.preventDefault();
                d.body.classList.add('left-tree-collapsed');
                return;
            }
            var show = e.target.closest('.show-left');
            if (show) {
                e.preventDefault();
                d.body.classList.remove('left-tree-collapsed');
            }
        });
    }

    /* ============================================================
     * 8. Layer 弹窗内按钮事件（btn-close-layer / btn-go-back）
     *  —— 事件委托到 document，boot 只跑一次
     *  iframe 子页面无法直接拿到 layer 实例，关闭交给 parent.layer
     *  ============================================================ */
    function initLayerButtons() {
        d.addEventListener('click', function (e) {
            // btn-close-layer：显式关闭按钮（btnLayerClose 输出的 class）
            var closeBtn = e.target.closest('.btn-close-layer');
            if (closeBtn) {
                e.preventDefault();
                e.stopPropagation();
                closeThisLayer();
                return;
            }

            // btn-go-back：返回按钮（btnBack 输出的 class）
            // iframe 弹窗里 history.go(-1) 无效（iframe history 只有当前页一条），
            // 优先关 layer，其次原生 history
            var backBtn = e.target.closest('.btn-go-back');
            if (backBtn) {
                // 让 PHP 注入的 onclick="history.go(-1)" 有机会先跑（如果有的话）
                // 但如果在 iframe 里且 history 只有一条，拦截掉改关 layer
                if (isInIframe()) {
                    // iframe 里：尝试关 layer（优先），失败再 history
                    if (!closeThisLayer()) {
                        try { w.history.go(-1); } catch (err) { /* noop */ }
                    }
                }
                // 非 iframe：让原生 onclick 处理 history.go(-1)
            }
        });
    }

    /** 判断当前页面是否在 iframe 里 */
    function isInIframe() {
        try { return w.self !== w.top; } catch (e) { return true; } // 跨域时也当作 iframe
    }

    /** 关闭当前 iframe 所在的 layer，成功返回 true */
    function closeThisLayer() {
        try {
            var parent = w.parent || w.opener;
            if (parent && parent !== w && parent.layer && typeof parent.layer.close === 'function') {
                var index = parent.layer.getFrameIndex(w.name);
                if (index !== undefined && index !== null) {
                    parent.layer.close(index);
                    return true;
                }
            }
        } catch (e) { /* 跨域安全错误 */ }
        // window.open 打开的弹窗
        try { if (w.opener && !w.opener.closed) { w.close(); return true; } } catch (e) { /* noop */ }
        return false;
    }

    /** initShowMore — Show::cut() 截断文本的「展开/收起」切换（6.225 补齐，原库丢漏）。
     *  模板结构：span.the-field(当前显示) + span.shwo-more(省略号) + span.hidden(全文)。
     *  类名保留原库拼写 shwo-more。事件委托：表格 AJAX 刷新后重建的行仍有效 */
    function initShowMore() {
        d.addEventListener('click', function (e) {
            var more = e.target.closest('.shwo-more');
            if (!more) return;
            var valueSpan = more.previousElementSibling;
            var fullSpan = more.nextElementSibling;
            if (!valueSpan || !fullSpan || fullSpan.tagName !== 'SPAN') return;
            var tmp = valueSpan.textContent;
            valueSpan.textContent = fullSpan.textContent;
            fullSpan.textContent = tmp;
            more.classList.toggle('showing');
        });
    }

    /* ============================================================
     * 6.230 表头冻结 / 列冻结
     * PHP 端 freezeHeader()/freezeColumn() 输出标记类，本模块负责实测几何信息：
     * ① freezeHeader 容器限高（不限高则容器随内容长高、随页面滚走，sticky 不生效）
     * ② 冻结列按 DOM 顺序累计偏移写入内联 left/right（列宽服务端未知，只能实测）
     * ============================================================ */

    /** 计算并分配一张表的冻结单元格偏移、边缘阴影标记。
     *  allowRefit：是否允许撑列（写 min-width）。只在首次初始化/局部刷新（表格服务端
     *  全新渲染）时允许；RO/resize 自愈路径不允许——空闲期任何布局瞬态被误读为
     *  「内容溢出」都会把 min-width 越撑越大且不可逆（6.233 窄窗口 ID 列持续变宽） */
    function applyTableFreeze(table, allowRefit) {
        var headRow = table.tHead ? table.tHead.rows[0] : table.rows[0];
        if (!headRow) return;

        // 6.233 先摘掉全部行的边缘阴影标记再测量：.freeze-edge-*)::after 向外扩 6px
        // 会计入单元格 scrollWidth，令「内容溢出」判定永远成立，撑列 min-width 被逐轮
        // 越撑越大（窄窗口下 ID 列不停变宽的根因）。标记在下方按行重新补挂
        Array.prototype.forEach.call(table.rows, function (tr) {
            Array.prototype.forEach.call(tr.cells, function (cell) {
                cell.classList.remove('freeze-edge-r', 'freeze-edge-l');
            });
        });

        var leftIdx = [];
        var rightIdx = [];
        Array.prototype.forEach.call(headRow.cells, function (cell, i) {
            if (cell.classList.contains('freeze-col-left')) {
                leftIdx.push(i);
            } else if (cell.classList.contains('freeze-col-right')) {
                rightIdx.push(i);
            }
        });

        if (!leftIdx.length && !rightIdx.length) return;

        // 6.231 冻结列内容被压缩溢出时先撑列：auto 布局可能把操作列压得比按钮行窄
        //（如 w-48 建议值被挤掉），不冻结时只是普通裁切，冻结钉住后永久可见。
        // 按溢出量在表头单元格设 min-width 撑整列，生效后再测列宽。
        // 上限=容器可视宽-60：冻结列的意义就是保持可见，超过它必然是误测
        if (allowRefit) {
            var wrapper = table.closest('.table-freeze-head');
            var cap = wrapper ? wrapper.clientWidth - 60 : 0;
            var fitNeed = {};
            Array.prototype.forEach.call(table.rows, function (tr) {
                leftIdx.concat(rightIdx).forEach(function (i) {
                    var cell = tr.cells[i];
                    if (!cell || cell.scrollWidth <= cell.clientWidth) return;
                    var cs = w.getComputedStyle(cell);
                    var need = cell.scrollWidth + parseFloat(cs.paddingRight);
                    if (need > (fitNeed[i] || 0)) fitNeed[i] = need;
                });
            });
            var refit = false;
            leftIdx.concat(rightIdx).forEach(function (i) {
                if (fitNeed[i] && Math.ceil(fitNeed[i]) > headRow.cells[i].offsetWidth) {
                    var mw = Math.min(Math.ceil(fitNeed[i]), cap);
                    if (mw > headRow.cells[i].offsetWidth) {
                        headRow.cells[i].style.minWidth = mw + 'px';
                        refit = true;
                    }
                }
            });
            if (refit) void headRow.offsetWidth; // 强制回流，让下面的测量拿到新列宽
        }

        // 列宽以表头行实测为准（同一列各行列宽一致）；用小数精度——offsetWidth 取整，
        // 表格压缩布局下列宽是小数，取整累计会留缝露出下层滚动内容（6.231）
        var widths = Array.prototype.map.call(headRow.cells, function (cell) {
            return cell.getBoundingClientRect().width;
        });

        Array.prototype.forEach.call(table.rows, function (tr) {
            var cells = tr.cells;
            if (cells.length !== headRow.cells.length) return; // colspan 等异常结构不处理

            var lAcc = 0;
            leftIdx.forEach(function (i) {
                cells[i].style.left = lAcc + 'px';
                lAcc += widths[i];
            });
            if (leftIdx.length) {
                cells[leftIdx[leftIdx.length - 1]].classList.add('freeze-edge-r');
            }

            var rAcc = 0;
            for (var k = rightIdx.length - 1; k >= 0; k--) {
                cells[rightIdx[k]].style.right = rAcc + 'px';
                rAcc += widths[rightIdx[k]];
            }
            if (rightIdx.length) {
                cells[rightIdx[0]].classList.add('freeze-edge-l');
            }
        });
    }

    /** freezeHeader 容器限高：视口高 - 容器顶部偏移 - 底部预留（分页条等） */
    // 6.396 锁高微校准：锁高后行高可能因「滚动条出现→内容变窄→行再折行」或
    // 列冻结撑列再长出零点几 px，ceil 后的盒高差 1px 即重新触发幻影纵向滚动条
    // （右冻结列被偷 16px）。按 scrollHeight-clientHeight 差值补足；上限 8——
    // 更大增量属真实内容增长，交由 RO force 全量重测决策。内容变短方向
    // scrollHeight 不可测（恒等于 clientHeight），同样由 RO force 重锁兜底
    function relockFreezeBump(wrap) {
        if (!wrap || !wrap.style.height) return;
        var lack = wrap.scrollHeight - wrap.clientHeight;
        if (lack > 0 && lack < 8) {
            wrap.style.height = (parseFloat(wrap.style.height) + lack) + 'px';
        }
    }

    function applyHeadFreeze(wrapper, force) {
        // 6.249 本函数会动几何：force 清 maxHeight、以及 6.231 自然高测量临时切
        // overflowY:hidden→auto——浏览器在这两种状态下都会把 scrollTop 无声钳回 0。
        // RO 自愈 tick 恰在翻页恢复后 ~120ms，表现为翻页滚动位"被偷"。进出快照回写，
        // 让重算对滚动位置保持中立（内容变短时浏览器按新 maxTop 自然钳制）
        var st = wrapper.scrollTop, sl = wrapper.scrollLeft;
        if (force) {
            wrapper.style.maxHeight = '';
            wrapper.style.height = '';
        }
        if (wrapper.style.maxHeight) return; // 已算过，或使用方显式指定过高度
        var rect = wrapper.getBoundingClientRect();
        var top = rect.top + (w.pageYOffset || 0);
        // 6.253 底部预留改为分页条实测高+上外边距+小留白（原写死 90 比实际多占 ~46px；
        // 既然滚动发生在表格内，表格就应占满到视口底）。无分页条只留 12
        var bar = wrapper.parentNode ? wrapper.parentNode.querySelector('.pagination-bar') : null;
        var reserve = 12;
        if (bar) {
            reserve = bar.offsetHeight + (parseFloat(w.getComputedStyle(bar).marginTop) || 0) + 8;
        }
        var h = w.innerHeight - top - reserve;
        if (h > w.innerHeight - 100) h = w.innerHeight - 100;
        if (h < 220) h = 220;
        wrapper.style.overflowY = 'auto';
        wrapper.style.maxHeight = h + 'px';

        // 6.256 溢出校正：reserve 只量了分页条，分页条之下仍可能有内容（APP_DEBUG 的
        // 执行时间 footer、自定义 addBottom 等）——menu 页 30px footer 令页面 730>720
        // 出现页面级纵条。设完 maxHeight 测一次整页溢出，能扣且扣后不破 220 下限就扣掉
        var over = (document.documentElement.scrollHeight || document.body.scrollHeight) - w.innerHeight;
        if (over > 0 && h - over >= 220) {
            h -= over;
            wrapper.style.maxHeight = h + 'px';
        }

        // 6.231 消除幻影纵向滚动条：横向滚动条会挤占 clientHeight，内容只差 1px 也算
        // 溢出→出现纵向滚动条→偷走 17px 宽度，右冻结列就钉不到容器右缘。
        // 内容本放得下时把高度锁到自然高（含横向滚动条），差值取 0，纵向条不再出现。
        // 锁高有前提：量到的自然高必须真装得下表格（表格此刻可见且完整）——
        // 初始化瞬间行/图片可能还没就绪，量到残缺高度会锁死把后来的行裁没（6.231）
        if (!wrapper.style.height) {
            var tableEl = wrapper.querySelector('table');
            var tableH = tableEl ? tableEl.offsetHeight : 0;
            wrapper.style.overflowY = 'hidden'; // 同步量自然高，不产生绘制
            var naturalH = wrapper.offsetHeight;
            // 6.396 表格带小数真实高 + 实测边框厚（offsetHeight-clientHeight）。
            // 原写死 +2px 冗余在表格下方留恒定 2px 空带（用户报告「wrap 比表格
            // 多 4px」=边框 2+冗余 2）；改 ceil(表格高)+边框后，clientHeight
            // 恰好 ≥ 内容 scrollHeight（幻影纵向滚动条的触发条件），冗余降到
            // [0,1)px 小数残差。注意 ceil 必须作用在内容高上——ceil 盒高会把
            // 边框吃进 clientHeight，差 1px 又触发滚动条
            var wrapBorderV = wrapper.offsetHeight - wrapper.clientHeight;
            var tableRectH = tableEl ? tableEl.getBoundingClientRect().height : 0;
            wrapper.style.overflowY = 'auto';
            if (naturalH <= h && tableH > 0 && naturalH + 4 >= tableH) {
                wrapper.style.height = (Math.ceil(tableRectH) + wrapBorderV) + 'px';
                relockFreezeBump(wrapper); // 锁定瞬间的取整差即校平
            }
            // 放不下或量不可靠的场景维持 maxHeight，让纵向滚动条正常工作（占宽属正常 UI）
        }
        if (wrapper.scrollTop !== st) wrapper.scrollTop = st;
        if (wrapper.scrollLeft !== sl) wrapper.scrollLeft = sl;
    }

    /** 扫描 scope（默认整页）内的表格，应用表头/列冻结；幂等，可重复调用 */
    function initTableFreeze(scope, force) {
        var root = scope && scope.querySelectorAll ? scope : d;

        var tables = root.matches && root.matches('table')
            ? [root]
            : Array.prototype.slice.call(root.querySelectorAll('table'));

        tables.forEach(function (table) {
            var wrapper = table.closest('.table-freeze-head');
            if (wrapper) applyHeadFreeze(wrapper, force);

            var headRow = table.tHead ? table.tHead.rows[0] : table.rows[0];
            if (!headRow) return;

            var hasMark = Array.prototype.some.call(headRow.cells, function (cell) {
                return cell.classList.contains('freeze-col-left') || cell.classList.contains('freeze-col-right');
            });
            if (!hasMark) return;

            // force=true 是自愈路径（RO/resize/字体），只重算偏移不撑列；
            // force=false 是首次初始化/局部刷新，表格服务端全新渲染，允许撑列
            applyTableFreeze(table, !force);

            // 6.231 表格尺寸后续变化（行异步到达/图片加载/列宽被内容撑变）时自愈：
            // 重算限高与偏移。ResizeObserver 回调里重算不会反过来改变表格尺寸，无死循环
            if (typeof ResizeObserver === 'function' && !table.__freezeRO__) {
                table.__freezeRO__ = new ResizeObserver(function () {
                    clearTimeout(table.__freezeROTimer__);
                table.__freezeROTimer__ = setTimeout(function () {
                    var wrap = table.closest('.table-freeze-head');
                    if (wrap) applyHeadFreeze(wrap, true);
                    applyTableFreeze(table, false);
                    relockFreezeBump(wrap); // 6.396 撑列后的行高小数增长
                }, 120);
                });
                table.__freezeRO__.observe(table);
            }

            // 初始化时不可见（折叠面板等）会量到 0 列宽：该容器首次滚动时重算一次
            var zero = Array.prototype.some.call(headRow.cells, function (cell) {
                return cell.offsetWidth === 0;
            });
            if (zero && wrapper && !wrapper.__freezeScrollBound__) {
                wrapper.__freezeScrollBound__ = true;
                wrapper.addEventListener('scroll', function () {
                    if (!wrapper.__freezeHealed__) {
                        wrapper.__freezeHealed__ = true;
                        applyTableFreeze(table, false);
                    }
                });
            }
        });

        // 6.396 首次初始化/局部刷新收尾校准：列冻结撑列后的行高小数增长
        // （机制与上限见 relockFreezeBump 注释）
        var relockList = root.matches && root.matches('.table-freeze-head')
            ? [root]
            : (root.querySelectorAll ? Array.prototype.slice.call(root.querySelectorAll('.table-freeze-head')) : []);
        relockList.forEach(relockFreezeBump);
    }

    /** 供 table.html 局部刷新脚本调用（单元格重建后重新分配偏移） */
    w.tpextbuilder.initTableFreeze = initTableFreeze;
    // 6.254 公开确认弹窗（tpb/lightyear.confirm 封装，带回退），供 searchScript 等处使用
    w.tpextbuilder.confirm = confirmModal;

    /* ============================================================
     * 6.286 表格树参考线满格（引擎无关）
     *
     * 参考线链路 td → wrapper(height:100%) → .the-field(100%) → .tline(stretch)
     * 依赖 td 内百分比高度的解析。图片列把行撑高（超 42px 定高）后，Gecko 等
     * 引擎仍按 td 的「指定高度 42px」解析百分比 → wrapper 停在 31px，参考线
     * 短一截（用户：图片列行高超过 42，树形的线不能占满行高；Chromium 按
     * used height 解析所以不复现）。
     * 改为实测回写：把含 .tline 的顶层容器高度写成 td 内容区高度（inline
     * !important 压过 6.285 的 CSS !important 规则）；ResizeObserver 自愈——
     * 图片异步加载、窗口缩放、AJAX 刷新后单元格尺寸变化都会重算。
     * 回写不影响 td 尺寸（写的是内容区高度，只可能 <= 自然高度），无死循环，
     * 且 __tlH__ 记录上次值，收敛后回调零写入。
     * ============================================================ */
    var treeLineRO = null;

    function fixTreeLineCell(td) {
        if (!td.querySelector) return;
        var tline = td.querySelector('.tline');
        if (!tline) return;

        var top = tline;
        while (top.parentElement && top.parentElement.tagName !== 'TD') {
            top = top.parentElement;
        }
        if (top === td) return; // .tline 已是 td 直接子级，无 wrapper 需要补高

        var cs = w.getComputedStyle(td);
        var h = td.clientHeight - parseFloat(cs.paddingTop || 0) - parseFloat(cs.paddingBottom || 0);
        if (!(h > 0)) return;
        if (top.__tlH__ === h) return;
        top.__tlH__ = h;
        top.style.setProperty('height', h + 'px', 'important');
    }

    function initTreeLines(scope, force) {
        var root = scope && scope.querySelectorAll ? scope : d;

        var tables = root.matches && root.matches('table')
            ? [root]
            : Array.prototype.slice.call(root.querySelectorAll('table'));

        tables.forEach(function (table) {
            var tds = Array.prototype.filter.call(table.querySelectorAll('td'), function (td) {
                return td.querySelector('.tline');
            });

            tds.forEach(function (td) {
                if (force) td.__tlH__ = -1; // 强制重算（字体/列宽变化后行高可能已变）
                fixTreeLineCell(td);

                if (typeof ResizeObserver === 'function' && !td.__tlROTarget__) {
                    td.__tlROTarget__ = true;
                    if (!treeLineRO) {
                        treeLineRO = new ResizeObserver(function (entries) {
                            for (var i = 0; i < entries.length; i++) {
                                fixTreeLineCell(entries[i].target);
                            }
                        });
                    }
                    treeLineRO.observe(td);
                }
            });
        });
    }
    w.tpextbuilder.initTreeLines = initTreeLines;

    /* ============================================================
     * 启动
     * ============================================================ */
    function boot() {
        // 本文件会经 layout(admin_js) 和 content.html($js) 双通道引入而执行两次，
        // boot 必须幂等，否则 document 级监听器（表单提交拦截/行操作等）会重复绑定
        if (w.__tpextbuilder_booted__) return;
        w.__tpextbuilder_booted__ = true;

        registerFlatpickrZh();
        registerTomSelectDefaults();
        initFormSubmit();
        // 6.429 校验提示层：修正即摘红的委托监听（validateForm 由 form.html 闸门调用）
        initInvalidClear();
        initBatchSelect();
        initFormCheckall();
        initVerticalTabs();
        initItemsActions();
        initLeftTreeToggle();
        initAxiosDefaults();
        initLayerButtons();
        initShowMore();

        // 6.230 表头/列冻结：首屏应用 + 视口变化重算（限高与列宽都会变）
        initTableFreeze();
        // 6.286 树参考线满格：首屏实测回写（RO 自愈后续尺寸变化）
        initTreeLines();
        // 图片异步加载会改变行高；RO 回调在后台标签页可能被渲染饥饿，load 再兜底一次
        w.addEventListener('load', function () {
            initTreeLines(null, true);
        });
        var freezeResizeTimer = 0;
        w.addEventListener('resize', function () {
            clearTimeout(freezeResizeTimer);
            freezeResizeTimer = setTimeout(function () {
                initTableFreeze(null, true);
            }, 150);
        });
        // 字体加载完成会改变列宽，重算一次
        if (d.fonts && d.fonts.ready) {
            d.fonts.ready.then(function () {
                initTableFreeze(null, true);
            });
        }
    }

    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})(window, document);