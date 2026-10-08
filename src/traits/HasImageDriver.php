<?php

namespace tpext\builder\traits;

use tpext\builder\inface\Image;

trait HasImageDriver
{
    /**
     * 图片处理驱动类名
     *
     * @var string
     */
    protected $imageDriver;

    protected $imageCommands = [];

    /**
     * 设置图片处理驱动
     *
     * @param string $driverClass 驱动的类名，如：\tpext\builder\logic\Image::class
     * @return $this
     */
    public function imageDriver($driverClass = '')
    {
        if (!is_string($driverClass) && ($driverClass instanceof Image)) {
            $driverClass = get_class($driverClass);
        }

        $this->imageDriver = $driverClass;

        return $this;
    }

    /**
     * 获取图片处理驱动的短类名
     *
     * @return string
     */
    public function getImageDriver()
    {
        if (empty($this->imageDriver)) {
            return '';
        }

        return str_replace('\\', '-', $this->storageDriver);
    }

    /**
     * 缩放图片
     *
     * @param integer $width　目标宽度
     * @param integer $height　目标高度
     * @param boolean $aspectRatio　等比例缩放，若为否则图片严格压缩到目标大小(可能变形)
     * @param boolean $upsize　两边都未超过设定值时，不放大图片
     * @return $this
     */
    public function imageResize($width = 0,  $height = 0, $aspectRatio = true, $upsize = true)
    {
        if (empty($width) && empty($height)) {
            return $this;
        }

        $this->imageCommands[] = [
            'name' => 'resize',
            'args' => [
                'width' => $width,
                'height' => $height,
                'aspectRatio' => $aspectRatio,
                'upsize' => $upsize,
            ],
        ];

        return $this;
    }

    /**
     * 文字
     *
     * @param string $text　给图片加上文字
     * @param integer $x　水平起始
     * @param integer $y　垂直起始
     * @param integer $fontSize　文字大小
     * @param integer $color　颜色
     * @param string $valign　垂直对齐方式 [top,bottom,middle,center]
     * @param string $align　水平对齐方式 [left,right,center]
     * @param string $fontFile　字体文件路径
     * @param string $angle　旋转角度
     * @param integer $kerning　字间距(gd不支持)
     *
     * @return $this
     */
    public function imageText($text,  $x = 0,  $y = 0, $fontSize = 12, $color = '#000000', $valign = '', $align = '', $fontFile = null, $angle = 0, $kerning = 0)
    {
        $this->imageCommands[] = [
            'name' => 'text',
            'args' => [
                'text' => urlencode($text),
                'x' => $x,
                'y' => $y,
                'fontSize' => $fontSize,
                'color' => $color,
                'angle' => $angle,
                'align' => $align,
                'valign' => $valign,
                'kerning' => $kerning,
                'fontFile' => $fontFile,
            ],
        ];

        return $this;
    }

    /**
     * 裁剪图片
     *
     * @param integer $width　宽度
     * @param integer $height　高度
     * @param integer $x　水平起始左边
     * @param integer $y　垂直起始左边
     * @return $this
     */
    public function imageCrop($width,  $height,  $x = 0,  $y = 0)
    {
        $this->imageCommands[] = [
            'name' => 'crop',
            'args' => [
                'width' => $width,
                'height' => $height,
                'x' => $x,
                'y' => $y,
            ],
        ];

        return $this;
    }

    /**
     * 图片水印
     *
     * @param string $imgPath 水印图片路径(绝对路径或相对路径)
     * @param string $position　位置[top-left(左上) top(中上) top-right(右上) left(左中) center(居中) right(右中) bottom-left(左下) bottom(中下) bottom-right(右下)]
     * @param integer $x 水平偏移
     * @param integer $y　垂直偏移
     * @return $this
     */
    public function imageWater($imgPath,  $position = 'bottom-right',  $x = 0,  $y = 0)
    {
        $this->imageCommands[] = [
            'name' => 'water',
            'args' => [
                'imgPath' => $imgPath,
                'position' => $position,
                'x' => $x,
                'y' => $y,
            ],
        ];

        return $this;
    }

    /**
     * 取消某个全局配置项(图片水印|上传图片大小限制)
     *
     * @param string|array $name image_water|image_size_limit|*
     * @example location clearImageGlobalConfig('image_size_limit') 清空全局图片限制设置
     * @example location clearImageGlobalConfig('*')　清空所有全局设置
     * @return $this
     */
    public function clearImageGlobalConfig($name = '*')
    {
        if ($name == '*') {
            $name = ['image_water', 'image_size_limit'];
        } else {
            if (!is_array($name)) {
                $name = explode(',', $name);
            }
        }

        foreach ($name as $nm) {

            $this->imageCommands[] = [
                'name' => 'clear_global_config',
                'args' => [
                    'name' => $nm,
                ],
            ];
        }

        return $this;
    }

    /**
     * 获取图片命令base46字符串
     * @param boolean $str
     * @return string|array
     */
    public function getImageCommands($str = true)
    {
        if (!$str) {
            return $this->imageCommands;
        }

        if (empty($this->imageCommands)) {
            return '';
        }

        return str_replace('=', '', base64_encode(json_encode($this->imageCommands)));
    }

    /**
     * 添加图片命令 (可以添加一些其他的自定义图片命令，然后自定义图片驱动`->imageDriver($driverClass)`去实现它)
     *
     * @param string $name
     * @param array $args
     * @return $this
     */
    public function addImageCommands($name, $args)
    {
        $this->imageCommands[] = [
            'name' => $name,
            'args' => $args,
        ];

        return $this;
    }
}
