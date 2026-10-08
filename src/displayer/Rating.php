<?php

namespace tpext\builder\displayer;

/**
 * 评分组件（新增组件，原库无对应）。
 *
 * 基于 DaisyUI 4.x rating：编辑态为一组原生 radio input，随表单天然提交，无需任何 JS 初始化脚本；
 * 只读 / disabled / 表格列场景输出静态 mask 星形展示。
 *
 * 用法：
 * $form->rating('score')->max(5)->clearable(true);
 * $form->rating('score')->half(true)->heart(true)->color('bg-orange-400');
 */
class Rating extends Field
{
    protected $view = 'rating';
    protected $cssFamily = 'widgets'; // 6.338 只读半星遮罩 .rating-half-clip 在 builder-widgets.css

    /**
     * 星星数量
     *
     * @var integer
     */
    protected $max = 5;

    /**
     * 是否允许半星（提交值为 0.5 步进）
     *
     * @var boolean
     */
    protected $half = false;

    /**
     * 是否心形（mask-heart）
     *
     * @var boolean
     */
    protected $heart = false;

    /**
     * 是否可清零（rating-hidden，提交值为 0）
     *
     * @var boolean
     */
    protected $clearable = false;

    /**
     * 选中星的颜色 class（DaisyUI/Tailwind 背景色类）
     *
     * @var string
     */
    protected $color = 'bg-warning';

    /**
     * 在表格单元格内使用时输出只读展示
     *
     * @var boolean
     */
    protected $isInTable = false;

    /**
     * 星星数量（1-20）
     *
     * @param integer $val
     * @return $this
     */
    public function max($val = 5)
    {
        $val = intval($val);
        if ($val < 1) {
            $val = 1;
        } else if ($val > 20) {
            $val = 20;
        }
        $this->max = $val;
        return $this;
    }

    /**
     * 是否允许半星
     *
     * @param boolean $val
     * @return $this
     */
    public function half($val = true)
    {
        $this->half = $val ? true : false;
        return $this;
    }

    /**
     * 是否心形
     *
     * @param boolean $val
     * @return $this
     */
    public function heart($val = true)
    {
        $this->heart = $val ? true : false;
        return $this;
    }

    /**
     * 是否可清零
     *
     * @param boolean $val
     * @return $this
     */
    public function clearable($val = true)
    {
        $this->clearable = $val ? true : false;
        return $this;
    }

    /**
     * 选中星的颜色 class，如 bg-warning、bg-orange-400
     *
     * @param string $val
     * @return $this
     */
    public function color($val = 'bg-warning')
    {
        $this->color = $val;
        return $this;
    }

    /**
     * 设置为表格单元格内使用（输出只读展示）
     *
     * @param boolean $val
     * @return $this
     */
    public function setIsInTable($val = true)
    {
        $this->isInTable = $val ? true : false;
        return $this;
    }

    /**
     * 渲染为HTML
     *
     * @return mixed
     */
    public function render()
    {
        $vars = $this->commonVars();

        $value = (string) ($vars['value'] === '' || $vars['value'] === null ? 0 : $vars['value']);

        $step = $this->half ? 0.5 : 1;

        $starValue = (float) $value;

        if ($starValue > $this->max) {
            $starValue = $this->max;
        }

        $fullStars = (int) floor($starValue + 0.0001);
        $hasHalf = $this->half && ($starValue - $fullStars) >= 0.4999;

        // 编辑态选项：half 时每颗星拆为左半(mask-half-1)/右半(mask-half-2)，值 0.5 步进
        $options = [];
        $seq = 0;
        for ($i = $step; $i <= $this->max + 0.0001; $i += $step) {
            $seq++;
            $options[] = [
                'value' => rtrim(rtrim(sprintf('%.1f', $i), '0'), '.'),
                'halfClass' => $this->half ? ($seq % 2 == 1 ? 'mask-half-1' : 'mask-half-2') : '',
                'checked' => $i == $starValue ? 'checked' : '',
            ];
        }

        // 只读展示：满星/半星/空星序列
        $stars = [];
        for ($i = 1; $i <= $this->max; $i++) {
            if ($i <= $fullStars) {
                $stars[] = ['type' => 'full'];
            } else if ($hasHalf && $i == $fullStars + 1) {
                $stars[] = ['type' => 'half'];
            } else {
                $stars[] = ['type' => 'empty'];
            }
        }

        $vars = array_merge($vars, [
            'max' => $this->max,
            'half' => $this->half ? 'rating-half' : '',
            'shape' => $this->heart ? 'mask-heart' : 'mask-star-2',
            'color' => $this->color,
            'emptyColor' => 'bg-base-300',
            'clearable' => $this->clearable,
            'clearChecked' => ((float) $value == 0) ? 'checked' : '',
            'options' => $options,
            'stars' => $stars,
            'value' => $value,
            'isInTable' => $this->isInTable,
        ]);

        $viewshow = $this->getViewInstance();

        return $viewshow->assign($vars)->getContent();
    }
}