/* ==========================================================================
   绘萤者 lynvortex.top — 全站共享脚本
   导航、搜索、文章列表渲染统一在此实现，页面只负责调用。
   ========================================================================== */
(function (global) {
  'use strict';

  // 必须用站点绝对路径：页面可能位于 /articles/ 等子目录下，
  // 相对路径会解析成 /articles/posts.json 而 404。
  var POSTS_URL = '/posts.json';

  /* ---------------- 工具 ---------------- */

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function getQuery(name) {
    return new URLSearchParams(global.location.search).get(name);
  }

  /* 文章地址：优先使用生成的静态页 /post/<slug>/，回退到查询串形式 */
  function postUrl(post) {
    if (post && post.slug) return '/post/' + encodeURIComponent(post.slug) + '/';
    return 'post.html?id=' + encodeURIComponent(post ? post.id : '');
  }

  function parseDate(str) {
    var parts = String(str || '').split('-');
    if (parts.length !== 3) return new Date(0);
    return new Date(+parts[0], +parts[1] - 1, +parts[2]);
  }

  /* 站内资源路径归一化。
     posts.json 里封面存的是 ./images/covers/x.webp 这类相对路径，
     页面位于 /articles/ 等子目录时会解析成 /articles/images/... 而 404，
     因此统一转成以站点根为基准的绝对路径。 */
  function assetUrl(p) {
    var s = String(p == null ? '' : p);
    if (s === '' || /^(https?:|\/\/|\/|data:)/i.test(s)) return s;
    return '/' + s.replace(/^(?:\.{1,2}\/)+/, '');
  }

  function formatDate(str) { return String(str || ''); }

  /* ---------------- 导航 ---------------- */

  function initNav() {
    var btn = $('#mobile-menu-btn');
    var menu = $('#mobile-menu');
    if (btn && menu) {
      btn.addEventListener('click', function () {
        var open = menu.classList.toggle('open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      $$('#mobile-menu a').forEach(function (a) {
        a.addEventListener('click', function () { menu.classList.remove('open'); });
      });
    }

    var top = $('#back-to-top');
    if (top) {
      var onScroll = function () {
        top.classList.toggle('visible', global.scrollY > 300);
      };
      global.addEventListener('scroll', onScroll, { passive: true });
      onScroll();
    }

    var input = $('#search-input');
    if (input) {
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
      });
    }
  }

  /* ---------------- 搜索 ---------------- */

  function toggleSearch() {
    var wrap = $('#search-container');
    if (!wrap) return;
    var shown = wrap.classList.toggle('show');
    if (shown) {
      var input = $('#search-input');
      if (input) input.focus();
    }
  }

  function clearSearchInput() {
    var input = $('#search-input');
    if (input) { input.value = ''; input.focus(); }
    var list = $('#articles-list');
    if (list && list.dataset.all) {
      renderArticleList(list, JSON.parse(list.dataset.all));
    }
  }

  function doSearch() {
    var input = $('#search-input');
    var kw = input ? input.value.trim() : '';
    if (!kw) { alert('请输入搜索关键词'); return; }
    global.location.href = '/articles/?search=' + encodeURIComponent(kw);
  }

  /* ---------------- 文章数据 ---------------- */

  function loadPosts() {
    return fetch(POSTS_URL, { headers: { Accept: 'application/json' } })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (data) {
        return data.slice().sort(function (a, b) { return parseDate(b.date) - parseDate(a.date); });
      });
  }

  /* ---------------- 文章卡片 ---------------- */

  function cardHtml(post) {
    var tags = (post.tags && post.tags.length)
      ? '<div class="article-card-tags">' + post.tags.slice(0, 3).map(function (t) {
          return '<span class="tag">' + escapeHtml(t) + '</span>';
        }).join('') + '</div>'
      : '';
    var desc = post.description
      ? '<p class="article-card-desc">' + escapeHtml(post.description) + '</p>'
      : '';
    var thumb = post.cover
      ? '<img class="article-card-thumb" src="' + escapeHtml(assetUrl(post.cover)) + '" alt="' + escapeHtml(post.title) + '" width="96" height="72" loading="lazy">'
      : '<div class="article-card-thumb-placeholder" aria-hidden="true"></div>';

    return '<a class="article-card" href="' + escapeHtml(postUrl(post)) + '">' +
      thumb +
      '<div class="article-card-body">' +
        '<h3 class="article-card-title">' + escapeHtml(post.title) + '</h3>' +
        desc + tags +
      '</div>' +
      '<span class="article-card-date">' + escapeHtml(formatDate(post.date)) + '</span>' +
    '</a>';
  }

  function showAllCardHtml() {
    return '<a class="article-card" href="/articles/">' +
      '<div class="article-card-thumb-placeholder" aria-hidden="true">' +
        '<svg class="icon"><use href="/images/icons.svg#arrow-right"/></svg>' +
      '</div>' +
      '<div class="article-card-body"><h3 class="article-card-title all-link">查看全部文章</h3></div>' +
      '<span class="article-card-date">更多 →</span>' +
    '</a>';
  }

  function renderArticleList(container, posts, opts) {
    opts = opts || {};
    var limit = opts.limit || posts.length;
    var list = posts.slice(0, limit);
    if (!list.length) {
      container.innerHTML = '<p class="no-result">没有找到匹配的文章</p>';
      return;
    }
    var html = list.map(cardHtml).join('');
    if (opts.showAll) html += showAllCardHtml();
    container.innerHTML = html;
  }

  /* ---------------- 骨架屏 ---------------- */

  function skeletonHtml(n) {
    var one = '<div class="article-card">' +
      '<div class="article-card-thumb skeleton"></div>' +
      '<div class="article-card-body"><div class="skeleton" style="height:18px;width:65%"></div></div>' +
      '<div class="skeleton" style="height:14px;width:56px"></div>' +
    '</div>';
    return new Array(n || 5).join(one) + one;
  }

  /* ---------------- 自动初始化 ---------------- */

  function autoInit() {
    initNav();

    var home = $('#home-latest-list');
    if (home) {
      home.innerHTML = skeletonHtml(6);
      loadPosts().then(function (posts) {
        renderArticleList(home, posts, { limit: 6, showAll: true });
      }).catch(function (err) {
        console.error('加载最新文章失败：', err);
        home.innerHTML = '<p class="no-result">加载文章失败，请稍后重试</p>';
      });
    }

    var listEl = $('#articles-list');
    if (listEl && !listEl.dataset.manual) {
      listEl.innerHTML = skeletonHtml(4);
      loadPosts().then(function (posts) {
        listEl.dataset.all = JSON.stringify(posts);
        var kw = (getQuery('search') || '').trim();
        var filtered = posts;
        if (kw) {
          var low = kw.toLowerCase();
          filtered = posts.filter(function (p) {
            return (p.title || '').toLowerCase().indexOf(low) !== -1 ||
                   (p.description || '').toLowerCase().indexOf(low) !== -1 ||
                   (p.content || '').toLowerCase().indexOf(low) !== -1;
          });
          var wrap = $('#search-container');
          if (wrap) {
            wrap.classList.add('show');
            var input = $('#search-input');
            if (input) input.value = kw;
          }
          var head = $('#articles-heading');
          if (head) head.textContent = '搜索「' + kw + '」的结果（' + filtered.length + ' 篇）';
        }
        renderArticleList(listEl, filtered);
      }).catch(function (err) {
        console.error('加载文章列表失败：', err);
        listEl.innerHTML = '<p class="no-result">加载失败，请稍后重试</p>';
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoInit);
  } else {
    autoInit();
  }

  /* ---------------- 导出 ---------------- */
  global.LY = {
    $: $, $$: $$,
    escapeHtml: escapeHtml,
    getQuery: getQuery,
    postUrl: postUrl,
    parseDate: parseDate,
    initNav: initNav,
    toggleSearch: toggleSearch,
    clearSearchInput: clearSearchInput,
    doSearch: doSearch,
    loadPosts: loadPosts,
    cardHtml: cardHtml,
    renderArticleList: renderArticleList,
    skeletonHtml: skeletonHtml
  };
})(window);
