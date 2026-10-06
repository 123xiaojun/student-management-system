<?php
/**
 * 首页 / 登录页
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// 自动初始化数据库
require_once __DIR__ . '/install.php';

// 如果已登录，跳转到对应首页
if (Auth::check()) {
    redirect(Auth::dashboardUrl());
}

$error = '';
$redirect = $_GET['redirect'] ?? '';

// 处理登录
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($phone) || empty($password)) {
        $error = '请输入手机号和密码';
    } else {
        $result = Auth::login($phone, $password);
        if ($result['success']) {
            if ($redirect) {
                redirect($redirect);
            }
            redirect(Auth::dashboardUrl());
        } else {
            $error = $result['message'];
        }
    }
}

// 演示账号（手机号 => 密码）
$demoAccounts = [
    ['role' => '学管', 'phone' => '13800138000', 'password' => 'admin123', 'class' => 'danger', 'icon' => 'person-gear'],
    ['role' => '老师', 'phone' => '13800138001', 'password' => 'teacher123', 'class' => 'info', 'icon' => 'person-badge'],
    ['role' => '学生', 'phone' => '13800138003', 'password' => 'student123', 'class' => 'success', 'icon' => 'person'],
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <i class="bi bi-mortarboard-fill"></i>
                <h1><?= SITE_NAME ?></h1>
                <p class="text-muted mb-0">教育培训机构管理系统</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= e($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" data-validate id="loginForm">
                <div class="mb-3">
                    <label for="phone" class="form-label">手机号</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-phone"></i></span>
                        <input type="tel" class="form-control" id="phone" name="phone" required placeholder="请输入手机号" maxlength="11" autofocus>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">密码</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required placeholder="请输入密码">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" title="显示/隐藏密码">
                            <i class="bi bi-eye-slash" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i> 登录
                    </button>
                </div>
            </form>
            


        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/app.js"></script>
    <script>
    // 演示账号一键填写并登录
    document.querySelectorAll('.demo-login-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var phone = this.getAttribute('data-phone');
            var password = this.getAttribute('data-password');
            document.getElementById('phone').value = phone;
            document.getElementById('password').value = password;
            document.getElementById('loginForm').submit();
        });
    });
    
    // 密码显示/隐藏切换
    document.getElementById('togglePassword').addEventListener('click', function() {
        var passwordInput = document.getElementById('password');
        var eyeIcon = document.getElementById('eyeIcon');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.className = 'bi bi-eye';
        } else {
            passwordInput.type = 'password';
            eyeIcon.className = 'bi bi-eye-slash';
        }
    });
    
    // 手机号输入限制
    document.getElementById('phone').addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 11);
    });
    </script>
</body>
</html>
