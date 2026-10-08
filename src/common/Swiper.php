<?php

namespace tpext\builder\common;

use tpext\builder\inface\Renderable;
use tpext\builder\inface\ReleaseAble;
use tpext\builder\traits\HasDestroyOnce;
use tpext\builder\traits\HasDom;
use tpext\think\View;

/**
 * 轮播图组件
 *
 * 6.262 新 UI 适配：原为旧库「纯复制」（#16），渲染输出是 Bootstrap 3 carousel 结构
 * （data-ride/item/carousel-control/icon-left-open-big），新库无 bootstrap JS、无该
 * CSS、无旧主题图标字体，轮播实际不可用。现改为 daisyUI 4 原生 carousel：
 * 锚点式（#id 锚点滚动）纯 CSS 实现，无任何 JS 依赖；指示点可点击、前后按钮循环跳页。
 * 差异（vs 旧库）：无自动轮播（bootstrap data-ride 特性，新库未带该 JS，本就失效）；
 * 旧版固定 id=carouselExampleIndicators 同页多实例会冲突，现每实例唯一 id。
 */
class Swiper extends Widget implements Renderable, ReleaseAble
{
    use HasDom;
    use HasDestroyOnce;

    /**
     * 渲染载体（模板 View）
     *
     * @var View
     */
    protected $content;

    protected $partial = false;

    /**
     * 实例标识，作为每张幻灯片锚点 id 前缀；未指定时自动生成（6.262）
     * @var string
     */
    protected $id = '';

    /**
     * 轮播高度：数值按 px 处理，也可传字符串（'50vh' 等）；默认 380 与旧版写死值一致
     * @var int|string
     */
    protected $height = 380;

    /**
     * 归一化后的图片列表 [['title' => .., 'image' => ..], ...]
     * @var array
     */
    protected $items = [];

    /**
     * 局部渲染模式：render() 返回 View 对象而非 HTML 串
     *
     * @param boolean $val
     * @return $this
     */
    public function partial($val = true)
    {
        $this->partial = $val;
        return $this;
    }

    /**
     * 设置实例 id（锚点前缀）
     *
     * @param string $val
     * @return $this
     */
    public function id($val)
    {
        $this->id = (string)$val;
        return $this;
    }

    /**
     * 获取实例id（无则生成）
     *
     * @return string
     */
    public function getId()
    {
        if (empty($this->id)) {
            $this->id = 'tpb-swiper-' . uniqid();
        }

        return $this->id;
    }

    /**
     * 设置轮播高度（6.262，新增链式方法；数值=px，字符串原样）
     *
     * @param int|string $val
     * @return $this
     */
    public function height($val)
    {
        $this->height = $val;
        return $this;
    }

    /**
     * 图片列表（与旧库同签名同用法）
     *
     * @param array $arr [['title'=>title1,'image'=>image1],['title'=>title2,'image'=>image2],...] or [image1, image2,...]
     * @return $this
     */
    public function images($arr)
    {
        $list = [];
        foreach ($arr as $k => $v) {
            if (isset($v['image'])) {
                $list[] = ['title' => $v['title'] ?? $k, 'image' => $v['image']];
            } else {
                $list[] = ['title' => $k, 'image' => $v];
            }
        }

        $this->items = $list;

        return $this;
    }

    /**
     * 渲染前的准备
     *
     * @return $this
     */
    public function beforRender()
    {
        return $this;
    }

    /**
     * 组装幻灯片锚点数据：模板保持纯 HTML（COMPONENT_SPEC §2）
     *
     * @return array
     */
    protected function slides()
    {
        $count = count($this->items);
        $uid = $this->getId();
        $slides = [];
        foreach ($this->items as $i => $item) {
            $no = $i + 1;
            if ($count <= 1) {
                $prevId = $nextId = $uid . '-' . $no;
            } else {
                $prevId = $uid . '-' . ((($i - 1 + $count) % $count) + 1);
                $nextId = $uid . '-' . ((($i + 1) % $count) + 1);
            }

            $slides[] = [
                'id' => $uid . '-' . $no,
                'prev' => $prevId,
                'next' => $nextId,
                'image' => $item['image'],
                'title' => (string)$item['title'],
            ];
        }

        return $slides;
    }

    /**
     * 渲染为HTML（partial时返回View对象）
     *
     * @return string|View
     */
    public function render()
    {
        $template = Module::getInstance()->getViewsPath() . 'swiper.html';

        $height = is_numeric($this->height) ? intval($this->height) . 'px' : (string)$this->height;

        $viewshow = new View($template);

        $vars = [
            'slides' => $this->slides(),
            'slide_count' => count($this->items),
            'height' => $height,
            'class' => $this->class,
            'attr' => $this->getAttrWithStyle(),
        ];

        $this->content = $viewshow->assign($vars);

        if ($this->partial) {
            return $this->content;
        }

        return $this->content->getContent();
    }

    /**
     * 转为字符串时返回渲染后的HTML
     *
     * @return string
     */
    public function __toString()
    {
        $this->partial = false;
        return $this->render();
    }

    /**
     * 释放资源
     *
     * @return void
     */
    public function destroy()
    {
        // 6.261 已销毁直接返回：同一组件可被多归属路径重复触达（契约见 traits\HasDestroyOnce）
        if ($this->__destroyed__) {
            return;
        }

        // 6.262 items 是数组属性，复位 []（6.260 规则）；content 是 think\View 对象引用置 null
        $this->items = [];
        $this->content = null;
        $this->__destroyed__ = true;
    }
}
