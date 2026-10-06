<?php
/**
 * 老师端 - 考勤记录
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireTeacher();

$db = Database::getInstance();
$userId = Auth::id();

// 筛选
$month = $_GET['month'] ?? date('Y-m');
$status = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$monthStart = $month . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

$where = ["a.user_id = ? AND a.user_role = 'teacher' AND date(a.check_in_time) BETWEEN ? AND ?"];
$params = [$userId, $monthStart, $monthEnd];

if ($status) {
    $where[] = "a.status = ?";
    $params[] = $status;
}

$whereSql = implode(' AND ', $where);

$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM attendance a WHERE $whereSql", $params)['cnt'];
$records = $db->fetchAll("
    SELECT a.*, s.class_date, s.start_time, s.end_time, s.classroom, c.course_name
    FROM attendance a
    LEFT JOIN schedules s ON a.schedule_id = s.id
    LEFT JOIN courses c ON s.course_id = c.id
    WHERE $whereSql
    ORDER BY s.class_date DESC, s.start_time ASC
    LIMIT $offset, $perPage
", $params);

// 统计数据
$totalHours = $db->fetchOne("
    SELECT COALESCE(SUM(hours), 0) as total 
    FROM attendance a
    WHERE $whereSql AND a.status = 'present'
", $params)['total'];

$presentCount = $db->fetchOne("
    SELECT COUNT(*) as cnt 
    FROM attendance a
    WHERE $whereSql AND a.status = 'present'
", $params)['cnt'];

$pageTitle = '考勤记录';
$activeMenu = 'attendance';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<main class="container py-4">
    <h2 class="page-title mb-4">考勤记录</h2>
    
    <!-- 统计卡片 -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-4">
            <div class="card">
                <div class="card-body text-center py-2">
                    <div class="text-primary fs-3 fw-bold"><?= $month ?></div>
                    <div class="text-muted small">统计月份</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card">
                <div class="card-body text-center py-2">
                    <div class="text-success fs-3 fw-bold"><?= $presentCount ?></div>
                    <div class="text-muted small">出勤次数</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card">
                <div class="card-body text-center py-2">
                    <div class="text-info fs-3 fw-bold"><?= number_format($totalHours, 1) ?>h</div>
                    <div class="text-muted small">累计课时</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 筛选 -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">月份</label>
                    <input type="month" name="month" class="form-control" value="<?= e($month) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">状态</label>
                    <select name="status" class="form-select">
                        <option value="">全部状态</option>
                        <option value="present" <?= $status === 'present' ? 'selected' : '' ?>>已出勤</option>
                        <option value="absent" <?= $status === 'absent' ? 'selected' : '' ?>>缺勤</option>
                        <option value="leave" <?= $status === 'leave' ? 'selected' : '' ?>>请假</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> 查询</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <?php if ($records): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>日期</th>
                                <th>课程</th>
                                <th class="hide-mobile">时间</th>
                                <th class="hide-mobile">教室</th>
                                <th>状态</th>
                                <th>课时</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                            <tr>
                                <td>
                                    <?= e($record['class_date']) ?><br>
                                    <small class="text-muted"><?= e(getWeekDay($record['class_date'])) ?></small>
                                </td>
                                <td class="fw-medium"><?= e($record['course_name']) ?></td>
                                <td class="hide-mobile">
                                    <small><?= e($record['start_time']) ?> - <?= e($record['end_time']) ?></small>
                                </td>
                                <td class="hide-mobile"><?= e($record['classroom'] ?? '-') ?></td>
                                <td><?= getAttendanceStatusText($record['status']) ?></td>
                                <td>
                                    <?php if ($record['status'] === 'present'): ?>
                                        <span class="fw-bold text-primary"><?= $record['hours'] ?>h</span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?= paginate($total, $page, $perPage, '/pages/teacher/attendance.php?month=' . urlencode($month) . '&status=' . urlencode($status)) ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-calendar-check"></i>
                    <p>暂无考勤记录</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
