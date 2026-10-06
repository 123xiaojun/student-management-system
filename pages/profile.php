<?php
/**
 * 个人中心 - 所有角色共用
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!Auth::check()) {
    redirect('/index.php');
}

$db = Database::getInstance();
$user = Auth::user();

$message = '';
$messageType = '';

// 处理更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $realName = trim($_POST['real_name']);
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($realName)) {
            $message = '请填写姓名';
            $messageType = 'danger';
        } else {
            $db->query(
                "UPDATE users SET real_name = ?, email = ?, phone = ? WHERE id = ?",
                [$realName, $email, $phone, $user['id']]
            );
            $_SESSION['real_name'] = $realName;
            $message = '个人信息更新成功';
            $messageType = 'success';
        }
    } elseif ($action === 'change_password') {
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        $userFull = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$user['id']]);
        
        if (!password_verify($oldPassword, $userFull['password'])) {
            $message = '原密码错误';
            $messageType = 'danger';
        } elseif (empty($newPassword) || strlen($newPassword) < 6) {
            $message = '新密码长度不能少于6位';
            $messageType = 'danger';
        } elseif ($newPassword !== $confirmPassword) {
            $message = '两次输入的新密码不一致';
            $messageType = 'danger';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->query("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $user['id']]);
            $message = '密码修改成功';
            $messageType = 'success';
        }
    }
    
    // 重新获取用户信息
    $user = Auth::user();
}

$pageTitle = '个人中心';
$activeMenu = '';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<main class="container py-4">
    <h2 class="page-title mb-4">个人中心</h2>
    
    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show alert-auto-dismiss" role="alert">
        <?= e($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-person me-2"></i>基本信息
                </div>
                <div class="card-body">
                    <form method="POST" data-validate>
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="mb-3">
                            <label class="form-label">用户名</label>
                            <input type="text" class="form-control" value="<?= e($user['username']) ?>" disabled>
                            <div class="form-text">用户名不可修改</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">角色</label>
                            <input type="text" class="form-control" value="<?= getRoleName($user['role']) ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">姓名 <span class="text-danger">*</span></label>
                            <input type="text" name="real_name" class="form-control" value="<?= e($user['real_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">邮箱</label>
                            <input type="email" name="email" class="form-control" value="<?= e($user['email'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">手机号</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> 保存修改
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-shield-lock me-2"></i>修改密码
                </div>
                <div class="card-body">
                    <form method="POST" data-validate>
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label class="form-label">原密码 <span class="text-danger">*</span></label>
                            <input type="password" name="old_password" class="form-control" required placeholder="请输入原密码">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">新密码 <span class="text-danger">*</span></label>
                            <input type="password" name="new_password" class="form-control" required placeholder="至少6位">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">确认新密码 <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" required placeholder="再次输入新密码">
                        </div>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-key me-1"></i> 修改密码
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <i class="bi bi-info-circle me-2"></i>账户信息
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="text-muted" style="width: 120px;">注册时间</td>
                            <td><?= e($user['created_at'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">账户状态</td>
                            <td>
                                <?php if ($user['status']): ?>
                                    <span class="badge bg-success">正常</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">已禁用</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
