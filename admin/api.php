<?php
/**
 * 绘萤者 — 后台 API
 * 所有写操作要求已登录 + 有效 CSRF。
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/render_post.php';

ly_session_start();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

if (!ly_is_logged_in()) {
    ly_json_response(['ok' => false, 'error' => '未登录或会话已过期，请重新登录。'], 401);
}

$input  = ly_read_json_body();
$action = (string)($_POST['action'] ?? $input['action'] ?? $_GET['action'] ?? '');

/* CSRF：POST 一律校验 */
$token = $_POST['csrf'] ?? $input['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!ly_csrf_check(is_string($token) ? $token : null)) {
    ly_json_response(['ok' => false, 'error' => 'CSRF 校验失败，请刷新页面后重试。'], 403);
}

switch ($action) {

    /* ---------------- 列表 ---------------- */
    case 'list': {
        $posts = ly_sort_posts(ly_load_posts());
        $out = [];
        foreach ($posts as $p) {
            $out[] = [
                'id'      => $p['id'],
                'title'   => $p['title'],
                'slug'    => $p['slug'],
                'date'    => $p['date'],
                'updated' => $p['updated'],
                'cover'   => $p['cover'],
                'tags'    => $p['tags'],
                'url'     => ly_post_url($p),
                'chars'   => mb_strlen($p['content'], 'UTF-8'),
            ];
        }
        ly_json_response(['ok' => true, 'posts' => $out]);
    }

    /* ---------------- 读取单篇 ---------------- */
    case 'get': {
        $id = (string)($input['id'] ?? $_GET['id'] ?? '');
        $post = ly_find_post(ly_load_posts(), $id);
        if (!$post) ly_json_response(['ok' => false, 'error' => '文章不存在'], 404);
        ly_json_response(['ok' => true, 'post' => $post]);
    }

    /* ---------------- 保存（新建 / 更新） ---------------- */
    case 'save': {
        $title = trim((string)($input['title'] ?? ''));
        $body  = (string)($input['content'] ?? '');

        if ($title === '') ly_json_response(['ok' => false, 'error' => '标题不能为空'], 400);
        if (mb_strlen($title, 'UTF-8') > 200) ly_json_response(['ok' => false, 'error' => '标题过长（上限 200 字）'], 400);
        if (trim($body) === '') ly_json_response(['ok' => false, 'error' => '正文不能为空'], 400);
        if (mb_strlen($body, 'UTF-8') > 500000) ly_json_response(['ok' => false, 'error' => '正文过长'], 400);

        $posts   = ly_load_posts();
        $id      = trim((string)($input['id'] ?? ''));
        $isNew   = ($id === '' || ly_find_post($posts, $id) === null);
        $oldSlug = '';

        if (!$isNew) {
            $existing = ly_find_post($posts, $id);
            $oldSlug  = (string)($existing['slug'] ?? '');
        } else {
            $id = 'p-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
        }

        $slugIn = trim((string)($input['slug'] ?? ''));
        if ($slugIn === '') $slugIn = ly_slugify($title);
        $slug = ly_unique_slug($slugIn, $posts, $isNew ? '' : $id);

        $date = trim((string)($input['date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

        $tags = $input['tags'] ?? [];
        if (is_string($tags)) $tags = array_filter(array_map('trim', explode(',', $tags)));
        $tags = is_array($tags) ? array_slice(array_values(array_filter(array_map('strval', $tags))), 0, 12) : [];

        $cover  = trim((string)($input['cover'] ?? ''));
        $author = trim((string)($input['author'] ?? '')) ?: ly_site_name();
        $desc   = trim((string)($input['description'] ?? ''));

        // 封面路径限定：站内 images/ 相对路径，或同站绝对地址
        // （前台 CSP 为 img-src 'self'，外部图片本来也无法显示）
        if ($cover !== '') {
            $okCover = (bool)preg_match('#^(\.{0,2}/?images/|/images/)#i', $cover);
            if (!$okCover && preg_match('#^https?://#i', $cover)) {
                $okCover = ly_is_same_host($cover);
            }
            if (!$okCover) {
                ly_json_response(['ok' => false, 'error' => '封面路径不合法：只允许本站 images/ 目录下的图片'], 400);
            }
        }

        $record = [
            'id'          => $id,
            'title'       => $title,
            'description' => $desc,
            'content'     => $body,
            'date'        => $date,
            'updated'     => date('Y-m-d'),
            'cover'       => $cover,
            'author'      => $author,
            'tags'        => $tags,
            'slug'        => $slug,
        ];

        if ($isNew) {
            $posts[] = $record;
        } else {
            foreach ($posts as $i => $p) {
                if ((string)$p['id'] === $id) { $posts[$i] = $record; break; }
            }
        }
        $posts = ly_sort_posts($posts);

        if (!ly_save_posts($posts)) {
            ly_json_response(['ok' => false, 'error' => '写入 posts.json 失败，请检查文件权限'], 500);
        }

        // slug 变更时清理旧静态目录
        if ($oldSlug !== '' && $oldSlug !== $slug) ly_delete_post_page($oldSlug);

        $generated = ly_generate_post_page($record, $posts);
        ly_generate_sitemap($posts);

        ly_json_response([
            'ok'        => true,
            'id'        => $id,
            'slug'      => $slug,
            'url'       => ly_post_url($record),
            'generated' => $generated,
            'message'   => $generated ? '已保存，并生成静态页' : '已保存，但静态页生成失败（请检查 post/ 目录权限）',
        ]);
    }

    /* ---------------- 删除 ---------------- */
    case 'delete': {
        $ids = $input['ids'] ?? ($input['id'] ?? []);
        if (is_string($ids)) $ids = [$ids];
        if (!is_array($ids) || !$ids) ly_json_response(['ok' => false, 'error' => '未指定要删除的文章'], 400);

        $posts = ly_load_posts();
        $removed = [];
        foreach ($posts as $i => $p) {
            if (in_array((string)$p['id'], array_map('strval', $ids), true)) {
                ly_delete_post_page((string)($p['slug'] ?? ''));
                $removed[] = $p['title'];
                unset($posts[$i]);
            }
        }
        if (!$removed) ly_json_response(['ok' => false, 'error' => '未找到匹配的文章'], 404);

        $posts = ly_sort_posts(array_values($posts));
        if (!ly_save_posts($posts)) ly_json_response(['ok' => false, 'error' => '写入 posts.json 失败'], 500);
        ly_generate_sitemap($posts);

        ly_json_response(['ok' => true, 'removed' => $removed, 'message' => '已删除 ' . count($removed) . ' 篇']);
    }

    /* ---------------- 图片上传 ---------------- */
    case 'upload': {
        if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
            ly_json_response(['ok' => false, 'error' => '没有收到文件'], 400);
        }
        $f = $_FILES['file'];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            ly_json_response(['ok' => false, 'error' => '上传失败（错误码 ' . (int)$f['error'] . '）'], 400);
        }
        $max = 8 * 1024 * 1024;
        if ((int)$f['size'] > $max) {
            ly_json_response(['ok' => false, 'error' => '图片过大，上限 8MB'], 400);
        }

        $info = @getimagesize($f['tmp_name']);
        if ($info === false) ly_json_response(['ok' => false, 'error' => '不是有效的图片文件'], 400);

        $extMap = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_GIF  => 'gif',
            IMAGETYPE_WEBP => 'webp',
        ];
        if (!isset($extMap[$info[2]])) {
            ly_json_response(['ok' => false, 'error' => '仅支持 JPG / PNG / GIF / WebP'], 400);
        }
        $ext = $extMap[$info[2]];

        $sub = date('Y/m');
        $dir = LY_UPLOAD_DIR . '/' . $sub;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            ly_json_response(['ok' => false, 'error' => '无法创建上传目录，请检查 images/uploads 权限'], 500);
        }

        $base = ly_slugify(pathinfo((string)$f['name'], PATHINFO_FILENAME));
        if ($base === '') $base = 'img';
        $base = substr($base, 0, 40);
        $name = date('Ymd-His') . '-' . $base . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;

        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            ly_json_response(['ok' => false, 'error' => '保存文件失败'], 500);
        }
        @chmod($dest, 0644);

        $rel = 'images/uploads/' . $sub . '/' . $name;
        ly_json_response([
            'ok'   => true,
            'url'  => './' . $rel,
            'path' => $rel,
            'markdown' => '![' . pathinfo((string)$f['name'], PATHINFO_FILENAME) . '](./' . $rel . ')',
        ]);
    }

    /* ---------------- 重建全部静态页 ---------------- */
    case 'rebuild': {
        $posts = ly_load_posts();
        $n = ly_generate_all_post_pages($posts);
        ly_generate_sitemap($posts);
        ly_json_response(['ok' => true, 'message' => '已重建 ' . $n . ' 个静态页，并更新 sitemap.xml']);
    }

    default:
        ly_json_response(['ok' => false, 'error' => '未知操作'], 400);
}
