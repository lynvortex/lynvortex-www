/* ==========================================================================
   绘萤者 — 后台编辑器
   ========================================================================== */
(function () {
  'use strict';

  var CSRF = window.LY_CSRF || '';
  var $ = function (s) { return document.querySelector(s); };

  var el = {
    list:      $('#ly-list'),
    count:     $('#ly-count'),
    title:     $('#ly-title'),
    slug:      $('#ly-slug'),
    date:      $('#ly-date'),
    author:    $('#ly-author'),
    tags:      $('#ly-tags'),
    desc:      $('#ly-desc'),
    cover:     $('#ly-cover'),
    coverPrev: $('#ly-cover-preview'),
    coverFile: $('#ly-cover-file'),
    content:   $('#ly-content'),
    preview:   $('#ly-preview'),
    meta:      $('#ly-meta'),
    status:    $('#ly-status'),
    save:      $('#ly-save')
  };

  var state = { posts: [], currentId: '', dirty: false };

  /* ---------------- 工具 ---------------- */
  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function status(msg, kind) {
    el.status.textContent = msg;
    el.status.className = 'ly-status show ' + (kind || '');
    clearTimeout(status._t);
    status._t = setTimeout(function () { el.status.classList.remove('show'); }, 4000);
  }

  function api(action, payload) {
    payload = payload || {};
    payload.action = action;
    payload.csrf = CSRF;
    return fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) {
      return r.json().catch(function () { throw new Error('服务器返回了非 JSON 响应（HTTP ' + r.status + '）'); });
    }).then(function (d) {
      if (r_is401(d)) { location.href = 'login.php'; throw new Error('会话已过期'); }
      if (!d.ok) throw new Error(d.error || '操作失败');
      return d;
    });
  }
  function r_is401(d) { return d && d.error && /未登录/.test(d.error); }

  /* ---------------- Markdown 预览 ---------------- */
  function renderPreview() {
    var text = el.content.value;
    if (!text.trim()) { el.preview.innerHTML = '<span class="ly-muted">预览区</span>'; return; }
    try {
      if (window.marked && window.DOMPurify) {
        var raw = window.marked.parse(text, { breaks: true, gfm: true });
        el.preview.innerHTML = window.DOMPurify.sanitize(raw, { ADD_ATTR: ['target', 'loading'] });
      } else {
        el.preview.textContent = text;
      }
      el.preview.querySelectorAll('pre code').forEach(function (b) {
        if (window.hljs) { try { window.hljs.highlightElement(b); } catch (e) {} }
      });
    } catch (e) {
      el.preview.textContent = '预览失败：' + e.message;
    }
  }

  /* ---------------- 文章列表 ---------------- */
  function loadList(selectId) {
    return api('list').then(function (d) {
      state.posts = d.posts || [];
      el.count.textContent = state.posts.length;
      if (!state.posts.length) {
        el.list.innerHTML = '<div class="ly-empty">还没有文章，点击「新建文章」开始。</div>';
        return;
      }
      el.list.innerHTML = state.posts.map(function (p) {
        return '<label class="ly-post-item' + (p.id === state.currentId ? ' active' : '') + '" data-id="' + esc(p.id) + '">' +
          '<input type="checkbox" value="' + esc(p.id) + '">' +
          '<span class="t" title="' + esc(p.title) + '">' + esc(p.title) + '</span>' +
          '<span class="d">' + esc(p.date) + '</span>' +
        '</label>';
      }).join('');

      el.list.querySelectorAll('.ly-post-item').forEach(function (row) {
        row.addEventListener('click', function (e) {
          if (e.target.tagName === 'INPUT') return;
          e.preventDefault();
          openPost(row.dataset.id);
        });
      });

      if (selectId) {
        var cur = el.list.querySelector('.ly-post-item[data-id="' + CSS.escape(selectId) + '"]');
        if (cur) cur.classList.add('active');
      }
    }).catch(function (e) { status(e.message, 'err'); });
  }

  /* ---------------- 打开 / 新建 ---------------- */
  function openPost(id) {
    if (state.dirty && !confirm('当前有未保存的修改，确定要放弃吗？')) return;
    api('get', { id: id }).then(function (d) {
      var p = d.post;
      state.currentId = p.id;
      el.title.value = p.title || '';
      el.slug.value = p.slug || '';
      el.date.value = p.date || '';
      el.author.value = p.author || '';
      el.tags.value = (p.tags || []).join(', ');
      el.desc.value = p.description || '';
      el.cover.value = p.cover || '';
      el.content.value = p.content || '';
      state.dirty = false;
      updateCoverPreview();
      renderPreview();
      updateMeta();
      loadList(p.id);
      document.title = (p.title || '文章') + ' - 绘萤者后台';
    }).catch(function (e) { status(e.message, 'err'); });
  }

  function newPost() {
    if (state.dirty && !confirm('当前有未保存的修改，确定要放弃吗？')) return;
    state.currentId = '';
    el.title.value = '';
    el.slug.value = '';
    el.date.value = new Date().toISOString().slice(0, 10);
    el.author.value = '绘萤者';
    el.tags.value = '';
    el.desc.value = '';
    el.cover.value = '';
    el.content.value = '';
    state.dirty = false;
    updateCoverPreview();
    renderPreview();
    updateMeta();
    loadList();
    document.title = '写文章 - 绘萤者后台';
    el.title.focus();
  }

  function updateMeta() {
    var n = el.content.value.length;
    var url = state.currentId && el.slug.value ? '/post/' + el.slug.value + '/' : '（保存后生成）';
    el.meta.textContent = n + ' 字符 · ' + url + (state.dirty ? ' · 未保存' : '');
  }

  function updateCoverPreview() {
    var v = el.cover.value.trim();
    if (v) { el.coverPrev.src = v; el.coverPrev.style.display = 'block'; }
    else { el.coverPrev.style.display = 'none'; el.coverPrev.removeAttribute('src'); }
  }

  /* ---------------- 保存 ---------------- */
  function save() {
    var payload = {
      id: state.currentId,
      title: el.title.value.trim(),
      slug: el.slug.value.trim(),
      date: el.date.value,
      author: el.author.value.trim(),
      tags: el.tags.value,
      description: el.desc.value.trim(),
      cover: el.cover.value.trim(),
      content: el.content.value
    };
    if (!payload.title) { status('请填写标题', 'err'); el.title.focus(); return; }
    if (!payload.content.trim()) { status('正文不能为空', 'err'); el.content.focus(); return; }

    el.save.disabled = true;
    el.save.textContent = '保存中…';

    api('save', payload).then(function (d) {
      state.currentId = d.id;
      state.dirty = false;
      if (d.slug) el.slug.value = d.slug;
      status(d.message + '（' + d.url + '）', 'ok');
      updateMeta();
      loadList(d.id);
    }).catch(function (e) {
      status(e.message, 'err');
    }).finally(function () {
      el.save.disabled = false;
      el.save.textContent = '💾 保存并生成静态页';
    });
  }

  /* ---------------- 删除 ---------------- */
  function deleteSelected() {
    var ids = Array.prototype.slice.call(el.list.querySelectorAll('input:checked')).map(function (c) { return c.value; });
    if (!ids.length) { status('请先勾选要删除的文章', 'err'); return; }
    if (!confirm('确定删除选中的 ' + ids.length + ' 篇文章？此操作不可撤销。')) return;
    api('delete', { ids: ids }).then(function (d) {
      status(d.message, 'ok');
      if (ids.indexOf(state.currentId) !== -1) newPost();
      loadList();
    }).catch(function (e) { status(e.message, 'err'); });
  }

  /* ---------------- 上传 ---------------- */
  function uploadFile(file) {
    var fd = new FormData();
    fd.append('action', 'upload');
    fd.append('csrf', CSRF);
    fd.append('file', file);
    return fetch('api.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) throw new Error(d.error || '上传失败');
        return d;
      });
  }

  function insertAtCursor(text) {
    var ta = el.content;
    var s = ta.selectionStart, e = ta.selectionEnd;
    ta.value = ta.value.slice(0, s) + text + ta.value.slice(e);
    ta.selectionStart = ta.selectionEnd = s + text.length;
    ta.focus();
    state.dirty = true;
    renderPreview();
    updateMeta();
  }

  function wrapSelection(before, after, placeholder) {
    var ta = el.content;
    var s = ta.selectionStart, e = ta.selectionEnd;
    var sel = ta.value.slice(s, e) || placeholder || '';
    ta.value = ta.value.slice(0, s) + before + sel + after + ta.value.slice(e);
    ta.selectionStart = s + before.length;
    ta.selectionEnd = s + before.length + sel.length;
    ta.focus();
    state.dirty = true;
    renderPreview();
    updateMeta();
  }

  /* ---------------- Markdown 工具栏 ---------------- */
  var MD_ACTIONS = {
    'h2':        function () { wrapSelection('\n## ', '\n', '小标题'); },
    'h3':        function () { wrapSelection('\n### ', '\n', '小标题'); },
    'bold':      function () { wrapSelection('**', '**', '粗体文字'); },
    'italic':    function () { wrapSelection('*', '*', '斜体文字'); },
    'link':      function () { wrapSelection('[', '](https://)', '链接文字'); },
    'code':      function () { wrapSelection('\n```\n', '\n```\n', '在此输入代码'); },
    'quote':     function () { wrapSelection('\n> ', '\n', '引用内容'); },
    'list':      function () { wrapSelection('\n- ', '\n- ', '列表项'); },
    'hr':        function () { insertAtCursor('\n\n---\n\n'); },
    'callout-note': function () {
      insertAtCursor('\n<div class="callout callout-note">\n<p class="callout-title">ℹ️ 注意</p>\n<p>提示内容</p>\n</div>\n\n');
    },
    'imggrid':   function () {
      insertAtCursor('\n<div class="img-grid">\n  <figure class="img-item">\n    <img src="./images/example.webp" alt="说明" loading="lazy">\n    <figcaption>图片说明</figcaption>\n  </figure>\n  <figure class="img-item">\n    <img src="./images/example2.webp" alt="说明" loading="lazy">\n    <figcaption>图片说明</figcaption>\n  </figure>\n</div>\n\n');
    }
  };

  /* ---------------- 事件绑定 ---------------- */
  function bind() {
    $('#ly-new').addEventListener('click', newPost);
    $('#ly-refresh').addEventListener('click', function () { loadList(state.currentId); });
    $('#ly-save').addEventListener('click', save);
    $('#ly-delete').addEventListener('click', deleteSelected);

    $('#ly-rebuild').addEventListener('click', function () {
      if (!confirm('重新生成所有文章的静态页与 sitemap？')) return;
      api('rebuild').then(function (d) { status(d.message, 'ok'); })
        .catch(function (e) { status(e.message, 'err'); });
    });

    el.content.addEventListener('input', function () {
      state.dirty = true;
      renderPreview();
      updateMeta();
    });
    el.slug.addEventListener('input', updateMeta);
    el.cover.addEventListener('change', updateCoverPreview);

    ['title', 'slug', 'date', 'author', 'tags', 'desc', 'cover'].forEach(function (k) {
      el[k].addEventListener('input', function () { state.dirty = true; updateMeta(); });
    });

    document.querySelectorAll('[data-md]').forEach(function (b) {
      b.addEventListener('click', function () {
        var fn = MD_ACTIONS[b.dataset.md];
        if (fn) fn();
      });
    });

    // Tab 缩进
    el.content.addEventListener('keydown', function (e) {
      if (e.key === 'Tab') {
        e.preventDefault();
        insertAtCursor('  ');
      }
      if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        save();
      }
    });

    // 预览开关
    $('#ly-preview-toggle').addEventListener('click', function () {
      var grid = document.querySelector('.ly-editor-grid');
      var single = grid.style.gridTemplateColumns === '1fr';
      grid.style.gridTemplateColumns = single ? '1fr 1fr' : '1fr';
      el.preview.style.display = single ? '' : 'none';
    });

    // 封面 / 正文图片上传
    $('#ly-upload-cover').addEventListener('click', function () { el.coverFile.click(); });
    el.coverFile.addEventListener('change', function () {
      var f = this.files[0];
      if (!f) return;
      status('上传中…');
      uploadFile(f).then(function (d) {
        el.cover.value = d.url;
        updateCoverPreview();
        state.dirty = true;
        status('封面已上传', 'ok');
      }).catch(function (e) { status(e.message, 'err'); });
      this.value = '';
    });

    $('#ly-upload-img').addEventListener('click', function () { $('#ly-img-file').click(); });
    $('#ly-img-file').addEventListener('change', function () {
      var files = Array.prototype.slice.call(this.files);
      if (!files.length) return;
      status('上传 ' + files.length + ' 张图片…');
      var done = 0;
      files.forEach(function (f) {
        uploadFile(f).then(function (d) {
          insertAtCursor('\n' + d.markdown + '\n');
          done++;
          if (done === files.length) status('已插入 ' + done + ' 张图片', 'ok');
        }).catch(function (e) { status(e.message, 'err'); });
      });
      this.value = '';
    });

    window.addEventListener('beforeunload', function (e) {
      if (state.dirty) { e.preventDefault(); e.returnValue = ''; }
    });
  }

  /* ---------------- 启动 ---------------- */
  document.addEventListener('DOMContentLoaded', function () {
    bind();
    if (!el.date.value) el.date.value = new Date().toISOString().slice(0, 10);
    loadList().then(function () {
      if (state.posts.length) openPost(state.posts[0].id);
      else newPost();
    });
    renderPreview();
  });
})();
