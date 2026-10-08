<?php

namespace tpext\builder\logic;

use tpext\builder\inface\Image as IImage;
use tpext\builder\common\model\Attachment;
use tpext\builder\common\Module;
use tpext\think\App;

/**
 * 图片处理：基于 Intervention/Image 的缩放、裁剪、水印、文字
 */
class ImageHandler implements IImage
{
    /**
     * Intervention/Image 版本
     * 
     * @var int
     */
    protected $version;

    /**
     * 驱动实例
     *
     * @var \Intervention\Image\AbstractDriver|\Intervention\Image\ImageManager
     */
    protected $driver;

    /**
     * 构造函数，检测 Intervention/Image 版本
     */
    public function __construct()
    {
        $this->version = $this->detectVersion();
    }

    /**
     * 检测 Intervention/Image 版本
     * 
     * @return int 2 或 3
     */
    protected function detectVersion()
    {
        // 检查 v3 特有的类是否存在
        if (class_exists('\\Intervention\\Image\\Drivers\\AbstractDriver')) {
            return 3;
        }

        return 2;
    }

    /**
     * 处理图片附件（按命令依次执行裁剪/缩放/文字/水印），返回处理后的url
     *
     * @param Attachment $attachment
     * @param array $commonds
     * @return string url
     */
    public function process($attachment, $commonds)
    {
        if (empty($commonds)) {
            return $attachment['url'];
        }

        $imgPath = $this->checkFile($attachment['url']);

        $clear_global_config = [];

        foreach ($commonds as $cmd) {
            if ($cmd['name'] == 'clear_global_config') {
                $clear_global_config[$cmd['args']['name']] = 1;
            }
        }

        //命令执行顺序优先级 crop>resize>text>water

        foreach ($commonds as $cmd) {
            if (isset($cmd['is_global_config']) && isset($clear_global_config[$cmd['is_global_config']])) {
                continue;
            }
            if ($cmd['name'] == 'crop') {
                $attachment['url'] = $this->crop($imgPath, $cmd['args']);
            }
        }

        foreach ($commonds as $cmd) {
            if (isset($cmd['is_global_config']) && isset($clear_global_config[$cmd['is_global_config']])) {
                continue;
            }
            if ($cmd['name'] == 'resize') {
                $attachment['url'] = $this->resize($imgPath, $cmd['args']);
            }
        }

        foreach ($commonds as $cmd) {
            if (isset($cmd['is_global_config']) && isset($clear_global_config[$cmd['is_global_config']])) {
                continue;
            }
            if ($cmd['name'] == 'text') {
                $attachment['url'] = $this->text($imgPath, $cmd['args']);
            }
        }

        foreach ($commonds as $cmd) {
            if (isset($cmd['is_global_config']) && isset($clear_global_config[$cmd['is_global_config']])) {
                continue;
            }
            if ($cmd['name'] == 'water') {
                $attachment['url'] = $this->water($imgPath, $cmd['args']);
            }
        }

        $attachment['url'] = str_replace(App::getPublicPath(), '', $attachment['url']);

        //修改了图片，更新文件信息
        $attachment['size'] = filesize($imgPath) / (1024 ** 2);
        $attachment['sha1'] = hash_file('sha1', $imgPath);
        $attachment->save();

        return $attachment['url'];
    }

    /**
     * 缩放图片
     *
     * @param string $imgPath
     * @param array $args
     * @return string
     */
    public function resize($imgPath, $args)
    {
        if (empty($args['width']) && empty($args['height'])) {
            return $imgPath;
        }

        $imageInstance = $this->image($imgPath);

        if (!$imageInstance) {
            return $imgPath;
        }

        if ($this->version == 3) {
            // v3 版本的 resize 操作

            // 处理宽高比
            if (!isset($args['aspectRatio']) || $args['aspectRatio'] == 1 || $args['aspectRatio'] == true) {
                $imageInstance->scaleDown($args['width'] ?: null, $args['height'] ?: null)->save($args['to_path'] ?? null);
            } else {
                $imageInstance->resizeDown($args['width'] ?: null, $args['height'] ?: null)->save($args['to_path'] ?? null);
            }
        } else {
            // v2 版本的 resize 操作
            $imageInstance->resize($args['width'] ?: null, $args['height'] ?: null, function ($constraint) use ($args) {
                if (!isset($args['aspectRatio']) || $args['aspectRatio'] == 1 || $args['aspectRatio'] == true) {
                    $constraint->aspectRatio();
                }

                if (!isset($args['upsize']) || $args['upsize'] == 1 || $args['upsize'] == true) {
                    $constraint->upsize();
                }
            })->save($args['to_path'] ?? null);
        }

        unset($imageInstance);

        return $imgPath;
    }

    /**
     * 给图片添加文字
     *
     * @param string $imgPath
     * @param array $args
     * @return string
     */
    public function text($imgPath, $args)
    {
        if (empty($args['text'])) {
            return $imgPath;
        }

        $imageInstance = $this->image($imgPath);

        if (!$imageInstance) {
            return $imgPath;
        }

        $args['text'] = urldecode($args['text']);

        // 处理字体文件
        $fontFile = null;
        if (isset($args['fontFile']) && $args['fontFile']) {
            $args['fontFile'] = $this->checkFile($args['fontFile']);
            $fontType = strtolower(pathinfo($args['fontFile'])['extension']);
            if (in_array($fontType, ['fft', 'otf', 'woff', 'woff2'])) {
                $fontFile = $args['fontFile'];
            }
        }

        if (!$fontFile) {
            $fontFile = Module::getInstance()->getRoot() . implode(DIRECTORY_SEPARATOR, ['assets', 'zhttfs', 'DroidSansFallbackFull.ttf']);
        }

        if ($this->version == 3) {
            // v3 版本的 text 操作
            $textOptions = [
                'fontFile' => $fontFile,
                'size' => $args['fontSize'] ?? 12,
                'color' => $args['color'] ?? '#000000',
                'align' => $args['align'] ?? 'left',
                'valign' => $args['valign'] ?? 'bottom',
                'angle' => $args['angle'] ?? 0
            ];

            // v3 中 kerning 可能在不同驱动中有不同实现
            if (isset($args['kerning'])) {
                $textOptions['kerning'] = $args['kerning'];
            }

            $imageInstance->text($args['text'], $args['x'] ?? 0, $args['y'] ?? 0, $textOptions)
                ->save($args['to_path'] ?? null);
        } else {
            // v2 版本的 text 操作
            $imageInstance->text($args['text'], $args['x'] ?? 0, $args['y'] ?? 0, function ($font) use ($args, $fontFile) {
                $font->file($fontFile);
                $font->size($args['fontSize'] ?? 12);
                $font->color($args['color'] ?? '#000000');
                $font->align($args['align'] ?? '');
                $font->valign($args['valign'] ?? '');
                $font->angle($args['angle'] ?? 0);

                if ($this->driver instanceof \Intervention\Image\Imagick\Driver) {
                    $font->kerning($args['kerning'] ?? 0);
                }
            })->save($args['to_path'] ?? null);
        }

        unset($imageInstance);

        return $imgPath;
    }

    /**
     * 裁剪图片
     *
     * @param string $imgPath
     * @param array $args
     * @return string
     */
    public function crop($imgPath, $args)
    {
        $imageInstance = $this->image($imgPath);

        if (!$imageInstance) {
            return $imgPath;
        }

        $imageInstance->crop($args['width'], $args['height'], $args['x'] ?? 0, $args['y'] ?? 0)
            ->save($args['to_path'] ?? null);

        unset($imageInstance);

        return $imgPath;
    }

    /**
     * 给图片添加水印
     *
     * @param string $imgPath
     * @param array $args
     * @return string
     */
    public function water($imgPath, $args)
    {
        if (empty($args['imgPath'])) {
            return $imgPath;
        }

        $args['imgPath'] = $this->checkFile($args['imgPath']);

        $imageInstance = $this->image($imgPath);

        if (!$imageInstance) {
            return $imgPath;
        }

        if ($this->version == 2) {
            $imageInstance->insert($args['imgPath'], $args['position'] ?? 'bottom-right', $args['x'] ?? 0, $args['y'] ?? 0)
                ->save($args['to_path'] ?? null);
        } else {
            $imageInstance->place($args['imgPath'], $args['position'] ?? 'bottom-right', $args['x'] ?? 0, $args['y'] ?? 0)
                ->save($args['to_path'] ?? null);
        }

        unset($imageInstance);

        return $imgPath;
    }

    /**
     * 获取Image示例
     *
     * @param string $imgPath 原始图片路径
     * @return null|\Intervention\Image\Image|\Intervention\Image\Interfaces\ImageInterface
     */
    public function image($imgPath)
    {
        $imgPath = $this->checkFile($imgPath);

        if (stripos($imgPath, App::getRootPath()) === false) {
            $imgPath = App::getRootPath() . 'public' . ltrim($imgPath, '.');
        }

        if (!$this->driver) {
            $driverType = $this->getDriverType();

            if (!$driverType) {
                return null;
            }

            $this->driver = $this->createDriver($driverType);
        }

        return $this->version == 2 ? $this->driver->init($imgPath) : $this->driver->read($imgPath);
    }

    /**
     * 选择图片驱动
     *
     * @return string imagick|gd
     */
    public function getDriverType()
    {
        if ($this->imagickAvailable()) {
            return 'imagick';
        }

        if ($this->gdAvailable()) {
            return 'gd';
        }

        return '';
    }

    /**
     * Creates a driver instance
     * @param string $driver imagick|gd
     * 
     * @return null|\Intervention\Image\AbstractDriver|\Intervention\Image\ImageManager
     */
    protected function createDriver($driverType)
    {
        if ($driverType) {

            $driverclass = null;

            if ($this->version == 2) {
                $driverclass = sprintf('\\Intervention\\Image\\%s\\Driver', ucfirst($driverType));
            } else {
                $driverclass = sprintf('\\Intervention\\Image\\Drivers\\%s\\Driver', ucfirst($driverType));
            }

            if (!class_exists($driverclass)) {
                throw new \Exception(
                    "Driver ({$driverType}) could not be instantiated."
                );
            }

            return $this->version == 2 ? new $driverclass
                : new \Intervention\Image\ImageManager(new $driverclass);
        }

        throw new \Exception(
            "no driver(imagick/gd) available"
        );
    }

    /**
     * 将相对路径转为站点内绝对路径（已是绝对路径则原样返回）
     *
     * @param string $path
     * @return string
     */
    public function checkFile($path)
    {
        //本站绝对路径
        if (stripos($path, App::getRootPath()) !== false) {
            return $path;
        }

        return App::getPublicPath() . ltrim($path, '.');
    }

    /**
     * Checks if Gd module installation is available
     *
     * @return boolean
     */
    public function gdAvailable()
    {
        return extension_loaded('gd') && function_exists('gd_info');
    }

    /**
     * Checks if Imagic module installation is available
     *
     * @return boolean
     */
    public function imagickAvailable()
    {
        return extension_loaded('imagick') && class_exists('Imagick');
    }
}
