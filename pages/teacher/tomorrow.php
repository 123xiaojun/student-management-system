<?php
/**
 * 老师端 - 明日课程
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireTeacher();

$db = Database::getInstance();
$userId = Auth::id();
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// 获取明日课程
$schedules = $db->fetchAll("
    SELECT s.*, c.course_name,
           (SELECT COUNT(*) FROM student_courses sc WHERE sc.course_id = s.course_id AND sc.status = 1) as student_count
    FROM schedules s 
    LEFT JOIN courses c ON s.course_id = c.id 
    WHERE s.teacher_id = ? AND s.class_date = ? AND s.status = 1
    ORDER BY s.start_time ASC
", [$userId, $tomorrow]);

$pageTitle = '明日课程';
$activeMenu = 'tomorrow';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="page-title mb-0">明日课程</h2>
        <span class="text-muted"><i class="bi bi-calendar3"></i> <?= e($tomorrow) ?> <?= e(getWeekDay($tomorrow)) ?></span>
    </div>
    
    <?php if ($schedules): ?>
        <div class="row g-3">
            <?php foreach ($schedules as $schedule): ?>
            <div class="col-lg-6">
                <div class="card course-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <div class="course-time">
                                    <i class="bi bi-clock"></i> 
                                    <?= e($schedule['start_time']) ?> - <?= e($schedule['end_time']) ?>
                                </div>
                                <h5 class="course-name mt-2 mb-1"><?= e($schedule['course_name']) ?></h5>
                            </div>
                            <span class="badge bg-info"><i class="bi bi-people me-1"></i><?= $schedule['student_count'] ?> 人</span>
                        </div>
                        
                        <div class="row small">
                            <div class="col-6">
                                <span class="text-muted">教室</span>
                                <div class="fw-medium"><i class="bi bi-geo-alt me-1"></i><?= e($schedule['classroom'] ?? '待定') ?></div>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">课时</span>
                                <div class="fw-medium"><?= $schedule['hours'] ?> 小时</div>
                            </div>
                        </div>
                        
                        <div class="mt-3 pt-3 border-top">
                            <a href="/pages/teacher/leaves.php?schedule_id=<?= $schedule['id'] ?>&date=<?= $tomorrow ?>" 
                               class="btn btn-outline-warning btn-sm">
                                <i class="bi bi-envelope me-1"></i>申请请假
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <i class="bi bi-calendar-x fs-1"></i>
                    <h5>明日暂无课程安排</h5>
                    <p class="text-muted">好好休息吧~</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
