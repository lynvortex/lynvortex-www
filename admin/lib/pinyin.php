<?php
/**
 * 绘萤者 — 中文标题转拼音 slug
 *
 *   「用户指南」   -> yong-hu-zhi-nan
 *   「绘萤者-启航」 -> hui-ying-zhe-qi-hang
 *
 * 拼音数据来自 mozillazg/pinyin-data（见 pinyin_raw.php），覆盖 2 万余个常用汉字。
 * 多音字取首个常用读音；未收录字符跳过，因此词库缺失也不会导致保存失败。
 */

declare(strict_types=1);

require_once __DIR__ . '/pinyin_raw.php';
require_once __DIR__ . '/pinyin_data.php';

/**
 * 中文标题 -> 拼音 slug。结果为空时返回空串，由 ly_make_slug() 兜底。
 */
function ly_slugify_zh(string $title): string
{
    $map   = ly_pinyin_data();
    $parts = [];
    $len   = mb_strlen($title, 'UTF-8');

    for ($i = 0; $i < $len; $i++) {
        $ch = mb_substr($title, $i, 1, 'UTF-8');

        // ASCII 字母数字：连续片段合并为一个词
        if (preg_match('/[A-Za-z0-9]/', $ch)) {
            $n = count($parts);
            if ($n > 0 && preg_match('/[A-Za-z0-9]$/', $parts[$n - 1])) {
                $parts[$n - 1] .= strtolower($ch);
            } else {
                $parts[] = strtolower($ch);
            }
            continue;
        }

        if (isset($map[$ch])) {
            $parts[] = $map[$ch];
        }
        // 标点、空格、未收录字符：忽略
    }

    $parts = array_values(array_filter($parts, static fn($p) => $p !== ''));
    $slug  = implode('-', $parts);
    $slug  = preg_replace('/-+/', '-', $slug) ?? $slug;
    $slug  = trim($slug, '-');

    return substr($slug, 0, 80);
}

/**
 * 生成 slug：优先拼音音译，失败则退回 ASCII 化，再退回日期短码。
 */
function ly_make_slug(string $title): string
{
    $s = ly_slugify_zh($title);
    if ($s === '') $s = ly_slugify($title);
    if ($s === '') $s = 'post-' . date('Ymd') . '-' . substr(md5($title . microtime()), 0, 6);
    return $s;
}
