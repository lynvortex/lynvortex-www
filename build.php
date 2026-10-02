<?php
/**
 * 绘萤者 — 静态页生成器
 * ==========================================================================
 * 用法：
 *     php build.php              # 生成全部静态页
 *     php build.php --dry-run    # 只检查，不写文件
 *
 * 生成内容：
 *     index.html            首页
 *     articles/index.html   文章列表
 *     about/index.html      关于我们
 *     404.html              错误页
 *     post.html             旧地址兼容页
 *     sitemap.xml           站点地图
 *     robots.txt            爬虫规则
 *     post/<slug>/index.html 每篇文章（调用 render_post.php）
 *
 * 页面文案与链接全部来自 site.config.php，改配置后重跑本脚本即可换站。
 * ==========================================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('build.php 只能通过命令行运行。');
}

require_once __DIR__ . '/admin/lib/bootstrap.php';
require_once __DIR__ . '/admin/lib/render_post.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);

$site     = ly_site_url();
$siteName = ly_site_name();

/* ==========================================================================
   首页
   ========================================================================== */
$heroImage = (string)ly_cfg('hero_image', '');
$heroStyle = $heroImage !== '' ? ' style="background-image:url(' . ly_e($heroImage) . ')"' : '';

$skeleton = static function (int $w): string {
    return '<div class="article-card">'
         . '<div class="article-card-thumb skeleton"></div>'
         . '<div class="article-card-body"><div class="skeleton" style="height:18px;width:' . $w . '%"></div></div>'
         . '<div class="skeleton" style="height:14px;width:56px"></div>'
         . '</div>';
};

$homeBody  = '<div id="app-content">';
$homeBody .= "\n\n  <!-- 英雄区 -->\n";
$homeBody .= '  <section class="hero"' . $heroStyle . '>' . "\n";
$homeBody .= '    <div class="hero-overlay"></div>' . "\n";
$homeBody .= '    <div class="container">' . "\n";
$homeBody .= '      <div class="hero-content">' . "\n";
if ((string)ly_cfg('eyebrow', '') !== '') {
    $homeBody .= '        <div class="hero-eyebrow">' . ly_e((string)ly_cfg('eyebrow')) . '</div>' . "\n";
}
$homeBody .= '        <h1 class="hero-title">' . ly_e($siteName);
if ((string)ly_cfg('slogan', '') !== '') {
    $homeBody .= '<span>' . ly_e((string)ly_cfg('slogan')) . '</span>';
}
$homeBody .= '</h1>' . "\n";
$homeBody .= '        <p class="hero-sub">' . ly_e((string)ly_cfg('description', '')) . '</p>' . "\n";
$homeBody .= '      </div>' . "\n";
$homeBody .= '    </div>' . "\n";
$homeBody .= '    <div class="hero-scroll">' . "\n";
$homeBody .= '      <span>向下滚动</span>' . "\n";
$homeBody .= '      <svg class="icon" aria-hidden="true"><use href="/images/icons.svg#chevron-down"/></svg>' . "\n";
$homeBody .= '    </div>' . "\n";
$homeBody .= '  </section>' . "\n\n";
$homeBody .= '  <main class="container main-content">' . "\n";
$homeBody .= '    <section>' . "\n";
$homeBody .= '      <div class="section-header">' . "\n";
$homeBody .= '        <h2 class="section-title">' . ly_e((string)ly_cfg('home.latest_title', '最新文章')) . '</h2>' . "\n";
$homeBody .= '        <a href="/articles/" class="section-more">查看全部 →</a>' . "\n";
$homeBody .= '      </div>' . "\n\n";
$homeBody .= '      <div id="home-latest-list" class="article-list">' . "\n";
$homeBody .= '        ' . $skeleton(70) . "\n";
$homeBody .= '        ' . $skeleton(60) . "\n";
$homeBody .= '        ' . $skeleton(75) . "\n";
$homeBody .= '      </div>' . "\n";
$homeBody .= '    </section>' . "\n";
$homeBody .= '  </main>' . "\n";
$homeBody .= '</div>';

$homeLd = [
    '@context'        => 'https://schema.org',
    '@type'           => 'WebSite',
    'name'            => $siteName,
    'alternateName'   => (string)ly_cfg('name_en', ''),
    'url'             => $site . '/',
    'description'     => (string)ly_cfg('description', ''),
    'inLanguage'      => (string)ly_cfg('lang', 'zh-CN'),
    'potentialAction' => [
        '@type'       => 'SearchAction',
        'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => $site . '/articles/?search={search_term_string}'],
        'query-input' => 'required name=search_term_string',
    ],
];

$homeHtml = ly_page([
    'title'       => $siteName . ' - ' . (string)ly_cfg('eyebrow', ''),
    'description' => (string)ly_cfg('description', ''),
    'keywords'    => (string)ly_cfg('keywords', ''),
    'canonical'   => $site . '/',
    'og_type'     => 'website',
    'fonts'       => 'serif',
    'json_ld'     => [$homeLd],
    'preload'     => array_values(array_filter([(string)ly_cfg('logo', ''), $heroImage])),
    'preload_priority' => $heroImage !== '' ? [$heroImage => 'high'] : [],
    'active'      => 'home',
    'search'      => true,
], $homeBody);

/* ==========================================================================
   文章列表
   ========================================================================== */
$artTitle = ly_e((string)ly_cfg('articles.title', '文章列表'));
$artSub   = ly_e((string)ly_cfg('articles.subtitle', ''));

$articlesHtml = ly_page([
    'title'       => (string)ly_cfg('articles.title', '文章列表') . ' - ' . $siteName,
    'description' => (string)ly_cfg('articles.subtitle', ''),
    'canonical'   => $site . '/articles/',
    'og_type'     => 'website',
    'fonts'       => 'serif',
    'json_ld'     => [[
        '@context'    => 'https://schema.org',
        '@type'       => 'CollectionPage',
        'name'        => (string)ly_cfg('articles.title', '文章列表'),
        'url'         => $site . '/articles/',
        'description' => (string)ly_cfg('articles.subtitle', ''),
        'inLanguage'  => (string)ly_cfg('lang', 'zh-CN'),
        'isPartOf'    => ['@type' => 'WebSite', 'name' => $siteName, 'url' => $site . '/'],
    ]],
    'active'      => 'articles',
    'search'      => true,
], <<<HTML
<div id="app-content">

  <section class="page-banner">
    <div class="container">
      <h1 id="articles-heading">{$artTitle}</h1>
      <p>{$artSub}</p>
    </div>
  </section>

  <main class="container main-content">
    <div id="search-container" class="search-wrap">
      <div class="search-inner">
        <input type="text" id="search-input" class="search-input" placeholder="输入关键词搜索文章..." aria-label="搜索关键词">
        <svg class="icon search-clear" onclick="LY.clearSearchInput()" role="button" tabindex="0" aria-label="清空搜索框"><use href="/images/icons.svg#times"/></svg>
        <button class="search-submit" onclick="LY.doSearch()" aria-label="执行搜索">搜索</button>
      </div>
    </div>

    <div id="articles-list" class="article-list">
      <div class="article-card">
        <div class="article-card-thumb skeleton"></div>
        <div class="article-card-body"><div class="skeleton" style="height:18px;width:65%"></div></div>
        <div class="skeleton" style="height:14px;width:56px"></div>
      </div>
      <div class="article-card">
        <div class="article-card-thumb skeleton"></div>
        <div class="article-card-body"><div class="skeleton" style="height:18px;width:55%"></div></div>
        <div class="skeleton" style="height:14px;width:56px"></div>
      </div>
    </div>
  </main>
</div>
HTML);

/* ==========================================================================
   关于我们
   ========================================================================== */
$email = (string)ly_cfg('email', '');
$qq    = (string)ly_cfg('qq_group', '');
$gh    = (string)ly_cfg('github', '');

$contact = '';
if ($email !== '') {
    $contact .= '        <div class="contact-item">' . "\n"
              . '          <div class="icon-wrap"><svg class="icon" aria-hidden="true"><use href="/images/icons.svg#envelope"/></svg></div>' . "\n"
              . '          <h4>邮箱</h4>' . "\n"
              . '          <p><a href="mailto:' . ly_e($email) . '">' . ly_e($email) . '</a></p>' . "\n"
              . '        </div>' . "\n";
}
if ($qq !== '') {
    $contact .= '        <div class="contact-item">' . "\n"
              . '          <div class="icon-wrap"><svg class="icon" aria-hidden="true"><use href="/images/icons.svg#qq"/></svg></div>' . "\n"
              . '          <h4>QQ群</h4>' . "\n"
              . '          <p>' . ly_e($qq) . '</p>' . "\n"
              . '        </div>' . "\n";
}
if ($gh !== '') {
    $contact .= '        <a href="' . ly_e($gh) . '" target="_blank" rel="noopener noreferrer" class="contact-item">' . "\n"
              . '          <div class="icon-wrap"><svg class="icon" aria-hidden="true"><use href="/images/icons.svg#github"/></svg></div>' . "\n"
              . '          <h4>GitHub</h4>' . "\n"
              . '          <p>开源项目</p>' . "\n"
              . '        </a>' . "\n";
}

$aboutBody  = '<div id="app-content">' . "\n\n";
$aboutBody .= '  <section class="page-banner">' . "\n";
$aboutBody .= '    <div class="container">' . "\n";
$aboutBody .= '      <h1>' . ly_e(ly_about_title()) . '</h1>' . "\n";
$aboutBody .= '      <p>' . ly_e((string)ly_cfg('about.subtitle', '')) . '</p>' . "\n";
$aboutBody .= '    </div>' . "\n";
$aboutBody .= '  </section>' . "\n\n";
$aboutBody .= '  <main class="container main-content">' . "\n";
$aboutBody .= '    <div class="about-card">' . "\n";
$aboutBody .= '      <div class="about-hero-inner">' . "\n";
$aboutBody .= '        <div class="about-icon blue">' . "\n";
$aboutBody .= '          <svg class="icon" aria-hidden="true"><use href="/images/icons.svg#lightbulb"/></svg>' . "\n";
$aboutBody .= '        </div>' . "\n";
$aboutBody .= '        <h2>我们的理念</h2>' . "\n";
$aboutBody .= '        <p>' . ly_e((string)ly_cfg('about.intro', '')) . '</p>' . "\n";
$aboutBody .= '      </div>' . "\n";
$aboutBody .= '    </div>' . "\n\n";
$aboutBody .= '    <div class="about-grid">' . "\n";
$aboutBody .= '      <div class="about-vision">' . "\n";
$aboutBody .= '        <div class="about-badge on-blue"><svg class="icon" aria-hidden="true"><use href="/images/icons.svg#eye"/></svg></div>' . "\n";
$aboutBody .= '        <h3>愿景</h3>' . "\n";
$aboutBody .= '        <p>' . ly_e((string)ly_cfg('about.vision', '')) . '</p>' . "\n";
$aboutBody .= '      </div>' . "\n";
$aboutBody .= '      <div class="about-mission">' . "\n";
$aboutBody .= '        <div class="about-badge on-light"><svg class="icon" aria-hidden="true"><use href="/images/icons.svg#rocket"/></svg></div>' . "\n";
$aboutBody .= '        <h3>使命</h3>' . "\n";
$aboutBody .= '        <p>' . ly_e((string)ly_cfg('about.mission', '')) . '</p>' . "\n";
$aboutBody .= '      </div>' . "\n";
$aboutBody .= '    </div>' . "\n";

if ($contact !== '') {
    $aboutBody .= "\n" . '    <div class="contact-section">' . "\n";
    $aboutBody .= '      <h3>联系我们</h3>' . "\n";
    $aboutBody .= '      <div class="contact-grid">' . "\n";
    $aboutBody .= $contact;
    $aboutBody .= '      </div>' . "\n";
    $aboutBody .= '    </div>' . "\n";
}
$aboutBody .= '  </main>' . "\n";
$aboutBody .= '</div>';

$aboutHtml = ly_page([
    'title'       => ly_about_title() . ' - ' . $siteName,
    'description' => (string)ly_cfg('about.subtitle', ''),
    'canonical'   => $site . '/about/',
    'og_type'     => 'website',
    'fonts'       => 'serif',
    'json_ld'     => [[
        '@context'    => 'https://schema.org',
        '@type'       => 'Organization',
        'name'        => $siteName,
        'alternateName' => (string)ly_cfg('name_en', ''),
        'url'         => $site . '/',
        'logo'        => $site . (string)ly_cfg('logo', '/images/icon.webp'),
        'description' => (string)ly_cfg('description', ''),
        'email'       => $email,
        'sameAs'      => $gh !== '' ? [$gh] : [],
    ]],
    'active'      => 'about',
    'search'      => false,
], $aboutBody);

/* ==========================================================================
   404
   ========================================================================== */
$notFoundHtml = ly_page([
    'title'       => '页面未找到 - ' . $siteName,
    'description' => '你访问的页面不存在。',
    'canonical'   => $site . '/404.html',
    'robots'      => 'noindex, follow',
    'og_type'     => 'website',
    'fonts'       => 'serif',
    'active'      => '',
    'search'      => false,
], <<<HTML
<div id="app-content">
  <main class="container">
    <div class="error-page">
      <div class="error-code">404</div>
      <h1>页面未找到</h1>
      <p>你访问的页面可能已被移动、删除，或者从未存在过。</p>
      <p>
        <a href="/" class="btn-outline" style="display:inline-block;">返回首页</a>
        <a href="/articles/" class="section-more" style="display:inline-block;margin-left:.75rem;">浏览文章列表 →</a>
      </p>
    </div>
  </main>
</div>
HTML);

/* ==========================================================================
   旧地址兼容页 post.html?id=<旧ID>
   ========================================================================== */
$legacyHtml = ly_page([
    'title'       => '正在跳转 - ' . $siteName,
    'description' => '正在跳转到文章页面。',
    'canonical'   => $site . '/articles/',
    'robots'      => 'noindex, follow',
    'og_type'     => 'website',
    'fonts'       => 'sans',
    'active'      => '',
    'search'      => false,
], <<<'HTML'
<div id="app-content">
  <main class="container">
    <div class="error-page">
      <div class="error-code">→</div>
      <h1>正在跳转到文章…</h1>
      <p id="ly-redirect-msg">如果没有自动跳转，请<a href="/articles/" style="color:#0066ff;text-decoration:underline">返回文章列表</a>。</p>
      <noscript>
        <p>你的浏览器禁用了 JavaScript，请<a href="/articles/" style="color:#0066ff;text-decoration:underline">返回文章列表</a>选择文章。</p>
      </noscript>
    </div>
  </main>
</div>

<script>
/* 旧地址 post.html?id=&lt;id&gt; 的兼容跳转。
   新文章页地址是 /post/&lt;slug&gt;/，这里按 id 找到对应 slug 后跳转。
   服务端 .htaccess 已处理无 id 的情况；此脚本用于带 id 的旧分享链接。 */
(function () {
  'use strict';
  var msg = document.getElementById('ly-redirect-msg');
  var id = new URLSearchParams(location.search).get('id');

  function back(text) {
    msg.innerHTML = text + '请<a href="/articles/" style="color:#0066ff;text-decoration:underline">返回文章列表</a>。';
  }

  if (!id) { back('缺少文章 ID，'); return; }

  fetch('/posts.json', { headers: { Accept: 'application/json' } })
    .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(function (posts) {
      for (var i = 0; i < posts.length; i++) {
        if (String(posts[i].id) === String(id)) {
          if (posts[i].slug) { location.replace('/post/' + encodeURIComponent(posts[i].slug) + '/'); return; }
          back('这篇文章还没有生成独立页面，');
          return;
        }
      }
      back('找不到这篇文章，');
    })
    .catch(function () { back('跳转失败，'); });
})();
</script>
HTML);

/* ==========================================================================
   sitemap.xml / robots.txt
   ========================================================================== */
$posts = ly_load_posts();

// 给缺少 slug 的文章补 slug（旧数据或全新克隆的仓库）
[$posts, $slugFixed] = ly_ensure_slugs($posts);
if ($slugFixed > 0 && !$dryRun) {
    ly_save_posts($posts);
}

$today = date('Y-m-d');

$urls = [
    ['loc' => $site . '/',           'lastmod' => $today, 'freq' => 'daily',   'pri' => '1.0'],
    ['loc' => $site . '/articles/',  'lastmod' => $today, 'freq' => 'daily',   'pri' => '0.8'],
    ['loc' => $site . '/about/',     'lastmod' => $today, 'freq' => 'monthly', 'pri' => '0.5'],
];
foreach (ly_sort_posts($posts) as $p) {
    if (empty($p['slug'])) continue;
    $urls[] = [
        'loc'     => $site . ly_post_url($p),
        'lastmod' => (string)(($p['updated'] ?? '') ?: ($p['date'] ?? $today)),
        'freq'    => 'monthly',
        'pri'     => '0.7',
    ];
}

$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    $xml .= "  <url>\n";
    $xml .= '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
    $xml .= '    <lastmod>' . htmlspecialchars($u['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</lastmod>\n";
    $xml .= '    <changefreq>' . $u['freq'] . "</changefreq>\n";
    $xml .= '    <priority>' . $u['pri'] . "</priority>\n";
    $xml .= "  </url>\n";
}
$xml .= "</urlset>\n";

$robots  = "User-agent: *\n";
$robots .= "Allow: /\n";
$robots .= "Disallow: /admin/\n";
$robots .= "Disallow: /post.html\n";
$robots .= "\n";
$robots .= "# 站内搜索结果页无需收录，避免重复内容\n";
$robots .= "Disallow: /articles/?search=\n";
$robots .= "\n";
$robots .= 'Sitemap: ' . $site . "/sitemap.xml\n";

/* ==========================================================================
   写出
   ========================================================================== */
$targets = [
    'index.html'          => $homeHtml,
    'articles/index.html' => $articlesHtml,
    'about/index.html'    => $aboutHtml,
    '404.html'            => $notFoundHtml,
    'post.html'           => $legacyHtml,
    'sitemap.xml'         => $xml,
    'robots.txt'          => $robots,
];

$fail = 0;
foreach ($targets as $rel => $content) {
    $path = __DIR__ . '/' . $rel;
    $dir  = dirname($path);
    if (!is_dir($dir) && !$dryRun) @mkdir($dir, 0755, true);

    if ($dryRun) {
        printf("  [dry-run] %-24s %6d 字节\n", $rel, strlen($content));
        continue;
    }
    // 无 BOM 写入
    if (@file_put_contents($path, $content, LOCK_EX) === false) {
        fwrite(STDERR, "  写入失败: {$rel}\n");
        $fail++;
    } else {
        printf("  ✓ %-24s %6d 字节\n", $rel, strlen($content));
    }
}

/* 文章页 */
if (!$dryRun) {
    $n = ly_generate_all_post_pages($posts);
    echo "  ✓ post/<slug>/index.html      共 {$n} 篇\n";
}

if ($fail) {
    fwrite(STDERR, "\n有 {$fail} 个文件写入失败，请检查目录权限。\n");
    exit(1);
}
echo "\n完成。共 " . count($posts) . " 篇文章。\n";
