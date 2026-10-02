<?php
/**
 * 绘萤者 — 静态文章页渲染器
 * 由后台保存时调用，生成 /post/<slug>/index.html（正文直接进 HTML，搜索引擎可抓取）
 */

declare(strict_types=1);

/**
 * 把正文/封面里的相对资源路径规范化为站点绝对路径。
 *
 * 文章位于 /post/<slug>/，原先在根目录可用的 ./images/x.webp 会被解析成
 * /post/<slug>/images/x.webp 而 404。这里统一改成 /images/x.webp。
 *
 * 同时改写正文里的站内链接：
 *   post.html?id=<旧ID>  ->  /post/<slug>/
 *   articles.html        ->  /articles/
 *   about.html           ->  /about/
 *   index.html           ->  /
 * 否则文章里链到另一篇文章时也会 404。
 */
function ly_normalize_asset_paths(string $html, array $posts = []): string
{
    // 旧文章 ID -> slug
    $slugById = [];
    foreach ($posts as $p) {
        if (isset($p['id'], $p['slug']) && (string)$p['slug'] !== '') {
            $slugById[(string)$p['id']] = (string)$p['slug'];
        }
    }

    $pageMap = [
        'index.html'    => '/',
        'articles.html' => '/articles/',
        'about.html'    => '/about/',
        'articles'      => '/articles/',
        'about'         => '/about/',
    ];

    return preg_replace_callback(
        '#\b(src|href)\s*=\s*(["\'])(.*?)\2#is',
        static function ($m) use ($slugById, $pageMap) {
            $attr = $m[1];
            $q    = $m[2];
            $url  = $m[3];

            // 已是绝对地址、协议相对、锚点、data: 的一律不动
            if ($url === '' || preg_match('#^(https?:|//|\#|data:|mailto:|tel:)#i', $url)) {
                return $m[0];
            }

            // 去掉可能的 ./ 或 ../ 前缀后再判断
            $bare = preg_replace('#^(?:\.{1,2}/)+#', '', $url) ?? $url;

            // 旧页面链接 -> 新地址
            if (isset($pageMap[$bare])) {
                return $attr . '=' . $q . $pageMap[$bare] . $q;
            }

            // post.html?id=<旧ID> -> /post/<slug>/
            if (preg_match('#^post\.html\?id=(.*)$#i', $bare, $mm)) {
                $id = rawurldecode($mm[1]);
                $to = isset($slugById[$id])
                    ? '/post/' . rawurlencode($slugById[$id]) . '/'
                    : '/articles/';
                return $attr . '=' . $q . $to . $q;
            }

            // images/ 或其它相对路径 -> 以站点根为基准
            if (preg_match('#^\.{1,2}/#', $url) || preg_match('#^images/#', $bare)) {
                $url = '/' . ltrim($bare, '/');
            }

            return $attr . '=' . $q . $url . $q;
        },
        $html
    ) ?? $html;
}

function ly_render_post_page(array $post, array $all): string
{
    $site      = ly_site_url();
    $siteName  = ly_site_name();
    $title     = (string)$post['title'];
    $slug      = (string)$post['slug'];
    $canonical = $site . '/post/' . rawurlencode($slug) . '/';
    $cover     = (string)$post['cover'];
    // 封面同样需要根相对路径，否则 /post/<slug>/ 下会解析错误
    $coverSrc  = '';
    if ($cover !== '') {
        $coverSrc = preg_match('#^https?://#i', $cover)
            ? $cover
            : '/' . ltrim(preg_replace('#^(?:\.{1,2}/)+#', '', $cover) ?? $cover, '/');
    }
    $coverAbs  = $coverSrc !== ''
        ? (preg_match('#^https?://#i', $coverSrc) ? $coverSrc : $site . $coverSrc)
        : $site . '/images/icon.webp';
    $desc      = trim((string)$post['description']) !== ''
        ? (string)$post['description']
        : ly_excerpt((string)$post['content'], 120);
    $author    = (string)$post['author'] ?: $siteName;
    $date      = (string)$post['date'];
    $updated   = (string)($post['updated'] ?: $date);
    $tags      = is_array($post['tags'] ?? null) ? $post['tags'] : [];

    $bodyHtml = ly_normalize_asset_paths(ly_markdown_to_html((string)$post['content']), $all);

    /* ---- 上下篇（按日期倒序，与原站语义一致） ---- */
    $sorted = ly_sort_posts($all);
    $idx    = null;
    foreach ($sorted as $i => $p) {
        if ((string)$p['id'] === (string)$post['id']) { $idx = $i; break; }
    }
    $newer = ($idx !== null && isset($sorted[$idx - 1])) ? $sorted[$idx - 1] : null;  // 上一篇（更新）
    $older = ($idx !== null && isset($sorted[$idx + 1])) ? $sorted[$idx + 1] : null;  // 下一篇（更早）

    /* ---- 推荐阅读：同标签优先，其余按日期补齐 ---- */
    $related = [];
    foreach ($sorted as $p) {
        if ((string)$p['id'] === (string)$post['id']) continue;
        $shared = array_intersect($tags, is_array($p['tags'] ?? null) ? $p['tags'] : []);
        if ($shared) $related[] = $p;
    }
    foreach ($sorted as $p) {
        if (count($related) >= 3) break;
        if ((string)$p['id'] === (string)$post['id']) continue;
        foreach ($related as $r) { if ((string)$r['id'] === (string)$p['id']) continue 2; }
        $related[] = $p;
    }
    $related = array_slice($related, 0, 3);

    $e = fn($s) => ly_e((string)$s);

    /* ---- 结构化数据 ---- */
    $ld = [
        '@context'         => 'https://schema.org',
        '@type'            => 'BlogPosting',
        'headline'         => $title,
        'description'      => $desc,
        'image'            => [$coverAbs],
        'datePublished'    => $date,
        'dateModified'     => $updated,
        'inLanguage'       => 'zh-CN',
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
        'author'           => ['@type' => 'Organization', 'name' => $author, 'url' => $site . '/'],
        'publisher'        => [
            '@type' => 'Organization',
            'name'  => $siteName,
            'logo'  => ['@type' => 'ImageObject', 'url' => $site . '/images/icon.webp'],
        ],
    ];
    if ($tags) $ld['keywords'] = implode(',', $tags);

    $ldBreadcrumb = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => '首页',     'item' => $site . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => '文章列表', 'item' => $site . '/articles/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $title,     'item' => $canonical],
        ],
    ];

    $jsonLd = json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $jsonLdBc = json_encode($ldBreadcrumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    /* ---- 组装页面主体 ---- */
    $body   = [];
    $body[] = '<div id="app-content" class="container main-content">';
    $body[] = '';
    $body[] = '  <nav class="breadcrumb" aria-label="面包屑">';
    $body[] = '    <a href="/">首页</a><span>/</span><a href="/articles/">' . $e(ly_cfg('articles.title', '文章列表')) . '</a><span>/</span>';
    $body[] = '    <span class="current">' . $e($title) . '</span>';
    $body[] = '  </nav>';
    $body[] = '';
    $body[] = '  <main class="post-shell">';
    $body[] = '    <article class="post-article">';
    $body[] = '      <div class="post-hero">';
    if ($coverSrc !== '') {
        $body[] = '        <img id="post-cover" src="' . $e($coverSrc) . '" alt="' . $e($title) . '" fetchpriority="high" width="896" height="360">';
    }
    $body[] = '        <div class="post-hero-mask"></div>';
    $body[] = '        <div class="post-hero-text">';
    $body[] = '          <h1 class="post-title">' . $e($title) . '</h1>';
    $body[] = '          <div class="post-meta">';
    $body[] = '            <span class="date-badge">' . $e($date) . '</span>';
    $body[] = '            <span class="meta-item"><svg class="icon" aria-hidden="true"><use href="/images/icons.svg#user"/></svg>' . $e($author) . '</span>';
    $body[] = '          </div>';
    $body[] = '        </div>';
    $body[] = '      </div>';
    $body[] = '      <div class="post-body">';
    if ($tags) {
        $body[] = '        <div class="post-tags">';
        foreach ($tags as $t) $body[] = '          <span class="tag">' . $e($t) . '</span>';
        $body[] = '        </div>';
    }
    $body[] = '        <div id="post-content" class="prose">' . $bodyHtml . '</div>';
    $body[] = '        <div class="post-footer">';
    $body[] = '          <span>最后更新于 ' . $e($updated) . '</span>';
    $body[] = '          <a onclick="LY.sharePost()"><svg class="icon" aria-hidden="true"><use href="/images/icons.svg#share-alt"/></svg> 分享</a>';
    $body[] = '        </div>';
    $body[] = '      </div>';
    $body[] = '    </article>';

    /* 上下篇 */
    $body[] = '    <div class="post-nav">';
    if ($newer) {
        $body[] = '      <a href="' . $e(ly_post_url($newer)) . '"><span class="nav-label">← 上一篇</span><div class="nav-title">' . $e($newer['title']) . '</div></a>';
    } else {
        $body[] = '      <div class="spacer"></div>';
    }
    if ($older) {
        $body[] = '      <a href="' . $e(ly_post_url($older)) . '" class="next"><span class="nav-label">下一篇 →</span><div class="nav-title">' . $e($older['title']) . '</div></a>';
    } else {
        $body[] = '      <div class="spacer"></div>';
    }
    $body[] = '    </div>';

    /* 作者卡片 + 推荐阅读 */
    $github = (string)ly_cfg('github', '');
    $body[] = '    <div class="side-grid">';
    $body[] = '      <div class="side-card">';
    $body[] = '        <div class="author-row">';
    $body[] = '          <img src="' . $e((string)ly_cfg('logo', '/images/icon.webp')) . '" alt="' . $e($siteName) . '" width="48" height="48" loading="lazy">';
    $body[] = '          <div><div class="name">' . $e($author) . '</div><div class="motto">' . $e((string)ly_cfg('eyebrow', 'Fight for Freedom')) . '</div></div>';
    $body[] = '        </div>';
    $body[] = '        <p class="desc">' . $e((string)ly_cfg('description', '')) . '</p>';
    if ($github !== '') {
        $body[] = '        <a href="' . $e($github) . '" target="_blank" rel="noopener noreferrer" class="btn-outline"><svg class="icon" aria-hidden="true"><use href="/images/icons.svg#github"/></svg> 在 GitHub 上关注我们</a>';
    }
    $body[] = '      </div>';
    $body[] = '      <div class="side-card">';
    $body[] = '        <h4>推荐阅读</h4>';
    $body[] = '        <div class="related-list">';
    foreach ($related as $r) {
        $body[] = '          <a href="' . $e(ly_post_url($r)) . '"><div class="r-title">' . $e($r['title']) . '</div><div class="r-date">' . $e($r['date']) . '</div></a>';
    }
    $body[] = '        </div>';
    $body[] = '      </div>';
    $body[] = '    </div>';
    $body[] = '  </main>';
    $body[] = '</div>';

    /* ---- 交给共享布局输出 ---- */
    return ly_page([
        'title'        => $title . ' - ' . $siteName,
        'description'  => $desc,
        'canonical'    => $canonical,
        'keywords'     => $tags ? implode(',', $tags) : '',
        'og_type'      => 'article',
        'og_image'     => $coverAbs,
        'og_extra'     => [
            'article:published_time' => $date,
            'article:modified_time'  => $updated,
        ],
        'fonts'        => 'sans',
        'json_ld'      => [$ld, $ldBreadcrumb],
        'css'          => ['/assets/vendor/github-dark.min.css'],
        'active'       => 'articles',
        'search'       => true,
        'post_scripts' => true,
        'body_prefix'  => '<div id="reading-progress"></div>' . "\n"
                        . '<div id="toast" role="status">✅ 复制成功，快去分享给好友吧！</div>',
    ], implode("\n", $body));
}
