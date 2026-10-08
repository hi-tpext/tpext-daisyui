<?php

namespace tpext\builder\traits;

use tpext\builder\inface\Storage;
use tpext\builder\common\Module;

trait HasStorageDriver
{
    /**
     * 上传驱动类名
     *
     * @var string
     */
    protected $storageDriver;

    protected $isRandName = '';

    /**
     * 设置文件上传驱动
     *
     * @param string $driverClass 驱动的类名，如：\tpext\builder\logic\LocalStorage::class
     * @return $this
     */
    public function storageDriver($driverClass = '')
    {
        if (!is_string($driverClass) && ($driverClass instanceof Storage)) {
            $driverClass = get_class($driverClass);
        }

        $this->storageDriver = $driverClass;

        return $this;
    }

    /**
     * 设置上传文件是否使用随机文件名
     *
     * @param boolean $val
     * @return $this
     */
    public function randName($val = true)
    {
        $this->isRandName = $val ? 'y' : 'n';

        return $this;
    }

    /**
     * 获取是否使用随机文件名
     *
     * @return string 'y' or 'n'
     */
    public function isRandName()
    {
        return $this->isRandName;
    }

    /**
     * 获取上传驱动的短类名（反斜杠替换为-）
     *
     * @return string
     */
    public function getStorageDriver()
    {
        if (empty($this->storageDriver)) {
            return '';
        }

        return str_replace('\\', '-', $this->storageDriver);
    }

    /**
     * 获取上传url路径
     * 默认：'/admin/upload/upfiles'
     * @return string
     */
    public function getUploadUrl()
    {
        return Module::getInstance()->getUploadUrl();
    }
}
