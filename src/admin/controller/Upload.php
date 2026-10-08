<?php

namespace tpext\builder\admin\controller;

use tpext\think\App;
use think\Controller;
use think\facade\Session;
use tpext\builder\common\Module;
use tpext\builder\logic\WebUploader;
use tpext\builder\common\model\Attachment;
use tpext\builder\logic\Upload as UploadTool;

/* 参照 Light-Year-Example 相关上传处理方式*/

/**
 * 上传控制器：兼容多种编辑器/组件的上传协议（webuploader/dropzone/ueditor/ckeditor等）
 * @title 上传
 */
class Upload extends Controller
{
    /**
     * 统一上传入口，按utype返回各编辑器要求的json格式
     *
     * @title 上传文件
     * @return mixed
     */
    public function upfiles()
    {
        $utype = input('utype');
        $token = input('token');
        $driver = input('driver');
        $is_rand_name = input('is_rand_name');
        $image_driver = input('image_driver');
        $image_commonds = input('image_commonds');

        if (empty($token)) {
            return json(
                ['info' => 'no token', 'picurl' => '']
            );
        }

        if (Session::get('_csrf_token_') != $token) {
            return json(
                ['info' => 'token error', 'picurl' => '']
            );
        }

        if ($utype == 'ueditor') { //ueditor
            $action = request()->get('action');
            if (!in_array($action, ['uploadimage', 'uploadscrawl', 'uploadvideo', 'uploadfile'])) { //不是上传文件动作
                return $this->ueditor();
            }
        }

        switch ($utype) {
            case 'editormd':
                $file_input_name = 'editormd-image-file';
                break;
            case 'ckeditor':
                $file_input_name = 'upload';
                break;
            case 'ueditor':
                $file_input_name = 'upfile';
                break;
            default:
                $file_input_name = 'file';
        }

        $config = Module::getInstance()->getConfig();

        $_config['allowSuffix'] = explode(',', $config['allow_suffix']);
        $_config['maxSize'] = $config['max_size'] * 1024 * 1024;

        if ($is_rand_name == 'n') {
            $_config['isRandName'] = 0;
        } else if ($is_rand_name == 'y') {
            $_config['isRandName'] = 1;
        } else {
            $_config['isRandName'] = $config['is_rand_name'];
        }

        $_config['fileByDate'] = $config['file_by_date'];

        $storageDriver = $config['storage_driver'];

        if ($driver) {
            $driver = str_replace('-', '\\', $driver);
            if (class_exists($driver)) {
                $storageDriver = $driver;
            }
        }

        $storageDriver = empty($storageDriver) || !class_exists($storageDriver)
            ? \tpext\builder\logic\LocalStorage::class : $storageDriver;

        $driver = new $storageDriver;

        $_config['driver'] = $driver;
        $_config['imageDriver'] = $image_driver && class_exists($image_driver) ? new $image_driver : new \tpext\builder\logic\ImageHandler;

        $_config['imageCommonds'] = [];

        if ($config['image_water']) {

            $_config['imageCommonds'][] = [
                'name' => 'water',
                'args' => ['imgPath' => $config['image_water'], 'position' => $config['image_water_position']],
                'is_global_config' => 'image_water',
            ];
        }
        if ($config['image_size_limit']) {
            $arr = explode(',', $config['image_size_limit']);

            if (count($arr) > 1 && (intval($arr[0]) > 0 || intval($arr[0]) > 0)) {
                $_config['imageCommonds'][] = [
                    'name' => 'resize',
                    'args' => ['width' => intval($arr[0]) ?: null, 'height' => intval($arr[1]) ?: null],
                    'is_global_config' => 'image_size_limit',
                ];
            }
        }

        if ($image_commonds) {
            $imgCmd = json_decode(base64_decode($image_commonds), true);

            if ($imgCmd) {
                $_config['imageCommonds'] = array_merge($_config['imageCommonds'], $imgCmd);
            }
        }

        $_config['admin_id'] = Session::has('admin_id') ? Session::get('admin_id') : 0;
        $_config['user_id'] = Session::has('user_id') ? Session::get('user_id') : 0;

        $up = null;

        if ($utype == 'webuploader') {
            $up = new WebUploader($_config);
        } else {
            $up = new UploadTool($_config);
        }

        $newPath = $up->uploadFile($file_input_name);

        if ($newPath === false) {
            //var_dump($up->errorNumber);
            return json(
                [
                    'status' => 500,
                    'success' => 0,
                    'uploaded' => 0,
                    'state' => 'ERROE',
                    'message' => __blang('builder_file_uploading_failed') . '-' . $up->errorInfo,
                    'title' => __blang('builder_file_uploading_failed') . '-' . $up->errorInfo,
                    'info' => __blang('builder_file_uploading_failed') . '-' . $up->errorInfo,
                    'class' => 'error'
                ]
            );
            // 失败跟成功同样的方式返回
        } else {
            switch ($utype) {
                case 'wangeditor':
                    return json(
                        ['url' => $newPath]
                    );
                case 'editormd':
                    return json(
                        [
                            "success" => 1,
                            "message" => __blang('builder_file_uploading_succeeded'),
                            "url" => $newPath,
                        ]
                    );
                case 'dropzone':
                    return json(
                        ['status' => 200, 'info' => __blang('builder_file_uploading_succeeded'), 'picurl' => $newPath]
                    );
                case 'webuploader':
                    $res = json(
                        ['status' => 200, 'info' => __blang('builder_file_uploading_succeeded'), 'class' => 'success', 'id' => rand(1, 9999), 'picurl' => $newPath]
                    );
                    // Make sure file is not cached (as it happens for example on iOS devices)
                    if ($res instanceof \Workerman\Protocols\Http\Response) {
                        $res->withHeaders([
                            "Expires" => "Mon, 26 Jul 1997 05:00:00 GMT",
                            "Last-Modified" => gmdate("D, d M Y H:i:s") . " GMT",
                            "Cache-Control" => "no-store, no-cache, must-revalidate",
                            "Pragma" => "no-cache"
                        ]);
                    } else {
                        $res->header([
                            "Expires" => "Mon, 26 Jul 1997 05:00:00 GMT",
                            "Last-Modified" => gmdate("D, d M Y H:i:s") . " GMT",
                            "Cache-Control" => "no-store, no-cache, must-revalidate",
                            "Pragma" => "no-cache"
                        ]);
                    }
                    return $res;
                case 'tinymce':
                    return json(
                        [
                            "location" => $newPath,
                        ]
                    );
                case 'ckeditor':
                    return json(
                        [
                            "uploaded" => 1,
                            "fileName" => pathinfo($newPath)['filename'],
                            "url" => $newPath,
                        ]
                    );
                case 'ueditor':
                    return json([
                        "state" => "SUCCESS", // 上传状态，上传成功时必须返回"SUCCESS"
                        "url" => $newPath, // 返回的地址
                        "title" => $newPath, // 附件名
                        '666' => 1
                    ]);
                default:
                    return json(
                        [
                            "status" => 1,
                            "info" => __blang('builder_file_uploading_succeeded'),
                            "url" => $newPath,
                        ]
                    );
            }
        }
    }

    /**
     * ueditor后端控制器（配置/列文件/抓取图片等动作分发）
     *
     * @title ueditor上传相关
     * @return mixed
     */
    protected function ueditor()
    {
        $action = request()->get('action');
        $config_file = App::getPublicPath() . '/assets/builderueditor/config.json';
        $config = json_decode(preg_replace("/\/\*[\s\S]+?\*\//", "", file_get_contents($config_file)), true);
        switch ($action) {
            /* 获取配置信息 */
            case 'config':
                $result = $config;
                break;
            /* 列出图片 */
            case 'listimage':
                return json($this->showFile('listimage', $config));

                /* 列出附件 */
            case 'listfile':
                return json($this->showFile('listfile', $config));

                /* 抓取远程附件 */
            case 'catchimage':
                $result = $this->catchFile();
                break;

            default:
                $result = ['state' => 'invalid action'];
                break;
        }

        /* 输出结果 */
        if ($callback = request()->get("callback")) {
            if (preg_match("/^[\w_]+$/", $callback)) {
                echo htmlspecialchars($callback) . '(' . $result . ')';
            } else {
                return json(['state' => 'invalid callback']);
            }
        } else {
            return json($result);
        }
    }

    /**
     * base64图片数据上传并保存为附件
     *
     * @title base64上传
     * @return mixed
     */
    public function base64()
    {
        $picdata =  request()->post('picdata');

        if (empty($picdata)) {
            return json(['state' => 400, 'message' => 'empty data']);
        }

        $picurl = $this->base64_image_content($picdata, 'images');

        if ($picurl) {
            return json(['state' => 200, 'picurl' => $picurl]);
        } else {
            return json(['state' => 500, 'message' => __blang('builder_file_uploading_failed')]);
        }
    }

    /**
     * 解析base64图片数据并保存到指定目录，返回url
     *
     * @param string $base64_image_content
     * @param string $dirName 保存子目录名
     * @return string|boolean
     */
    protected function base64_image_content($base64_image_content, $dirName)
    {
        //匹配出图片的格式
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
            $type = $result[2];
            if (!preg_match('/^(png|jpg|jpeg|bmp|gif|webpg)$/i', $type)) {
                return false;
            }

            $fileByDate = Module::config('file_by_date');
            $storageDriver = Module::config('storage_driver');

            $storageDriver = empty($storageDriver) || !class_exists($storageDriver)
                ? \tpext\builder\logic\LocalStorage::class : $storageDriver;

            $driver = new $storageDriver;

            $date = '';

            if ($fileByDate == 2) {
                $date = date('Ymd');
            } else if ($fileByDate == 3) {
                $date = date('Y/m');
            } else if ($fileByDate == 4) {
                $date = date('Y/md');
            } else if ($fileByDate == 5) {
                $date = date('Ym/d');
            } else if ($fileByDate == 6) {
                $date = date('Y/m/d');
            } else {
                $date = date('Ym');
            }

            $path = App::getPublicPath() . "/uploads/{$dirName}/" . $date . '/';

            if (!is_dir($path)) {
                //检查是否有该文件夹，如果没有就创建，并给予最高权限
                mkdir($path, 0755, true);
            }

            $newName = 'file' . md5(microtime(true)) . mt_rand(1000, 9999) . '.' . $type;

            if (file_put_contents($path . $newName, base64_decode(str_replace($result[1], '', $base64_image_content)))) {

                $url = "/uploads/{$dirName}/" . $date . '/' . $newName;
                $name = 'base64' . date('YmdHis');

                $attachment = new Attachment;

                $res = $attachment->save([
                    'name' => mb_substr($name, 0, 55),
                    'admin_id' => Session::has('admin_id') ? Session::get('admin_id') : 0,
                    'user_id' => Session::has('user_id') ? Session::get('user_id') : 0,
                    'mime' => $this->mime_content_type($path . $newName),
                    'suffix' => $type,
                    'size' => filesize($path . $newName) / (1024 ** 2),
                    'sha1' => hash_file('sha1', $path . $newName),
                    'storage' => 'local',
                    'url' => $url,
                ]);

                if ($res) {
                    $url =  $driver->process($attachment);
                }

                return $url;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 获取文件mime类型（优先系统mime_content_type函数）
     *
     * @param string $filename
     * @return string|boolean
     */
    protected function mime_content_type($filename)
    {
        if (function_exists('mime_content_type')) {

            return mime_content_type($filename);
        }

        $result = new \finfo();

        if (is_resource($result) === true) {
            return $result->file($filename, FILEINFO_MIME_TYPE);
        }

        return false;
    }

    /**
     * 抓取远程附件（当前为占位实现，固定返回成功）
     *
     * @return array
     */
    protected function catchFile()
    {
        // 假装抓取成功了
        return  [
            "state" => "SUCCESS", // 上传状态，上传成功时必须返回"SUCCESS"
            "url" => './upload/images/lyear_5de21f46cd8ba.jpg', // 返回的地址
            "title" => 'lyear_5de21f46cd8ba', // 附件名
        ];
    }

    /**
     * 列出已上传的图片/附件（ueditor在线管理）
     *
     * @param string $type listimage|listfile
     * @param array $config
     * @return array
     */
    protected function showFile($type = '', $config = [])
    {
        /* 判断类型 */
        switch ($type) {
            /* 列出附件 */
            case 'listfile':
                $allowFiles = $config['fileManagerAllowFiles'];
                $listSize = $config['fileManagerListSize'];
                $path = realpath('./upload/files/');
                break;

            /* 列出图片 */
            case 'listimage':
            default:
                $allowFiles = $config['imageManagerAllowFiles'];
                $listSize = $config['imageManagerListSize'];
                $path = realpath('./upload/images/');
        }
        $allowFiles = substr(str_replace(".", "|", join("", $allowFiles)), 1);

        /* 获取参数 */
        $size = request()->get('size', $listSize);
        $start = request()->get('start', 0);
        $end = $start + $size;

        /* 获取附件列表 */
        $files = $this->getfiles($path, $allowFiles);
        if (!count($files)) {
            return array(
                "state" => "no match file",
                "list" => array(),
                "start" => $start,
                "total" => count($files),
            );
        }

        /* 获取指定范围的列表 */
        $len = count($files);
        for ($i = min($end, $len) - 1, $list = array(); $i < $len && $i >= 0 && $i >= $start; $i--) {
            $list[] = $files[$i];
        }

        /* 返回数据 */
        $result = array(
            "state" => "SUCCESS",
            "list" => $list,
            "start" => $start,
            "total" => count($files),
        );

        return $result;
    }

    /**
     * 递归遍历目录收集匹配后缀的文件列表
     *
     * @param string $path
     * @param string $allowFiles 允许的后缀正则片段，如 'jpg|png'
     * @param array $files
     * @return array|null
     */
    protected function getfiles($path = '', $allowFiles = '', &$files = array())
    {
        if (!is_dir($path)) {
            return null;
        }

        if (substr($path, strlen($path) - 1) != '/') {
            $path .= '/';
        }

        $handle = opendir($path);
        while (false !== ($file = readdir($handle))) {
            if ($file != '.' && $file != '..') {
                $path2 = $path . $file;
                if (is_dir($path2)) {
                    $this->getfiles($path2, $allowFiles, $files);
                } else {
                    if (preg_match("/\.(" . $allowFiles . ")$/i", $file)) {
                        $files[] = array(
                            'url' => str_replace("\\", "/", substr($path2, strlen(request()->server('DOCUMENT_ROOT')))),
                            'mtime' => filemtime($path2),
                        );
                    }
                }
            }
        }
        return $files;
    }
}
