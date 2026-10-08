<?php

namespace tpext\builder\common;

/**
 * SizeAdapter — Tailwind CSS grid 版本
 *
 * 核心语义：传入的数字 = 中等屏幕（Tailwind md, ≥768px）的 col 占比
 * 对应 Bootstrap 3 的 col-md-*，是用户主战场（PC 端后台优先）。
 *
 * Tailwind 断点 ↔ Bootstrap 3 映射：
 *   默认 (<640px) → col-xs-*      手机独占（除非用户指定）
 *   sm (≥640px)  → 无对应（新库利用做过渡，比 md 疏朗一阶）
 *   md (≥768px)  → col-sm-*       主战场
 *   lg (≥1024px) → col-lg-*       大屏（和 md 同值，稳定不突变）
 *   xl (≥1280px) → 无对应（新库利用，和 lg 同值）
 *
 * 推导规则：
 *   default → 12（手机独占；如果用户只写了 col-xs-*，按 xs 值）
 *   sm      → 比 md 疏朗一个台阶（平板过渡）
 *   md      → 用户传入值（主战场）
 *   lg/xl   → 和 md 相同（保持稳定密度）
 *
 * 兼容的输入格式（三种都支持）：
 *   纯数字: 3
 *   旧 Bootstrap: '6 col-lg-6 col-sm-6 col-xs-6'
 *   Tailwind: '3 sm:col-span-6 lg:col-span-2'
 *   混合: '3 col-sm-8 lg:col-span-2'
 */
class SizeAdapter extends Widget
{
    protected static $instance = null;

    /**
     * 获取单例
     *
     * @param mixed $arguments
     * @return static
     */
    public static function make(...$arguments)
    {
        if (!static::$instance) {
            static::$instance = self::makeWidget('SizeAdapter', $arguments);
        }
        return static::$instance;
    }

    /**
     * 从 md 推导 sm：比 md 疏朗一个台阶（平板过渡）
     * 公式：sm = ceil(12 / max(1, ceil(12/md) - 1))
     * 即平板每行比 md 少放 1 个
     *
     * @param int $md
     * @return int
     */
    private function calcSmFromMd($md)
    {
        if ($md == 12) {
            return 12;
        }
        if ($md <= 2) {
            return 4; // 经验值兜底：md=2 平板 3 列，不直接独占
        }
        $mdPerRow = max(1, ceil(12 / $md));
        $smPerRow = max(1, $mdPerRow - 1);
        return (int)ceil(12 / $smPerRow);
    }

    /**
     * 解析 col size：纯数字、Tailwind 格式、旧 Bootstrap 格式
     * 返回完整 class 字符串
     *
     * @param string|int $size
     * @return string
     */
    private function parseSize($size)
    {
        $mdValue = null;
        $userClasses = [];
        $extra = '';

        if (is_numeric($size)) {
            $mdValue = intval($size);
        } elseif (is_string($size)) {
            $trimmed = trim($size);

            // 开头数字 = md 值
            if (preg_match('/^(\d{1,2})\b/', $trimmed, $mch)) {
                $mdValue = intval($mch[1]);
            }

            // Tailwind 格式: sm:col-span-6
            if (preg_match_all('/(?:(sm|md|lg|xl):)?col-span-(\d{1,2})/', $trimmed, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $userClasses[$m[1]] = intval($m[2]);
                }
            }

            // 旧 Bootstrap 格式: col-xs-6 / col-sm-6 / col-md-6 / col-lg-6
            if (preg_match_all('/\bcol-(xs|sm|md|lg)-(\d{1,2})\b/', $trimmed, $matches, PREG_SET_ORDER)) {
                $bpMap = ['xs' => '', 'sm' => 'sm', 'md' => 'md', 'lg' => 'lg'];
                foreach ($matches as $m) {
                    $twBp = $bpMap[$m[1]];
                    $userClasses[$twBp] = intval($m[2]);
                }
            }

            // 提取自定义 class（去掉数字、所有 col-*、所有 col-span-*）
            $extra = preg_replace('/^\d{1,2}\s*/', '', $trimmed);
            $extra = preg_replace('/\bcol-(xs|sm|md|lg)-\d{1,2}\b/', '', $extra);
            $extra = preg_replace('/(?:(sm|md|lg|xl):)?col-span-\d{1,2}/', '', $extra);
            $extra = preg_replace('/\s{2,}/', ' ', trim($extra));
        }

        if ($mdValue === null || $mdValue <= 0) {
            $mdValue = 4; // 默认值（每行 3 个，搜索/表单通用）
        }

        // 各断点推导
        $smValue = $this->calcSmFromMd($mdValue);

        $targets = [
            ''   => $userClasses['']   ?? 12,
            'sm' => $userClasses['sm'] ?? $smValue,
            'md' => $userClasses['md'] ?? $mdValue,
            'lg' => $userClasses['lg'] ?? $mdValue,
            'xl' => $userClasses['xl'] ?? $mdValue,
        ];

        // 边界：lg/xl 不能比 md 大（不能更松）
        foreach (['lg', 'xl'] as $bp) {
            if ($targets[$bp] > $targets['md']) {
                $targets[$bp] = $targets['md'];
            }
        }

        // 拼接
        $parts = [];
        foreach ($targets as $bp => $val) {
            $prefix = $bp ? $bp . ':' : '';
            $parts[] = "{$prefix}col-span-{$val}";
        }

        return trim(implode(' ', $parts) . ' ' . $extra);
    }

    /**
     * 调整列尺寸（对外入口）
     *
     * @param string|int $size 数字、Tailwind 格式或旧 Bootstrap 格式
     * @return string Tailwind class 字符串
     */
    public function adjustColSize($size = '')
    {
        return $this->parseSize($size);
    }

    /**
     * 从值提取 md 数字
     * 支持：纯数字 3、旧格式 '4 col-xs-4'、Tailwind 格式 '3 sm:col-span-6'
     * 旧格式带 col-lg-* 时优先取它：旧库 bootstrap 在 PC 端实际生效的是 col-lg-*，
     * 如 searchButtons 的 label '3 col-lg-4 col-sm-2 col-xs-12'，PC 端 label 占 4/12，
     * 只取开头裸数字 3 会导致按钮区 label 比字段行 label(4/12) 窄一档、按钮与控件列错位
     *
     * @param string|int|null $val
     * @return int|null
     */
    private function extractMdValue($val)
    {
        if ($val === null) {
            return null;
        }
        if (is_numeric($val)) {
            return intval($val);
        }
        if (is_string($val)) {
            $trimmed = trim($val);
            if (preg_match('/\bcol-lg-(\d{1,2})\b/', $trimmed, $mch)) {
                return intval($mch[1]);
            }
            if (preg_match('/\blg:col-span-(\d{1,2})\b/', $trimmed, $mch)) {
                return intval($mch[1]);
            }
            if (preg_match('/(\d{1,2})/', $trimmed, $mch)) {
                return intval($mch[1]);
            }
        }
        return null;
    }

    /**
     * 调整 displayer 尺寸（表单 label + input 双列）
     *
     * 兼容旧库格式：数组元素可以是纯数字或旧格式字符串
     *   [2, 8]               → label=2 input=8
     *   ['4 col-xs-4', '8 col-xs-8']  → Attachment 旧写法
     *
     * 规则：
     *   md: label + input = 12
     *   sm: label 疏朗，input = label 独占则 12，否则 12 - labelSm
     *   default: 都 12（手机上下布局）
     *
     * @param array $size [label, input]，可以是数字或旧格式字符串
     * @return array [labelClass, inputClass]
     */
    public function adjustDisplayerSize($size = [2, 8])
    {
        $labelMd = $this->extractMdValue($size[0] ?? null);
        $inputMd = $this->extractMdValue($size[1] ?? null);

        // label 隐藏 → input 独占全断点
        if ($labelMd === null || $labelMd == 0) {
            return ['hidden', 'col-span-12'];
        }

        // input 隐藏 → label 独占全断点
        if ($inputMd === null || $inputMd == 0) {
            return ["col-span-12 sm:col-span-12 md:col-span-{$labelMd} lg:col-span-{$labelMd}", 'hidden'];
        }

        // 任一 md 独占 → 两个都独占全断点（上下布局）
        if ($labelMd >= 12 || $inputMd >= 12) {
            return ['col-span-12', 'col-span-12'];
        }

        // 与旧 BS3 一致：label + input 按业务给定值排列，不足 12 时右侧留空
        // （默认 [2, 8] → 右侧空 2 列），超过 12 时 grid 自动让 input 折行
        // （同 BS3 float 换行）。不要再"补齐到 12"——那会把行宽占满，右侧没有余地

        // sm 断点：label 疏朗，input 补到 12
        $labelSm = $this->calcSmFromMd($labelMd);
        $inputSm = $labelSm == 12 ? 12 : (12 - $labelSm);

        $labelClass = "col-span-12 sm:col-span-{$labelSm} md:col-span-{$labelMd} lg:col-span-{$labelMd}";
        $inputClass = "col-span-12 sm:col-span-{$inputSm} md:col-span-{$inputMd} lg:col-span-{$inputMd}";

        return [$labelClass, $inputClass];
    }
}
