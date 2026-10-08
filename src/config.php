<?php

use tpext\builder\common\Module;
use tpext\builder\common\Form;

return [
    'search_open' => 1,
    'layer_size' => '1000px,auto',
    'max_size' => 20,
    'is_rand_name' => 1,
    'file_by_date' => 5,
    'storage_driver' => \tpext\builder\logic\LocalStorage::class,
    'image_water' => '',
    'image_water_position' => 'bottom-right',
    'image_size_limit' => '1024,1024',
    'allow_suffix' =>
    //
    "jpg,jpeg,gif,wbmp,webpg,png,ico," .
        //
        "flv,swf,mkv,avi,rm,rmvb,mpeg,mpg,ogv,mov,wmv,mp4,webm," .
        //
        "ogg,mp3,wav,mid," .
        //
        "rar,zip,tar,gz,7z,bz2,cab,iso," .
        //
        "doc,docx,xls,xlsx,ppt,pptx,pdf,txt,md",
    //
    // '__hr__' => '地图api，按需配置',
    'table_empty_text' => '<div class="text-center"><img src="/assets/tpextdaisyui/images/empty.png" /><p>暂无相关数据~</p></div>',
    'export_only_choosed_columns' => 1,
    'amap_js_key' => '//webapi.amap.com/maps?v=1.4.15&key=您申请的key&jscode=你的jscode',
    'baidu_map_js_key' => '//api.map.baidu.com/api?v=3.0&ak=您的密钥',
    'google_map_js_key' => '//maps.googleapis.com/maps/api/js?v=3.exp&sensor=false&libraries=places&key=您申请的key值',
    'tcent_map_js_key' => '//map.qq.com/api/js?v=2.exp&libraries=place&key=您申请的key值',
    'yandex_map_js_key' => '//api-maps.yandex.ru/2.1/?lang=ru_RU',
    //
    '__config__' => function (Form $form) {

        $form->defaultDisplayerSize(12, 12);

        $form->left(4)->with(function () use ($form) {
            $form->radio('search_open', '列表页搜索默认展开')->options([1 => '是', 0 => '否']);
            $form->text('layer_size', 'layer弹窗大小')->help('宽高以英文,号分割，如1000px,auto、1000px,98%，若高为`auto`，则自适应');
            $form->number('max_size', '上传文件大小限制(MB)');
            $form->radio('is_rand_name', '随机文件名')->options([1 => '是', 0 => '否']);
            $form->select('file_by_date', '文件目录归档')->options([1 => 'Ym(年月)', 2 => 'Ymd(年月日)', 3 => 'Y/m(年/月)', 4 => 'Y/md(年/月日)', 5 => 'Ym/d(年月/日)', 6 => 'Y/m/d(年/月/日)']);
            $form->select('storage_driver', '存储驱动')->options(Module::getInstance()->getStorageDrivers());
            $form->image('image_water', '图片水印')->help('若设置则所有上传图片(jpg/jpeg/png/webp/gif)都加此水印');
            $form->select('image_water_position', '图片水印位置')->options(['top-left' => '左上', 'top' => '中上', 'top-right' => '右上', 'left' => '左中', 'center' => '居中', 'right' => '右中', 'bottom-left' => '左下', 'bottom' => '中下', 'bottom-right' => '右下']);
            $form->text('image_size_limit', '上传图片大小限制')->help('宽高以英文,号分割，如1024,1024，0值不限制，如果上传图片宽或高超过限制，则缩放此边到限制大小，另一边等比例缩放');
        });

        $form->right(8)->with(function () use ($form) {
            $form->textarea('allow_suffix', '允许上传的文件后缀')->help('以英文,号分割');
            $form->textarea('table_empty_text', '列表无数据显示')->help('支持html');
            $form->radio('export_only_choosed_columns', '只导出已选列')->options([1 => '是', 0 => '否'])->help('全局设置，只导出用户前端页面选择的列，也可在控制器中`$this->exportOnlyChoosedColumns=true|false`单独控制');
            $form->text('amap_js_key', '高德地图js')->help('[高德]自2021年12月02日升级之后所申请的`key`必须配备安全密钥`jscode`一起使用。两个参数按`&key=你的key&jscode=你的jscode`');
            $form->text('baidu_map_js_key', '百度地图js');
            $form->text('google_map_js_key', 'google地图js');
            $form->text('tcent_map_js_key', '腾讯地图js');
            $form->text('yandex_map_js_key', 'yandex地图js');
        });
    },
];
