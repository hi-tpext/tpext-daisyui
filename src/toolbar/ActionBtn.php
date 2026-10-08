<?php

namespace tpext\builder\toolbar;

use think\Model;
use tpext\builder\common\Builder;

class ActionBtn extends Bar
{
    protected $view = 'actionbtn';

    protected $mapClass = [];

    protected $postRowid = '';

    protected $extClass = '';

    protected $data = [];

    protected $dataId = 0;

    protected $confirm = true;

    protected $initPostRowidScript = false;

    /**
     * 解析链接中的行数据占位符（如 __data.pk__）
     *
     * @param array|Model|\ArrayAccess $data
     * @return $this
     */
    public function parseUrl($data = [])
    {
        if ($data) {
            $this->data = $data;
        } else {
            $data = $this->data;
        }

        if (empty($this->href) || empty($data)) {
            return $this;
        }

        preg_match_all('/__data\.([\w\.]+)__/', $this->href, $matches);

        $keys = ['__data.pk__'];
        $replace = [$this->dataId];
        $arr = [];

        if (isset($matches[1]) && count($matches[1]) > 0) {
            foreach ($matches[1] as $match) {
                $arr = explode('.', $match);

                if (count($arr) == 1) {

                    $keys[] = '__data.' . $arr[0] . '__';
                    $replace[] = isset($data[$arr[0]]) ? $data[$arr[0]] : '';
                } else if (count($arr) == 2) {

                    $keys[] = '__data.' . $arr[0] . '.' . $arr[1] . '__';
                    $replace[] = isset($data[$arr[0]]) && isset($data[$arr[0]][$arr[1]]) ? $data[$arr[0]][$arr[1]] : '';
                } else {
                    //最多支持两层 xx 或 xx.yy
                }
            }

            $this->__href__ = str_replace($keys, $replace, $this->href);
        } else {
            $this->__href__ = $this->href;
        }

        return $this;
    }

    /**
     * 解析标签文字中的行数据占位符（如 {id}）
     *
     * @return string
     */
    protected function parseLabel()
    {
        $data = $this->data;

        $label = $this->label;

        if ($label instanceof \Closure) {
            return $label($data);
        }

        if (empty($label)) {
            return '';
        }
        if (empty($data)) {
            return $label;
        }

        preg_match_all('/\{([\w\.]+)\}/', $label, $matches);

        $keys = [];
        $replace = [];
        $arr = [];

        if (isset($matches[1]) && count($matches[1]) > 0) {
            foreach ($matches[1] as $match) {
                $arr = explode('.', $match);

                if (count($arr) == 1) {

                    $keys[] = '{' . $arr[0] . '}';
                    $replace[] = isset($data[$arr[0]]) ? $data[$arr[0]] : '';
                } else if (count($arr) == 2) {

                    $keys[] = '{' . $arr[0] . '.' . $arr[1] . '}';
                    $replace[] = isset($data[$arr[0]]) && isset($data[$arr[0]][$arr[1]]) ? $data[$arr[0]][$arr[1]] : '-';
                } else {
                    //最多支持两层 xx 或 xx.yy
                }
            }

            $val = str_replace($keys, $replace, $label);
        } else {
            $val = $label;
        }

        return $val;
    }

    /**
     * 按行数据解析条件类名映射并附加到按钮
     *
     * @param array $data
     * @return $this
     */
    public function parseMapClass($data)
    {
        if (empty($data)) {
            return $this;
        }
        $matchClass = [];

        $values = $class = $field = $logic = $val = $match = null;

        $this->extClass = '';
        foreach ($this->mapClass as $class => $mp) {
            if (is_array($mp)) { // 'enable' => ['hidden' => [1, 'status']],
                $values = $mp[0];

                if (!is_array($values)) {
                    $values = [$values];
                }

                $field = $mp[1];
                $logic = $mp[2] ?? ''; //in_array|not_in_array|eq|gt|lt|egt|elt|strstr|not_strstr

                if (strstr($field, '.')) {

                    $arr = explode('.', $field);

                    if (isset($data[$arr[0]]) && isset($data[$arr[0]][$arr[1]])) {

                        $val = $data[$arr[0]][$arr[1]];
                    } else {
                        continue;
                    }
                } else {

                    if (!isset($data[$field])) {
                        continue;
                    }

                    $val = $data[$field];
                }

                $match = false;
                if ($logic == 'not_in_array') {
                    $match = !in_array($val, $values);
                } else if ($logic == 'eq' || $logic == '==') {
                    $match = $val == $values[0];
                } else if ($logic == 'gt' || $logic == '>') {
                    $match = is_numeric($values[0]) && $val > $values[0];
                } else if ($logic == 'lt' || $logic == '<') {
                    $match = is_numeric($values[0]) && $val < $values[0];
                } else if ($logic == 'egt' || $logic == '>=') {
                    $match = is_numeric($values[0]) && $val >= $values[0];
                } else if ($logic == 'elt' || $logic == '<=') {
                    $match = is_numeric($values[0]) && $val <= $values[0];
                } else if ($logic == 'strpos' || $logic == 'strstr') {
                    $match = strstr($val, $values[0]);
                } else if ($logic == 'not_strpos' || $logic == 'not_strstr' || $logic == '!strpos' || $logic == '!strstr') {
                    $match = !strstr($val, $values[0]);
                } else //default in_array
                {
                    $match = in_array($val, $values);
                }
                if ($match) {
                    $matchClass[] = $class;
                }
            } else if ($mp instanceof \Closure) {
                // 'delete' => ['hidden' => function ($data) {
                //     return $data['pay_status'] >1;
                // }],
                $match = $mp($data);
                if ($match) {
                    $matchClass[] = $class;
                }
            } else { // 'enable' => ['hidden' => '__hi_en__'],
                if (isset($data[$mp]) && $data[$mp]) {
                    $matchClass[] = $class;
                }
            }
        }

        if (count($matchClass)) {
            $this->extClass .= ' ' . implode(' ', array_unique($matchClass));
        }

        return $this;
    }

    /**
     * 设置类名映射配置
     *
     * @param array $mapData
     * @return $this
     */
    public function mapClass($mapData)
    {
        if (!empty($mapData) && isset($mapData[$this->name])) {
            $this->mapClass = $mapData[$this->name];
        }

        return $this;
    }

    /**
     * 设置行数据ID
     *
     * @param string|int $val
     * @return $this
     */
    public function dataId($val)
    {
        $this->dataId = $val;
        return $this;
    }

    /**
     * 设置为按行提交POST请求的按钮
     *
     * @param string $url
     * @param boolean|string $confirm
     * @return $this
     */
    public function postRowid($url, $confirm = true)
    {
        $this->postRowid = (string)$url;
        $this->confirm = $confirm ? $confirm : 0;

        return $this;
    }

    /**
     * 生成按行提交POST请求的脚本
     *
     * @return string
     */
    protected function postRowidScript()
    {
        if ($this->initPostRowidScript) {
            return '';
        }
        $script = '';
        $class = 'action-' . $this->name;

        $script = <<<EOT

        tpextbuilder.postRowid('{$class}', '{$this->postRowid}', '{$this->confirm}');

EOT;
        $this->script[] = $script;

        $this->initPostRowidScript = true;

        return $script;
    }

    /**
     * 渲染前处理，注册提交脚本并解析条件类名映射
     *
     * @return $this
     */
    public function beforRender()
    {
        if ($this->postRowid) {

            if (Builder::checkUrl($this->postRowid)) {
                $this->postRowidScript();
            } else {
                $this->addClass('hidden disabled');
            }
        }

        $this->parseMapClass($this->data);

        return parent::beforRender();
    }

    /**
     * 渲染操作按钮
     *
     * @return mixed
     */
    public function render()
    {
        $vars = $this->commonVars();

        $label = $this->parseLabel();

        $class = $vars['class'] . $this->extClass;

        if ('' === (string)$label) {
            // 6.236 纯图标按钮（无文字）：加标识类，CSS 收成正方形（builder-core-layout.css）。
            // 模板里 label 是裸文本节点，CSS :has 无法区分纯图标与图标+文字，只能在 php 侧标记
            $class .= ' btn-icon-only';
        }

        $vars = array_merge($vars, [
            'class' => $class,
            'dataId' => $this->dataId,
            'label' => $label
        ]);

        $viewshow = $this->getViewInstance();

        return $viewshow->assign($vars)->getContent();
    }
}
