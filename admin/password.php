<?php
/**
 * 绘萤者 — 修改后台密码
 * ==========================================================================
 * 需要当前密码确认。修改成功后当前会话保持登录。
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
ly_require_login();

$minPw  = ly_password_min_length();
$errors = [];
$done   = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!ly_csrf_check((string)($_POST['csrf'] ?? ''))) {
        $errors[] = '会话已过期，请重新提交。';
    } else {
        $cur  = (string)($_POST['current'] ?? '');
        $new  = (string)($_POST['password'] ?? '');
        $new2 = (string)($_POST['password2'] ?? '');

        $cfg  = ly_config();
        $hash = (string)($cfg['password_hash'] ?? '');

        if ($hash === '' || !password_verify($cur, $hash)) {
            $errors[] = '当前密码不正确。';
        }
        if (strlen($new) < $minPw) {
            $errors[] = '新密码至少 ' . $minPw . ' 位。';
        }
        if ($new !== $new2) {
            $errors[] = '两次输入的新密码不一致。';
        }
        if (!$errors && $new === $cur) {
            $errors[] = '新密码不能和当前密码相同。';
        }

        if (!$errors) {
            if (ly_set_password($new)) {
                ly_login_throttle_clear();
                $done = true;
            } else {
                $errors[] = '写入 admin/config.php 失败，请检查 admin 目录是否可写。';
            }
        }
    }
}

$usingDefault = ly_is_using_default_password();
$csrf = ly_csrf_token();
?>
<?= ly_admin_head('修改密码') ?>
<body>

<?= ly_admin_topbar('password') ?>

<?= ly_default_password_notice() ?>

<div class="ly-narrow">
  <div class="ly-panel">
    <h2>修改后台密码</h2>

    <?php if ($done): ?>
      <div class="ly-alert ly-alert-ok">密码已更新。</div>
      <?php if (!$usingDefault): ?>
        <p class="ly-muted">你已经不再使用默认密码，很好。</p>
      <?php endif; ?>
      <p><a class="ly-btn ly-btn-primary" href="index.php">返回写文章</a></p>

    <?php else: ?>
      <?php if ($errors): ?>
        <div class="ly-alert ly-alert-danger">
          <?php foreach ($errors as $e): ?><div><?= ly_e($e) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($usingDefault): ?>
        <div class="ly-alert ly-alert-danger">
          当前密码是项目自带的<strong>默认值</strong>，它随仓库公开。请在这里改掉。
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= ly_e($csrf) ?>">

        <div class="ly-field">
          <label for="current">当前密码</label>
          <input class="ly-input" type="password" id="current" name="current" required autocomplete="current-password">
        </div>

        <div class="ly-field">
          <label for="password">新密码 <span class="ly-muted">（至少 <?= $minPw ?> 位）</span></label>
          <input class="ly-input" type="password" id="password" name="password" minlength="<?= $minPw ?>" required autocomplete="new-password">
        </div>

        <div class="ly-field">
          <label for="password2">确认新密码</label>
          <input class="ly-input" type="password" id="password2" name="password2" minlength="<?= $minPw ?>" required autocomplete="new-password">
        </div>

        <button class="ly-btn ly-btn-primary" type="submit">保存新密码</button>
      </form>

      <p class="ly-muted" style="margin-top:1rem;font-size:.85rem;">
        密码以 bcrypt 哈希形式存放在 <code>admin/config.php</code>，不存明文。
        忘记密码时，删除该文件后重新访问 <code>admin/setup.php</code> 即可重设，文章数据不受影响。
      </p>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
