<?php

namespace tpext\builder\form;

use tpext\builder\displayer;
use tpext\builder\common\Form;
use tpext\builder\common\Search;

class When
{
    /**
     * 被监听的字段
     *
     * @var displayer\Field
     */
    protected $watchFor = null;

    /**
     * 监听匹配的值列表
     *
     * @var array
     */
    protected $cases = '';

    /**
     * 匹配时显示的字段
     *
     * @var array
     */
    protected $fields = [];

    /**
     * 
     * @var bool|null
     */
    protected $matchCase = null;

    /**
     * 所属表单/搜索对象
     *
     * @var Form|Search
     */
    protected $form;

    /**
     * 监听指定字段，设置要匹配的值
     *
     * @param displayer\Field $watchFor
     * @param string|int|array $cases
     * @return $this
     */
    public function watch($watchFor, $cases)
    {
        $this->watchFor = $watchFor;
        if (!is_array($cases)) {
            $cases = [(string)$cases];
        }
        $this->cases = $cases;
        //
        return $this;
    }

    /**
     * 判断是否匹配
     * @return bool
     */
    public function judgeMatchCase()
    {
        if (!is_null($this->matchCase)) {
            return $this->matchCase;
        }

        $watchForValue = $this->watchFor->renderValue();

        $matchCase = false;

        if ($this->watchFor instanceof displayer\Checkbox || $this->watchFor instanceof displayer\Transfer || $this->watchFor instanceof displayer\MultipleSelect) {

            $watchForValueArr = explode(',', trim($watchForValue, ','));

            foreach ($this->cases as $cs) {

                $csArr = explode('+', $cs);

                if (count($watchForValueArr) !== count($csArr)) {
                    continue;
                }

                $m = 0;

                foreach ($csArr as $ca) {

                    if (in_array(trim($ca), $watchForValueArr)) {
                        $m += 1;
                    }
                }

                if ($m > 0 && $m == count($watchForValueArr)) {
                    $matchCase = true;
                    break;
                }
            }
        } else // Radio / Select
        {
            $matchCase = in_array($watchForValue, $this->cases);
        }

        $this->matchCase = $matchCase;

        return $this->matchCase;
    }

    /**
     * 设置匹配时显示/不匹配时隐藏的字段
     *
     * @param displayer\Field $field
     * @return $this
     */
    public function toggle($field)
    {
        $that = $this;
        //防止不同case中有重复字段的一些问题，因为trigger('change')调用时机，js处理重name/id有局限。
        $key = preg_replace('/[^\w\-]/', '-', $that->watchFor->getName()) . md5(json_encode($that->cases));
        $this->watchFor->rendering(function () use ($field, $that, $key) {
            $matchCase = $that->judgeMatchCase();
            // rendering 回调在一次渲染中可能被多条链路触发（beforRender 会被 Form/Row/Builder
            // 重复调用），第二次进来 getName() 已带 extNameKey 后缀，addAttr 会拼出重复的
            // data-name（真名 + 怪名）—— 已加过本 key 的后缀就只刷新显隐类，不再动 name/id。
            $applied = ('_' . $key) === ($field instanceof displayer\Fields ? '' : $field->getExtNameKey());
            if ($field instanceof displayer\Fields) {
                $field->extKey('-watch-' . $key) //防止id重复
                    ->getWrapper()->addClass($matchCase ? 'match-case' : 'hidden');
                $rows = $field->getContent()->getRows();
                $subFields = null;
                foreach ($rows as $row) {
                    $subFields = $row->getDisplayer();
                    if ($subFields instanceof displayer\Fields) {
                        continue;
                    }
                    if (('_' . $key) === $subFields->getExtNameKey()) {
                        continue;
                    }
                    $subFields->extKey('-watch-' . $key) //防止id重复
                        ->addAttr('data-name="' . $subFields->getName() . ($subFields->isArrayValue() ? '[]' : '') . '"')
                        ->extNameKey('_' . $key); //防止name重复。真实name放在[data-name]中，case选中时替换到name属性中
                }
            } else {
                if (!$applied) {
                    $field->extKey('-watch-' . $key) //防止id重复
                        ->addAttr('data-name="' . $field->getName() . ($field->isArrayValue() ? '[]' : '') . '"')
                        ->extNameKey('_' . $key); //防止name重复。真实name放在[data-name]中，case选中时替换到name属性中
                }
                $field->getWrapper()->addClass($matchCase ? 'match-case' : 'hidden');
            }
        });

        $this->fields[] = $field;
        //
        return $this;
    }

    /**
     * 设置所属表单/搜索对象
     *
     * @param Form|Search $val
     * @return $this
     */
    public function setForm($val)
    {
        $this->form = $val;
        return $this;
    }

    /**
     * 获取所属表单/搜索对象
     *
     * @return Form|Search
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * 获取监听匹配的值列表
     *
     * @return array
     */
    public function getCases()
    {
        return $this->cases;
    }

    /**
     * 为受控字段的包裹元素添加类名
     *
     * @param string $key
     * @return $this
     */
    public function setWrapperClass($key)
    {
        foreach ($this->fields as $field) {

            $field->getWrapper()->addClass($key);
        }

        return $this;
    }
}
