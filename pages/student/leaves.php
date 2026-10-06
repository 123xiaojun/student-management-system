<?php
/**
 * 学生端 - 请假申请
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireStudent();

$db = Database::getInstance();
$userId = Auth::id();

$message = '';
$messageType = '';

// 处理提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'apply') {
        $leaveDate = $_POST['leave_date'];
        $leaveType = trim($_POST['leave_type'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $scheduleId = $_POST['schedule_id'] ?? null;
        
        if (empty($leaveDate) || empty($reason)) {
            $message = '请填写请假日期和事由';
            $messageType = 'danger';
        } else {
            $db->query(
                "INSERT INTO leave_requests (user_id, user_role, schedule_id, leave_date, leave_type, reason) VALUES (?, 'student', ?, ?, ?, ?)",
                [$userId, $scheduleId ?: null, $leaveDate, $leaveType, $reason]
            );
            $message = '请假申请已提交，请等待学管审批';
            $messageType = 'success';
        }
    } elseif ($action === 'cancel') {
        $id = $_POST['id'];
        $leave = $db->fetchOne("SELECT * FROM leave_requests WHERE id = ? AND user_id = ?", [$id, $userId]);
        
        if ($leave && $leave['status'] === 'pending') {
            $db->query("DELETE FROM leave_requests WHERE id = ?", [$id]);
            $message = '已撤销请假申请';
            $messageType = 'success';
        } else {
            $message = '无法撤销该申请';
            $messageType = 'danger';
        }
    }
}

// 筛选
// 获取所有请假记录（前端筛选，不跳转页面）
$leaves = $db->fetchAll("
    SELECT lr.*, c.course_name, a.real_name as approver_name
    FROM leave_requests lr 
    LEFT JOIN schedules s ON lr.schedule_id = s.id
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN users a ON lr.approved_by = a.id
    WHERE lr.user_id = ? AND lr.user_role = 'student'
    ORDER BY lr.created_at DESC 
", [$userId]);

// 预填参数
$preDate = $_GET['date'] ?? date('Y-m-d');
$preScheduleId = $_GET['schedule_id'] ?? '';

$pageTitle = '请假申请';
$activeMenu = 'leaves';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="page-title mb-0">请假申请</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyModal">
            <i class="bi bi-plus-lg me-1"></i> 申请请假
        </button>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show alert-auto-dismiss" role="alert">
        <?= e($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- 筛选标签 -->
    <div class="mb-3">
        <div class="btn-group flex-wrap" role="group">
            <button class="btn btn-outline-secondary active" data-filter="all" onclick="filterLeaves('all')">全部</button>
            <button class="btn btn-outline-warning" data-filter="pending" onclick="filterLeaves('pending')">待审批</button>
            <button class="btn btn-outline-success" data-filter="approved" onclick="filterLeaves('approved')">已批准</button>
            <button class="btn btn-outline-danger" data-filter="rejected" onclick="filterLeaves('rejected')">已拒绝</button>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <?php if ($leaves): ?>
                <?php foreach ($leaves as $leave): ?>
                <div class="border-bottom py-3 leave-item" data-status="<?= $leave['status'] ?>">
                    <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
                        <div>
                            <span class="fw-bold"><?= e($leave['leave_date']) ?></span>
                            <span class="text-muted ms-2"><?= e(getWeekDay($leave['leave_date'])) ?></span>
                            <?php if ($leave['leave_type']): ?>
                            <span class="badge bg-info ms-2"><?= e($leave['leave_type']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div><?= getLeaveStatusText($leave['status']) ?></div>
                    </div>
                    <?php if ($leave['course_name']): ?>
                    <div class="text-muted small mb-2">
                        <i class="bi bi-book me-1"></i>关联课程：<?= e($leave['course_name']) ?>
                    </div>
                    <?php endif; ?>
                    <div class="mb-2">
                        <small class="text-muted">请假事由</small>
                        <p class="mb-1"><?= e($leave['reason']) ?></p>
                    </div>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <small class="text-muted">
                            申请时间：<?= e($leave['created_at']) ?>
                            <?php if ($leave['approved_by']): ?>
                            | 审批人：<?= e($leave['approver_name']) ?>
                            <?php endif; ?>
                        </small>
                        <?php if ($leave['status'] === 'pending'): ?>
                        <form method="POST" onsubmit="return confirm('确定要撤销该请假申请吗？')">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="id" value="<?= $leave['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-x me-1"></i>撤销申请
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php if ($leave['status'] === 'rejected' && $leave['reject_reason']): ?>
                        <small class="text-danger">拒绝原因：<?= e($leave['reject_reason']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <script>
function filterLeaves(status) {
    document.querySelectorAll('[data-filter]').forEach(function(btn) {
        btn.classList.toggle('active', btn.getAttribute('data-filter') === status);
    });
    document.querySelectorAll('.leave-item').forEach(function(item) {
        if (status === 'all' || item.getAttribute('data-status') === status) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}
</script>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>暂无请假记录</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- 申请请假模态框 -->
<div class="modal fade" id="applyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" data-validate>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-envelope-plus me-2"></i>申请请假</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="apply">
                    <input type="hidden" name="schedule_id" value="<?= e($preScheduleId) ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">请假日期 <span class="text-danger">*</span></label>
                        <input type="date" name="leave_date" class="form-control" required value="<?= e($preDate) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">请假类型</label>
                        <select name="leave_type" class="form-select">
                            <option value="">请选择类型</option>
                            <option value="病假">病假</option>
                            <option value="事假">事假</option>
                            <option value="公假">公假</option>
                            <option value="其他">其他</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">请假事由 <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="4" required placeholder="请详细描述请假原因"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">提交申请</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($_SERVER['REQUEST_METHOD'] !== 'POST' && (isset($_GET['date']) || isset($_GET['schedule_id']))): ?>
<script>
// 如果是从课程页跳转过来，自动打开请假窗口
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('applyModal'));
    modal.show();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
