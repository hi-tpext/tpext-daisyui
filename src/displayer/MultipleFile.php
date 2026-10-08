<?php

namespace tpext\builder\displayer;

use tpext\think\App;
use tpext\builder\common\Module;
use tpext\builder\logic\ImageHandler;
use tpext\builder\traits\HasImageDriver;
use tpext\builder\traits\HasStorageDriver;

/**
 * MultipleFile多文件上传组件
 * @method $this  image()
 * @method $this  office()
 * @method $this  video()
 * @method $this  audio()
 * @method $this  pkg()
 */
class MultipleFile extends Field
{
    use HasStorageDriver;
    use HasImageDriver;

    protected $view = 'multiplefile';
    protected $cssFamily = 'widgets'; // 6.181 按需加载

    protected $js = [
        '/assets/tpextdaisyui/js/tpext-uploader.js',
    ];

    protected $css = [
        '/assets/tpextdaisyui/css/uploadfiles.css',
    ];

    protected $placeholder = '';

    protected $canUpload = true;

    protected $showInput = true;

    protected $showChooseBtn = true;

    protected $showUploadBtn = true;

    protected $isInTable = false;

    protected $files = [];

    protected $cover = '/assets/tpextdaisyui/images/cover/file.svg';

    protected $jsOptions = [
        'resize' => false,
        'compress' => false, //前端压缩jpg图片设置，与后端压缩图片设置其中一种
        'duplicate' => true,
        'ext' => [
            //
            'jpg', 'jpeg', 'gif', 'wbmp', 'webp', 'png', 'bmp', 'ico', 'swf', 'psd', 'jpc', 'jp2', 'jpx', 'jb2', 'swc', 'iff', 'xbm', 'svg',
            //
            "flv", "mkv", "avi", "rm", "rmvb", "mpeg", "mpg", "ogv", "mov", "wmv", "mp4", "webm",
            //
            "ogg", "mp3", "wav", "mid",
            //
            "rar", "zip", "tar", "gz", "7z", "bz2", "cab", "iso",
            //
            "doc", "docx", "xls", "xlsx", "ppt", "pptx", "pdf", "txt", "md",
            //
            "xml", "json",
        ],
        'multiple' => true,
        'mimeTypes' => '*/*',
        'swf_url' => '/assets/tpextdaisyui/js/webuploader/Uploader.swf',
        'fileSingleSizeLimit' => 250 * 1024 * 1024,
        'fileNumLimit' => 5,
        'fileSizeLimit' => 0,
        'thumbnailWidth' => 80,
        'thumbnailHeight' => 80,
        'chunkSize' => 10 * 1024 * 1024,
        'isImage' => false,
        'istable' => false,
    ];

    protected $extTypes = [
        'image' => ['jpg', 'jpeg', 'gif', 'wbmp', 'webp', 'png', 'bmp', 'ico', 'swf', 'psd', 'jpc', 'jp2', 'jpx', 'jb2', 'swc', 'iff', 'xbm', 'svg'],
        'office' => ["doc", "docx", "xls", "xlsx", "ppt", "pptx", "pdf"],
        'video' => ["flv", "mkv", "avi", "rm", "rmvb", "mpeg", "mpg", "ogv", "mov", "wmv", "mp4", "webm"],
        'audio' => ["ogg", "mp3", "wav", "mid"],
        'pkg' => ["rar", "zip", "tar", "gz", "7z", "bz2", "cab", "iso"],
    ];

    protected $coverList = [
        'image' => '/assets/tpextdaisyui/images/cover/image.svg',
        'office' => '/assets/tpextdaisyui/images/cover/office.svg',
        'video' => '/assets/tpextdaisyui/images/cover/video.svg',
        'audio' => '/assets/tpextdaisyui/images/cover/audio.svg',
        'pkg' => '/assets/tpextdaisyui/images/cover/pkg.svg',
    ];

    /**
     * 设置输入框占位提示
     *
     * @param string $val
     * @return $this
     */
    public function placeholder($val)
    {
        $this->placeholder = $val;
        return $this;
    }

    /**
     * 设置默认文件列表
     *
     * @param array $val
     * @return $this
     */
    public function default($val = [])
    {
        $this->default = $val;
        return $this;
    }

    /**
     * 可以上传
     *
     * @param boolean $val
     * @return $this
     */
    public function canUpload($val = true)
    {
        $this->canUpload = $val;
        return $this;
    }

    /**
     * 是否显示文件输入框
     *
     * @param boolean $val
     * @return $this
     */
    public function showInput($val = true)
    {
        $this->showInput = $val;
        return $this;
    }

    /**
     * 是否显示[选择已上传文件]按钮
     *
     * @param boolean $val
     * @return $this
     */
    public function showChooseBtn($val = true)
    {
        $this->showChooseBtn = $val;
        return $this;
    }

    /**
     * 是否显示[上传新文件]按钮
     *
     * @param boolean $val
     * @return $this
     */
    public function showUploadBtn($val = true)
    {
        $this->showUploadBtn = $val;
        return $this;
    }

    /**
     * 同时禁用[上传新文件][选择已上传文件]
     * 可通过cover图片控制
     * 
     * @param boolean $val
     * @return $this
     */
    public function disableButtons($val = true)
    {
        $this->showUploadBtn = !$val;
        $this->showChooseBtn = !$val;

        return $this;
    }

    /**
     * 累计文件数量限制
     * 
     * @param int $val
     * @return $this
     */
    public function limit($val)
    {
        $this->jsOptions['fileNumLimit'] = $val;
        return $this;
    }

    /**
     * 占位图片，当为文件列表空时显示
     *
     * @param string $val
     * @return $this
     */
    public function cover($val)
    {
        $this->cover = $val;
        return $this;
    }

    /**
     * 设置是否处于表格/items内
     *
     * @param boolean $val
     * @return $this
     */
    public function setIsInTable($val = true)
    {
        $this->isInTable = $val;
        return $this;
    }

    /**
     * 设置小号缩略图（50px）
     *
     * @return $this
     */
    public function smallSize()
    {
        $this->jsOptions['thumbnailWidth'] = 50;
        $this->jsOptions['thumbnailHeight'] = 50;

        return $this;
    }

    /**
     * 设置中号缩略图（120px）
     *
     * @return $this
     */
    public function mediumSize()
    {
        $this->jsOptions['thumbnailWidth'] = 120;
        $this->jsOptions['thumbnailHeight'] = 120;

        return $this;
    }

    /**
     * 设置大号缩略图（240px）
     *
     * @return $this
     */
    public function bigSize()
    {
        $this->jsOptions['thumbnailWidth'] = 240;
        $this->jsOptions['thumbnailHeight'] = 240;

        return $this;
    }

    /**
     * 设置超大号缩略图（480px）
     *
     * @return $this
     */
    public function largeSize()
    {
        $this->jsOptions['thumbnailWidth'] = 480;
        $this->jsOptions['thumbnailHeight'] = 480;

        return $this;
    }

    /**
     * 设置缩略图尺寸
     *
     * @param integer $w
     * @param integer $h
     * @return $this
     */
    public function thumbSize($w, $h)
    {
        $this->jsOptions['thumbnailWidth'] = $w;
        $this->jsOptions['thumbnailHeight'] = $h;

        return $this;
    }

    /**
     * 渲染为HTML
     *
     * @return mixed
     */
    public function render()
    {
        // 不能上传的场景：只读、表格列、搜索区（Table/Search 会给字段赋 extKey）。
        // items 嵌套行（isInTable）与 when 联动字段（extKey 含 '-watch-'）仍允许上传/选择。
        // 与 Image/MultipleImage 的判定保持一致——原库仅 Image 系列有此判断，
        // 导致 file 列在表格里仍会输出上传按钮，与「表格中只可查看/选择」的设计不符。
        $this->canUpload = !$this->readonly && $this->canUpload
            && ($this->isInTable || empty($this->extKey) || stripos($this->extKey, '-watch-') !== false);

        if (!$this->canUpload) {
            $this->cover = '';
            if (empty($this->default)) {
                $this->default = '/assets/tpextdaisyui/images/ext/0.png';
            }
        }

        if ($this->canUpload) {

            if (!isset($this->jsOptions['upload_url']) || empty($this->jsOptions['upload_url'])) {
                $token = $this->getCsrfToken();
                $this->jsOptions['upload_url'] = (string)url($this->getUploadUrl(), [
                    'utype' => 'webuploader',
                    'token' => $token,
                    'driver' => $this->getStorageDriver(),
                    'is_rand_name' => $this->isRandName(),
                    'image_driver' => $this->getImageDriver(),
                    'image_commonds' => $this->getImageCommands()
                ]);
            }

            if (!isset($this->jsOptions['chooseUrl']) || empty($this->jsOptions['chooseUrl'])) {
                $this->jsOptions['chooseUrl'] = (string)url(Module::getInstance()->getChooseUrl()) . '?';
            }
        }

        if ($this->extKey) { //table 或 items 中
            $this->showInput = false; //隐藏输入框
            $this->getWrapper()->addClass('in-table-in-items');
        }

        $vars = $this->commonVars();

        $this->value = $vars['value'];

        if (!empty($this->value)) {
            $this->files = is_array($this->value) ? $this->value : explode(',', $this->value);
        } else if (!empty($this->default)) {
            $this->files = is_array($this->default) ? $this->default : explode(',', $this->default);
        } else {
            $this->files = [];
        }

        $this->files = array_filter($this->files, 'strlen');

        $thumbs = $this->thumbs();

        // 不能上传时（表格列/搜索区/只读/查看）模板只做静态展示，不再输出
        // input、picker_、uploadConfigs 等上传元素，这里预备好服务端渲染所需的数据。
        $fileItems = [];
        $index = 0;

        foreach ($this->files as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $fileItems[] = [
                'url' => $file,
                'thumb' => isset($thumbs[$index]) ? $thumbs[$index] : $file,
                'name' => basename($file),
                'isImage' => in_array($ext, ['jpg', 'jpeg', 'gif', 'png', 'bmp', 'webp', 'ico', 'svg', 'avif']),
            ];
            $index++;
        }

        $this->jsOptions['canUpload'] = $this->canUpload;
        $this->jsOptions['showInput'] = $this->showInput;
        $this->jsOptions['showChooseBtn'] = $this->showChooseBtn;
        $this->jsOptions['showUploadBtn'] = $this->showUploadBtn;
        $this->jsOptions['isInTable'] = $this->isInTable;
        $this->jsOptions['cover'] = $this->cover;

        $vars = array_merge($vars, [
            'jsOptions' => $this->jsOptions,
            'canUpload' => $this->canUpload,
            'showInput' => $this->showInput,
            'showChooseBtn' => $this->showChooseBtn,
            'showUploadBtn' => $this->showUploadBtn,
            'isInTable' => $this->isInTable,
            'files' => $this->files,
            'thumbs' => $thumbs,
            'fileItems' => $fileItems,
            'thumbWidth' => $this->jsOptions['thumbnailWidth'],
            'thumbHeight' => $this->jsOptions['thumbnailHeight'],
            'cover' => $this->cover,
            'inputType' => $this->showInput ? 'text' : 'hidden',
            'placeholder' => $this->placeholder ?: __blang('builder_please_enter') . $this->label,
            // x-upload 元素配置：jsOptions 全量（ENT_QUOTES 转义防属性截断）
            'cfg' => htmlspecialchars(json_encode($this->jsOptions, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'),
        ]);

        $viewshow = $this->getViewInstance();

        return $viewshow->assign($vars)->getContent();
    }

    /**
     * 设置允许上传的扩展名
     *
     * @param string|array $types ['jpg', 'jpeg', 'gif'] or 'jpg,jpeg,gif'
     * @return $this
     */
    public function extTypes($types)
    {
        $this->jsOptions['ext'] = is_string($types) ? explode(',', $types) : $types;
        return $this;
    }

    /**
     * 支持image()/office()等方法快速设置扩展类型与封面
     *
     * @param string $name
     * @param array $arguments
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function __call($name, $arguments)
    {
        if (isset($this->extTypes[$name])) {
            $this->jsOptions['ext'] = $this->extTypes[$name];
            if ($this->cover) {
                $this->cover = $this->coverList[$name];
            }
            return $this;
        }

        throw new \InvalidArgumentException(__blang('builder_invalid_argument_exception') . ' : ' . $name);
    }

    /**
     * 获取缩略图
     * @return array
     */
    protected function thumbs()
    {
        $handler = new ImageHandler();
        $options = [
            'width' => $this->jsOptions['thumbnailWidth'] * 2,
            'height' => $this->jsOptions['thumbnailHeight'] * 2,
        ];

        if (!is_dir(App::getPublicPath() . '/thumb/')) {
            mkdir(App::getPublicPath() . '/thumb/', 0777, true);
        }

        $thumbs = [];

        foreach ($this->files as $file) {
            if (strstr($file, '/assets/tpextdaisyui/images/')) {
                $thumbs[] = $file;
                continue;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'gif', 'png', 'bmp', 'webp'])) {
                $thumbs[] = $file;
                continue;
            }

            $thumbFile = '/thumb/' . md5($file) . '-' . $options['width'] . 'x' . $options['height'] . '.' . $ext;

            if (is_file(App::getPublicPath() .$thumbFile)) {
                $thumbs[] = $thumbFile;
                continue;
            }

            if (strstr($file, 'http')) {
                $data = @file_get_contents($file);
                if (!$data) {
                    $thumbs[] = $file;
                    continue;
                }
                if (!@file_put_contents(App::getPublicPath() . $thumbFile, $data)) {
                    $thumbs[] = $file;
                    continue;
                }
                $file = $thumbFile;
            } else if (!is_file(App::getPublicPath() . $file)) {
                $thumbs[] = $file;
                continue;
            }
            try {
                $options['to_path'] = App::getPublicPath() . $thumbFile;
                $thumbs[] = $handler->resize($file, $options);
            } catch (\Exception $e) {
                $thumbs[] = $file;
            }
        }

        return $thumbs;
    }
}
