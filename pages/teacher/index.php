<?php
/**
 * 老师端 - 首页
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireTeacher();

$db = Database::getInstance();
$userId = Auth::id();
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// 今日课程
$todaySchedules = $db->fetchAll("
    SELECT s.*, c.course_name 
    FROM schedules s 
    LEFT JOIN courses c ON s.course_id = c.id 
    WHERE s.teacher_id = ? AND s.class_date = ? AND s.status = 1
    ORDER BY s.start_time ASC
", [$userId, $today]);

// 明日课程
$tomorrowSchedules = $db->fetchAll("
    SELECT s.*, c.course_name 
    FROM schedules s 
    LEFT JOIN courses c ON s.course_id = c.id 
    WHERE s.teacher_id = ? AND s.class_date = ? AND s.status = 1
    ORDER BY s.start_time ASC
", [$userId, $tomorrow]);

// 本月课时
$monthStart = date('Y-m-01');
$monthHours = $db->fetchOne("
    SELECT COALESCE(SUM(hours), 0) as total 
    FROM attendance 
    WHERE user_id = ? AND user_role = 'teacher' AND status = 'present' 
      AND date(check_out_time) >= ?
", [$userId, $monthStart])['total'];

// 总课时
$totalHours = $db->fetchOne("
    SELECT COALESCE(SUM(hours), 0) as total 
    FROM attendance 
    WHERE user_id = ? AND user_role = 'teacher' AND status = 'present'
", [$userId])['total'];

// 待审批请假数
$pendingLeaves = $db->fetchOne("
    SELECT COUNT(*) as cnt 
    FROM leave_requests 
    WHERE user_id = ? AND status = 'pending'
", [$userId])['cnt'];

// 我教授的课程数
$myCourses = $db->fetchOne("
    SELECT COUNT(*) as cnt 
    FROM courses 
    WHERE teacher_id = ? AND status = 1
", [$userId])['cnt'];

$pageTitle = '老师首页';
$activeMenu = 'dashboard';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="page-title mb-0">老师工作台</h2>
            <p class="page-subtitle mb-0">欢迎回来，<?= e(Auth::user()['real_name']) ?> 老师 👋</p>
        </div>
        <div class="text-end">
            <p class="text-muted mb-0"><?= e($today) ?> <?= e(getWeekDay($today)) ?></p>
        </div>
    </div>
    
    <!-- 数据卡片 -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card dashboard-card bg-primary text-white">
                <div class="card-body">
                    <div class="icon"><i class="bi bi-book"></i></div>
                    <div class="number"><?= $myCourses ?></div>
                    <div class="label text-white-50">任教课程</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card dashboard-card bg-success text-white">
                <div class="card-body">
                    <div class="icon"><i class="bi bi-calendar-day"></i></div>
                    <div class="number"><?= count($todaySchedules) ?></div>
                    <div class="label text-white-50">今日课程</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card dashboard-card bg-warning text-white">
                <div class="card-body">
                    <div class="icon"><i class="bi bi-clock-history"></i></div>
                    <div class="number"><?= number_format($monthHours, 1) ?></div>
                    <div class="label text-white-50">本月课时</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card dashboard-card bg-info text-white">
                <div class="card-body">
                    <div class="icon"><i class="bi bi-envelope"></i></div>
                    <div class="number"><?= $pendingLeaves ?></div>
                    <div class="label text-white-50">待批请假</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-3">
        <!-- 今日课程 -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-calendar-day me-2"></i>今日课程</span>
                    <a href="/pages/teacher/today.php" class="btn btn-sm btn-outline-primary">详情</a>
                </div>
                <div class="card-body">
                    <?php if ($todaySchedules): ?>
                        <?php foreach ($todaySchedules as $schedule): 
                            $attendance = $db->fetchOne(
                                "SELECT * FROM attendance WHERE schedule_id = ? AND user_id = ? AND user_role = 'teacher'",
                                [$schedule['id'], $userId]
                            );
                        ?>
                        <div class="card course-card mb-2">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="course-time"><?= e($schedule['start_time']) ?> - <?= e($schedule['end_time']) ?></div>
                                        <div class="course-name mt-1"><?= e($schedule['course_name']) ?></div>
                                        <div class="text-muted small"><i class="bi bi-geo-alt"></i> <?= e($schedule['classroom'] ?? '待定') ?></div>
                                    </div>
                                    <div>
                                        <?php if ($attendance && $attendance['status'] === 'present'): ?>
                                            <span class="badge bg-success">已签到</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">未签到</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-calendar-x"></i>
                            <p>今日暂无课程</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- 明日课程 -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-calendar2-plus me-2"></i>明日课程</span>
                    <a href="/pages/teacher/tomorrow.php" class="btn btn-sm btn-outline-primary">详情</a>
                </div>
                <div class="card-body">
                    <?php if ($tomorrowSchedules): ?>
                        <?php foreach ($tomorrowSchedules as $schedule): ?>
                        <div class="card course-card mb-2">
                            <div class="card-body py-2 px-3">
                                <div class="course-time"><?= e($schedule['start_time']) ?> - <?= e($schedule['end_time']) ?></div>
                                <div class="course-name mt-1"><?= e($schedule['course_name']) ?></div>
                                <div class="text-muted small"><i class="bi bi-geo-alt"></i> <?= e($schedule['classroom'] ?? '待定') ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-calendar-x"></i>
                            <p>明日暂无课程</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 快捷操作 -->
    <div class="card mt-3">
        <div class="card-header">
            <i class="bi bi-lightning me-2"></i>快捷操作
        </div>
        <div class="card-body">
            <div class="row g-2 text-center">
                <div class="col-6 col-md-3">
                    <a href="/pages/teacher/today.php" class="btn btn-outline-primary w-100 py-3">
                        <i class="bi bi-calendar-day d-block fs-3 mb-1"></i>
                        今日课程
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="/pages/teacher/tomorrow.php" class="btn btn-outline-success w-100 py-3">
                        <i class="bi bi-calendar2-plus d-block fs-3 mb-1"></i>
                        明日课程
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="/pages/teacher/attendance.php" class="btn btn-outline-info w-100 py-3">
                        <i class="bi bi-check2-square d-block fs-3 mb-1"></i>
                        考勤记录
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="/pages/teacher/leaves.php" class="btn btn-outline-warning w-100 py-3">
                        <i class="bi bi-envelope d-block fs-3 mb-1"></i>
                        请假申请
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
