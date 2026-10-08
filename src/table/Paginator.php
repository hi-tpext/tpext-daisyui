<?php

namespace tpext\builder\table;

use think\Collection;
use think\paginator\driver\Bootstrap;
use tpext\builder\traits\HasDom;

class Paginator extends Bootstrap
{
    use HasDom;

    protected $paginatorClass = 'pagination-sm';

    protected $summary = true;

    /**
     * 合并分页配置选项
     *
     * @param array $val
     * @return $this
     */
    public function options($val)
    {
        $this->options = array_merge($this->options, $val);
        $this->reset();
        return $this;
    }

    /**
     * 重新计算分页信息
     *
     * @return void
     */
    public function reset()
    {
        $this->lastPage = (int) ceil($this->total / $this->listRows);
        $this->currentPage = $this->setCurrentPage($this->currentPage);
        $this->hasMore = $this->currentPage < $this->lastPage;
    }

    /**
     * 设置数据集
     *
     * @param array|Collection $items
     * @return $this
     */
    public function setItems($items)
    {
        if (!($items instanceof Collection)) {
            $items = Collection::make($items);
        }

        $this->items = $items;
        $this->reset();
        return $this;
    }

    /**
     * 设置是否显示数据摘要
     *
     * @param boolean $val
     * @return $this
     */
    public function summary($val)
    {
        $this->summary = $val;
        return $this;
    }

    /**
     * 设置分页样式类名
     *
     * @param string $val
     * @return $this
     */
    public function paginatorClass($val)
    {
        $this->paginatorClass = $val;
        return $this;
    }

    /**
     * 设置总记录数
     *
     * @param int $val
     * @return $this
     */
    public function setTotal($val)
    {
        $this->total = $val;
        $this->reset();
        return $this;
    }

    /**
     * 获取样式类名
     *
     * @return string
     */
    public function getClass()
    {
        if (!$this->class) {
            $this->class = 'text-center';
        }

        return $this->class;
    }

    /**
     * 判断数据是否为空
     *
     * @return boolean
     */
    public function isEmpty(): bool
    {
        if ($this->currentPage > 1) {
            return false;
        }
        return parent::isEmpty();
    }

    /**
     * 渲染分页条
     *
     * @return mixed
     */
    public function render()
    {
        if (!$this->total) {
            return '';
        }

        $html = (string) parent::render();

        if ($this->paginatorClass) {
            $html = preg_replace('/(.+)(pagination)(.+)/i', '$1$2 ' . $this->paginatorClass . '$3', $html);
        }

        if ($this->summary) {
            $a = ($this->currentPage - 1) * $this->listRows + 1;
            $b = $a - 1 + $this->items->count();
            if ($this->total != $this->items->count()) {
                $html = "<span class='pagination-summary'>" . __blang('builder_paginator_summary', ['total' => $this->total, 'from' => $a, 'to' => $b]) . "</span>" . $html;
                if ($this->lastPage > 10) {
                    $gotoPage = "<li><a data-last='{$this->lastPage}' class='goto-page'>&nbsp;&nbsp;" . __blang('builder_paginator_goto') . "&nbsp;&nbsp;</a></li>";
                    $html = preg_replace('/^(.+)<\/ul>$/', '$1' . $gotoPage . '</ul>', $html);
                }
            } else {
                $html = "<span class='pagination-summary'>" . __blang('builder_paginator_total', ['total' => $this->total]) . "</span>" . $html;
            }
        }

        return $html;
    }
}
