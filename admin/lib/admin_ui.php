<?php
/**
 * 绘萤者 — 后台界面片段
 * ==========================================================================
 * 后台各页共用的 head 与顶栏，以及默认密码告警。
 * 所有站点文案取自 site.config.php，不写死。
 * ==========================================================================
 */

declare(strict_types=1);

/** 后台页面 <head>（到 </head> 为止） */
function ly_admin_head(string $title): string
{
    $name = ly_site_name();
    $h = [];
    $h[] = '<!DOCTYPE html>';
    $h[] = '<html lang="zh-CN">';
    $h[] = '<head>';
    $h[] = '<meta charset="UTF-8">';
    $h[] = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    $h[] = '<meta name="robots" content="noindex, nofollow">';
    $h[] = '<title>' . ly_e($title) . ' - ' . ly_e($name) . '后台</title>';
    $h[] = '<link rel="icon" href="../images/icon.webp" type="image/webp">';
    $h[] = '<link rel="stylesheet" href="assets/admin.css">';
    $h[] = '</head>';
    return implode("\n", $h);
}

/**
 * 后台顶栏。$active 取 'write' | 'password'，用于高亮当前页。
 * $extra 可插入额外按钮（例如「新建文章」）。
 */
function ly_admin_topbar(string $active = '', string $extra = ''): string
{
    $name = ly_site_name();
    $h = [];
    $h[] = '<div class="ly-topbar">';
    $h[] = '  <div class="ly-brand">';
    $h[] = '    <img src="../images/icon.webp" alt="" width="26" height="26">';
    $h[] = '    ' . ly_e($name) . '后台';
    $h[] = '  </div>';
    if ($extra !== '') $h[] = '  ' . $extra;
    $h[] = '  <a class="ly-btn' . ($active === 'write' ? ' ly-btn-active' : '') . '" href="index.php">写文章</a>';
    $h[] = '  <a class="ly-btn' . ($active === 'password' ? ' ly-btn-active' : '') . '" href="password.php">修改密码</a>';
    $h[] = '  <a class="ly-btn" href="../" target="_blank" rel="noopener">查看网站 ↗</a>';
    $h[] = '  <a class="ly-btn" href="logout.php">退出</a>';
    $h[] = '</div>';
    return implode("\n", $h);
}

/**
 * 默认密码告警条。
 *
 * site.config.php 里的默认密码是公开的（随仓库分发），
 * 任何用默认密码上线的后台等于没有密码，所以这里必须一直提醒到改掉为止。
 */
function ly_default_password_notice(): string
{
    if (!ly_is_using_default_password()) return '';

    $min = ly_password_min_length();
    return '<div class="ly-alert ly-alert-danger">'
         . '⚠️ <strong>你正在使用公开的默认密码</strong>，任何人都能登录这个后台。'
         . '请立即 <a href="password.php">修改密码</a>。'
         . '</div>';
}
