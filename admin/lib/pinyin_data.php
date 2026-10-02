<?php
/**
 * 绘萤者 — 汉字拼音数据（自动生成，请勿手工编辑）
 *
 * 数据来源：mozillazg/pinyin-data (MIT License)
 * 格式：每项 "汉字=拼音"，以空格分隔，运行时解析为映射表。
 * 多音字取该字的首个常用读音。
 */

declare(strict_types=1);

function ly_pinyin_data(): array
{
    static $map = null;
    if ($map !== null) return $map;

    $map = [];
    $raw = LY_PINYIN_RAW;
    $len = strlen($raw);
    $i = 0;
    while ($i < $len) {
        // 读取一个 UTF-8 汉字（3 字节，BMP 范围内）
        $ch = substr($raw, $i, 3);
        $i += 3;
        if ($i >= $len || $raw[$i] !== '=') continue;
        $i++; // 跳过 '='
        $start = $i;
        while ($i < $len && $raw[$i] !== ' ') $i++;
        $map[$ch] = substr($raw, $start, $i - $start);
        $i++; // 跳过空格
    }
    return $map;
}
