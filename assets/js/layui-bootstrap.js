/**
 * layui-bootstrap — layui（全量包）加载后把 layer 模块挂到 window.layer
 *
 * 加载顺序：layui.js → 本文件 → tpb.js → tpextbuilder.js
 * layui.use 异步就绪，回调会覆盖 tpb.js 先行占位的兜底 shim；
 * 业务脚本（layerOpen / layerApi() 等）均在调用期解析 window.layer，无需关心就绪时机。
 */
(function () {
    'use strict';

    if (typeof layui === 'undefined') {
        console.error('[tpextbuilder] layui 未加载，layer 弹窗不可用');
        return;
    }

    layui.use('layer', function () {
        window.layer = layui.layer;
    });
})();
