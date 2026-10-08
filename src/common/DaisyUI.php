<?php

namespace tpext\builder\common;

use tpext\common\ExtLoader;
use tpext\common\Module as baseModule;

class DaisyUI extends baseModule
{
    protected $version = '1.0.0';

    protected $name = 'tpext.daisyui';

    protected $title = 'tpext DaisyUI 生成';

    protected $description = '后台ui生成(DaisyUI+Tailwind+Alpine)';

    protected $root = __DIR__ . '/../../';

    protected $assets = 'assets';

    protected $modules = [
        'admin' => ['upload', 'import', 'attachment'],
        'index' => ['file'],
    ];

    /**
     * 版本列表
     *
     * @var array
     */
    protected $versions = [
        '1.0.0' => '',
    ];

    /**扩展基本信息完**/

    //所有视图的基础路径
    protected $viewsPath = '';

    /**
     * 通用文件上传url
     *
     * @var string
     */
    protected $uploadUrl = '';

    /**
     * 导入页面url
     *
     * @var string
     */
    protected $importUrl = '';

    /**
     * 文件上传：选择文件列表页url
     *
     * @var string
     */
    protected $chooseUrl = '';

    /**
     * 存储驱动类列表
     *
     * @var array
     */
    protected $storageDrivers = [\tpext\builder\logic\LocalStorage::class => '本地'];

    /**
     * 设置视图路径
     *
     * @param string $newPath
     * @return $this
     */
    public function setViewsPath($newPath)
    {
        $this->viewsPath = $newPath;

        return $this;
    }

    /**
     * 设置上传url
     *
     * @param string $newUrl
     * @return $this
     */
    public function setUploadUrl($newUrl)
    {
        $this->uploadUrl = (string) $newUrl;

        return $this;
    }

    /**
     * 设置导入url
     *
     * @param string $newUrl
     * @return $this
     */
    public function setImportUrl($newUrl)
    {
        $this->importUrl = (string) $newUrl;

        return $this;
    }

    /**
     * 设置选择文件url
     *
     * @param string $newUrl
     * @return $this
     */
    public function setChooseUrl($newUrl)
    {
        $this->chooseUrl = (string) $newUrl;

        return $this;
    }

    /**
     * 添加存储驱动
     *
     * @param string $class 驱动类名
     * @param string $title 驱动名称
     * @return $this
     */
    public function addStorageDriver($class, $title)
    {
        $this->storageDrivers[$class] = $title;

        return $this;
    }

    /**
     * 获取视图路径
     *
     * @return string
     */
    public function getViewsPath()
    {
        ExtLoader::trigger('tpext_builder_get_views_path');

        if (empty($this->viewsPath)) {
            $this->viewsPath = $this->getRoot() . implode(DIRECTORY_SEPARATOR, ['src', 'view', '']);
        }

        return $this->viewsPath;
    }

    /**
     * 获取上传url
     *
     * @return string
     */
    public function getUploadUrl()
    {
        ExtLoader::trigger('tpext_builder_get_upload_url');
        return $this->uploadUrl ?: '/admin/upload/upfiles';
    }

    /**
     * 获取导入url
     *
     * @return string
     */
    public function getImportUrl()
    {
        ExtLoader::trigger('tpext_builder_get_import_url');
        return $this->importUrl ?: '/admin/import/page';
    }

    /**
     * 获取选择文件url
     *
     * @return string
     */
    public function getChooseUrl()
    {
        ExtLoader::trigger('tpext_builder_get_choose_url');
        return $this->chooseUrl ?: '/admin/attachment/index';
    }

    /**
     * 获取存储驱动列表
     *
     * @return array
     */
    public function getStorageDrivers()
    {
        ExtLoader::trigger('tpext_builder_find_storage_drivers');
        return $this->storageDrivers;
    }

    /**
     * 扩展加载完成后的处理：加载语言包
     *
     * @return void
     */
    public function loaded()
    {
        $this->loadLang('common');
    }
}
