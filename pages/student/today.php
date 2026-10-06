<?php
/**
 * 学生端 - 今日课程（含签到/签退）
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireStudent();

$db = Database::getInstance();
$userId = Auth::id();
$today = date('Y-m-d');

$message = '';
$messageType = '';

// 处理签到/签退
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $scheduleId = $_POST['schedule_id'] ?? 0;
    
    // 验证是否是该学生的课程
    $schedule = $db->fetchOne("
        SELECT s.* FROM schedules s
        LEFT JOIN student_courses sc ON s.course_id = sc.course_id
        WHERE s.id = ? AND sc.student_id = ? AND sc.status = 1
    ", [$scheduleId, $userId]);
    
    if (!$schedule) {
        $message = '课程不存在或未选课';
        $messageType = 'danger';
    } elseif ($action === 'checkin') {
        // 签到
        $existing = $db->fetchOne(
            "SELECT * FROM attendance WHERE schedule_id = ? AND user_id = ? AND user_role = 'student'",
            [$scheduleId, $userId]
        );
        
        if ($existing) {
            $message = '您已签到过了';
            $messageType = 'warning';
        } else {
            $db->query(
                "INSERT INTO attendance (schedule_id, user_id, user_role, status, check_in_time, hours) VALUES (?, ?, 'student', 'present', ?, 0)",
                [$scheduleId, $userId, date('Y-m-d H:i:s')]
            );
            $message = '签到成功！下课记得签退哦~';
            $messageType = 'success';
        }
    } elseif ($action === 'checkout') {
        // 签退（下课时点确认完成签到，计算课时）
        $attendance = $db->fetchOne(
            "SELECT * FROM attendance WHERE schedule_id = ? AND user_id = ? AND user_role = 'student'",
            [$scheduleId, $userId]
        );
        
        if (!$attendance) {
            $message = '请先签到';
            $messageType = 'danger';
        } elseif ($attendance['check_out_time']) {
            $message = '您已签退过了';
            $messageType = 'warning';
        } else {
            $hours = $schedule['hours'];
            $db->query(
                "UPDATE attendance SET check_out_time = ?, hours = ? WHERE id = ?",
                [date('Y-m-d H:i:s'), $hours, $attendance['id']]
            );
            $message = "签退成功！本次课时：{$hours}h";
            $messageType = 'success';
        }
    }
}

// 获取今日课程
$schedules = $db->fetchAll("
    SELECT s.*, c.course_name, u.real_name as teacher_name 
    FROM schedules s 
    LEFT JOIN courses c ON s.course_id = c.id 
    LEFT JOIN users u ON s.teacher_id = u.id
    LEFT JOIN student_courses sc ON s.course_id = sc.course_id
    WHERE sc.student_id = ? AND s.class_date = ? AND s.status = 1 AND sc.status = 1
    ORDER BY s.start_time ASC
", [$userId, $today]);

$pageTitle = '今日课程';
$activeMenu = 'today';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="page-title mb-0">今日课程</h2>
        <span class="text-muted"><i class="bi bi-calendar3"></i> <?= e($today) ?> <?= e(getWeekDay($today)) ?></span>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show alert-auto-dismiss" role="alert">
        <?= e($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($schedules): ?>
        <div class="row g-3">
            <?php foreach ($schedules as $schedule): 
                $attendance = $db->fetchOne(
                    "SELECT * FROM attendance WHERE schedule_id = ? AND user_id = ? AND user_role = 'student'",
                    [$schedule['id'], $userId]
                );
                
                $isCheckedIn = $attendance && $attendance['status'] === 'present';
                $isCheckedOut = $attendance && $attendance['check_out_time'];
                
                // 计算是否可以签到
                $checkInBefore = intval(get_setting('check_in_before', 30));
                $startTimestamp = strtotime($schedule['start_time']);
                $canCheckIn = time() >= ($startTimestamp - $checkInBefore * 60);
                
                // 是否可以签退（下课时间后）
                $checkOutAfter = intval(get_setting('check_out_after', 0));
                $endTimestamp = strtotime($schedule['end_time']);
                $canCheckOut = time() >= ($endTimestamp + $checkOutAfter * 60);
            ?>
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
                                <div class="text-muted small">
                                    <i class="bi bi-person me-1"></i><?= e($schedule['teacher_name']) ?> 老师
                                </div>
                            </div>
                            <div>
                                <?php if ($isCheckedOut): ?>
                                    <span class="badge bg-success fs-6"><i class="bi bi-check2-all"></i> 已完成</span>
                                <?php elseif ($isCheckedIn): ?>
                                    <span class="badge bg-warning text-dark fs-6"><i class="bi bi-play-fill"></i> 上课中</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary fs-6"><i class="bi bi-clock"></i> 待上课</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row mb-3 small">
                            <div class="col-6">
                                <span class="text-muted">教室</span>
                                <div class="fw-medium"><i class="bi bi-geo-alt me-1"></i><?= e($schedule['classroom'] ?? '待定') ?></div>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">课时</span>
                                <div class="fw-medium"><?= $schedule['hours'] ?> 小时</div>
                            </div>
                        </div>
                        
                        <?php if ($attendance): ?>
                        <div class="row mb-3 small text-muted">
                            <?php if ($attendance['check_in_time']): ?>
                            <div class="col-6">
                                签到时间：<?= e(date('H:i:s', strtotime($attendance['check_in_time']))) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($attendance['check_out_time']): ?>
                            <div class="col-6">
                                签退时间：<?= e(date('H:i:s', strtotime($attendance['check_out_time']))) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- 操作按钮 -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <?php if (!$isCheckedIn): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="checkin">
                                    <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>">
                                    <button type="submit" class="btn btn-primary" <?= !$canCheckIn ? 'disabled' : '' ?>>
                                        <i class="bi bi-box-arrow-in-right me-1"></i> 上课签到
                                    </button>
                                </form>
                                <?php if (!$canCheckIn): ?>
                                <small class="text-muted d-block mt-1">上课前 {$checkInBefore} 分钟内可签到</small>
                                <?php endif; ?>
                            <?php elseif (!$isCheckedOut): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="checkout">
                                    <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>">
                                    <button type="submit" class="btn btn-success" <?= !$canCheckOut ? 'disabled' : '' ?>>
                                        <i class="bi bi-check2-square me-1"></i> 下课确认（签退）
                                    </button>
                                </form>
                                <?php if (!$canCheckOut): ?>
                                <small class="text-muted d-block mt-1">下课后才可签退</small>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-success mb-0 w-100 text-center py-2">
                                    <i class="bi bi-check-circle me-1"></i>
                                    已完成签到签退，本次 <?= $schedule['hours'] ?> 课时
                                </div>
                            <?php endif; ?>
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
                    <h5>今日暂无课程安排</h5>
                    <p class="text-muted">好好休息，准备明天的课程吧~</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
