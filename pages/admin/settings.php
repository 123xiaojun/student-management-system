<?php
/**
 * 学管端 - 系统设置
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAdmin();

$db = Database::getInstance();

$message = '';
$messageType = '';

// 处理保存
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_settings') {
        $settings = [
            'site_name' => $_POST['site_name'] ?? '学管系统',
            'check_in_before' => intval($_POST['check_in_before'] ?? 30),
            'check_out_after' => intval($_POST['check_out_after'] ?? 0),
        ];
        
        foreach ($settings as $key => $value) {
            $existing = $db->fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
            if ($existing) {
                $db->query("UPDATE settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?", 
                    [$value, date('Y-m-d H:i:s'), $key]);
            } else {
                $db->query("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)", [$key, $value]);
            }
        }
        
        $message = '设置保存成功';
        $messageType = 'success';
    }
}

// 读取设置
$siteName = get_setting('site_name', '学管系统');
$checkInBefore = get_setting('check_in_before', '30');
$checkOutAfter = get_setting('check_out_after', '0');

$pageTitle = '系统设置';
$activeMenu = 'settings';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<main class="container py-4">
    <h2 class="page-title mb-4">系统设置</h2>
    
    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show alert-auto-dismiss" role="alert">
        <?= e($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-gear me-2"></i>基础设置
                </div>
                <div class="card-body">
                    <form method="POST" data-validate>
                        <input type="hidden" name="action" value="save_settings">
                        
                        <div class="mb-3">
                            <label class="form-label">系统名称</label>
                            <input type="text" name="site_name" class="form-control" value="<?= e($siteName) ?>">
                            <div class="form-text">显示在页面标题和登录页的系统名称</div>
                        </div>
                        
                        <hr>
                        <h6 class="mb-3">签到设置</h6>
                        
                        <div class="mb-3">
                            <label class="form-label">提前签到时间（分钟）</label>
                            <input type="number" name="check_in_before" class="form-control" value="<?= e($checkInBefore) ?>" min="0">
                            <div class="form-text">上课前多少分钟可以开始签到</div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">课后签退延迟（分钟）</label>
                            <input type="number" name="check_out_after" class="form-control" value="<?= e($checkOutAfter) ?>" min="0">
                            <div class="form-text">下课后多少分钟后允许签退（0表示立即可以签退）</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> 保存设置
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-info-circle me-2"></i>系统信息
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td class="text-muted">系统版本</td>
                            <td><?= SITE_VERSION ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">数据库类型</td>
                            <td><?= DB_TYPE === 'mysql' ? 'MySQL' : 'SQLite' ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">PHP 版本</td>
                            <td><?= phpversion() ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">时区</td>
                            <td><?= date_default_timezone_get() ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <i class="bi bi-shield-lock me-2"></i>安全提示
                </div>
                <div class="card-body">
                    <ul class="list-unstyled small text-muted mb-0">
                        <li class="mb-2">• 定期修改管理员密码</li>
                        <li class="mb-2">• 不要使用过于简单的密码</li>
                        <li class="mb-2">• 定期备份数据库</li>
                        <li>• 及时关注系统安全更新</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
