<?php
/**
 * 学管端 - 请假审批
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAdmin();

$db = Database::getInstance();

$message = '';
$messageType = '';

// 处理审批
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? 0;
    $adminId = Auth::id();
    $now = date('Y-m-d H:i:s');
    
    if ($action === 'approve') {
        $db->query(
            "UPDATE leave_requests SET status = 'approved', approved_by = ?, approved_at = ? WHERE id = ?",
            [$adminId, $now, $id]
        );
        $message = '已批准请假';
        $messageType = 'success';
    } elseif ($action === 'reject') {
        $rejectReason = trim($_POST['reject_reason'] ?? '');
        $db->query(
            "UPDATE leave_requests SET status = 'rejected', approved_by = ?, approved_at = ?, reject_reason = ? WHERE id = ?",
            [$adminId, $now, $rejectReason, $id]
        );
        $message = '已拒绝请假';
        $messageType = 'success';
    }
}

// 筛选
$status = $_GET['status'] ?? 'pending';
$role = $_GET['role'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($status) {
    $where[] = "lr.status = ?";
    $params[] = $status;
}
if ($role) {
    $where[] = "lr.user_role = ?";
    $params[] = $role;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM leave_requests lr $whereSql", $params)['cnt'];
$leaves = $db->fetchAll("
    SELECT lr.*, u.real_name, u.role, a.real_name as approver_name, c.course_name
    FROM leave_requests lr 
    LEFT JOIN users u ON lr.user_id = u.id 
    LEFT JOIN users a ON lr.approved_by = a.id 
    LEFT JOIN schedules s ON lr.schedule_id = s.id
    LEFT JOIN courses c ON s.course_id = c.id
    $whereSql 
    ORDER BY lr.created_at DESC 
    LIMIT $offset, $perPage
", $params);

// 统计
$pendingCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM leave_requests WHERE status = 'pending'")['cnt'];
$approvedCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM leave_requests WHERE status = 'approved'")['cnt'];
$rejectedCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM leave_requests WHERE status = 'rejected'")['cnt'];

$pageTitle = '请假审批';
$activeMenu = 'leaves';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<main class="container py-4">
    <h2 class="page-title mb-4">请假审批</h2>
    
    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show alert-auto-dismiss" role="alert">
        <?= e($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- 统计 -->
    <div class="row g-3 mb-3">
        <div class="col-4 col-md-2">
            <a href="?status=pending" class="text-decoration-none">
                <div class="card text-center <?= $status === 'pending' ? 'border-warning' : '' ?>">
                    <div class="card-body py-2">
                        <div class="text-warning fw-bold fs-4"><?= $pendingCount ?></div>
                        <div class="small text-muted">待审批</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-4 col-md-2">
            <a href="?status=approved" class="text-decoration-none">
                <div class="card text-center <?= $status === 'approved' ? 'border-success' : '' ?>">
                    <div class="card-body py-2">
                        <div class="text-success fw-bold fs-4"><?= $approvedCount ?></div>
                        <div class="small text-muted">已批准</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-4 col-md-2">
            <a href="?status=rejected" class="text-decoration-none">
                <div class="card text-center <?= $status === 'rejected' ? 'border-danger' : '' ?>">
                    <div class="card-body py-2">
                        <div class="text-danger fw-bold fs-4"><?= $rejectedCount ?></div>
                        <div class="small text-muted">已拒绝</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-md-6">
            <form method="GET" class="h-100">
                <input type="hidden" name="status" value="<?= e($status) ?>">
                <div class="d-flex gap-2 align-items-end h-100">
                    <div class="flex-grow-1">
                        <label class="form-label">角色筛选</label>
                        <select name="role" class="form-select" onchange="this.form.submit()">
                            <option value="">全部角色</option>
                            <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>学生</option>
                            <option value="teacher" <?= $role === 'teacher' ? 'selected' : '' ?>>老师</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <?php if ($leaves): ?>
                <?php foreach ($leaves as $leave): ?>
                <div class="border-bottom py-3">
                    <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
                        <div>
                            <span class="fw-bold me-2"><?= e($leave['real_name']) ?></span>
                            <span class="badge bg-secondary me-2"><?= getRoleName($leave['user_role']) ?></span>
                            <span class="text-muted small"><i class="bi bi-clock"></i> <?= e($leave['created_at']) ?></span>
                        </div>
                        <div><?= getLeaveStatusText($leave['status']) ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3">
                            <small class="text-muted">请假日期</small>
                            <div class="fw-medium"><?= e($leave['leave_date']) ?> (<?= e(getWeekDay($leave['leave_date'])) ?>)</div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">请假类型</small>
                            <div class="fw-medium"><?= e($leave['leave_type'] ?? '-') ?></div>
                        </div>
                        <?php if ($leave['course_name']): ?>
                        <div class="col-md-3">
                            <small class="text-muted">关联课程</small>
                            <div class="fw-medium"><?= e($leave['course_name']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">请假事由</small>
                        <p class="mb-1"><?= e($leave['reason']) ?></p>
                    </div>
                    <?php if ($leave['status'] === 'rejected' && $leave['reject_reason']): ?>
                    <div class="mb-2 text-danger">
                        <small>拒绝原因：<?= e($leave['reject_reason']) ?></small>
                    </div>
                    <?php endif; ?>
                    <?php if ($leave['approved_by']): ?>
                    <div class="text-muted small">
                        审批人：<?= e($leave['approver_name']) ?> | 审批时间：<?= e($leave['approved_at']) ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($leave['status'] === 'pending'): ?>
                    <div class="mt-2 d-flex gap-2">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="id" value="<?= $leave['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-success">
                                <i class="bi bi-check-lg me-1"></i>批准
                            </button>
                        </form>
                        <button class="btn btn-sm btn-danger" onclick="showRejectModal(<?= $leave['id'] ?>)">
                            <i class="bi bi-x-lg me-1"></i>拒绝
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
                <?= paginate($total, $page, $perPage, '/pages/admin/leaves.php?status=' . urlencode($status) . '&role=' . urlencode($role)) ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>暂无请假记录</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- 拒绝模态框 -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-x-circle text-danger me-2"></i>拒绝请假</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="id" id="reject-id">
                    <div class="mb-0">
                        <label class="form-label">拒绝原因</label>
                        <textarea name="reject_reason" class="form-control" rows="3" placeholder="请输入拒绝原因"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-danger">确认拒绝</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showRejectModal(id) {
    document.getElementById('reject-id').value = id;
    const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
