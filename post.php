<?php
/**
 * 绘萤者 — 文章静态页直出（回退用）
 *
 * 正常情况下 /post/<slug>/ 由生成的 index.html 直接提供，不经过 PHP。
 * 该文件仅在静态文件缺失时兜底渲染，保证链接始终可用。
 */

declare(strict_types=1);

require_once __DIR__ . '/admin/lib/bootstrap.php';
require_once __DIR__ . '/admin/lib/render_post.php';

$slug = (string)($_GET['slug'] ?? '');
if ($slug === '' || !preg_match('/^[a-z0-9\-]+$/', $slug)) {
    http_response_code(404);
    include __DIR__ . '/404.html';
    exit;
}

$posts = ly_load_posts();
$post  = null;
foreach ($posts as $p) {
    if ((string)($p['slug'] ?? '') === $slug) { $post = $p; break; }
}

if ($post === null) {
    http_response_code(404);
    include __DIR__ . '/404.html';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo ly_render_post_page($post, $posts);
