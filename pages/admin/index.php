<?php
require_once '../../config/database.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

Auth::requireRole('admin');

$pageTitle = '学管端 - 信息栏';
$activeMenu = 'dashboard';

$db = Database::getInstance();

// 统计数据
$studentCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE role = 'student'")['cnt'];
$teacherCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE role = 'teacher'")['cnt'];
$courseCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM courses WHERE status = 1")['cnt'];

// 今日日期
$today = date('Y-m-d');

// 今日课程数
$todaySchedules = $db->fetchAll(
    "SELECT s.*, c.course_name, u.real_name as teacher_name 
     FROM schedules s 
     LEFT JOIN courses c ON s.course_id = c.id 
     LEFT JOIN users u ON s.teacher_id = u.id 
     WHERE s.class_date = ? 
     ORDER BY s.start_time",
    [$today]
);
$todayCount = count($todaySchedules);

// 待审批请假
$pendingLeaves = $db->fetchAll(
    "SELECT lr.*, u.real_name, u.role, c.course_name 
     FROM leave_requests lr 
     LEFT JOIN users u ON lr.user_id = u.id 
     LEFT JOIN schedules s ON lr.schedule_id = s.id 
     LEFT JOIN courses c ON s.course_id = c.id 
     WHERE lr.status = 'pending' 
     ORDER BY lr.created_at DESC 
     LIMIT 5"
);
$pendingCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM leave_requests WHERE status = 'pending'")['cnt'];

// 本月课时
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$monthHours = $db->fetchOne(
    "SELECT COALESCE(SUM(hours), 0) as total FROM attendance 
     WHERE status = 'present' AND check_in_time >= ? AND check_in_time <= ?",
    [$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']
)['total'];

require '../../includes/header.php';
require '../../includes/navbar.php';
?>

<!-- 顶部信息栏 -->
<div class="info-hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="hero-title">
                    <i class="bi bi-sunrise-fill me-2"></i>
                    早上好，<?= e(Auth::user()['real_name']) ?>！
                </h2>
                <p class="hero-subtitle mb-0">
                    <i class="bi bi-calendar-date me-1"></i>
                    <?= date('Y年m月d日') ?> &nbsp;·&nbsp; 星期<?= $weekdays[date('w')] ?? '' ?>
                    &nbsp;·&nbsp; 今日共有 <strong><?= $todayCount ?></strong> 节课
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="hero-quick-badge">
                    <i class="bi bi-bell me-1"></i>
                    待办事项：<strong><?= $pendingCount + $todayCount ?></strong> 项
                </div>
            </div>
        </div>
    </div>
</div>

<main class="container py-4">
    <!-- 快速操作 -->
    <section class="mb-4">
        <h3 class="section-title">快速操作</h3>
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <a href="/pages/admin/teaching.php?tab=today" class="quick-action-card qa-today">
                    <div class="qa-icon"><i class="bi bi-calendar-day"></i></div>
                    <div class="qa-title">今日课程</div>
                    <div class="qa-badge"><?= $todayCount ?> 节</div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="/pages/admin/leaves.php?status=pending" class="quick-action-card qa-leave">
                    <div class="qa-icon"><i class="bi bi-envelope-exclamation"></i></div>
                    <div class="qa-title">请假审批</div>
                    <?php if ($pendingCount > 0): ?>
                    <div class="qa-badge"><?= $pendingCount ?> 待审</div>
                    <?php else: ?>
                    <div class="qa-badge qa-badge-muted">暂无</div>
                    <?php endif; ?>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="/pages/admin/teaching.php?tab=schedules" class="quick-action-card qa-add">
                    <div class="qa-icon"><i class="bi bi-plus-circle"></i></div>
                    <div class="qa-title">增加课程</div>
                    <div class="qa-badge qa-badge-muted">排课</div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="/pages/admin/users.php?action=add" class="quick-action-card qa-user">
                    <div class="qa-icon"><i class="bi bi-person-plus"></i></div>
                    <div class="qa-title">添加用户</div>
                    <div class="qa-badge qa-badge-muted">学生/老师</div>
                </a>
            </div>
        </div>
    </section>

    <!-- 数据统计卡片 -->
    <section class="mb-4">
        <h3 class="section-title">数据统计</h3>
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="stat-card stat-primary">
                    <div class="stat-icon"><i class="bi bi-people"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?= $studentCount ?></div>
                        <div class="stat-label">学生总数</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card stat-success">
                    <div class="stat-icon"><i class="bi bi-person-check"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?= $teacherCount ?></div>
                        <div class="stat-label">老师总数</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card stat-warning">
                    <div class="stat-icon"><i class="bi bi-book"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?= $courseCount ?></div>
                        <div class="stat-label">在开课程</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card stat-info">
                    <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?= $monthHours ?></div>
                        <div class="stat-label">本月课时</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4">
        <!-- 今日课程列表 -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-calendar-day me-1"></i> 今日课程</span>
                    <a href="/pages/admin/teaching.php?tab=today" class="btn btn-sm btn-outline-primary">
                        查看全部 <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($todaySchedules)): ?>
                    <div class="empty-state">
                        <i class="bi bi-calendar-x empty-icon"></i>
                        <p>今日暂无课程安排</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($todaySchedules, 0, 6) as $sched): ?>
                        <div class="list-group-item list-group-item-action d-flex align-items-center">
                            <div class="schedule-time me-3">
                                <div class="time-start"><?= e($sched['start_time']) ?></div>
                                <div class="time-line"></div>
                                <div class="time-end"><?= e($sched['end_time']) ?></div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?= e($sched['course_name']) ?></div>
                                <div class="text-muted small">
                                    <i class="bi bi-person me-1"></i><?= e($sched['teacher_name']) ?>
                                    <?php if (!empty($sched['classroom'])): ?>
                                    <span class="ms-2"><i class="bi bi-geo-alt me-1"></i><?= e($sched['classroom']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="badge bg-primary"><?= $sched['hours'] ?>课时</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 待办事项 -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-check me-1"></i> 待办事项</span>
                    <?php if ($pendingCount > 0): ?>
                    <span class="badge bg-warning"><?= $pendingCount ?> 待审</span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pendingLeaves)): ?>
                    <div class="empty-state">
                        <i class="bi bi-check2-all empty-icon text-success"></i>
                        <p>暂无待办事项</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($pendingLeaves as $leave): ?>
                        <a href="/pages/admin/leaves.php?status=pending" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <strong><?= e($leave['real_name']) ?></strong>
                                <span class="badge bg-warning">
                                    <?= $leave['role'] === 'teacher' ? '老师' : '学生' ?>
                                </span>
                            </div>
                            <div class="text-muted small mb-1">
                                <i class="bi bi-calendar-date me-1"></i><?= e($leave['leave_date']) ?>
                                <span class="ms-2">
                                    <i class="bi bi-tag me-1"></i>
                                    <?= $leaveTypes[$leave['leave_type']] ?? $leave['leave_type'] ?>
                                </span>
                            </div>
                            <div class="text-truncate small" style="color: #6c757d;">
                                <?= e(mb_substr($leave['reason'], 0, 30)) ?>...
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require '../../includes/footer.php'; ?>
