<?php
/**
 * 学管端 - 课时统计
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAdmin();

$db = Database::getInstance();

// 筛选
$month = $_GET['month'] ?? date('Y-m');
$role = $_GET['role'] ?? 'student';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 当月总课时
$monthStart = $month . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

$totalHours = $db->fetchOne("
    SELECT COALESCE(SUM(hours), 0) as total 
    FROM attendance 
    WHERE status = 'present' AND user_role = ? 
      AND date(check_out_time) BETWEEN ? AND ?
", [$role, $monthStart, $monthEnd])['total'];

// 按人员统计
$total = $db->fetchOne("
    SELECT COUNT(DISTINCT user_id) as cnt 
    FROM attendance a
    WHERE a.user_role = ? AND a.status = 'present'
      AND date(a.check_out_time) BETWEEN ? AND ?
", [$role, $monthStart, $monthEnd])['cnt'];

$userStats = $db->fetchAll("
    SELECT 
        a.user_id, 
        u.real_name, 
        u.username,
        COALESCE(SUM(a.hours), 0) as total_hours,
        COUNT(DISTINCT a.schedule_id) as class_count
    FROM attendance a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.user_role = ? AND a.status = 'present'
      AND date(a.check_out_time) BETWEEN ? AND ?
    GROUP BY a.user_id, u.real_name, u.username
    ORDER BY total_hours DESC
    LIMIT $offset, $perPage
", [$role, $monthStart, $monthEnd]);

// 课程统计
$courseStats = $db->fetchAll("
    SELECT 
        c.id,
        c.course_name,
        u.real_name as teacher_name,
        COALESCE(SUM(CASE WHEN a.user_role = 'student' THEN a.hours ELSE 0 END), 0) as student_hours,
        COALESCE(SUM(CASE WHEN a.user_role = 'teacher' THEN a.hours ELSE 0 END), 0) as teacher_hours,
        COUNT(DISTINCT a.schedule_id) as class_count
    FROM attendance a
    LEFT JOIN schedules s ON a.schedule_id = s.id
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN users u ON c.teacher_id = u.id
    WHERE a.status = 'present'
      AND date(a.check_out_time) BETWEEN ? AND ?
    GROUP BY c.id, c.course_name, u.real_name
    ORDER BY class_count DESC
    LIMIT 10
", [$monthStart, $monthEnd]);

$pageTitle = '课时统计';
$activeMenu = 'hours';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<main class="container py-4">
    <h2 class="page-title mb-4">课时统计</h2>
    
    <!-- 筛选 -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">统计月份</label>
                    <input type="month" name="month" class="form-control" value="<?= e($month) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">统计角色</label>
                    <select name="role" class="form-select">
                        <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>学生课时</option>
                        <option value="teacher" <?= $role === 'teacher' ? 'selected' : '' ?>>老师课时</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-bar-chart me-1"></i> 统计</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 总览 -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold"><?= number_format($totalHours, 1) ?></div>
                    <div class="text-white-50"><?= $month ?> 总课时</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold"><?= $total ?></div>
                    <div class="text-white-50">出勤<?= $role === 'teacher' ? '老师' : '学生' ?>人数</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold"><?= $total > 0 ? number_format($totalHours / $total, 1) : 0 ?></div>
                    <div class="text-white-50">人均课时</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-3">
        <!-- 人员排行 -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-person me-2"></i><?= $role === 'teacher' ? '老师' : '学生' ?>课时排行
                </div>
                <div class="card-body">
                    <?php if ($userStats): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>排名</th>
                                        <th>姓名</th>
                                        <th>用户名</th>
                                        <th>上课次数</th>
                                        <th>总课时</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userStats as $index => $stat): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            $rank = $offset + $index + 1;
                                            if ($rank == 1) echo '<i class="bi bi-trophy-fill text-warning"></i>';
                                            elseif ($rank == 2) echo '<i class="bi bi-trophy-fill text-secondary"></i>';
                                            elseif ($rank == 3) echo '<i class="bi bi-trophy-fill text-danger"></i>';
                                            else echo $rank;
                                            ?>
                                        </td>
                                        <td class="fw-medium"><?= e($stat['real_name']) ?></td>
                                        <td class="text-muted"><?= e($stat['username']) ?></td>
                                        <td><?= $stat['class_count'] ?> 次</td>
                                        <td><span class="fw-bold text-primary"><?= number_format($stat['total_hours'], 1) ?>h</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?= paginate($total, $page, $perPage, '/pages/admin/hours.php?month=' . urlencode($month) . '&role=' . urlencode($role)) ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-bar-chart"></i>
                            <p>暂无统计数据</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- 课程统计 -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-book me-2"></i>课程课时统计
                </div>
                <div class="card-body">
                    <?php if ($courseStats): ?>
                        <?php foreach ($courseStats as $stat): ?>
                        <div class="py-2 border-bottom">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-medium"><?= e($stat['course_name']) ?></span>
                                <span class="text-primary"><?= $stat['class_count'] ?> 次课</span>
                            </div>
                            <div class="d-flex justify-content-between small text-muted">
                                <span>老师：<?= e($stat['teacher_name'] ?? '-') ?></span>
                                <span>学生总课时: <?= number_format($stat['student_hours'], 1) ?>h</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-book"></i>
                            <p>暂无课程统计</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
