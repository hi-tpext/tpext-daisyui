<?php

namespace tpext\builder\index\controller;

use think\Controller;
use tpext\builder\common\Module;
use tpext\common\ExtLoader;
use Webman\Http\Response;

/**
 * 文件图标输出控制器（无缩略图时按扩展名返回占位图标）
 * @title 文件缩略图
 */
class File extends Controller
{
    /**
     * 按文件类型输出对应的扩展名图标（带304缓存）
     *
     * @title 文件缩略图
     * @return mixed
     */
    public function extimg()
    {
        $type = strtolower(input('type'));

        $file = Module::getInstance()->getRoot() . implode(DIRECTORY_SEPARATOR, ['assets', 'images', 'ext', $type . '.png']);
        if (!file_exists($file)) {
            $file = Module::getInstance()->getRoot() . implode(DIRECTORY_SEPARATOR, ['assets', 'images', 'ext', '0.png']);
        }

        if (ExtLoader::isWebman()) {
            return (new Response())->file($file);
        }

        if (ob_get_contents()) {
            ob_end_clean();
        }

        $gmt_mtime = gmdate('r', filemtime($file));
        $ETag = '"' . md5($gmt_mtime . $file) . '"';

        if (request()->server('HTTP_IF_MODIFIED_SINCE') === $gmt_mtime) {
            header('ETag: ' . $ETag, true, 304);
            exit;
        }

        if (request()->server('HTTP_IF_NONE_MATCH') === $ETag) {
            header('ETag: ' . $ETag, true, 304);
            exit;
        } else {
            header('ETag: ' . $ETag);
            header("Content-type: image/png");
            header("Cache-Control: private, max-age=10800, pre-check=10800");
            header("Pragma: private");
            header("Expires: " . date(DATE_RFC822, strtotime("+30day")));
            readfile($file);
            exit;
        }
    }
}
