<?php
/**
 * 绘萤者 — 后台登录
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

if (!ly_is_installed()) {
    header('Location: setup.php');
    exit;
}

ly_session_start();

if (ly_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$locked = !ly_login_throttle_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ly_csrf_check($_POST['csrf'] ?? null)) {
        $error = '会话已过期，请重试。';
    } elseif ($locked) {
        $error = '尝试次数过多，请 15 分钟后再试。';
    } else {
        $pw = (string)($_POST['password'] ?? '');
        if (ly_login($pw)) {
            ly_login_throttle_clear();
            header('Location: index.php');
            exit;
        }
        ly_login_throttle_hit();
        $locked = !ly_login_throttle_check();
        $error = $locked ? '尝试次数过多，请 15 分钟后再试。' : '密码错误。';
    }
}

$secureOk = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>登录 - 绘萤者后台</title>
<link rel="icon" href="../images/icon.webp" type="image/webp">
<link rel="stylesheet" href="assets/admin.css">
</head>
<body class="ly-auth-body">
<div class="ly-auth-card">
  <div class="ly-auth-logo">
    <img src="../images/icon.webp" alt="绘萤者" width="44" height="44">
    <h1>绘萤者后台</h1>
  </div>

  <?php if ($error !== ''): ?>
    <div class="ly-note ly-note-err"><?= ly_e($error) ?></div>
  <?php endif; ?>

  <?php if (!$secureOk): ?>
    <div class="ly-note ly-note-warn">当前不是 HTTPS 连接，密码可能被窃听。</div>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= ly_e(ly_csrf_token()) ?>">
    <label class="ly-label" for="password">密码</label>
    <input class="ly-input" type="password" id="password" name="password" required autofocus autocomplete="current-password" <?= $locked ? 'disabled' : '' ?>>
    <button class="ly-btn ly-btn-primary ly-btn-block" type="submit" <?= $locked ? 'disabled' : '' ?>>登录</button>
  </form>

  <p class="ly-muted ly-center" style="margin-top:1rem">
    <a href="../">← 返回网站首页</a>
  </p>
</div>
</body>
</html>
