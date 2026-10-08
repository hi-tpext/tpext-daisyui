/**
 * tpext-uploader.js — 原生上传组件（替代依赖 jQuery 的 webuploader）
 *
 * 职责：
 * 1. 读取页面 uploadConfigs（multiplefile.html 等模板输出），为每个上传字段绑定
 *    「上传按钮 → 隐藏文件选择器(picker_{id}) → axios 上传」流程
 * 2. 提供 window.chooseFile(id, inputName)：layer iframe 打开附件选择页
 *
 * 上传契约（admin/controller/Upload.php @ upfiles，utype=webuploader）：
 *   WebUploader 协议分块（logic/WebUploader.php）：每片 POST multipart 字段
 *   file（片内容）/name（原始文件名）/chunk（0 起序号）/chunks（总片数），
 *   后端按 {文件名}_{序号}.part 落盘、全部就绪后按序合并；
 *   中间片响应 {status:200, picurl:'uploading'}，最后一片 picurl 为真实路径
 *   （如 /uploads/...）。小文件单片（chunks=1）走同一协议路径。
 *
 * 附件选择页回传：Attachment.php 原生 DOM 写入输入框并派发 change 事件（不依赖 jQuery）
 */
(function (w, d) {
    'use strict';

    function getConfig(inputId) {
        return (w.uploadConfigs && w.uploadConfigs[inputId]) || null;
    }

    function notify(msg, style) {
        if (w.tpb) { w.tpb.notify(msg, style); } else { w.alert(msg); }
    }

    function blang(key, fallback) {
        return (w.__blang && w.__blang[key]) || fallback;
    }

    function setValue(input, value) {
        input.value = value;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        renderPreview(input);
    }

    /** 上传预览——已上传文件缩略图 + "+" 添加块
     *  canUpload=false（table 列/搜索栏/readonly）：只读视图，缩略图点击查看，无删除/上传/添加
     *  isInTable=true（form items 嵌套行）：只可选择，不直接上传（对齐原库行为） */
    /** 6.150 预览图拖动排序（多文件时）：HTML5 原生 DnD，dragend 按 DOM 顺序回写 input。
     *  单文件（limit=1）与只读视图没有排序意义，不启用；
     *  上传中的本地预览块（data-local）不可拖、也不参与回写——存在时跳过
     *  （setValue 会派发 change 触发 renderPreview，把上传中占位块清掉）。 */
    function enableItemDrag(box, item, input, url) {
        var cfg = getConfig(input.id) || {};
        if (cfg.fileNumLimit === 1 || cfg.canUpload === false) return;

        item.setAttribute('data-url', url);
        item.draggable = true;
        // img 默认可拖拽会抢走 item 的 dragstart（拖出的是浏览器原图下载而非排序）
        var img = item.querySelector('img');
        if (img) img.draggable = false;

        item.addEventListener('dragstart', function (e) {
            box.__dragEl = item;
            item.style.opacity = '0.4';
            // Firefox 需要 setData 才会真正进入拖拽
            try { e.dataTransfer.setData('text/plain', url); } catch (err) { /* 忽略 */ }
            if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move';
        });

        item.addEventListener('dragend', function () {
            item.style.opacity = '';
            box.__dragEl = null;
            if (box.querySelector('[data-local]')) return;
            var ordered = Array.prototype.map.call(
                box.querySelectorAll('[data-url]'),
                function (el) { return el.getAttribute('data-url'); }
            );
            if (ordered.join(',') !== currentUrls(input).join(',')) {
                setValue(input, ordered.join(','));
            }
        });

        // 落点在其它块上：按鼠标处于块内左/右半边决定插入其前/后（实时移动给出位置反馈）
        item.addEventListener('dragover', function (e) {
            var dragEl = box.__dragEl;
            if (!dragEl || dragEl === item) return;
            e.preventDefault();
            if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
            var rect = item.getBoundingClientRect();
            var ref;
            if (e.clientX > rect.left + rect.width / 2) {
                ref = item.nextSibling;
                // 允许拖到末尾，但不能越过 "+" 块（ref 为 null 时 insertBefore 等价 append）
                if (!ref) ref = box.querySelector('[data-add]');
            } else {
                ref = item;
            }
            box.insertBefore(dragEl, ref);
        });
        item.addEventListener('drop', function (e) { e.preventDefault(); });
    }

    function renderPreview(input) {
        var cfg = getConfig(input.id);
        if (!cfg) return;

        var canUp = cfg.canUpload !== false;
        var inItems = !!cfg.isInTable;
        var anchor = input.closest('.upload-input-wrap') || input.parentElement;
        var box = d.getElementById('preview_' + input.id);
        if (!box) {
            box = d.createElement('div');
            box.id = 'preview_' + input.id;
            box.className = 'upload-preview-list';
            anchor.parentElement.insertBefore(box, anchor);
        }
        box.innerHTML = '';

        var urls = currentUrls(input);
        var limit = cfg.fileNumLimit || 0;
        var tw = parseInt(cfg.thumbnailWidth, 10) || 64;
        var thh = parseInt(cfg.thumbnailHeight, 10) || 64;

        // 已上传文件预览
        urls.forEach(function (url) {
            var item = d.createElement('div');
            // 6.337 设计债 Batch B：chrome 样式收敛到 builder-widgets.css 集成 class，
            // 内联只留动态缩略图尺寸（thumbnailWidth/Height 来自配置）
            item.className = 'upload-preview-item';
            item.style.cssText = 'width:' + tw + 'px;height:' + thh + 'px';

            var isImg = isImageExt(url);

            var img = d.createElement('img');
            img.src = url;
            img.alt = url.split('/').pop();
            // 6.275 cover→contain（完整显示）；6.277 contain→fill：用户要求宽高都占满
            // 预览框——fill 拉伸到 100%×100%（非等比，整图可见但比例随框）；
            // 若要等比占满需裁剪回到 cover，一处改动（现由 .upload-preview-item img 提供）
            img.loading = 'lazy';

            if (canUp) {
                // hover 覆盖层：预览 / 移除（已上传文件应该看预览，不是再上传）
                // 6.337 显隐改 CSS :hover（.upload-preview-item:hover .upload-preview-overlay）
                var overlay = d.createElement('div');
                overlay.className = 'upload-preview-overlay';

                // 预览按钮：图片 → previewFile 弹出层，其他类型 → 新窗口打开
                var viewBtn = d.createElement('button');
                viewBtn.type = 'button';
                viewBtn.title = blang('builder_action_view', 'View');
                viewBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
                viewBtn.className = 'upload-mask-btn';
                viewBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (isImg) {
                        previewFile(url, true);
                    } else {
                        w.open(url, '_blank');
                    }
                });
                overlay.appendChild(viewBtn);

                // 移除按钮
                var delBtn = d.createElement('button');
                delBtn.type = 'button';
                delBtn.title = blang('builder_remove', 'Remove');
                delBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6M14 11v6"/></svg>';
                delBtn.className = 'upload-mask-btn upload-mask-btn--danger'; /* 6.337 原 cssText 内联 + rgba(220,38,38) */
                delBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var doRemove = function () {
                        var urls2 = currentUrls(input);
                        var idx = urls2.indexOf(url);
                        if (idx >= 0) {
                            urls2.splice(idx, 1);
                            setValue(input, urls2.join(','));
                        }
                    };
                    // 删除已上传文件需二次确认（tpb.confirm 弹窗，未加载时回退原生 confirm）
                    var msg = blang('builder_confirm_remove_file', '确定要删除该文件吗？');
                    if (w.tpb && w.tpb.confirm) {
                        w.tpb.confirm({
                            title: blang('builder_operation_tips', '操作提示'),
                            msg: msg,
                            onOk: doRemove
                        });
                    } else if (w.confirm(msg)) {
                        doRemove();
                    }
                });
                overlay.appendChild(delBtn);

                item.appendChild(overlay);
                // 6.337 原 mouseenter/mouseleave 切 overlay.style.opacity —— 改 CSS :hover
            } else {
                // 只读视图：整图遮罩 + 预览按钮居中（图片只有预览；其他文件另加下载）
                item.className += ' upload-preview-item--readonly';

                if (isImg) {
                    item.addEventListener('click', function () { previewFile(url, true); });
                } else {
                    var icon = d.createElement('div');
                    icon.style.cssText = 'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:oklch(var(--bc)/0.05)';
                    icon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" style="stroke:oklch(var(--bc)/0.4)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>';
                    item.appendChild(icon);
                    item.addEventListener('click', function () { previewFile(url, false); });
                }

                var cover = d.createElement('div');
                cover.className = 'upload-preview-overlay';

                var viewBtn = d.createElement('button');
                viewBtn.type = 'button';
                viewBtn.title = blang('builder_action_view', 'View');
                viewBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
                viewBtn.className = 'upload-mask-btn';
                viewBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    previewFile(url, isImg);
                });

                cover.appendChild(viewBtn);

                // 图片只提供预览；其他文件提供 新窗口打开 + 下载
                if (!isImg) {
                    var dlBtn = d.createElement('button');
                    dlBtn.type = 'button';
                    dlBtn.title = blang('builder_download_file', 'Download file');
                    dlBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>';
                    dlBtn.className = 'upload-mask-btn';
                    dlBtn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        downloadFile(url);
                    });

                    cover.appendChild(dlBtn);
                }
                item.appendChild(cover);
                // 6.337 原 mouseenter/mouseleave 切 cover.style.opacity —— 改 CSS :hover
            }

            if (canUp || isImg) {
                item.appendChild(img);
            }
            enableItemDrag(box, item, input, url);
            box.appendChild(item);
        });

        // "+" 添加占位块（可上传且未满时显示）：hover 出现 上传/选择 按钮（与输入框旁按钮功能一致）
        // 6.192：showUploadBtn/showChooseBtn 同时控制输入框旁按钮和遮罩按钮——
        // 遮罩与 x-upload 同规则：Upload 需 showUploadBtn 且非 items 行，Choose 需 showChooseBtn；
        // 两个按钮都不可用时占位块无任何添加途径，整块不渲染
        var maskUpload = canUp && !inItems && cfg.showUploadBtn !== false;
        var maskChoose = canUp && cfg.showChooseBtn !== false;
        if ((maskUpload || maskChoose) && (!limit || urls.length < limit)) {
            var add = d.createElement('div');
            add.className = 'upload-add-block';
            add.style.cssText = 'width:' + tw + 'px;height:' + thh + 'px';
            add.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" style="stroke:oklch(var(--bc)/0.35)" stroke-width="2"><path stroke-linecap="round" d="M12 4v16m8-8H4"/></svg>';

            var addMask = d.createElement('div');
            addMask.className = 'upload-preview-overlay';

            // 6.190：按钮样式统一成预览/删除的图标钮（28×22 白描边图标 + 半透明白底），
            // 不再是 11px 文字钮；文字保留在 title 里作悬停提示
            function addMaskBtn(icon, title, fn) {
                var b = d.createElement('button');
                b.type = 'button';
                b.title = title;
                b.innerHTML = icon;
                b.className = 'upload-mask-btn';
                b.addEventListener('click', function (e) {
                    e.stopPropagation();
                    fn();
                });
                return b;
            }

            // 6.200 与输入框旁按钮（builder-elements.js XUpload）统一为同一对 SVG，
            // stroke=currentColor：遮罩按钮 color:#fff → 白描边；输入侧继承按钮文字色
            var uploadSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4m0 0 4 4m-4-4-4 4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>';
            var chooseSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>';

            if (maskUpload) {
                addMask.appendChild(addMaskBtn(uploadSvg, blang('builder_upload_file_button', 'Upload'), function () {
                    var picker = d.getElementById('picker_' + input.id);
                    if (picker) picker.click();
                }));
            }
            if (maskChoose) {
                addMask.appendChild(addMaskBtn(chooseSvg, blang('builder_choose_file_button', 'Choose'), function () {
                    w.chooseFile(input.id, input.id);
                }));
            }

            add.setAttribute('data-add', '1');
            // 6.150 拖到"+"块上 = 把拖拽块移到末尾
            add.addEventListener('dragover', function (e) {
                var dragEl = box.__dragEl;
                if (!dragEl) return;
                e.preventDefault();
                if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
                box.insertBefore(dragEl, add);
            });
            add.addEventListener('drop', function (e) { e.preventDefault(); });

            add.appendChild(addMask);
            // 6.337 原 mouseenter/mouseleave 切 borderColor 与 mask 透明度 —— 改 CSS :hover
            add.addEventListener('click', function () {
                if (inItems && maskChoose) {
                    w.chooseFile(input.id, input.id);
                    return;
                }
                if (maskUpload) {
                    var picker = d.getElementById('picker_' + input.id);
                    if (picker) picker.click();
                    return;
                }
                if (maskChoose) w.chooseFile(input.id, input.id);
            });
            box.appendChild(add);
        }
    }

    function currentUrls(input) {
        return String(input.value || '').trim(',').split(',').filter(Boolean);
    }

    function isImageExt(url) {
        var ext = String(url).split('?')[0].split('#')[0].split('.').pop().toLowerCase();
        return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico', 'svg', 'avif'].indexOf(ext) >= 0;
    }

    /** 只读态预览：图片用 layer.photos 灯箱，其余类型交给浏览器（pdf/视频/音频内建预览，其余转为下载） */
    function previewFile(url, isImg) {
        if (isImg && w.layer && w.layer.photos) {
            w.layer.photos({ photos: { data: [{ src: url }], start: 0 }, anim: 0 });
        } else {
            w.open(url, '_blank');
        }
    }

    /** 只读态下载：a[download] 同源直接下载；跨源时浏览器可能改为导航打开 */
    function downloadFile(url) {
        var a = d.createElement('a');
        a.href = url;
        a.download = String(url).split('?')[0].split('/').pop() || 'file';
        d.body.appendChild(a);
        a.click();
        d.body.removeChild(a);
    }

    function appendValue(input, url, limit) {
        var urls = currentUrls(input);
        if (limit > 0 && urls.length >= limit) {
            notify(blang('builder_maximum_upload_files_num_is', 'Max upload limit: ') + limit, 'warning');
            return false;
        }
        urls.push(url);
        setValue(input, urls.join(','));
        return true;
    }

    /* ------------------------------------------------------------
     * 分块上传（6.307）——WebUploader 协议前端实现
     * 按上传配置 chunkSize（MultipleFile 默认 10MB）切片，逐片顺序
     * POST（后端按序号落 .part、最后一片触发合并，顺序片避免同名
     * 并发合并竞态）。每片网络级失败重试 MAX_CHUNK_TRY 次（间隔
     * CHUNK_RETRY_DELAY），HTTP 4xx/5xx 属确定性失败不重试。
     * 业务失败（HTTP 200 但 status!=200，含 no token）reject
     * {business:true, data} 由调用方 notify；网络错误直接 reject——
     * 全局 axios 拦截器是唯一提示出口（6.257，重试期间会由拦截器
     * 逐次提示，大文件传输中断重试优于静默丢片）。
     * ------------------------------------------------------------ */
    var MAX_CHUNK_TRY = 3;
    var CHUNK_RETRY_DELAY = 1000;

    function postChunk(cfg, blob, fileName, idx, total) {
        var fd = new FormData();
        fd.append('file', blob, fileName);
        fd.append('name', fileName);
        fd.append('chunk', idx);
        fd.append('chunks', total);
        return axios.post(cfg.upload_url, fd);
    }

    function postChunkRetry(cfg, blob, fileName, idx, total) {
        var tries = 0;
        function attempt() {
            tries++;
            return postChunk(cfg, blob, fileName, idx, total).catch(function (err) {
                if (!err.response && tries < MAX_CHUNK_TRY) {
                    return new Promise(function (resolve) {
                        setTimeout(resolve, CHUNK_RETRY_DELAY);
                    }).then(attempt);
                }
                throw err;
            });
        }
        return attempt();
    }

    /** 上传一个文件直到服务端合并完成，resolve 最终响应（含真实 picurl） */
    function uploadOne(file, cfg) {
        var chunkSize = parseInt(cfg.chunkSize, 10) || 10 * 1024 * 1024;
        var total = Math.max(1, Math.ceil(file.size / chunkSize));
        var idx = 0;

        function next() {
            var cur = idx;
            var start = cur * chunkSize;
            var blob = total > 1 ? file.slice(start, Math.min(start + chunkSize, file.size)) : file;
            return postChunkRetry(cfg, blob, file.name, cur, total).then(function (res) {
                var data = res.data || {};
                if (data.status == 200 && data.picurl && data.picurl !== 'uploading') {
                    return data; // 最后一片已合并
                }
                if (data.status == 200) { // 中间片已保存（picurl === 'uploading'）
                    idx = cur + 1;
                    return next();
                }
                return Promise.reject({ business: true, data: data });
            });
        }
        return next();
    }

    function uploadFiles(inputId, files) {
        var cfg = getConfig(inputId);
        var input = d.getElementById(inputId);
        if (!cfg || !input || !files.length) return;

        var limit = cfg.fileNumLimit || 0;
        files = Array.prototype.slice.call(files);

        if (limit > 0) {
            var rest = limit - currentUrls(input).length;
            if (files.length > rest) {
                files = files.slice(0, Math.max(0, rest));
                notify(blang('builder_maximum_upload_files_num_is', 'Max upload limit: ') + limit, 'warning');
            }
        }

        // 6.325 客户端校验补齐（旧 webuploader 行为）：类型后缀 + 单文件大小。
        // fileTypeSuffixes 由业务经 jsOptions 设置（'zip,rar' 或 '.zip,.rar'），缺省不限制；
        // fileSingleSizeLimit 默认 File=250MB / Image=2MB（PHP 侧 jsOptions）
        var sizeLimit = parseInt(cfg.fileSingleSizeLimit, 10) || 0;
        var suffixAllowed = cfg.fileTypeSuffixes
            ? String(cfg.fileTypeSuffixes).toLowerCase().split(',').map(function (s) {
                return s.trim().replace(/^\./, '');
            }).filter(function (s) { return s; })
            : null;
        var fmtSize = function (bytes) {
            if (bytes >= 1048576) return (Math.round(bytes / 1048576 * 100) / 100) + 'MB';
            if (bytes >= 1024) return (Math.round(bytes / 1024 * 100) / 100) + 'KB';
            return bytes + 'B';
        };

        files = files.filter(function (file) {
            var name = file.name || '';
            var dot = name.lastIndexOf('.');
            var ext = dot > -1 ? name.slice(dot + 1).toLowerCase() : '';

            if (suffixAllowed && suffixAllowed.indexOf(ext) === -1) {
                notify(blang('builder_file_type_suffix_allowed_is', 'File type suffix allowed is :') + ' ' + cfg.fileTypeSuffixes, 'warning');
                return false;
            }
            if (sizeLimit > 0 && file.size > sizeLimit) {
                notify(blang('builder_file_size_cannot_exceed', 'File size cannot exceed :') + ' ' + fmtSize(sizeLimit), 'warning');
                return false;
            }
            return true;
        });
        if (!files.length) return;

        // 先本地预览（URL.createObjectURL 零延迟）
        var box = d.getElementById('preview_' + inputId);
        if (!box) {
            box = d.createElement('div');
            box.id = 'preview_' + inputId;
            box.className = 'upload-preview-list';
            var anchor = input.closest('.upload-input-wrap') || input.parentElement;
            anchor.parentElement.insertBefore(box, anchor);
        }

        Array.prototype.forEach.call(files, function (file) {
            // 本地预览（立即可见）
            var localUrl = URL.createObjectURL(file);
            var previewItem = createPreviewItem(input, localUrl, function() {
                URL.revokeObjectURL(localUrl);
                var urls = currentUrls(input);
                var idx = urls.indexOf(localUrl);
                if (idx >= 0) { urls.splice(idx, 1); input.value = urls.join(','); }
                renderPreview(input);
            });
            box.appendChild(previewItem);
        });

        // 后台上传
        tpb.loading('show');
        var pending = files.length;

        Array.prototype.forEach.call(files, function (file, fileIdx) {
            // 6.307 分块上传：单片/多片统一走 WebUploader 协议（uploadOne）
            uploadOne(file, cfg)
                .then(function (data) {
                    pending--;
                    // 上传成功：用服务器 URL 追加到 input value
                    var urls = currentUrls(input);
                    if (limit <= 0 || urls.length < limit) {
                        urls.push(data.picurl);
                        input.value = urls.join(',');
                    }
                    // 预览已在本地阶段展示
                    if (pending === 0) {
                        tpb.loading('hide');
                        renderPreview(input);
                        notify(data.info || blang('builder_upload_success', 'Upload succeeded'), 'success');
                    }
                })
                .catch(function (err) {
                    pending--;
                    if (pending === 0) {
                        tpb.loading('hide');
                        renderPreview(input);
                    }
                    if (err && err.business) {
                        // HTTP 200 但业务失败：拦截器不管这类响应，此处是唯一提示出口
                        var data = err.data || {};
                        notify(data.info || data.message || blang('builder_upload_failed', 'Upload failed'), 'danger');
                    }
                    // 网络错误提示由全局 axios 拦截器统一出口，勿在此重复 notify
                });
        });
    }

    function createPreviewItem(input, src, onRemove) {
        var cfg = getConfig(input.id) || {};
        var tw = parseInt(cfg.thumbnailWidth, 10) || 64;
        var thh = parseInt(cfg.thumbnailHeight, 10) || 64;
        var item = d.createElement('div');
        item.setAttribute('data-local', '1'); // 6.150 上传中占位块：不参与拖动排序回写
        item.className = 'upload-preview-item';
        item.style.cssText = 'width:' + tw + 'px;height:' + thh + 'px';

        var img = d.createElement('img');
        img.src = src; /* 6.275/6.277 尺寸与 object-fit 由 .upload-preview-item img 提供 */

        var btn = d.createElement('button');
        btn.type = 'button';
        btn.innerHTML = '×';
        btn.className = 'upload-local-del';
        btn.addEventListener('click', function () {
            if (item.parentNode) item.parentNode.removeChild(item);
            if (onRemove) onRemove();
        });

        item.appendChild(img);
        item.appendChild(btn);
        return item;
    }

    /**
     * 6.289 输入框右侧留白按上传/选择按钮的**实际宽度**自适应。
     *
     * 按钮是绝对定位浮在输入框右端（6.76 用户要求移进框内），输入框靠 padding-right
     * 预留位置——CSS 里的定值（单按钮 6rem / 双按钮 11.25rem）是按英文标签估的，
     * 中文标签、字体变化、表格内只留图标、按钮增减等场景都对不上：英文实测按钮组
     * 138.7px（+右偏移 4）却预留 180px，文字被提前截断、右侧空一大段（用户报的
     * 「输入框右侧边距过大，不适配按钮实际宽度」）。实测回写内联 important
     * （压过 CSS 的 !important），RO 自愈：语言/字体加载、表格局部刷新、隐藏字段
     * 变可见都会重算；__upPad__ 记忆上次值，收敛后零写入。
     */
    function fitInputPadding(wrap) {
        var input = wrap.querySelector('.file-url-input');
        var btns = wrap.querySelector('.upload-input-btns');
        if (!input || !btns) return;
        var btnsW = btns.offsetWidth;
        if (!btnsW) return; // 按钮隐藏（未激活 tab / showInput=false），保留 CSS 值
        var right = parseFloat(w.getComputedStyle(btns).right) || 0;
        var need = Math.ceil(btnsW + right + 8); // +8：文字与按钮之间的呼吸
        if (input.__upPad__ === need) return;
        input.__upPad__ = need;
        input.style.setProperty('padding-right', need + 'px', 'important');
    }

    /** 扫描（或指定容器内）的上传输入行，回写留白并挂 RO；renderFiles 局部刷新路径也会调 */
    function syncInputPadding(scope) {
        var root = scope && scope.querySelectorAll ? scope : (typeof scope === 'string' && scope ? d.querySelector(scope) : d);
        if (!root || !root.querySelectorAll) return;
        Array.prototype.forEach.call(root.querySelectorAll('.upload-input-wrap'), function (wrap) {
            fitInputPadding(wrap);
            var btns = wrap.querySelector('.upload-input-btns');
            if (!btns || wrap.__upPadRO__ || typeof w.ResizeObserver !== 'function') return;
            var ro = new w.ResizeObserver(function () { fitInputPadding(wrap); });
            ro.observe(btns);
            wrap.__upPadRO__ = ro;
        });
    }

    /** 初始化页面中的上传字段；scope（如 '#table-goods '）时只处理该容器内未绑定的字段 */
    function initUploaders(scope) {
        if (!w.uploadConfigs) return;
        scope = String(scope || '').trim();

        Object.keys(w.uploadConfigs).forEach(function (inputId) {
            var cfg = w.uploadConfigs[inputId];
            var input = d.getElementById(inputId);
            if (!input || input.__uploader_bound__) return;
            if (scope && !input.closest(scope)) return;
            input.__uploader_bound__ = true;

            // 6.149 附件选择页（Attachment choose 模式）在 iframe 里直接
            // input.value = v 并派发 change（旧库 jQuery .trigger('change') 的原生等价）。
            // 引擎此前只在自身 setValue 里重建预览、从不监听 change，导致
            // "选择已上传文件"后只有输入框更新、预览区不刷新。补监听统一重建；
            // renderPreview 幂等（先清空再重建），setValue 路径重复触发无害。
            // 顺带恢复旧库行为：手动在输入框里改 URL 后失焦也会刷新预览。
            input.addEventListener('change', function () { renderPreview(input); });

            var picker = d.getElementById('picker_' + inputId);
            if (picker && !picker.__uploader_bound__) {
                picker.__uploader_bound__ = true;

                if (cfg.multiple && (cfg.fileNumLimit || 1) > 1) { picker.multiple = true; }
                if (cfg.ext && cfg.ext.length) {
                    picker.accept = cfg.ext.map(function (e) { return '.' + e; }).join(',');
                }

                picker.addEventListener('change', function () {
                    if (picker.files && picker.files.length) {
                        uploadFiles(inputId, picker.files);
                        picker.value = '';
                    }
                });
            }

            renderPreview(input);
        });
    }

    /** table.html AJAX 局部刷新后重建表格内上传预览（模板钩子，勿改名）；
     *  顺带按按钮实宽重算输入框右留白（6.289） */
    w.renderFiles = function (scope) {
        initUploaders(scope);
        syncInputPadding(scope);
    };

    /** 6.289 供外部（表格刷新/动态插入字段）主动调用 */
    w.syncUploadInputPadding = syncInputPadding;

    /** chooseFile — 打开附件选择页（layer iframe），附件页选定后原生 DOM 回写输入框 */
    w.chooseFile = function (id, inputName) {
        var cfg = getConfig(inputName) || {};
        if (!w.layer) { console.error('[tpext-uploader] layer 未加载'); return; }

        var input = d.getElementById(id);
        var limit = cfg.fileNumLimit || 1;
        var urls = input ? currentUrls(input) : [];
        if (limit > 1 && urls.length >= limit) {
            notify(blang('builder_maximum_upload_files_num_is', 'Max upload limit: ') + limit, 'warning');
            return;
        }

        var chooseUrl = cfg.chooseUrl || '/admin/attachment/index?';
        chooseUrl += (chooseUrl.indexOf('?') >= 0 ? '&' : '?')
            + 'choose=1&id=' + id + '&limit=' + limit + '&ext=' + (cfg.ext || []).join(',');

        var size = ['98%', '98%'];
        if (input && input.getAttribute('data-layer-size')) {
            size = input.getAttribute('data-layer-size').split(',');
        }

        w.layer.open({
            type: 2,
            title: blang('builder_choose_uploaded_file', 'Choose uploaded file'),
            shadeClose: false,
            maxmin: true,
            area: size,
            content: chooseUrl
        });
    };

    /** 只读展示（表格列 / 搜索区 / 查看页）图片预览：
     *  不能上传时模板不输出 input/picker_/uploadConfigs，改为 <a class="upload-readonly-item"><img></a>；
     *  这里用事件委托（document）接管图片点击 → layer.photos 灯箱（与原库 magnific-popup 行为对齐），
     *  委托方式兼容 table.html AJAX 局部刷新后重建的节点，无需重新绑定；非图片文件保持 <a target="_blank"> 原行为。 */
    function initReadonlyPreview() {
        if (w.__uploaderReadonlyBound__) return;
        w.__uploaderReadonlyBound__ = true;

        d.addEventListener('click', function (e) {
            var node = e.target;
            if (!node || !node.closest) return;
            var link = node.closest('a.upload-readonly-item');
            if (!link) return;
            var url = link.getAttribute('href');
            if (!url || !isImageExt(url)) return;
            if (w.layer && w.layer.photos) {
                e.preventDefault();
                previewFile(url, true);
            }
        });
    }

    initReadonlyPreview();

    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', function () {
            initUploaders();
            syncInputPadding();
        });
    } else {
        initUploaders();
        syncInputPadding();
    }

    // 6.289 字体（含 CJK 回退字体）晚于首屏加载会改变按钮文字宽度 → 按钮组尺寸变化，
    // RO 已能捕获；再补一次 window.load 兜底（RO 回调在后台标签页可能被渲染饥饿）
    w.addEventListener('load', function () { syncInputPadding(); });
})(window, document);
