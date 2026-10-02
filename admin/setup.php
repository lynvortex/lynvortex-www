<?php
/**
 * 绘萤者 — 首次安装
 * ==========================================================================
 * 仅在 admin/config.php 不存在时可用；安装完成后自动锁定。
 *
 * 密码框会预填 site.config.php 里的 default_password（默认 abc123），
 * 方便把项目跑起来。但那个值是公开的，正式上线前务必改成自己的密码。
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
ly_session_start();

$installed = ly_is_installed();
$errors    = [];
$done      = false;

$minPw     = ly_password_min_length();
$defaultPw = ly_default_password();
$defaultUrl = ly_site_url();

if (!$installed && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $pw  = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password2'] ?? '');
    $url = trim((string)($_POST['site_url'] ?? $defaultUrl));

    if (strlen($pw) < $minPw) {
        $errors[] = '密码至少 ' . $minPw . ' 位。';
    }
    if ($pw !== $pw2) {
        $errors[] = '两次输入的密码不一致。';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $errors[] = '站点地址需以 http:// 或 https:// 开头。';
    }

    if (!$errors) {
        if (ly_install($pw, $url)) {
            $done = true;
        } else {
            $errors[] = '无法写入 admin/config.php，请检查 admin 目录是否可写。';
        }
    }
}

$secureOk  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$isDefault = ($defaultPw !== '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>安装 - <?= ly_e(ly_site_name()) ?>后台</title>
<link rel="icon" href="../images/icon.webp" type="image/webp">
<link rel="stylesheet" href="assets/admin.css">
</head>
<body class="ly-auth-body">
<div class="ly-auth-card">
  <div class="ly-auth-logo">
    <img src="../images/icon.webp" alt="<?= ly_e(ly_site_name()) ?>" width="44" height="44">
    <h1><?= ly_e(ly_site_name()) ?>后台 · 安装</h1>
  </div>

<?php if ($installed): ?>
  <p class="ly-note ly-note-ok">后台已安装完成。</p>
  <p class="ly-muted">如需重设密码，请删除服务器上的 <code>admin/config.php</code> 后重新访问本页，
     或登录后在「修改密码」页面直接修改。</p>
  <a class="ly-btn ly-btn-primary ly-btn-block" href="login.php">前往登录</a>

<?php elseif ($done): ?>
  <p class="ly-note ly-note-ok">安装成功！</p>
  <?php if ($isDefault && ($_POST['password'] ?? '') === $defaultPw): ?>
    <div class="ly-note ly-note-warn">
      你正在使用<strong>公开的默认密码</strong>。请立刻到「修改密码」页面改掉，
      否则任何人都能登录你的后台。
    </div>
  <?php endif; ?>
  <p class="ly-muted">为了安全，请确认 <code>admin/</code> 目录不可被直接浏览，并使用 HTTPS 访问后台。</p>
  <a class="ly-btn ly-btn-primary ly-btn-block" href="login.php">前往登录</a>

<?php else: ?>
  <?php if ($errors): ?>
    <div class="ly-note ly-note-err">
      <?php foreach ($errors as $e): ?><div><?= ly_e($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$secureOk): ?>
    <div class="ly-note ly-note-warn">当前不是 HTTPS 连接。正式使用时请务必通过 HTTPS 访问后台，否则密码可能被窃听。</div>
  <?php endif; ?>

  <?php if ($isDefault): ?>
    <div class="ly-note ly-note-warn">
      下面预填的是项目自带的<strong>默认密码</strong>，它是公开的。
      这里可以直接用，但<strong>上线前必须改成自己的密码</strong>。
    </div>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <label class="ly-label" for="password">设置后台密码</label>
    <input class="ly-input" type="password" id="password" name="password"
           minlength="<?= $minPw ?>" required autocomplete="new-password"
           value="<?= ly_e($defaultPw) ?>" placeholder="至少 <?= $minPw ?> 位">

    <label class="ly-label" for="password2">确认密码</label>
    <input class="ly-input" type="password" id="password2" name="password2"
           minlength="<?= $minPw ?>" required autocomplete="new-password"
           value="<?= ly_e($defaultPw) ?>" placeholder="再次输入">

    <label class="ly-label" for="site_url">站点地址</label>
    <input class="ly-input" type="url" id="site_url" name="site_url" required
           value="<?= ly_e($defaultUrl) ?>" placeholder="https://example.com">

    <button class="ly-btn ly-btn-primary ly-btn-block" type="submit">完成安装</button>
  </form>

  <p class="ly-muted" style="margin-top:1rem;font-size:.82rem;">
    站点名称、联系方式、备案号等在根目录 <code>site.config.php</code> 修改，改完运行 <code>php build.php</code>。
  </p>
<?php endif; ?>
</div>
</body>
</html>
