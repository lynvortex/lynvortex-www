<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
ly_logout();
header('Location: login.php');
exit;
