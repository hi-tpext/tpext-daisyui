<?php

namespace tpext\builder\common;

use tpext\common\ExtLoader;

/**
 * Widget — 组件工厂
 *
 * 维护 widget 名称到类名的映射，提供 makeWidget() 工厂方法。
 */
class Widget
{
    protected static $widgets = [];

    protected static $sizeAdapter = null;

    protected static $widgetsMap = [
        //widgets
        'Form' => \tpext\builder\common\Form::class,
        'Table' => \tpext\builder\common\Table::class,
        'Search' => \tpext\builder\common\Search::class,
        'Toolbar' => \tpext\builder\common\Toolbar::class,
        'Tree' => \tpext\builder\tree\Tree::class,
        'Content' => \tpext\builder\common\Content::class,
        'Tab' => \tpext\builder\common\Tab::class,
        'Swiper' => \tpext\builder\common\Swiper::class,
        'Row' => \tpext\builder\common\Row::class,
        'Column' => \tpext\builder\common\Column::class,
        //tools
        'SizeAdapter' => \tpext\builder\common\SizeAdapter::class,
        'Layer' => \tpext\builder\common\Layer::class,
        'FRow' => \tpext\builder\form\FRow::class,
        'SRow' => \tpext\builder\search\SRow::class,
        'TColumn' => \tpext\builder\table\TColumn::class,
        'Actionbar' => \tpext\builder\table\Actionbar::class,
        'MultipleToolbar' => \tpext\builder\table\MultipleToolbar::class,
    ];

    /**
     * 扩展 widget 映射
     *
     * @param array $pair
     * @return void
     */
    public static function extend($pair)
    {
        self::$widgetsMap = array_merge(self::$widgetsMap, $pair);
    }

    /**
     * 获取 widget 映射表
     *
     * @return array
     */
    public static function getWidgetsMap()
    {
        return self::$widgetsMap;
    }

    /**
     * 判断是否为 widget
     *
     * @param string $name
     * @return boolean
     */
    public static function isWidget($name)
    {
        if (empty(self::$widgets)) {
            self::$widgets = array_keys(self::$widgetsMap);
        }

        return in_array($name, self::$widgets);
    }

    /**
     * 获取 widget 类名
     *
     * @param string $name
     * @return string
     */
    public static function getWidgetClass($name)
    {
        return self::$widgetsMap[$name];
    }

    /**
     * 获取 SizeAdapter 单例
     *
     * @param mixed ...$arguments
     * @return \tpext\builder\common\SizeAdapter
     */
    public static function getSizeAdapter(...$arguments)
    {
        if (!self::$sizeAdapter) {
            self::$sizeAdapter = self::makeWidget('SizeAdapter', $arguments);
        }

        return self::$sizeAdapter;
    }

    /**
     * created 钩子
     *
     * @return $this
     */
    public function created()
    {
        return $this;
    }

    /**
     * 创建自身实例
     *
     * @param mixed $arguments
     * @return static
     */
    public static function make(...$arguments)
    {
        return self::makeWidget(class_basename(get_called_class()), $arguments);
    }

    /**
     * 工厂方法：根据名称创建 widget
     *
     * @param string $name
     * @param array|mixed $arguments
     * @return mixed
     */
    public static function makeWidget($name, $arguments = [])
    {
        if (!is_array($arguments)) {
            $arguments = [$arguments];
        }

        $widget = new self::$widgetsMap[$name](...$arguments);

        ExtLoader::trigger('tpext_widget_created', $widget);

        $widget->created();

        return $widget;
    }
}
