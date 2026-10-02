<?php
/**
 * 绘萤者 — 共享页面布局
 * ==========================================================================
 * 全站导航、页脚、head 元信息只在这里定义一次。
 * 静态页面由 build.php 调用这些函数生成，文章页由 render_post.php 调用。
 *
 * 所有文案与链接都取自 site.config.php，这里不写死任何站点专有内容。
 * ==========================================================================
 */

declare(strict_types=1);

/** 读取站点配置（单例缓存）。支持点号路径，如 ly_cfg('home.latest_count')。 */
function ly_cfg(string $path = '', $default = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $file = LY_ROOT . '/site.config.php';
        $cfg  = is_file($file) ? (require $file) : [];
        if (!is_array($cfg)) $cfg = [];
    }
    if ($path === '') return $cfg;

    $cur = $cfg;
    foreach (explode('.', $path) as $seg) {
        if (!is_array($cur) || !array_key_exists($seg, $cur)) return $default;
        $cur = $cur[$seg];
    }
    return $cur;
}

/** 站点根地址，结尾不带 / */
function ly_site_url(): string
{
    $u = (string)ly_cfg('url', '');
    if ($u === '') {
        // 回退到 admin/config.php 里的安装信息，再回退到当前请求
        $inst = ly_installed_config();
        $u = (string)($inst['site_url'] ?? '');
    }
    if ($u === '') $u = 'http://localhost';
    return rtrim($u, '/');
}

/** 站点名 */
function ly_site_name(): string
{
    $n = (string)ly_cfg('name', '');
    if ($n === '') {
        $inst = ly_installed_config();
        $n = (string)($inst['site_name'] ?? '绘萤者');
    }
    return $n;
}

/**
 * 默认 keywords：配置留空时用站名 + 英文名拼。
 * 这样换站只改 name / name_en，不用记得同步改关键词。
 */
function ly_default_keywords(): string
{
    $kw = trim((string)ly_cfg('keywords', ''));
    if ($kw !== '') return $kw;

    $parts = array_filter([
        ly_site_name(),
        (string)ly_cfg('name_en', ''),
    ], static fn($s) => $s !== '');
    return implode(', ', $parts);
}

/** 关于页标题：配置留空时用「关于 + 站名」 */
function ly_about_title(): string
{
    $t = trim((string)ly_cfg('about.title', ''));
    return $t !== '' ? $t : '关于' . ly_site_name();
}

/** 已安装配置（admin/config.php），未安装返回空数组 */
function ly_installed_config(): array
{
    static $inst = null;
    if ($inst === null) {
        $f = LY_CONFIG ?? (LY_ADMIN . '/config.php');
        $inst = is_file($f) ? (require $f) : [];
        if (!is_array($inst)) $inst = [];
    }
    return $inst;
}

/** HTML 转义 */
function ly_e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ==========================================================================
   head
   ========================================================================== */

/**
 * 生成 <head> 到 </head>。
 *
 * $o 支持：
 *   title, description, canonical, keywords, robots, og_type, og_image,
 *   fonts ('serif'|'sans'), json_ld (数组的数组), css (额外样式表),
 *   preload (预加载资源), head (额外原始 HTML)
 */
function ly_head(array $o): string
{
    $site     = ly_site_url();
    $siteName = ly_site_name();
    $lang     = (string)ly_cfg('lang', 'zh-CN');
    $theme    = (string)ly_cfg('theme_color', '#0066ff');
    $logo     = (string)ly_cfg('logo', '/images/icon.webp');

    $title    = (string)($o['title'] ?? $siteName);
    $desc     = (string)($o['description'] ?? ly_cfg('description', ''));
    $canonical = (string)($o['canonical'] ?? ($site . '/'));

    // keywords 留空时自动用站名与英文名拼一个，换站时不用手改
    $keywords = (string)($o['keywords'] ?? '');
    if ($keywords === '') $keywords = ly_default_keywords();

    $ogType   = (string)($o['og_type'] ?? 'website');
    $ogImage  = (string)($o['og_image'] ?? ly_cfg('og_image', $logo));
    if (!preg_match('#^https?://#i', $ogImage)) $ogImage = $site . $ogImage;
    $robots   = (string)($o['robots'] ?? 'index, follow');

    $h = [];
    $h[] = '<!DOCTYPE html>';
    $h[] = '<html lang="' . ly_e($lang) . '">';
    $h[] = '<head>';
    $h[] = '<meta charset="UTF-8">';
    $h[] = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    $h[] = '<meta http-equiv="Content-Security-Policy" content="default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src \'self\' data:; connect-src \'self\'; frame-ancestors \'none\'; base-uri \'self\'; form-action \'self\'">';
    $h[] = '<title>' . ly_e($title) . '</title>';
    $h[] = '<meta name="description" content="' . ly_e($desc) . '">';
    if ($keywords !== '') {
        $h[] = '<meta name="keywords" content="' . ly_e($keywords) . '">';
    }
    $h[] = '<link rel="canonical" href="' . ly_e($canonical) . '">';
    $h[] = '<meta name="theme-color" content="' . ly_e($theme) . '">';
    $h[] = '<meta name="robots" content="' . ly_e($robots) . '" id="search-robots">';

    $h[] = '';
    $h[] = '<!-- Open Graph -->';
    $h[] = '<meta property="og:type" content="' . ly_e($ogType) . '">';
    $h[] = '<meta property="og:site_name" content="' . ly_e($siteName) . '">';
    $h[] = '<meta property="og:title" content="' . ly_e($title) . '">';
    $h[] = '<meta property="og:description" content="' . ly_e($desc) . '">';
    $h[] = '<meta property="og:url" content="' . ly_e($canonical) . '">';
    $h[] = '<meta property="og:image" content="' . ly_e($ogImage) . '">';
    $h[] = '<meta property="og:locale" content="zh_CN">';
    foreach ((array)($o['og_extra'] ?? []) as $k => $v) {
        $h[] = '<meta property="' . ly_e($k) . '" content="' . ly_e((string)$v) . '">';
    }

    $h[] = '';
    $h[] = '<!-- Twitter -->';
    $h[] = '<meta name="twitter:card" content="summary_large_image">';
    $h[] = '<meta name="twitter:title" content="' . ly_e($title) . '">';
    $h[] = '<meta name="twitter:description" content="' . ly_e($desc) . '">';
    $h[] = '<meta name="twitter:image" content="' . ly_e($ogImage) . '">';

    $lds = (array)($o['json_ld'] ?? []);
    if ($lds) {
        $h[] = '';
        $h[] = '<!-- 结构化数据 -->';
        foreach ($lds as $ld) {
            if (!$ld) continue;
            $h[] = '<script type="application/ld+json">'
                 . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                 . '</script>';
        }
    }

    $h[] = '';
    $h[] = '<link rel="icon" href="' . ly_e($logo) . '" type="image/webp">';
    $h[] = '<link rel="apple-touch-icon" href="' . ly_e($logo) . '">';
    foreach ((array)($o['preload'] ?? []) as $p) {
        $h[] = '<link rel="preload" href="' . ly_e((string)$p) . '" as="image"' 
             . (isset($o['preload_priority'][$p]) ? ' fetchpriority="' . ly_e((string)$o['preload_priority'][$p]) . '"' : '')
             . '>';
    }
    $h[] = '<link rel="preconnect" href="https://fonts.googleapis.com">';
    $h[] = '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    if (($o['fonts'] ?? 'serif') === 'serif') {
        $h[] = '<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;700&family=Noto+Sans+SC:wght@400;500;700&display=swap" rel="stylesheet">';
    } else {
        $h[] = '<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700&display=swap" rel="stylesheet">';
    }
    $h[] = '<link rel="stylesheet" href="/assets/css/site.css">';
    foreach ((array)($o['css'] ?? []) as $c) {
        $h[] = '<link rel="stylesheet" href="' . ly_e((string)$c) . '">';
    }
    if (!empty($o['head'])) {
        $h[] = '';
        $h[] = (string)$o['head'];
    }
    $h[] = '</head>';
    return implode("\n", $h);
}

/* ==========================================================================
   导航栏
   ========================================================================== */

/**
 * 生成导航栏。$active 取 'home' | 'articles' | 'about' | ''。
 * $withSearch 为 true 时带上可展开的搜索框。
 */
function ly_navbar(string $active = '', bool $withSearch = true): string
{
    $siteName = ly_site_name();
    $logo     = (string)ly_cfg('logo', '/images/icon.webp');

    $items = [
        'home'     => ['/',          '首页'],
        'articles' => ['/articles/', '文章列表'],
        'about'    => ['/about/',    '关于我们'],
    ];

    $h = [];
    $h[] = '<header class="navbar">';
    $h[] = '  <div class="container">';
    $h[] = '    <div class="navbar-inner">';
    $h[] = '      <a href="/" class="logo"><img src="' . ly_e($logo) . '" alt="' . ly_e($siteName) . ' Logo" width="36" height="36">' . ly_e($siteName) . '</a>';
    $h[] = '      <nav class="desktop-nav" aria-label="主导航">';
    foreach ($items as $key => [$url, $label]) {
        $cls = $key === $active ? 'nav-link active' : 'nav-link';
        $h[] = '        <a href="' . $url . '" class="' . $cls . '">' . ly_e($label) . '</a>';
    }
    $h[] = '      </nav>';
    $h[] = '      <div class="nav-actions">';
    if ($withSearch) {
        $h[] = '        <button class="action-btn" onclick="LY.toggleSearch()" aria-label="搜索文章"><svg class="icon" aria-hidden="true"><use href="/images/icons.svg#search"/></svg></button>';
    }
    $h[] = '        <button id="mobile-menu-btn" class="action-btn mobile-menu-btn" aria-label="打开菜单" aria-expanded="false" aria-controls="mobile-menu"><svg class="icon" aria-hidden="true"><use href="/images/icons.svg#bars"/></svg></button>';
    $h[] = '      </div>';
    $h[] = '    </div>';

    if ($withSearch) {
        $h[] = '    <div id="search-container" class="search-wrap">';
        $h[] = '      <div class="search-inner">';
        $h[] = '        <input type="text" id="search-input" class="search-input" placeholder="输入关键词搜索文章..." aria-label="搜索关键词">';
        $h[] = '        <svg class="icon search-clear" onclick="LY.clearSearchInput()" role="button" tabindex="0" aria-label="清空搜索框"><use href="/images/icons.svg#times"/></svg>';
        $h[] = '        <button class="search-submit" onclick="LY.doSearch()" aria-label="执行搜索">搜索</button>';
        $h[] = '      </div>';
        $h[] = '    </div>';
    }

    $h[] = '    <div id="mobile-menu" class="mobile-menu" aria-label="移动端导航">';
    foreach ($items as $key => [$url, $label]) {
        $cls = $key === $active ? ' class="active"' : '';
        $h[] = '      <a href="' . $url . '"' . $cls . '>' . ly_e($label) . '</a>';
    }
    $h[] = '    </div>';
    $h[] = '  </div>';
    $h[] = '</header>';
    return implode("\n", $h);
}

/* ==========================================================================
   页脚
   ========================================================================== */

function ly_footer(): string
{
    $siteName = ly_site_name();
    $nameEn   = (string)ly_cfg('name_en', '');
    $year     = date('Y');
    $github   = (string)ly_cfg('github', '');
    $links    = (array)ly_cfg('friend_links', []);
    $icp      = (string)ly_cfg('icp', '');
    $police   = (string)ly_cfg('police', '');
    $code     = (string)ly_cfg('police_code', '');
    $icon     = (string)ly_cfg('police_icon', '/images/beian.png');

    $h = [];
    $h[] = '<footer class="footer">';
    $h[] = '  <div class="container">';

    if ($links || $github) {
        $h[] = '    <div class="footer-links">';
        if ($links) $h[] = '      <span class="footer-links-label">友情链接</span>';
        foreach ($links as $l) {
            $h[] = '      <a href="' . ly_e((string)($l['url'] ?? '#')) . '" target="_blank" rel="noopener" class="footer-link">' . ly_e((string)($l['name'] ?? '')) . '</a>';
        }
        if ($github) {
            $h[] = '      <a href="' . ly_e($github) . '" target="_blank" rel="noopener" class="footer-link">GitHub 仓库</a>';
        }
        $h[] = '    </div>';
    }

    $copy = '&copy; ' . $year . ' ' . ly_e($siteName);
    if ($nameEn !== '') $copy .= '-' . ly_e($nameEn);
    $h[] = '    <p class="footer-copy">' . $copy . '</p>';

    if ($icp !== '' || $police !== '') {
        $h[] = '    <p class="footer-beian">';
        if ($icp !== '') {
            $h[] = '      <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener">' . ly_e($icp) . '</a>';
        }
        if ($police !== '') {
            $q = $code !== '' ? 'https://beian.mps.gov.cn/#/query/webSearch?code=' . rawurlencode($code) : 'https://beian.mps.gov.cn/';
            $h[] = '      <a href="' . ly_e($q) . '" target="_blank" rel="noopener" style="margin-left:10px;"><img src="' . ly_e($icon) . '" alt="公安备案徽标" width="16" height="16">' . ly_e($police) . '</a>';
        }
        $h[] = '    </p>';
    }

    $h[] = '  </div>';
    $h[] = '</footer>';
    return implode("\n", $h);
}

/* ==========================================================================
   页尾脚本与回顶按钮
   ========================================================================== */

function ly_back_to_top(): string
{
    return '<button id="back-to-top" class="back-to-top" onclick="window.scrollTo({top:0,behavior:\'smooth\'})" aria-label="返回顶部"><svg class="icon" aria-hidden="true"><use href="/images/icons.svg#arrow-up"/></svg></button>';
}

/** $withPost 为 true 时额外引入代码高亮与文章页脚本 */
function ly_scripts(bool $withPost = false): string
{
    $h = [];
    if ($withPost) $h[] = '<script src="/assets/vendor/highlight.min.js" defer></script>';
    $h[] = '<script src="/assets/js/site.js" defer></script>';
    if ($withPost) $h[] = '<script src="/assets/js/post.js" defer></script>';
    return implode("\n", $h);
}

/**
 * 拼装一个完整页面。
 * $body 是 <body> 内部内容（不含导航与页脚），会自动插入导航、页脚、回顶、脚本。
 */
function ly_page(array $o, string $body): string
{
    $h = [];
    $h[] = ly_head($o);
    $h[] = '<body>';
    if (!empty($o['body_prefix'])) {
        $h[] = (string)$o['body_prefix'];
    }
    $h[] = '';
    $h[] = ly_navbar((string)($o['active'] ?? ''), (bool)($o['search'] ?? true));
    $h[] = '';
    $h[] = $body;
    $h[] = '';
    $h[] = ly_footer();
    $h[] = '';
    $h[] = ly_back_to_top();
    $h[] = '';
    $h[] = ly_scripts((bool)($o['post_scripts'] ?? false));
    $h[] = '</body>';
    $h[] = '</html>';
    return implode("\n", $h) . "\n";
}
