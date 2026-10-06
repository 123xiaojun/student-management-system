<?php
/**
 * 登出
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

Auth::logout();
redirect('/index.php');
