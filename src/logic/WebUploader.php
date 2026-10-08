<?php

namespace tpext\builder\logic;

use tpext\builder\common\model\Attachment;
use tpext\builder\inface\Storage;
use tpext\builder\inface\Image;
use tpext\think\App;

/**
 * 分块大文件上传器（WebUploader协议：分块保存、完成后合并）
 */
class WebUploader
{
    /**
     * 存储驱动
     *
     * @var Storage
     */
    protected $driver = null;

    /**
     * 图片驱动
     *
     * @var Image
     */
    protected $imageDriver = null;

    protected $imageCommonds = [];

    //文件上传保存路径
    protected $path = '';

    //临时目录
    protected $targetDir = '';

    /**
     * 是否完成
     *
     * @var boolean
     */
    protected $done = false;

    //允许文件上传的后缀
    protected $allowSuffix = [
        //
        'jpg', 'jpeg', 'gif', 'wbmp', 'webp', 'png', 'bmp', 'ico', 'swf', 'psd', 'jpc', 'jp2', 'jpx', 'jb2', 'swc', 'iff', 'xbm', 'svg',
        //
        "flv", "mkv", "avi", "rm", "rmvb", "mpeg", "mpg", "ogv", "mov", "wmv", "mp4", "webm",
        //
        "ogg", "mp3", "wav", "mid",
        //
        "rar", "zip", "tar", "gz", "7z", "bz2", "cab", "iso",
        //
        "doc", "docx", "xls", "xlsx", "ppt", "pptx", "pdf", "txt", "md"
    ];

    public const IMAGE_TYPES = ['jpg', 'jpeg', 'gif', 'wbmp', 'webp', 'png', 'bmp', 'ico', 'swf', 'psd', 'jpc', 'jp2', 'jpx', 'jb2', 'swc', 'iff', 'xbm', 'svg'];

    //允许文件上传的 Mime 类型
    protected $allowMime = [
        'image/jpeg', 'image/gif', 'image/wbmp', 'image/wbmp', 'image/png',
        'application/x-zip-compressed'
    ];

    //允许文件上传的文件最大大小
    protected $maxSize = 20 * 1024 * 1024;
    //是否启用随机名
    protected $isRandName = true;

    protected $fileByDate = 0;
    //加上文件前缀
    protected $prefix = 'file';

    //自定义的错误号码和错误信息
    protected $errorNumber;
    protected $errorInfo;

    //文件的信息
    protected $oldName; //文件名
    protected $suffix; //文件后缀
    protected $size; //文件大小
    protected $mime; //文件 mime
    protected $tmpName; //文件临时路径
    protected $newName; //文件新名字
    protected $dirName;
    protected $admin_id = 0;
    protected $user_id = 0;

    /**
     * 构造函数，可通过数组批量设置成员属性（仅接受存在的属性名）
     *
     * @param array $arr
     */
    public function __construct($arr = [])
    {
        foreach ($arr as $key => $value) {
            $this->setOption($key, $value);
        }
    }

    /**
     * 魔术方法：读取errorNumber或errorInfo
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if ($name == 'errorNumber') {
            return $this->errorNumber;
        } else if ($name == 'errorInfo') {
            return $this->getErrorInfo();
        }
    }

    /**
     * 判断这个$key 是不是我的成员属性，如果是，则设置
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function setOption($key, $value)
    {
        //得到所有的成员属性
        $keys = array_keys(get_class_vars(__CLASS__));
        if (in_array($key, $keys)) {
            $this->$key = $value;
        }
    }

    /**
     * 清理临时目录
     *
     * @param string $targetDir
     * @param string $chunk
     * @param string $filePath
     * @return bool
     */
    protected function cleanupTargetDir($targetDir, $chunk, $filePath)
    {
        $maxFileAge = 5 * 3600; // Temp file age in seconds

        if (!is_dir($targetDir) || !$dir = opendir($targetDir)) {
            $this->setOption('errorNumber', 6);
            return false;
        }

        while (($file = readdir($dir)) !== false) {
            $tmpfilePath = $targetDir . DIRECTORY_SEPARATOR . $file;

            // If temp file is current file proceed to the next
            if ($tmpfilePath == "{$filePath}_{$chunk}.part" || $tmpfilePath == "{$filePath}_{$chunk}.parttmp") {
                continue;
            }

            // Remove temp file if it is older than the max age and is not the current file
            if (preg_match('/\.(part|parttmp)$/', $file) && (@filemtime($tmpfilePath) < time() - $maxFileAge)) {
                @unlink($tmpfilePath);
            }
        }
        closedir($dir);

        return true;
    }

    /**
     * 保存分块
     *
     * @param string $filePath
     * @param string $chunk
     * @param string $key
     * @return bool
     */
    protected function savePart($filePath, $chunk, $key)
    {
        // Open temp file
        if (!$out = @fopen("{$filePath}_{$chunk}.parttmp", "wb")) {
            $this->setOption('errorNumber', 7);
            return false;
        }

        $filses = request()->file();

        $file = request()->file($key);
        if ($file) {
            if (!$file->isValid()) {
                $this->setOption('errorNumber', -7);
                return false;
            }
            // Read binary input stream and append it to temp file
            if (!$in = @fopen($filses[$key]->getPathname(), "rb")) {
                $this->setOption('errorNumber', 4);
                return false;
            }
        } else {
            if (!$in = @fopen("php://input", "rb")) {
                $this->setOption('errorNumber', 4);
                return false;
            }
        }

        while ($buff = fread($in, 4096)) {
            fwrite($out, $buff);
        }

        @fclose($out);
        @fclose($in);

        $result = rename("{$filePath}_{$chunk}.parttmp", "{$filePath}_{$chunk}.part");

        if (!$result) {
            $this->setOption('errorNumber', 7);
        }

        return $result;
    }

    /**
     * 检查是否上传完所有
     *
     * @param string $filePath
     * @param string $chunks
     * @return bool
     */
    public function checkDone($filePath, $chunks)
    {
        $index = 0;
        $this->done = true;
        for ($index = 0; $index < $chunks; $index++) {
            if (!file_exists("{$filePath}_{$index}.part")) {
                $this->done = false;
                break;
            }
        }

        return $this->done;
    }

    /**
     * 接收并保存一个分块，全部分块就绪时合并为最终文件
     *
     * @param string $key 上传字段名
     * @return bool
     */
    protected function chunk($key)
    {
        // @set_time_limit(5 * 60);

        // Get a file name
        if (request()->post("name")) {
            $fileName = request()->post("name");
        } else if (!empty(request()->file($key))) {
            $fileName = request()->file($key)->getBasename();
        } else {
            return false;
        }

        $this->oldName = $fileName;

        $this->suffix = strtolower(preg_replace('/.+?(\w+)$/', '$1', $fileName));

        $this->targetDir = App::getPublicPath() . "/uploads/tmp/";

        //判断该路径是否存在，是否可写
        if (!$this->checkTmp()) {
            $this->setOption('errorNumber', -2);
            return false;
        }

        $fileName = str_replace(['\\', '/', '.' . $this->suffix, '..', ' ', '.'], '', $fileName) . '.' . $this->suffix;

        $filePath = $this->targetDir . DIRECTORY_SEPARATOR . $fileName;

        // Chunking might be enabled
        $chunk = request()->post('chunk', 0);
        $chunks = request()->post('chunks', 1);

        $cleanup = $this->cleanupTargetDir($this->targetDir, $chunk, $filePath);

        if (!$cleanup) {
            return false;
        }

        $save = $this->savePart($filePath, $chunk, $key);

        if (!$save) {
            return false;
        }

        $this->checkDone($filePath, $chunks);

        if ($this->done) {
            $combineRes = $this->combine($filePath, $chunks);
            return $combineRes;
        }

        return true;
    }

    /**
     * 按序合并全部分块为最终文件
     *
     * @param string $filePath
     * @param string $chunks
     * @return bool
     */
    protected function combine($filePath, $chunks)
    {
        $this->newName = $this->createNewName();

        $saveto  = $this->path . $this->newName;

        if (!$out = @fopen($saveto, "wb")) {
            trace($saveto);
            $this->setOption('errorNumber', 7);
            return false;
        }

        if (flock($out, LOCK_EX)) {
            for ($index = 0; $index < $chunks; $index++) {
                if (!$in = @fopen("{$filePath}_{$index}.part", "rb")) {
                    break;
                }

                while ($buff = fread($in, 4096)) {
                    fwrite($out, $buff);
                }

                @fclose($in);
                @unlink("{$filePath}_{$index}.part");
            }

            flock($out, LOCK_UN);
        }
        @fclose($out);

        return true;
    }

    /**
     * 文件上传函数
     * $key 就是你 input 框中的 name 属性值
     *
     * @param string $key
     * @return boolean|string
     */
    public function uploadFile($key)
    {
        if (!$this->dirName) {

            $this->dirName = 'images';
            if (!in_array($this->suffix, self::IMAGE_TYPES)) {
                $this->dirName = 'files';
            }
        }

        $date = '';

        if ($this->fileByDate == 2) {
            $date = date('Ymd');
        } else if ($this->fileByDate == 3) {
            $date = date('Y/m');
        } else if ($this->fileByDate == 4) {
            $date = date('Y/md');
        } else if ($this->fileByDate == 5) {
            $date = date('Ym/d');
        } else if ($this->fileByDate == 6) {
            $date = date('Y/m/d');
        } else {
            $date = date('Ym');
        }

        $this->path = App::getPublicPath() . "/uploads/{$this->dirName}/" . $date . '/';

        //判断该路径是否存在，是否可写
        if (!$this->check()) {
            $this->setOption('errorNumber', -2);
            return false;
        }

        $chunkRes = $this->chunk($key);

        if (!$chunkRes) {
            return false;
        }

        if (!$this->done) {
            return 'uploading';
        }

        $this->getFileInfo($this->path . $this->newName);

        if (!$this->checkSize() /*|| !$this->checkMime() */ || !$this->checkSuffix()) {
            @unlink($this->path . $this->newName);
            return false;
        }

        $url = "/uploads/{$this->dirName}/" . $date . '/' . $this->newName;
        $name = str_replace(['.' . $this->suffix], '', $this->oldName);

        $attachment = new Attachment;

        $res = $attachment->save([
            'name' => mb_substr($name, 0, 55),
            'admin_id' => $this->admin_id ?: 0,
            'user_id' => $this->user_id ?: 0,
            'mime' => $this->mime,
            'suffix' => $this->suffix,
            'size' => $this->size / (1024 ** 2),
            'sha1' => hash_file('sha1', $this->path . $this->newName),
            'storage' => 'local',
            'url' => $url,
        ]);

        if ($res) {
            if (in_array($this->suffix, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {

                $url =  $this->imageDriver->process($attachment, $this->imageCommonds);
            }

            $url =  $this->driver->process($attachment);
        }

        if (empty($url)) {
            $this->setOption('errorNumber', -7);
            return false;
        }

        return $url;
    }

    /**
     * 得到文件的新名字
     *
     * @return string
     */
    protected function createNewName()
    {
        //判断是否使用随机名
        if ($this->isRandName) {
            $name = $this->prefix . md5(microtime(true)) . mt_rand(1000, 9999) . '.' . $this->suffix;
        } else {
            $name = str_replace(['\\', '/', '.' . $this->suffix, '..', ' ', '.'], '', $this->oldName) . '.' . $this->suffix;
        }

        return $name;
    }

    /**
     * 判断该路径是否存在，是否可写
     *
     * @return boolean
     */
    protected function check()
    {
        //文件夹不存在或者不是目录。创建文件夹
        if (!file_exists($this->path) || !is_dir($this->path)) {
            return mkdir($this->path, 0755, true);
        }
        //判断文件是否可写
        if (!is_writeable($this->path)) {
            return chmod($this->path, 0755);
        }
        return true;
    }

    /**
     * 判断该路径是否存在，是否可写
     *
     * @return boolean
     */
    protected function checkTmp()
    {
        //文件夹不存在或者不是目录。创建文件夹
        if (!file_exists($this->targetDir) || !is_dir($this->targetDir)) {
            return mkdir($this->targetDir, 0755, true);
        }
        //判断文件是否可写
        if (!is_writeable($this->targetDir)) {
            return chmod($this->targetDir, 0755);
        }
        return true;
    }

    /**
     * 提取文件相关信息并且保存到成员属性中
     *
     * @param string $file
     * @return void
     */
    protected function getFileInfo($file)
    {
        //得到文件的 mime 类型
        $this->mime = $this->mime_content_type($file);
        //得到文件临时路径
        $this->tmpName = $file;
        //得到文件大小
        $this->size = filesize($file);
        //得到文件后缀
        $this->suffix = strtolower(pathinfo($file)['extension']);
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
     * 判断文件大小
     *
     * @return boolean
     */
    protected function checkSize()
    {
        if ($this->size > $this->maxSize) {
            $this->setOption('errorNumber', -3);
            return false;
        }
        return true;
    }

    /**
     * 判断文件的 mime 是否符合
     *
     * @return boolean
     */
    protected function checkMime()
    {
        if (!in_array($this->mime, $this->allowMime)) {
            $this->setOption('errorNumber', -4);
            return false;
        }
        return true;
    }

    /**
     * 判断文件的后缀是否符合
     *
     * @return boolean
     */
    protected function checkSuffix()
    {
        if (in_array($this->suffix, ['php', 'phps', 'php5', 'php7', 'htaccess', 'cgi', 'config', 'conf', 'js', 'css', 'html', 'htm', 'exe', 'asp', 'dll', 'aspx', 'asa', 'asax', 'ascx', 'asmx', 'ashx', 'axd', 'jsp', 'jspx', 'cer', 'cdx'])) {
            $this->setOption('errorNumber', -5);
            return false;
        }
        if (!in_array($this->suffix, $this->allowSuffix)) {
            $this->setOption('errorNumber', -5);
            return false;
        }
        /* 对图像文件进行严格检测 */
        if (in_array($this->suffix, self::IMAGE_TYPES)) {

            $imageType = $this->getImageType($this->tmpName);
            
            if ($imageType < 1 || $imageType > 18) {
                $this->setOption('errorNumber', -5);
                return false;
            }
        }

        return true;
    }

    /**
     * 判断图像类型（exif_imagetype优先，失败返回0）
     *
     * @param string $image 图片路径
     * @return int
     */
    protected function getImageType($image)
    {
        if (function_exists('exif_imagetype')) {
            return exif_imagetype($image);
        }

        try {
            $info = getimagesize($image);
            return $info ? $info[2] : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * 返回错误信息
     *
     * @return string
     */
    protected function getErrorInfo()
    {
        switch ($this->errorNumber) {
            case -1:
                $str = '文件路径没有设置';
                break;
            case -2:
                $str = '文件路径不是目录或者没有权限';
                break;
            case -3:
                $str = '文件大小超过指定范围';
                break;
            case -4:
                $str = '文件 mime 类型不符合';
                break;
            case -5:
                $str = '文件后缀不符合';
                break;
            case -6:
                $str = '不是上传文件';
                break;
            case -7:
                $str = '文件上传失败';
                break;
            case 1:
                $str = '文件超出 php.ini 设置大小';
                break;
            case 2:
                $str = '文件超出 html 设置大小';
                break;
            case 3:
                $str = '文件部分上传';
                break;
            case 4:
                $str = '没有文件上传';
                break;
            case 6:
                $str = '找不到临时文件';
                break;
            case 7:
                $str = '文件写入失败';
                break;
            default:
                $str = '' . $this->errorNumber;
        }
        return $str;
    }
}
