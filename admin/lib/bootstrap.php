<?php
/**
 * 绘萤者 — 后台核心库
 * 配置、会话、鉴权、CSRF、Markdown 渲染、HTML 净化、文章读写、静态页生成
 */

declare(strict_types=1);

define('LY_ROOT', dirname(__DIR__, 2));          // 站点根目录
define('LY_ADMIN', LY_ROOT . '/admin');
define('LY_DATA', LY_ADMIN . '/data');
define('LY_POSTS', LY_ROOT . '/posts.json');
define('LY_POST_DIR', LY_ROOT . '/post');
define('LY_UPLOAD_DIR', LY_ROOT . '/images/uploads');
define('LY_CONFIG', LY_ADMIN . '/config.php');

require_once __DIR__ . '/Parsedown.php';
require_once __DIR__ . '/pinyin.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/admin_ui.php';

/* =========================================================================
   配置
   ========================================================================= */

/**
 * 后台安装配置（admin/config.php）：只存放密码哈希与安装信息。
 * 站点身份（站名、域名、联系方式等）在站点根目录的 site.config.php，
 * 由 layout.php 的 ly_cfg() 读取。
 */
function ly_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = is_file(LY_CONFIG) ? (require LY_CONFIG) : [];
        if (!is_array($cfg)) $cfg = [];
    }
    return $cfg;
}

function ly_is_installed(): bool
{
    $c = ly_config();
    return !empty($c['password_hash']);
}

/* =========================================================================
   会话与鉴权
   ========================================================================= */

function ly_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $secure,
        'samesite' => 'Lax',
    ]);
    session_name('ly_admin');
    session_start();
}

function ly_is_logged_in(): bool
{
    ly_session_start();
    if (empty($_SESSION['uid'])) return false;

    // 30 分钟无操作自动登出
    $timeout = 1800;
    if (isset($_SESSION['last']) && (time() - (int)$_SESSION['last']) > $timeout) {
        ly_logout();
        return false;
    }
    $_SESSION['last'] = time();
    return true;
}

function ly_require_login(): void
{
    if (!ly_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function ly_login(string $password): bool
{
    $cfg = ly_config();
    $hash = $cfg['password_hash'] ?? '';
    if ($hash === '' || !password_verify($password, $hash)) return false;

    ly_session_start();
    session_regenerate_id(true);
    $_SESSION['uid'] = 1;
    $_SESSION['last'] = time();
    return true;
}

function ly_logout(): void
{
    ly_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool)($p['secure'] ?? false), true);
    }
    session_destroy();
}

/* =========================================================================
   密码管理
   ========================================================================= */

/** 密码最短长度（来自 site.config.php） */
function ly_password_min_length(): int
{
    $n = (int)ly_cfg('admin.min_password_length', 6);
    return $n > 0 ? $n : 6;
}

/** 首次安装预填的默认密码（来自 site.config.php） */
function ly_default_password(): string
{
    return (string)ly_cfg('admin.default_password', '');
}

/**
 * 当前是否仍在使用默认密码。
 * 用于在后台顶部显示醒目警告——默认密码是公开的，等于没有密码。
 */
function ly_is_using_default_password(): bool
{
    $cfg = ly_config();
    $hash = (string)($cfg['password_hash'] ?? '');
    if ($hash === '') return false;

    $def = ly_default_password();
    if ($def !== '' && password_verify($def, $hash)) return true;

    // 兼容旧版：安装时若未改默认值，标记位仍会保留
    return !empty($cfg['using_default_password']);
}

/** 写入 admin/config.php（保留已有键，合并 $patch） */
function ly_save_config(array $patch): bool
{
    $cfg = array_merge(ly_config(), $patch);

    $lines = [];
    $lines[] = '<?php';
    $lines[] = '/**';
    $lines[] = ' * 绘萤者后台配置 — 自动生成，请勿提交到公开仓库';
    $lines[] = ' *';
    $lines[] = ' * 站点身份（站名、域名、联系方式等）在根目录 site.config.php。';
    $lines[] = ' * 本文件只存放密码哈希与安装信息。';
    $lines[] = ' */';
    $lines[] = '';
    $lines[] = 'return [';
    foreach ($cfg as $k => $v) {
        $lines[] = '    ' . var_export((string)$k, true) . ' => ' . var_export($v, true) . ',';
    }
    $lines[] = '];';
    $lines[] = '';

    $tmp = LY_CONFIG . '.tmp';
    if (@file_put_contents($tmp, implode("\n", $lines), LOCK_EX) === false) return false;
    if (!@rename($tmp, LY_CONFIG)) { @unlink($tmp); return false; }
    @chmod(LY_CONFIG, 0600);
    return true;
}

/** 设置新密码 */
function ly_set_password(string $password): bool
{
    return ly_save_config([
        'password_hash'            => password_hash($password, PASSWORD_DEFAULT),
        'using_default_password'   => ($password === ly_default_password()),
    ]);
}

/** 首次安装：写入密码与站点地址 */
function ly_install(string $password, string $siteUrl): bool
{
    return ly_save_config([
        'password_hash'          => password_hash($password, PASSWORD_DEFAULT),
        'site_url'               => rtrim($siteUrl, '/'),
        'site_name'              => ly_site_name(),
        'using_default_password' => ($password === ly_default_password()),
        'installed_at'           => date('c'),
    ]);
}

/* ---- 登录失败限流（同一 IP 15 分钟内最多 8 次） ---- */

function ly_login_throttle_path(): string
{
    if (!is_dir(LY_DATA)) @mkdir(LY_DATA, 0700, true);
    return LY_DATA . '/throttle.json';
}

function ly_login_throttle_check(): bool
{
    $file = ly_login_throttle_path();
    if (!is_file($file)) return true;
    $data = json_decode((string)@file_get_contents($file), true);
    if (!is_array($data)) return true;

    $ip  = ly_client_ip();
    $rec = $data[$ip] ?? null;
    if (!$rec) return true;
    if ((time() - (int)$rec['first']) > 900) return true;   // 窗口已过
    return (int)$rec['count'] < 8;
}

function ly_login_throttle_hit(): void
{
    $file = ly_login_throttle_path();
    $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : [];
    if (!is_array($data)) $data = [];

    $ip  = ly_client_ip();
    $now = time();
    $rec = $data[$ip] ?? ['first' => $now, 'count' => 0];
    if (($now - (int)$rec['first']) > 900) $rec = ['first' => $now, 'count' => 0];
    $rec['count'] = (int)$rec['count'] + 1;
    $data[$ip] = $rec;

    // 清理过期记录
    foreach ($data as $k => $v) {
        if (($now - (int)($v['first'] ?? 0)) > 3600) unset($data[$k]);
    }
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

function ly_login_throttle_clear(): void
{
    $file = ly_login_throttle_path();
    if (!is_file($file)) return;
    $data = json_decode((string)@file_get_contents($file), true);
    if (!is_array($data)) return;
    unset($data[ly_client_ip()]);
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

function ly_client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

/* =========================================================================
   CSRF
   ========================================================================= */

function ly_csrf_token(): string
{
    ly_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function ly_csrf_check(?string $token): bool
{
    ly_session_start();
    if (empty($_SESSION['csrf']) || !is_string($token) || $token === '') return false;
    return hash_equals($_SESSION['csrf'], $token);
}

/* =========================================================================
   工具
   ========================================================================= */

/** 由标题生成 ASCII slug；中文标题返回空串，由调用方兜底 */
function ly_slugify(string $title): string
{
    $s = trim($title);
    $s = preg_replace('/\s+/u', '-', $s) ?? '';
    // 仅保留 ASCII 字母数字与连字符
    $s = preg_replace('/[^A-Za-z0-9\-]/', '', $s) ?? '';
    $s = preg_replace('/-+/', '-', $s) ?? '';
    $s = trim($s, '-');
    return strtolower(substr($s, 0, 80));
}

/** 确保 slug 唯一，且绝不包含路径分隔符 */
function ly_unique_slug(string $slug, array $posts, string $ignoreId = ''): string
{
    $slug = strtolower(preg_replace('/[^a-z0-9\-]/', '', strtolower($slug)) ?? '');
    $slug = trim(preg_replace('/-+/', '-', $slug) ?? '', '-');
    if ($slug === '') $slug = 'post-' . date('Ymd-His');

    // 保留字：避免与真实存在的页面/目录冲突
    $reserved = ['admin', 'assets', 'images', 'post', 'index', 'about', 'articles', 'api', 'login', 'setup', 'logout', '404', 'robots', 'sitemap'];
    if (in_array($slug, $reserved, true)) $slug = 'post-' . $slug;

    $taken = [];
    foreach ($posts as $p) {
        if ((string)($p['id'] ?? '') === $ignoreId) continue;
        if (!empty($p['slug'])) $taken[strtolower((string)$p['slug'])] = true;
    }
    if (!isset($taken[$slug])) return $slug;

    $i = 2;
    while (isset($taken[$slug . '-' . $i])) $i++;
    return $slug . '-' . $i;
}

function ly_json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ly_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

/* =========================================================================
   文章读写
   ========================================================================= */

function ly_load_posts(): array
{
    if (!is_file(LY_POSTS)) return [];
    $raw = (string)@file_get_contents(LY_POSTS);
    $d = json_decode($raw, true);
    if (!is_array($d)) return [];

    // 规范化：补齐字段
    foreach ($d as &$p) {
        $p['id']          = (string)($p['id'] ?? '');
        $p['title']       = (string)($p['title'] ?? '');
        $p['description'] = (string)($p['description'] ?? '');
        $p['content']     = (string)($p['content'] ?? '');
        $p['date']        = (string)($p['date'] ?? date('Y-m-d'));
        $p['cover']       = (string)($p['cover'] ?? '');
        $p['author']      = (string)($p['author'] ?? ly_site_name());
        $p['tags']        = isset($p['tags']) && is_array($p['tags']) ? array_values($p['tags']) : [];
        $p['slug']        = (string)($p['slug'] ?? '');
        $p['updated']     = (string)($p['updated'] ?? $p['date']);
    }
    unset($p);
    return $d;
}

function ly_save_posts(array $posts): bool
{
    $json = json_encode(
        array_values($posts),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if ($json === false) return false;

    $tmp = LY_POSTS . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return @rename($tmp, LY_POSTS);
}

function ly_find_post(array $posts, string $id): ?array
{
    foreach ($posts as $p) {
        if ((string)$p['id'] === $id) return $p;
    }
    return null;
}

function ly_sort_posts(array $posts): array
{
    usort($posts, function ($a, $b) {
        return strcmp((string)$b['date'], (string)$a['date']);
    });
    return $posts;
}

function ly_post_url(array $post): string
{
    $slug = (string)($post['slug'] ?? '');
    if ($slug !== '') return '/post/' . rawurlencode($slug) . '/';
    return '/post.html?id=' . rawurlencode((string)$post['id']);
}

/* =========================================================================
   HTML 净化（白名单）
   ========================================================================= */

function ly_allowed_classes(): array
{
    return [
        'callout', 'callout-title', 'callout-note', 'callout-tip', 'callout-warning', 'callout-danger',
        'img-grid', 'img-item', 'img-with-caption', 'caption',
        'code-block', 'copy-btn', 'collapse-code', 'collapse-header', 'collapse-body',
        'divider', 'divider-slim', 'divider-bold', 'divider-dashed',
        'alert', 'alert-success', 'alert-warning', 'alert-error', 'alert-info',
        'prose',
    ];
}

function ly_allowed_tags(): array
{
    return [
        'p', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 'del', 's', 'sup', 'sub',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'blockquote', 'code', 'pre',
        'a', 'img', 'figure', 'figcaption',
        'div', 'span', 'section',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
    ];
}

function ly_allowed_style_props(): array
{
    return ['color', 'font-size', 'font-weight', 'text-align', 'background-color'];
}

/**
 * 净化正文 HTML：白名单标签 / 属性 / class / style，
 * 阻止 javascript: 等危险协议，外链强制 rel=noopener。
 */
function ly_sanitize_html(string $html): string
{
    if (trim($html) === '') return '';

    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $wrapped = '<?xml encoding="UTF-8"><div id="ly-root">' . $html . '</div>';
    $ok = $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$ok) return '';

    $root = $doc->getElementById('ly-root');
    if (!$root) return '';

    $allowedTags  = array_flip(ly_allowed_tags());
    $allowedClass = array_flip(ly_allowed_classes());
    $allowedStyle = array_flip(ly_allowed_style_props());

    $walk = function (DOMNode $node) use (&$walk, $allowedTags, $allowedClass, $allowedStyle, $doc) {
        // 倒序遍历，便于安全移除
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if (!$child) continue;

            if ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
                continue;
            }
            if ($child->nodeType === XML_TEXT_NODE) {
                continue;
            }
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                $node->removeChild($child);
                continue;
            }

            /** @var DOMElement $child */
            $tag = strtolower($child->nodeName);

            // 危险容器整体丢弃
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'link', 'meta', 'base', 'svg', 'math'], true)) {
                $node->removeChild($child);
                continue;
            }

            if (!isset($allowedTags[$tag])) {
                // 不在白名单：保留文字内容，去掉标签
                $walk($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            // 清理属性
            $remove = [];
            foreach ($child->attributes as $attr) {
                $name = strtolower($attr->nodeName);
                $val  = $attr->nodeValue ?? '';

                if (strpos($name, 'on') === 0) { $remove[] = $name; continue; }   // 事件处理器
                if ($name === 'style') {
                    $clean = ly_filter_style($val, $allowedStyle);
                    if ($clean === '') $remove[] = $name;
                    else $child->setAttribute('style', $clean);
                    continue;
                }
                if ($name === 'class') {
                    $keep = [];
                    foreach (preg_split('/\s+/', trim($val)) ?: [] as $c) {
                        if ($c !== '' && isset($allowedClass[$c])) $keep[] = $c;
                    }
                    if ($keep) $child->setAttribute('class', implode(' ', $keep));
                    else $remove[] = $name;
                    continue;
                }
                if ($name === 'href' || $name === 'src') {
                    if (!ly_is_safe_url($val, $name === 'src')) { $remove[] = $name; }
                    continue;
                }
                $perTag = [
                    'a'      => ['href', 'title', 'target', 'rel'],
                    'img'    => ['src', 'alt', 'title', 'width', 'height', 'loading'],
                    'td'     => ['colspan', 'rowspan'],
                    'th'     => ['colspan', 'rowspan'],
                    'div'    => ['id'],
                    'span'   => [],
                    'figure' => [],
                ];
                $allowed = $perTag[$tag] ?? [];
                if (!in_array($name, $allowed, true)) $remove[] = $name;
            }
            foreach ($remove as $r) $child->removeAttribute($r);

            // 外链安全
            if ($tag === 'a') {
                $href = $child->getAttribute('href');
                if ($href !== '' && preg_match('#^https?://#i', $href) && !ly_is_same_host($href)) {
                    $child->setAttribute('target', '_blank');
                    $child->setAttribute('rel', 'noopener noreferrer');
                } else {
                    $child->removeAttribute('target');
                    if ($child->getAttribute('rel') === '') $child->removeAttribute('rel');
                }
            }
            if ($tag === 'img' && !$child->hasAttribute('loading')) {
                $child->setAttribute('loading', 'lazy');
            }

            $walk($child);
        }
    };

    $walk($root);

    $out = '';
    foreach ($root->childNodes as $c) {
        $out .= $doc->saveHTML($c);
    }
    return trim($out);
}

function ly_filter_style(string $style, array $allowed): string
{
    $keep = [];
    foreach (explode(';', $style) as $decl) {
        if (strpos($decl, ':') === false) continue;
        [$prop, $val] = array_map('trim', explode(':', $decl, 2));
        $prop = strtolower($prop);
        if (!isset($allowed[$prop])) continue;
        if (preg_match('/expression|javascript:|url\s*\(/i', $val)) continue;
        // 值只允许安全字符
        if (!preg_match('/^[#a-zA-Z0-9\s,\.\(\)%\-]+$/', $val)) continue;
        $keep[] = $prop . ':' . $val;
    }
    return implode(';', $keep);
}

function ly_is_safe_url(string $url, bool $isSrc): bool
{
    $u = trim($url);
    if ($u === '') return false;
    if (preg_match('/^\s*(javascript|vbscript|data|file)\s*:/i', $u)) {
        // 仅允许 data:image 作为图片源
        if ($isSrc && preg_match('#^data:image/(png|jpe?g|gif|webp|svg\+xml);base64,#i', $u)) return true;
        return false;
    }
    return true;
}

function ly_is_same_host(string $url): bool
{
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return true;
    $self = parse_url(ly_site_url(), PHP_URL_HOST) ?: '';
    return strcasecmp((string)$host, (string)$self) === 0;
}

/* =========================================================================
   Markdown → HTML
   ========================================================================= */

function ly_markdown_to_html(string $markdown): string
{
    $pd = new Parsedown();
    $pd->setBreaksEnabled(true);      // 单换行视为 <br>，与原站行为一致
    $pd->setMarkupEscaped(false);     // 允许内嵌 HTML（内容确实需要）
    $pd->setSafeMode(false);          // 安全性交给下面的白名单净化
    $html = $pd->text($markdown);

    // 支持 > [!NOTE] / [!TIP] / [!WARNING] / [!DANGER] 提示框语法
    $html = preg_replace_callback(
        '#<blockquote>\s*<p>\[!(NOTE|TIP|WARNING|DANGER)\]\s*(.*?)</p>\s*</blockquote>#is',
        function ($m) {
            $type  = strtolower($m[1]);
            $icons = ['note' => 'ℹ️', 'tip' => '💡', 'warning' => '⚠️', 'danger' => '🚨'];
            $title = ['note' => '注意', 'tip' => '提示', 'warning' => '警告', 'danger' => '危险'];
            return '<div class="callout callout-' . $type . '">'
                 . '<p class="callout-title">' . $icons[$type] . ' ' . $title[$type] . '</p>'
                 . '<p>' . $m[2] . '</p></div>';
        },
        $html
    ) ?? $html;

    return ly_sanitize_html($html);
}

/** 正文纯文本摘要，用于 meta description */
function ly_excerpt(string $markdown, int $len = 120): string
{
    $t = preg_replace('/```[\s\S]*?```/', ' ', $markdown) ?? $markdown;
    $t = strip_tags($t);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
    $t = trim($t);
    if (mb_strlen($t, 'UTF-8') > $len) {
        $t = mb_substr($t, 0, $len, 'UTF-8') . '…';
    }
    return $t;
}

/* =========================================================================
   静态页生成
   ========================================================================= */

/**
 * 给缺少 slug 的文章补上 slug（按标题生成，保证唯一）。
 *
 * 开源用户拿到的 posts.json 可能没有 slug 字段，或者从旧版本升级上来。
 * build.php 会先调用本函数，因此不需要单独的迁移脚本。
 *
 * @return array [0] => 补过 slug 的文章数组, [1] => 实际补了几篇
 */
function ly_ensure_slugs(array $posts): array
{
    $changed = 0;
    foreach ($posts as $i => $p) {
        $slug = trim((string)($p['slug'] ?? ''));
        if ($slug !== '' && preg_match('/^[a-z0-9\-]+$/', $slug)) continue;

        $base = ly_make_slug((string)($p['title'] ?? ''));
        $slug = ly_unique_slug($base, $posts, (string)($p['id'] ?? ''));
        $posts[$i]['slug'] = $slug;

        if (empty($posts[$i]['updated'])) {
            $posts[$i]['updated'] = (string)($p['date'] ?? date('Y-m-d'));
        }
        if (!isset($posts[$i]['tags']) || !is_array($posts[$i]['tags'])) {
            $posts[$i]['tags'] = [];
        }
        $changed++;
    }
    return [$posts, $changed];
}

function ly_generate_post_page(array $post, array $all): bool
{    $slug = (string)($post['slug'] ?? '');
    if ($slug === '' || !preg_match('/^[a-z0-9\-]+$/', $slug)) return false;

    $dir = LY_POST_DIR . '/' . $slug;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;

    $html = ly_render_post_page($post, $all);
    return @file_put_contents($dir . '/index.html', $html, LOCK_EX) !== false;
}

function ly_generate_all_post_pages(array $posts): int
{
    $n = 0;
    foreach ($posts as $p) {
        if (ly_generate_post_page($p, $posts)) $n++;
    }
    return $n;
}

function ly_delete_post_page(string $slug): void
{
    if ($slug === '' || !preg_match('/^[a-z0-9\-]+$/', $slug)) return;
    $dir = LY_POST_DIR . '/' . $slug;
    if (is_file($dir . '/index.html')) @unlink($dir . '/index.html');
    if (is_dir($dir)) @rmdir($dir);
}

/* =========================================================================
   sitemap.xml
   ========================================================================= */

function ly_generate_sitemap(array $posts): bool
{
    $base = ly_site_url();
    $today = date('Y-m-d');

    $urls = [
        ['loc' => $base . '/',          'lastmod' => $today, 'priority' => '1.0', 'freq' => 'daily'],
        ['loc' => $base . '/articles/', 'lastmod' => $today, 'priority' => '0.8', 'freq' => 'daily'],
        ['loc' => $base . '/about/',    'lastmod' => $today, 'priority' => '0.5', 'freq' => 'monthly'],
    ];
    foreach (ly_sort_posts($posts) as $p) {
        $urls[] = [
            'loc'      => $base . ly_post_url($p),
            'lastmod'  => (string)($p['updated'] ?: $p['date']),
            'priority' => '0.7',
            'freq'     => 'monthly',
        ];
    }

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $u) {
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
        $xml .= '    <lastmod>' . htmlspecialchars($u['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</lastmod>\n";
        $xml .= '    <changefreq>' . $u['freq'] . "</changefreq>\n";
        $xml .= '    <priority>' . $u['priority'] . "</priority>\n";
        $xml .= "  </url>\n";
    }
    $xml .= "</urlset>\n";

    return @file_put_contents(LY_ROOT . '/sitemap.xml', $xml, LOCK_EX) !== false;
}
