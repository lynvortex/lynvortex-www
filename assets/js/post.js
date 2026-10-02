/* ==========================================================================
   绘萤者 — 文章详情页增强
   正文已由服务端渲染进 HTML，这里只做渐进增强：
   代码高亮、复制按钮、阅读进度、分享、提示框兼容。
   ========================================================================== */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  /* ---------------- 分享 ---------------- */
  function sharePost() {
    var title = (document.querySelector('.post-title') || {}).textContent || document.title;
    var url = location.href;
    var text = '我在绘萤者上看到一篇超棒的文章：' + title + ' ' + url;

    if (navigator.share) {
      navigator.share({ title: title, text: text, url: url }).catch(function () {});
      return;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(showToast).catch(function () {
        window.prompt('复制下面的链接分享：', url);
      });
    } else {
      window.prompt('复制下面的链接分享：', url);
    }
  }

  function showToast() {
    var t = document.getElementById('toast');
    if (!t) return;
    t.classList.add('show');
    setTimeout(function () { t.classList.remove('show'); }, 1800);
  }

  /* ---------------- 阅读进度 ---------------- */
  function initProgress() {
    var bar = document.getElementById('reading-progress');
    if (!bar) return;
    var update = function () {
      var el = document.documentElement;
      var max = el.scrollHeight - el.clientHeight;
      var pct = max > 0 ? (el.scrollTop / max) * 100 : 0;
      bar.style.width = Math.min(100, Math.max(0, pct)) + '%';
    };
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
  }

  /* ---------------- 代码块：高亮 + 复制 ---------------- */
  function initCode() {
    var content = document.getElementById('post-content');
    if (!content) return;

    content.querySelectorAll('pre code').forEach(function (block) {
      if (window.hljs && !block.dataset.highlighted) {
        try { window.hljs.highlightElement(block); } catch (e) {}
      }
    });

    content.querySelectorAll('pre').forEach(function (pre) {
      if (pre.parentNode && pre.parentNode.classList.contains('code-block-wrapper')) return;

      var wrapper = document.createElement('div');
      wrapper.className = 'code-block-wrapper';
      pre.parentNode.insertBefore(wrapper, pre);
      wrapper.appendChild(pre);

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'copy-btn';
      btn.textContent = '复制代码';
      btn.addEventListener('click', function () {
        var text = pre.innerText;
        var done = function () {
          btn.textContent = '已复制!';
          setTimeout(function () { btn.textContent = '复制代码'; }, 2000);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(done).catch(function () { btn.textContent = '复制失败'; });
        } else {
          var ta = document.createElement('textarea');
          ta.value = text; document.body.appendChild(ta); ta.select();
          try { document.execCommand('copy'); done(); } catch (e) { btn.textContent = '复制失败'; }
          document.body.removeChild(ta);
        }
      });
      wrapper.appendChild(btn);
    });
  }

  /* ---------------- 引用块 → 提示框（兼容 Markdown 写法） ---------------- */
  function initCallouts() {
    var content = document.getElementById('post-content');
    if (!content) return;
    content.querySelectorAll('blockquote').forEach(function (bq) {
      var p = bq.querySelector('p');
      if (!p) return;
      var m = p.innerHTML.match(/^\[!(NOTE|TIP|WARNING|DANGER)\]\s*([\s\S]*)/i);
      if (!m) return;
      var type = m[1].toLowerCase();
      var icons = { note: 'ℹ️', tip: '💡', warning: '⚠️', danger: '🚨' };
      var titles = { note: '注意', tip: '提示', warning: '警告', danger: '危险' };
      bq.className = 'callout callout-' + type;
      bq.innerHTML = '<p class="callout-title">' + icons[type] + ' ' + titles[type] + '</p><p>' + m[2] + '</p>';
    });
  }

  /* ---------------- 图片：加载失败兜底 ---------------- */
  function initImages() {
    var content = document.getElementById('post-content');
    if (!content) return;
    content.querySelectorAll('img').forEach(function (img) {
      img.addEventListener('error', function () {
        img.style.display = 'none';
      });
    });
  }

  ready(function () {
    initProgress();
    initCode();
    initCallouts();
    initImages();
  });

  window.LY = window.LY || {};
  window.LY.sharePost = sharePost;
  window.LY.showToast = showToast;
})();
