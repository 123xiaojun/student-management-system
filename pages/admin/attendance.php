<?php
/**
 * 学管端 - 考勤管理 + 一键批量签到
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAdmin();

$db = Database::getInstance();

$message = '';
$messageType = '';

// 处理批量签到
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'batch_checkin') {
        $scheduleId = $_POST['schedule_id'];
        $now = date('Y-m-d H:i:s');
        $schedule = $db->fetchOne("SELECT * FROM schedules WHERE id = ?", [$scheduleId]);
        
        if ($schedule) {
            // 给所有选课学生批量签到
            $students = $db->fetchAll("
                SELECT sc.student_id 
                FROM student_courses sc 
                WHERE sc.course_id = ? AND sc.status = 1
            ", [$schedule['course_id']]);
            
            $count = 0;
            foreach ($students as $student) {
                // 检查是否已有签到记录
                $existing = $db->fetchOne(
                    "SELECT id FROM attendance WHERE schedule_id = ? AND user_id = ? AND user_role = 'student'",
                    [$scheduleId, $student['student_id']]
                );
                
                if (!$existing) {
                    $db->query(
                        "INSERT INTO attendance (schedule_id, user_id, user_role, status, check_in_time, check_out_time, hours) VALUES (?, ?, 'student', 'present', ?, ?, ?)",
                        [$scheduleId, $student['student_id'], $now, $now, $schedule['hours']]
                    );
                    $count++;
                }
            }
            
            // 老师也签到
            $teacherAttendance = $db->fetchOne(
                "SELECT id FROM attendance WHERE schedule_id = ? AND user_id = ? AND user_role = 'teacher'",
                [$scheduleId, $schedule['teacher_id']]
            );
            if (!$teacherAttendance) {
                $db->query(
                    "INSERT INTO attendance (schedule_id, user_id, user_role, status, check_in_time, check_out_time, hours) VALUES (?, ?, 'teacher', 'present', ?, ?, ?)",
                    [$scheduleId, $schedule['teacher_id'], $now, $now, $schedule['hours']]
                );
            }
            
            $message = "批量签到完成，共签到 {$count} 名学生";
            $messageType = 'success';
        }
    }
}

// 筛选
$date = $_GET['date'] ?? date('Y-m-d');
$courseId = $_GET['course_id'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// 获取当天课程列表
$where = ["s.class_date = ?"];
$params = [$date];

if ($courseId) {
    $where[] = "s.course_id = ?";
    $params[] = $courseId;
}

$whereSql = implode(' AND ', $where);

$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM schedules s WHERE $whereSql", $params)['cnt'];
$schedules = $db->fetchAll("
    SELECT s.*, c.course_name, u.real_name as teacher_name 
    FROM schedules s 
    LEFT JOIN courses c ON s.course_id = c.id 
    LEFT JOIN users u ON s.teacher_id = u.id 
    WHERE $whereSql 
    ORDER BY s.start_time ASC 
    LIMIT $offset, $perPage
", $params);

// 获取课程列表
$courses = $db->fetchAll("SELECT id, course_name FROM courses WHERE status = 1 ORDER BY course_name");

$pageTitle = '考勤管理';
$activeMenu = 'attendance';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<main class="container py-4">
    <h2 class="page-title mb-4">考勤管理</h2>
    
    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show alert-auto-dismiss" role="alert">
        <?= e($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- 筛选 -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">日期</label>
                    <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">课程</label>
                    <select name="course_id" class="form-select">
                        <option value="">全部课程</option>
                        <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $courseId == $c['id'] ? 'selected' : '' ?>><?= e($c['course_name']) ?></option>
                        <?php endforeach; ?>
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
            <h6 class="mb-3"><?= e($date) ?> <?= e(getWeekDay($date)) ?> 课程列表</h6>
            
            <?php if ($schedules): ?>
                <?php foreach ($schedules as $schedule): 
                    // 统计签到情况
                    $totalStudents = $db->fetchOne("
                        SELECT COUNT(*) as cnt 
                        FROM student_courses sc 
                        WHERE sc.course_id = ? AND sc.status = 1
                    ", [$schedule['course_id']])['cnt'];
                    
                    $checkedIn = $db->fetchOne("
                        SELECT COUNT(*) as cnt 
                        FROM attendance 
                        WHERE schedule_id = ? AND user_role = 'student' AND status = 'present'
                    ", [$schedule['id']])['cnt'];
                    
                    $teacherChecked = $db->fetchOne("
                        SELECT id 
                        FROM attendance 
                        WHERE schedule_id = ? AND user_role = 'teacher' AND status = 'present'
                    ", [$schedule['id']]);
                ?>
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
                        <div>
                            <span class="text-primary fw-bold">
                                <i class="bi bi-clock"></i> 
                                <?= e($schedule['start_time']) ?> - <?= e($schedule['end_time']) ?>
                            </span>
                            <span class="ms-3 fw-bold"><?= e($schedule['course_name']) ?></span>
                        </div>
                        <div>
                            <form method="POST" class="d-inline" onsubmit="return confirm('确定要对该课程所有学生和老师批量签到吗？')">
                                <input type="hidden" name="action" value="batch_checkin">
                                <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="bi bi-check2-all me-1"></i> 一键批量签到
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="row text-center">
                        <div class="col-4">
                            <small class="text-muted">授课老师</small>
                            <div class="fw-medium"><?= e($schedule['teacher_name']) ?> 
                                <?php if ($teacherChecked): ?>
                                    <span class="badge bg-success ms-1">已签到</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary ms-1">未签到</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">学生出勤</small>
                            <div class="fw-medium">
                                <span class="text-success"><?= $checkedIn ?></span> / <?= $totalStudents ?>
                            </div>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">教室</small>
                            <div class="fw-medium"><?= e($schedule['classroom'] ?? '-') ?></div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-success" style="width: <?= $totalStudents > 0 ? ($checkedIn / $totalStudents * 100) : 0 ?>%"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?= paginate($total, $page, $perPage, '/pages/admin/attendance.php?date=' . urlencode($date) . '&course_id=' . urlencode($courseId)) ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-calendar-x"></i>
                    <p>当天暂无课程安排</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
