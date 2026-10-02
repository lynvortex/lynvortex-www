<?php
/**
 * 绘萤者 — 后台主界面
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
ly_require_login();

$csrf = ly_csrf_token();
?>
<?= ly_admin_head('写文章') ?>
<body>

<?= ly_admin_topbar('write', '<button class="ly-btn" id="ly-new">＋ 新建文章</button>'
                         . '<button class="ly-btn" id="ly-rebuild">重建静态页</button>') ?>

<?= ly_default_password_notice() ?>

<div class="ly-layout">

  <!-- 左：文章列表 -->
  <aside class="ly-panel">
    <h2>文章（<span id="ly-count">0</span>）</h2>
    <div class="ly-post-list" id="ly-list"></div>
    <div style="display:flex;gap:.5rem;margin-top:.75rem">
      <button class="ly-btn" id="ly-refresh" style="flex:1">刷新</button>
      <button class="ly-btn ly-btn-danger" id="ly-delete" style="flex:1">删除选中</button>
    </div>
  </aside>

  <!-- 右：编辑器 -->
  <main class="ly-panel">
    <div class="ly-field">
      <label for="ly-title">标题 <span class="ly-muted">（必填）</span></label>
      <input class="ly-input" id="ly-title" placeholder="文章标题" maxlength="200">
    </div>

    <div class="ly-row">
      <div class="ly-field">
        <label for="ly-slug">URL 别名 slug <span class="ly-muted">（留空自动生成）</span></label>
        <input class="ly-input" id="ly-slug" placeholder="my-first-post" pattern="[a-z0-9\-]*">
      </div>
      <div class="ly-field">
        <label for="ly-date">发布日期</label>
        <input class="ly-input" id="ly-date" type="date">
      </div>
      <div class="ly-field">
        <label for="ly-author">作者</label>
        <input class="ly-input" id="ly-author" placeholder="绘萤者">
      </div>
    </div>

    <div class="ly-row">
      <div class="ly-field">
        <label for="ly-tags">标签 <span class="ly-muted">（逗号分隔）</span></label>
        <input class="ly-input" id="ly-tags" placeholder="开源, 互联网">
      </div>
      <div class="ly-field">
        <label for="ly-desc">摘要 <span class="ly-muted">（留空自动截取正文）</span></label>
        <input class="ly-input" id="ly-desc" placeholder="用于搜索结果与分享卡片">
      </div>
    </div>

    <div class="ly-field">
      <label for="ly-cover">封面图路径</label>
      <div style="display:flex;gap:.5rem">
        <input class="ly-input" id="ly-cover" placeholder="./images/covers/cover-guide.webp">
        <button class="ly-btn" id="ly-upload-cover" type="button">上传</button>
      </div>
      <img id="ly-cover-preview" class="ly-cover-preview" alt="" style="display:none">
      <input type="file" id="ly-cover-file" accept="image/*" style="display:none">
    </div>

    <div class="ly-field">
      <label>正文（Markdown，支持直接写 HTML）</label>
      <div class="ly-toolbar">
        <button class="ly-btn" data-md="h2">H2</button>
        <button class="ly-btn" data-md="h3">H3</button>
        <button class="ly-btn" data-md="bold"><b>粗体</b></button>
        <button class="ly-btn" data-md="italic"><i>斜体</i></button>
        <button class="ly-btn" data-md="link">链接</button>
        <button class="ly-btn" data-md="code">代码块</button>
        <button class="ly-btn" data-md="quote">引用</button>
        <button class="ly-btn" data-md="list">列表</button>
        <span class="sep"></span>
        <button class="ly-btn" data-md="callout-note">提示框</button>
        <button class="ly-btn" data-md="imggrid">图片组</button>
        <button class="ly-btn" data-md="hr">分割线</button>
        <span class="sep"></span>
        <button class="ly-btn" id="ly-upload-img">插入图片</button>
        <input type="file" id="ly-img-file" accept="image/*" multiple style="display:none">
      </div>
    </div>

    <div class="ly-editor-grid">
      <textarea id="ly-content" placeholder="在这里写正文…" spellcheck="false"></textarea>
      <div id="ly-preview" class="ly-muted">预览区</div>
    </div>

    <div style="display:flex;gap:.5rem;margin-top:.9rem;flex-wrap:wrap">
      <button class="ly-btn ly-btn-primary" id="ly-save" style="min-width:140px">💾 保存并生成静态页</button>
      <button class="ly-btn" id="ly-preview-toggle">切换预览</button>
      <span class="ly-muted" id="ly-meta" style="align-self:center"></span>
    </div>
  </main>
</div>

<div class="ly-status" id="ly-status"></div>

<script src="../assets/vendor/highlight.min.js" defer></script>
<script src="../assets/vendor/marked.min.js" defer></script>
<script src="../assets/vendor/purify.min.js" defer></script>
<script>window.LY_CSRF = <?= json_encode($csrf) ?>;</script>
<script src="assets/admin.js" defer></script>
</body>
</html>
