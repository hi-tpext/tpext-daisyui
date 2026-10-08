/**
 * tpb.js — tpext-builder DaisyUI 兼容层
 *
 * 提供 lightyear.* 和 layer.* 同名 API，让原有内联脚本零修改迁移。
 * 内部实现基于 DaisyUI 的 .toast / .modal 和自写 DOM 操作。
 *
 * 注意：本文件应在 Alpine.js / Axios / Tom-Select 之后加载，
 * 但在 tpextbuilder.js 之前加载（后者可能引用 lightyear/layer）。
 */
(function () {
    'use strict';

    // ---------------------------------------------------------------------
    // 工具函数
    // ---------------------------------------------------------------------

    /** 将字符串转为安全的 HTML（防止 XSS） */
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /** 获取 DaisyUI toast 类型映射 */
    function toastClassFromStyle(style) {
        switch (style) {
            case 'success':
            case 'green':
                return 'toast-success';
            case 'danger':
            case 'error':
            case 'red':
                return 'toast-error';
            case 'warning':
            case 'yellow':
                return 'toast-warning';
            case 'info':
            case 'blue':
                return 'toast-info';
            default:
                return 'toast-info';
        }
    }

    /** 确保 Alpine 扫描新 DOM */
    function rescanAlpine(root) {
        if (window.Alpine && typeof window.Alpine.scan === 'function') {
            window.Alpine.scan(root || document);
        }
    }

    // 最近一次按下位置（capture 阶段，先于页面所有点击逻辑）——confirm 弹窗从该处放大移入
    var lastPoint = {
        x: Math.round(window.innerWidth / 2),
        y: Math.round(window.innerHeight / 3)
    };
    document.addEventListener('pointerdown', function (e) {
        lastPoint.x = e.clientX;
        lastPoint.y = e.clientY;
    }, true);

    // ---------------------------------------------------------------------
    // tpb 主命名空间
    // ---------------------------------------------------------------------

    var tpb = {};

    /**
     * 轻量通知（替代 lightyear.notify / layer.msg）
     *
     * @param {string} msg     消息内容
     * @param {string} style   'success'|'danger'|'warning'|'info'
     * @param {number} delay   显示时长（毫秒），默认 3000
     */
    tpb.notify = function (msg, style, delay) {
        style = style || 'info';
        delay = delay || 3000;

        var container = document.getElementById('tpb-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'tpb-toast-container';
            container.className = 'toast toast-top toast-end';
            // 6.147 z-[1000] 是 Tailwind 任意值类，预编译 builder.css 里没有它，实际不生效；
            // 通知要压过导航栏等 sticky 层，改用内联 z-index
            container.style.zIndex = '1000';
            container.style.pointerEvents = 'none';
            document.body.appendChild(container);
        }

        var toast = document.createElement('div');
        toast.className = 'tpb-toast ' + toastClassFromStyle(style);
        toast.style.pointerEvents = 'auto';
        toast.textContent = msg || '';

        // 插入容器
        container.appendChild(toast);
        rescanAlpine(toast);

        // 自动移除
        setTimeout(function () {
            toast.style.transition = 'opacity 0.2s, transform 0.2s';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-8px)';
            setTimeout(function () {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
                // 如果容器空了，清理
                if (container.children.length === 0 && container.parentNode) {
                    container.parentNode.removeChild(container);
                }
            }, 200);
        }, delay);
    };

    /** 隐藏通知的兼容占位（lightyear.notify('msg', 'success', {offset:0}) 不会自动处理 offset） */
    tpb.loading = function (action) {
        if (action === 'show') {
            // 只在页面还没有 loading 遮罩时创建
            if (!document.getElementById('tpb-loading-mask')) {
                var mask = document.createElement('div');
                mask.id = 'tpb-loading-mask';
                mask.style.cssText = [
                    'position:fixed',
                    'inset:0',
                    'background:rgba(0,0,0,0.15)',
                    'z-index:9999',
                    'display:flex',
                    'align-items:center',
                    'justify-content:center',
                    'pointer-events:none',
                    'transition:opacity 0.15s'
                ].join(';');

                var spinner = document.createElement('div');
                spinner.style.cssText = [
                    'width:40px',
                    'height:40px',
                    'border:3px solid rgba(0,0,0,0.1)',
                    'border-top-color:hsl(var(--p))',
                    'border-radius:50%',
                    'animation:tpb-spin 0.8s linear infinite'
                ].join(';');

                mask.appendChild(spinner);
                document.body.appendChild(mask);

                // 注入 keyframes（只一次）
                if (!document.getElementById('tpb-keyframes')) {
                    var style = document.createElement('style');
                    style.id = 'tpb-keyframes';
                    style.textContent = '@keyframes tpb-spin { to { transform: rotate(360deg); } }';
                    document.head.appendChild(style);
                }
            }
        } else if (action === 'hide') {
            var mask = document.getElementById('tpb-loading-mask');
            if (mask && mask.parentNode) mask.parentNode.removeChild(mask);
        }
    };

    // ---------------------------------------------------------------------
    // lightyear 兼容层 — 原 lightyear.* API 的同名代理
    // ---------------------------------------------------------------------

    var lightyear = {};

    /** 通知 — lightyear.notify(msg, style) */
    lightyear.notify = tpb.notify;

    /** loading — lightyear.loading('show'|'hide') */
    lightyear.loading = tpb.loading;

    /** 从语言包取文案（content.html 已注入 window.__blang，未注入时用兜底值） */
    function blangText(key, fallback) {
        return (window.__blang && window.__blang[key]) || fallback;
    }

    /** confirm — lightyear.confirm({msg, onOk, onCancel})
     *  msg 支持原始 HTML（原库确认文案含 <strong>，与 jquery-confirm 行为一致） */
    lightyear.confirm = function (opts) {
        opts = opts || {};
        var msg = opts.msg || opts.message || '确定操作？';
        var onOk = opts.onOk || opts.ok || function () {};
        var onCancel = opts.onCancel || opts.cancel || function () {};
        var title = opts.title || blangText('builder_operation_tips', '提示');

        var modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.zIndex = '9000';
        modal.innerHTML = [
            '<div class="modal-box">',
            '  <h3 class="font-bold text-lg">' + escapeHtml(title) + '</h3>',
            '  <p class="py-4">' + msg + '</p>',
            '  <div class="modal-action">',
            '    <button class="btn btn-sm tpb-cancel">' + escapeHtml(blangText('builder_button_cancel', '取消')) + '</button>',
            '    <button class="btn btn-sm btn-primary tpb-ok">' + escapeHtml(blangText('builder_button_ok', '确定')) + '</button>',
            '  </div>',
            '</div>',
            '<form method="dialog" class="modal-backdrop"><button class="tpb-cancel">close</button></form>'
        ].join('');

        document.body.appendChild(modal);
        rescanAlpine(modal);

        // 入场动画：从按下位置移入并放大。
        // 1) 计算 modal-box 最终位置与点击点的位移，先置于「偏移 + 缩小 + 透明」态；
        // 2) 强制回流后加 .modal-open 并过渡到正常位置（位移+缩放+淡入一体）。
        var box = modal.querySelector('.modal-box');
        var shiftToLastPoint = function () {
            var r = box.getBoundingClientRect();
            var dx = lastPoint.x - (r.left + r.width / 2);
            var dy = lastPoint.y - (r.top + r.height / 2);
            return 'translate(' + dx + 'px,' + dy + 'px) scale(.05)';
        };
        box.style.transform = shiftToLastPoint();
        box.style.opacity = '0';
        void modal.offsetWidth;
        modal.classList.add('modal-open');
        box.style.transform = 'translate(0,0) scale(1)';
        box.style.opacity = '1';

        var closed = false;
        function closeModal(cb) {
            return function () {
                if (closed) return; // 出场动画期间节点仍在，防止二次触发（如双击确定发两次请求）
                closed = true;
                // 出场反向：缩回并移向按下位置，过渡结束后移除节点
                box.style.transform = shiftToLastPoint();
                box.style.opacity = '0';
                setTimeout(function () {
                    if (modal.parentNode) modal.parentNode.removeChild(modal);
                }, 200); // 与 .modal/.modal-box 的 0.2s 过渡时长一致
                if (cb) { try { cb(); } catch (e) { console.error(e); } }
            };
        }

        modal.querySelector('.tpb-ok').addEventListener('click', closeModal(onOk));
        modal.querySelectorAll('.tpb-cancel').forEach(function (btn) {
            btn.addEventListener('click', closeModal(onCancel));
        });
    };

    // ---------------------------------------------------------------------
    // layer 兼容层 — 国产弹窗库 layer.js 的核心 API
    // ---------------------------------------------------------------------

    var layer = {};
    var _layerIndex = 0;
    var _layerStore = {}; // index -> { container, type }

    /**
     * 打开弹窗
     *
     * @param {object} options
     *   type: 0（content 弹窗）| 1（page HTML 弹窗）| 2（iframe 弹窗）| 3（loading）
     *   title: 标题
     *   content: type=0/1 时的 HTML 内容；type=2 时为 iframe URL
     *   btn: false | [{text, style}]
     *   area: ['600px', '400px'] 或 '600px'
     *   shade: true/false
     *   success: function(layero, index) — 弹窗打开后的回调
     *   end: function() — 弹窗关闭后的回调
     *
     * @returns {number} 弹窗 index
     */
    layer.open = function (options) {
        options = options || {};
        var index = ++_layerIndex;
        var type = options.type || 0;

        var area = options.area || ['600px', '400px'];
        if (typeof area === 'string') area = [area, ''];

        var modal = document.createElement('div');
        modal.className = 'modal modal-open';
        modal.style.zIndex = String(9500 + index);

        var modalBox = document.createElement('div');
        modalBox.className = 'modal-box max-w-full w-[calc(100%-2rem)]';
        if (area[0]) modalBox.style.maxWidth = area[0];
        if (area[1]) modalBox.style.height = area[1];

        // header
        if (options.title !== undefined && options.title !== false) {
            var header = document.createElement('div');
            header.className = 'flex items-center justify-between mb-4';
            header.innerHTML = '<h3 class="font-bold text-lg">' + escapeHtml(options.title) + '</h3>' +
                '<button class="btn btn-sm btn-circle btn-ghost tpb-layer-close">✕</button>';
            modalBox.appendChild(header);
        }

        // body
        var body = document.createElement('div');
        body.className = 'tpb-layer-body';

        if (type === 2) {
            // iframe 模式
            var iframe = document.createElement('iframe');
            iframe.src = options.content || '';
            iframe.style.cssText = 'width:100%;height:100%;border:none;min-height:300px;';
            body.appendChild(iframe);
        } else if (type === 0 || type === 1) {
            body.innerHTML = options.content || '';
        }

        modalBox.appendChild(body);

        // footer buttons
        if (options.btn !== false) {
            var footer = document.createElement('div');
            footer.className = 'modal-action';
            footer.innerHTML = '<button class="btn tpb-layer-close">关闭</button>';
            modalBox.appendChild(footer);
        }

        modal.appendChild(modalBox);

        // shade backdrop (DaisyUI modal 自带)
        if (options.shade !== false) {
            var backdrop = document.createElement('form');
            backdrop.method = 'dialog';
            backdrop.className = 'modal-backdrop';
            var closeBtn = document.createElement('button');
            closeBtn.className = 'tpb-layer-close';
            backdrop.appendChild(closeBtn);
            modal.appendChild(backdrop);
        }

        document.body.appendChild(modal);
        rescanAlpine(modal);

        // 注册
        _layerStore[index] = {
            container: modal,
            iframe: type === 2 ? iframe : null,
            end: options.end || null
        };

        // close handlers
        modal.querySelectorAll('.tpb-layer-close').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                layer.close(index);
            });
        });

        // success 回调
        if (typeof options.success === 'function') {
            try { options.success(modal, index); } catch (e) { console.error(e); }
        }

        return index;
    };

    /** 关闭弹窗 */
    layer.close = function (index) {
        if (!_layerStore[index]) return;
        var rec = _layerStore[index];
        var container = rec.container;
        if (container && container.parentNode) container.parentNode.removeChild(container);
        var endCb = rec.end;
        delete _layerStore[index];
        if (typeof endCb === 'function') {
            try { endCb(); } catch (e) { console.error(e); }
        }
    };

    /** 关闭所有弹窗 */
    layer.closeAll = function () {
        Object.keys(_layerStore).forEach(function (k) {
            layer.close(parseInt(k, 10));
        });
    };

    /** 简单消息提示 */
    layer.msg = function (msg, options) {
        var style = (options && options.icon === 1) ? 'success' :
                   (options && options.icon === 2) ? 'danger' : 'info';
        tpb.notify(msg, style);
    };

    /** loading */
    layer.load = function () { tpb.loading('show'); };
    layer.closeAll('loading'); // 占位

    /** 获取 iframe 弹窗的 frame index（window.name 模式） */
    layer.getFrameIndex = function (windowName) {
        // layer.js 用 window.name 存 index；我们也沿用这个契约
        return parseInt(window.name || '', 10) || 0;
    };

    /** iframe 内容窗口回填父层值 */
    layer.setFrameSrc = function (src, index) {
        if (_layerStore[index] && _layerStore[index].iframe) {
            _layerStore[index].iframe.src = src;
        }
    };

    /**
     * 6.255 导出「文件已生成」下载框：AJAX 生成完成后弹出，
     * 展示提示文案 + 生成的文件名，点「下载」走 export?path= 链接
     * （响应为 Content-Disposition: attachment，不离开页面），点「关闭」收起。
     * 与 confirm 同款：从按下位置移入的入场动画、反向出场、closed 防重入。
     *
     * @param {string} msg      提示文案（后端 __blang 文案，仍做转义）
     * @param {string} url      下载地址（json.data）
     * @param {string} fileName 展示用文件名（从 path 参数解析出的 basename，可空）
     */
    lightyear.downloadBox = function (msg, url, fileName) {
        var title = blangText('builder_operation_tips', '提示');
        var modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.zIndex = '9000';
        modal.innerHTML = [
            '<div class="modal-box">',
            '  <h3 class="font-bold text-lg">' + escapeHtml(title) + '</h3>',
            '  <p class="py-2">' + escapeHtml(msg || '') + '</p>',
            fileName ? [
                '  <div class="mt-1 mb-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">',
                '    <a class="tpb-file-link link link-primary break-all text-sm" href="' + escapeHtml(url) + '">' + escapeHtml(fileName) + '</a>',
                '  </div>',
            ].join('') : '',
            '  <div class="modal-action">',
            '    <button class="btn btn-sm tpb-close">' + escapeHtml(blangText('builder_button_close', '关闭')) + '</button>',
            '    <a class="btn btn-sm btn-primary" href="' + escapeHtml(url) + '">' + escapeHtml(blangText('builder_button_download', '下载')) + '</a>',
            '  </div>',
            '</div>',
            '<form method="dialog" class="modal-backdrop"><button class="tpb-close">close</button></form>'
        ].join('');

        document.body.appendChild(modal);
        rescanAlpine(modal);

        var box = modal.querySelector('.modal-box');
        var shiftToLastPoint = function () {
            var r = box.getBoundingClientRect();
            var dx = lastPoint.x - (r.left + r.width / 2);
            var dy = lastPoint.y - (r.top + r.height / 2);
            return 'translate(' + dx + 'px,' + dy + 'px) scale(.05)';
        };
        box.style.transform = shiftToLastPoint();
        box.style.opacity = '0';
        void modal.offsetWidth;
        modal.classList.add('modal-open');
        box.style.transform = 'translate(0,0) scale(1)';
        box.style.opacity = '1';

        var closed = false;
        function closeModal() {
            return function () {
                if (closed) return;
                closed = true;
                box.style.transform = shiftToLastPoint();
                box.style.opacity = '0';
                setTimeout(function () {
                    if (modal.parentNode) modal.parentNode.removeChild(modal);
                }, 200); // 与 .modal/.modal-box 的 0.2s 过渡时长一致
            };
        }

        var close = closeModal();
        modal.querySelectorAll('.tpb-close').forEach(function (btn) {
            btn.addEventListener('click', close);
        });
        // 6.255⑦ 下载按钮与文件名链接：触发下载的同时自动关闭弹窗
        // （不 preventDefault，attachment 响应由浏览器接管下载，不离开页面）
        modal.querySelectorAll('.tpb-file-link, a.btn-primary').forEach(function (a) {
            a.addEventListener('click', close);
        });
    };

    // ---------------------------------------------------------------------
    // 暴露到全局
    // ---------------------------------------------------------------------

    tpb.confirm = lightyear.confirm;
    tpb.downloadBox = lightyear.downloadBox;

    window.tpb = tpb;
    window.lightyear = lightyear;

    // 真 layer（v3.1.1，layer/layer.js）优先已加载时不得覆盖——tpb 的 layer 仅作缺位兜底
    if (!window.layer) {
        window.layer = layer;
    }

})();
