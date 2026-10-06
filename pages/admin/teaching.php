<?php
/**
 * 学管端 - 教学管理（整合课程、排课、考勤 + 今日课程大屏）
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAdmin();

$db = Database::getInstance();

$message = '';
$messageType = '';

$tab = $_GET['tab'] ?? 'today';

// ========== 公共数据 ==========
$today = date('Y-m-d');
$teachers = $db->fetchAll("SELECT id, real_name FROM users WHERE role = 'teacher' AND status = 1 ORDER BY real_name");
$allCourses = $db->fetchAll("SELECT id, course_name FROM courses WHERE status = 1 ORDER BY course_name");

// 教室历史记录
$classrooms = $db->fetchAll("SELECT DISTINCT classroom FROM schedules WHERE classroom IS NOT NULL AND classroom != '' ORDER BY classroom");

// 老师-课程映射（用于自动填写课程）
$teacherCourses = $db->fetchAll("SELECT teacher_id, id, course_name FROM courses WHERE status = 1 AND teacher_id IS NOT NULL");
$teacherCourseMap = [];
foreach ($teacherCourses as $tc) {
    $teacherCourseMap[$tc['teacher_id']][] = ['id' => $tc['id'], 'course_name' => $tc['course_name']];
}

// ========== 处理 POST 请求 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- 课程管理 ---
    if ($action === 'add_course') {
        $courseName = trim($_POST['course_name']);
        $description = trim($_POST['description'] ?? '');
        $teacherId = $_POST['teacher_id'] ?? null;
        $totalHours = intval($_POST['total_hours'] ?? 0);
        if (empty($courseName)) {
            $message = '请填写课程名称';
            $messageType = 'danger';
        } else {
            $db->query("INSERT INTO courses (course_name, description, teacher_id, total_hours) VALUES (?, ?, ?, ?)",
                [$courseName, $description, $teacherId, $totalHours]);
            $message = '课程添加成功';
            $messageType = 'success';
            $tab = 'courses';
        }
    } elseif ($action === 'edit_course') {
        $id = $_POST['id'];
        $db->query("UPDATE courses SET course_name=?, description=?, teacher_id=?, total_hours=?, status=? WHERE id=?",
            [trim($_POST['course_name']), trim($_POST['description'] ?? ''), $_POST['teacher_id'] ?? null,
             intval($_POST['total_hours'] ?? 0), $_POST['status'] ?? 1, $id]);
        $message = '课程更新成功';
        $messageType = 'success';
    } elseif ($action === 'delete_course') {
        $db->query("DELETE FROM courses WHERE id=?", [$_POST['id']]);
        $message = '课程删除成功';
        $messageType = 'success';
    }

    // --- 排课管理 ---
    elseif ($action === 'add_schedule') {
        $courseId = $_POST['course_id'];
        $teacherId = $_POST['teacher_id'];
        $classDate = $_POST['class_date'];
        $startTime = $_POST['start_time'];
        $endTime = $_POST['end_time'];
        $classroom = trim($_POST['classroom'] ?? '');
        $hours = floatval($_POST['hours'] ?? 1.0);
        if (empty($courseId) || empty($teacherId) || empty($classDate) || empty($startTime) || empty($endTime)) {
            $message = '请填写必填项';
            $messageType = 'danger';
        } else {
            $db->query("INSERT INTO schedules (course_id, teacher_id, class_date, start_time, end_time, classroom, hours) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$courseId, $teacherId, $classDate, $startTime, $endTime, $classroom, $hours]);
            $message = '排课成功';
            $messageType = 'success';
        }
    } elseif ($action === 'edit_schedule') {
        $id = $_POST['id'];
        $db->query("UPDATE schedules SET course_id=?, teacher_id=?, class_date=?, start_time=?, end_time=?, classroom=?, hours=?, status=? WHERE id=?",
            [$_POST['course_id'], $_POST['teacher_id'], $_POST['class_date'], $_POST['start_time'], $_POST['end_time'],
             trim($_POST['classroom'] ?? ''), floatval($_POST['hours'] ?? 1.0), $_POST['status'] ?? 1, $id]);
        $message = '排课更新成功';
        $messageType = 'success';
    } elseif ($action === 'delete_schedule') {
        $id = $_POST['id'];
        // 先删除关联的考勤和请假记录，再删除排课
        $db->query("DELETE FROM attendance WHERE schedule_id=?", [$id]);
        $db->query("DELETE FROM leave_requests WHERE schedule_id=?", [$id]);
        $db->query("DELETE FROM schedules WHERE id=?", [$id]);
        $message = '排课删除成功';
        $messageType = 'success';
    }

    // --- 考勤：批量签到 ---
    elseif ($action === 'batch_checkin') {
        $scheduleId = $_POST['schedule_id'];
        $now = date('Y-m-d H:i:s');
        $schedule = $db->fetchOne("SELECT * FROM schedules WHERE id = ?", [$scheduleId]);
        if ($schedule) {
            $students = $db->fetchAll("SELECT sc.student_id FROM student_courses sc WHERE sc.course_id = ? AND sc.status = 1", [$schedule['course_id']]);
            $count = 0;
            foreach ($students as $s) {
                $existing = $db->fetchOne("SELECT id FROM attendance WHERE schedule_id=? AND user_id=? AND user_role='student'", [$scheduleId, $s['student_id']]);
                if (!$existing) {
                    $db->query("INSERT INTO attendance (schedule_id, user_id, user_role, status, check_in_time, check_out_time, hours) VALUES (?, ?, 'student', 'present', ?, ?, ?)",
                        [$scheduleId, $s['student_id'], $now, $now, $schedule['hours']]);
                    $count++;
                }
            }
            $teacherDone = $db->fetchOne("SELECT id FROM attendance WHERE schedule_id=? AND user_id=? AND user_role='teacher'", [$scheduleId, $schedule['teacher_id']]);
            if (!$teacherDone) {
                $db->query("INSERT INTO attendance (schedule_id, user_id, user_role, status, check_in_time, check_out_time, hours) VALUES (?, ?, 'teacher', 'present', ?, ?, ?)",
                    [$scheduleId, $schedule['teacher_id'], $now, $now, $schedule['hours']]);
            }
            $message = "批量签到完成，共签到 {$count} 名学生 + 老师";
            $messageType = 'success';
        }
    }

    // --- 考勤：单独签到 ---
    elseif ($action === 'signin_student') {
        $scheduleId = $_POST['schedule_id'];
        $studentId = $_POST['student_id'];
        $now = date('Y-m-d H:i:s');
        $schedule = $db->fetchOne("SELECT * FROM schedules WHERE id = ?", [$scheduleId]);
        if ($schedule) {
            $existing = $db->fetchOne("SELECT id FROM attendance WHERE schedule_id=? AND user_id=? AND user_role='student'", [$scheduleId, $studentId]);
            if (!$existing) {
                $db->query("INSERT INTO attendance (schedule_id, user_id, user_role, status, check_in_time, check_out_time, hours) VALUES (?, ?, 'student', 'present', ?, ?, ?)",
                    [$scheduleId, $studentId, $now, $now, $schedule['hours']]);
                $message = '签到成功';
                $messageType = 'success';
            } else {
                $message = '该学生已签到';
                $messageType = 'info';
            }
        }
    }
}

// ========== 各 tab 数据 ==========

// --- 今日课程数据 ---
$todaySchedules = $db->fetchAll("
    SELECT s.*, c.course_name, u.real_name as teacher_name, u.phone as teacher_phone
    FROM schedules s
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN users u ON s.teacher_id = u.id
    WHERE s.class_date = ?
    ORDER BY s.start_time ASC
", [$today]);

// 为每个今日课程补充学生信息
foreach ($todaySchedules as &$sched) {
    $sched['students'] = $db->fetchAll("
        SELECT u.id, u.real_name, u.phone,
               (SELECT COUNT(*) FROM attendance a WHERE a.schedule_id=? AND a.user_id=u.id AND a.user_role='student' AND a.status='present') as checked,
               (SELECT COUNT(*) FROM leave_requests lr WHERE lr.schedule_id=? AND lr.user_id=u.id AND lr.status='approved') as has_leave
        FROM student_courses sc
        LEFT JOIN users u ON sc.student_id = u.id
        WHERE sc.course_id = ? AND sc.status = 1
        ORDER BY u.real_name
    ", [$sched['id'], $sched['id'], $sched['course_id']]);
    $sched['student_count'] = count($sched['students']);
    $sched['checked_count'] = count(array_filter($sched['students'], function($s) { return $s['checked'] > 0; }));
    $sched['leave_count'] = count(array_filter($sched['students'], function($s) { return $s['has_leave'] > 0; }));
}
unset($sched);

// --- 课程管理数据 ---
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;
$courseTotal = $db->fetchOne("SELECT COUNT(*) as cnt FROM courses")['cnt'];
$courses = $db->fetchAll("
    SELECT c.*, u.real_name as teacher_name
    FROM courses c LEFT JOIN users u ON c.teacher_id = u.id
    ORDER BY c.id ASC LIMIT $offset, $perPage
");

// --- 排课管理数据 ---
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d', strtotime('+30 days'));
$filterCourse = $_GET['filter_course'] ?? '';
$filterTeacher = $_GET['filter_teacher'] ?? '';

$sWhere = ["s.class_date BETWEEN ? AND ?"];
$sParams = [$dateFrom, $dateTo];
if ($filterCourse) { $sWhere[] = "s.course_id = ?"; $sParams[] = $filterCourse; }
if ($filterTeacher) { $sWhere[] = "s.teacher_id = ?"; $sParams[] = $filterTeacher; }
$sWhereSql = implode(' AND ', $sWhere);

$schedPage = max(1, intval($_GET['s_page'] ?? 1));
$schedPerPage = 15;
$schedOffset = ($schedPage - 1) * $schedPerPage;
$schedTotal = $db->fetchOne("SELECT COUNT(*) as cnt FROM schedules s WHERE $sWhereSql", $sParams)['cnt'];
$schedules = $db->fetchAll("
    SELECT s.*, c.course_name, u.real_name as teacher_name
    FROM schedules s LEFT JOIN courses c ON s.course_id = c.id LEFT JOIN users u ON s.teacher_id = u.id
    WHERE $sWhereSql ORDER BY s.class_date DESC, s.start_time ASC LIMIT $schedOffset, $schedPerPage
", $sParams);

// --- 课时统计数据（主标签页） ---
$statsMonth = $_GET['stats_month'] ?? date('Y-m');
$statsRole = $_GET['stats_role'] ?? 'student';
$statsMonthStart = $statsMonth . '-01';
$statsMonthEnd = date('Y-m-t', strtotime($statsMonthStart));
$statsTotalHours = $db->fetchOne("SELECT COALESCE(SUM(hours),0) as total FROM attendance WHERE status='present' AND user_role=? AND date(check_out_time) BETWEEN ? AND ?", [$statsRole, $statsMonthStart, $statsMonthEnd])['total'];
$statsUserCount = $db->fetchOne("SELECT COUNT(DISTINCT user_id) as cnt FROM attendance a WHERE a.user_role=? AND a.status='present' AND date(a.check_out_time) BETWEEN ? AND ?", [$statsRole, $statsMonthStart, $statsMonthEnd])['cnt'];
$statsUserData = $db->fetchAll("SELECT a.user_id, u.real_name, u.username, COALESCE(SUM(a.hours),0) as total_hours, COUNT(DISTINCT a.schedule_id) as class_count FROM attendance a LEFT JOIN users u ON a.user_id=u.id WHERE a.user_role=? AND a.status='present' AND date(a.check_out_time) BETWEEN ? AND ? GROUP BY a.user_id, u.real_name, u.username ORDER BY total_hours DESC LIMIT 50", [$statsRole, $statsMonthStart, $statsMonthEnd]);
$statsCourseData = $db->fetchAll("SELECT c.id, c.course_name, u.real_name as teacher_name, COALESCE(SUM(CASE WHEN a.user_role='student' THEN a.hours ELSE 0 END),0) as student_hours, COALESCE(SUM(CASE WHEN a.user_role='teacher' THEN a.hours ELSE 0 END),0) as teacher_hours, COUNT(DISTINCT a.schedule_id) as class_count FROM attendance a LEFT JOIN schedules s ON a.schedule_id=s.id LEFT JOIN courses c ON s.course_id=c.id LEFT JOIN users u ON c.teacher_id=u.id WHERE a.status='present' AND date(a.check_out_time) BETWEEN ? AND ? GROUP BY c.id, c.course_name, u.real_name ORDER BY class_count DESC LIMIT 10", [$statsMonthStart, $statsMonthEnd]);

$pageTitle = '教学管理';
$activeMenu = 'teaching';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<main class="container py-4">
    <h2 class="page-title mb-4">
        <i class="bi bi-mortarboard me-2"></i>教学管理
        <small class="text-muted fs-6 ms-2"><?= e($today) ?> <?= e(getWeekDay($today)) ?></small>
    </h2>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show alert-auto-dismiss" role="alert">
        <?= e($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ====== Tab 导航 ====== -->
    <ul class="nav nav-tabs nav-fill mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'today' ? 'active' : '' ?>" href="?tab=today">
                <i class="bi bi-calendar-day me-1"></i> 今日课程
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'courses' ? 'active' : '' ?>" href="?tab=courses">
                <i class="bi bi-book me-1"></i> 课程管理
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'schedules' ? 'active' : '' ?>" href="?tab=schedules">
                <i class="bi bi-calendar3 me-1"></i> 排课管理
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'stats' ? 'active' : '' ?>" href="?tab=stats">
                <i class="bi bi-bar-chart me-1"></i> 课时统计
            </a>
        </li>
    </ul>

    <!-- ============================================================ -->
    <!-- Tab 1: 今日课程 -->
    <!-- ============================================================ -->
    <?php if ($tab === 'today'): ?>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md">
            <div class="card text-center border-primary">
                <div class="card-body py-3">
                    <div class="text-primary fw-bold fs-3"><?= count($todaySchedules) ?></div>
                    <div class="small text-muted">今日课程</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card text-center border-success">
                <div class="card-body py-3">
                    <div class="text-success fw-bold fs-3">
                        <?php
                        $totalT = 0;
                        foreach ($todaySchedules as $s) $totalT += $s['student_count'];
                        echo $totalT;
                        ?>
                    </div>
                    <div class="small text-muted">今日学生</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card text-center border-warning">
                <div class="card-body py-3">
                    <div class="text-warning fw-bold fs-3">
                        <?php
                        $totalC = 0;
                        foreach ($todaySchedules as $s) $totalC += $s['checked_count'];
                        echo $totalC;
                        ?>
                    </div>
                    <div class="small text-muted">已签到</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card text-center border-info">
                <div class="card-body py-3">
                    <div class="text-info fw-bold fs-3">
                        <?php
                        $totalL = 0;
                        foreach ($todaySchedules as $s) $totalL += $s['leave_count'];
                        echo $totalL;
                        ?>
                    </div>
                    <div class="small text-muted">已请假</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card text-center border-secondary">
                <div class="card-body py-3">
                    <div class="text-secondary fw-bold fs-3">
                        <?php
                        $totalS = 0;
                        $totalL = 0;
                        $totalC = 0;
                        foreach ($todaySchedules as $s) { $totalS += $s['student_count']; $totalC += $s['checked_count']; $totalL += $s['leave_count']; }
                        $expected = $totalS - $totalL;
                        echo $expected > 0 ? round($totalC / $expected * 100) . '%' : '0%';
                        ?>
                    </div>
                    <div class="small text-muted">出勤率</div>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($todaySchedules)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="bi bi-calendar-x fs-1"></i>
                <h5>今日暂无课程安排</h5>
                <p class="text-muted">去「排课管理」标签页添加课程安排吧</p>
                <a href="?tab=schedules" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>去排课</a>
            </div>
        </div>
    </div>
    <?php else: ?>
        <?php foreach ($todaySchedules as $sched): ?>
        <div class="card mb-3 border-start border-4 border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="text-center" style="min-width: 60px;">
                            <div class="fw-bold fs-5 text-primary"><?= e($sched['start_time']) ?></div>
                            <div class="small text-muted"><?= e($sched['end_time']) ?></div>
                        </div>
                        <div>
                            <h5 class="mb-1"><?= e($sched['course_name']) ?></h5>
                            <div class="text-muted small">
                                <i class="bi bi-person me-1"></i><?= e($sched['teacher_name']) ?> 老师
                                <?php if (!empty($sched['teacher_phone'])): ?>
                                <span class="ms-2"><i class="bi bi-telephone me-1"></i><?= e($sched['teacher_phone']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($sched['classroom'])): ?>
                                <span class="ms-2"><i class="bi bi-geo-alt me-1"></i><?= e($sched['classroom']) ?></span>
                                <?php endif; ?>
                                <span class="ms-2 badge bg-info"><?= $sched['hours'] ?>课时</span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="badge bg-success fs-6">
                            <i class="bi bi-check-circle me-1"></i><?= $sched['checked_count'] ?>/<?= $sched['student_count'] ?>
                        </span>
                        <form method="POST" class="d-inline" onsubmit="return confirm('为该课程所有学生和老师批量签到？')">
                            <input type="hidden" name="action" value="batch_checkin">
                            <input type="hidden" name="schedule_id" value="<?= $sched['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-check2-all me-1"></i>批量签到
                            </button>
                        </form>
                    </div>
                </div>

                <!-- 学生列表 -->
                <?php if (!empty($sched['students'])): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>姓名</th>
                                <th class="hide-mobile">手机号</th>
                                <th style="min-width:120px;">状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sched['students'] as $i => $stu): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e($stu['real_name']) ?></td>
                                <td class="hide-mobile"><?= e($stu['phone'] ?? '-') ?></td>
                                <td class="text-nowrap">
                                    <?php if ($stu['has_leave']): ?>
                                    <span class="badge bg-warning text-dark py-1 px-2"><i class="bi bi-file-text"></i> 请假</span>
                                    <?php elseif ($stu['checked']): ?>
                                    <span class="badge bg-success py-1 px-2"><i class="bi bi-check"></i> 已到</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary py-1 px-2 me-1"><i class="bi bi-x"></i> 未到</span>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="signin_student">
                                        <input type="hidden" name="schedule_id" value="<?= $sched['id'] ?>">
                                        <input type="hidden" name="student_id" value="<?= $stu['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm" title="点击签到">
                                            <i class="bi bi-check2"></i> 签到
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-muted small"><i class="bi bi-info-circle me-1"></i>该课程暂无学生选课</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- Tab 2: 课程管理 -->
    <!-- ============================================================ -->
    <?php elseif ($tab === 'courses'): ?>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <span class="text-muted">共 <?= $courseTotal ?> 门课程</span>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
            <i class="bi bi-plus-lg me-1"></i> 添加课程
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>课程名称</th>
                            <th class="hide-mobile">授课老师</th>
                            <th class="hide-mobile">总课时</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $c): ?>
                        <tr>
                            <td><?= $c['id'] ?></td>
                            <td>
                                <div class="fw-medium"><?= e($c['course_name']) ?></div>
                                <?php if ($c['description']): ?>
                                <small class="text-muted"><?= e(mb_substr($c['description'], 0, 30)) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="hide-mobile"><?= e($c['teacher_name'] ?? '-') ?></td>
                            <td class="hide-mobile"><?= $c['total_hours'] ?>h</td>
                            <td>
                                <?php if ($c['status']): ?>
                                    <span class="badge bg-success">启用</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">禁用</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editCourse(<?= $c['id'] ?>, '<?= e($c['course_name']) ?>', '<?= e($c['description']) ?>', <?= $c['teacher_id'] ?? 'null' ?>, <?= $c['total_hours'] ?>, <?= $c['status'] ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('确定删除该课程？')">
                                        <input type="hidden" name="action" value="delete_course">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= paginate($courseTotal, $page, $perPage, '?tab=courses') ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- Tab 3: 排课管理 -->
    <!-- ============================================================ -->
    <?php elseif ($tab === 'schedules'): ?>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <span class="text-muted">共 <?= $schedTotal ?> 条排课记录</span>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
            <i class="bi bi-plus-lg me-1"></i> 新增排课
        </button>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="schedules">
                <div class="col-md-3">
                    <label class="form-label">开始日期</label>
                    <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">结束日期</label>
                    <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">课程</label>
                    <select name="filter_course" class="form-select">
                        <option value="">全部</option>
                        <?php foreach ($allCourses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filterCourse == $c['id'] ? 'selected' : '' ?>><?= e($c['course_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">老师</label>
                    <select name="filter_teacher" class="form-select">
                        <option value="">全部</option>
                        <?php foreach ($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $filterTeacher == $t['id'] ? 'selected' : '' ?>><?= e($t['real_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> 搜索</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>日期</th>
                            <th>时间</th>
                            <th>课程</th>
                            <th>老师</th>
                            <th class="hide-mobile">教室</th>
                            <th>课时</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $s): ?>
                        <tr>
                            <td><?= e($s['class_date']) ?><br><small class="text-muted"><?= e(getWeekDay($s['class_date'])) ?></small></td>
                            <td class="text-primary fw-bold"><?= e($s['start_time']) ?>-<?= e($s['end_time']) ?></td>
                            <td><?= e($s['course_name']) ?></td>
                            <td><?= e($s['teacher_name']) ?></td>
                            <td class="hide-mobile"><?= e($s['classroom'] ?? '-') ?></td>
                            <td><?= $s['hours'] ?>h</td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editSchedule(<?= $s['id'] ?>, <?= $s['course_id'] ?>, <?= $s['teacher_id'] ?>, '<?= $s['class_date'] ?>', '<?= $s['start_time'] ?>', '<?= $s['end_time'] ?>', '<?= e($s['classroom'] ?? '') ?>', <?= $s['hours'] ?>, <?= $s['status'] ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('确定删除该排课？')">
                                        <input type="hidden" name="action" value="delete_schedule">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
            $sq = http_build_query(['tab'=>'schedules','date_from'=>$dateFrom,'date_to'=>$dateTo,'filter_course'=>$filterCourse,'filter_teacher'=>$filterTeacher]);
            echo paginate($schedTotal, $schedPage, $schedPerPage, '?' . $sq, 's_page');
            ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- Tab 4: 课时统计 -->
    <!-- ============================================================ -->
    <?php elseif ($tab === 'stats'): ?>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="stats">
                <div class="col-md-4">
                    <label class="form-label">统计月份</label>
                    <input type="month" name="stats_month" class="form-control" value="<?= e($statsMonth) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">统计对象</label>
                    <select name="stats_role" class="form-select">
                        <option value="student" <?= $statsRole === 'student' ? 'selected' : '' ?>>学生课时</option>
                        <option value="teacher" <?= $statsRole === 'teacher' ? 'selected' : '' ?>>老师课时</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-bar-chart me-1"></i> 统计</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 总览卡片 -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <div class="fs-2 fw-bold"><?= number_format($statsTotalHours, 1) ?></div>
                    <div class="text-white-50"><?= $statsMonth ?> 总课时</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <div class="fs-2 fw-bold"><?= $statsUserCount ?></div>
                    <div class="text-white-50">出勤<?= $statsRole === 'teacher' ? '老师' : '学生' ?>人数</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <div class="fs-2 fw-bold"><?= $statsUserCount > 0 ? number_format($statsTotalHours / $statsUserCount, 1) : 0 ?></div>
                    <div class="text-white-50">人均课时</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><i class="bi bi-person me-2"></i><?= $statsRole === 'teacher' ? '老师' : '学生' ?>课时排行</div>
                <div class="card-body">
                    <?php if ($statsUserData): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>排名</th><th>姓名</th><th>用户名</th><th>上课次数</th><th>总课时</th></tr></thead>
                            <tbody>
                                <?php foreach ($statsUserData as $i => $u): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td class="fw-medium"><?= e($u['real_name']) ?></td>
                                    <td class="text-muted"><?= e($u['username']) ?></td>
                                    <td><?= $u['class_count'] ?> 次</td>
                                    <td><span class="fw-bold text-primary"><?= number_format($u['total_hours'], 1) ?>h</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state"><i class="bi bi-bar-chart"></i><p>暂无统计数据</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><i class="bi bi-book me-2"></i>课程课时统计</div>
                <div class="card-body">
                    <?php if ($statsCourseData): ?>
                        <?php foreach ($statsCourseData as $cs): ?>
                        <div class="py-2 border-bottom">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-medium"><?= e($cs['course_name']) ?></span>
                                <span class="text-primary"><?= $cs['class_count'] ?> 次课</span>
                            </div>
                            <div class="d-flex justify-content-between small text-muted">
                                <span>老师：<?= e($cs['teacher_name'] ?? '-') ?></span>
                                <span>学生总课时: <?= number_format($cs['student_hours'], 1) ?>h</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="empty-state"><i class="bi bi-book"></i><p>暂无课程统计</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</main>

<!-- ====== 模态框：添加课程 ====== -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5 class="modal-title"><i class="bi bi-book me-2"></i>添加课程</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_course">
                    <div class="mb-3"><label class="form-label">课程名称 <span class="text-danger">*</span></label>
                        <input type="text" name="course_name" class="form-control" required placeholder="请输入课程名称"></div>
                    <div class="mb-3"><label class="form-label">授课老师</label>
                        <select name="teacher_id" class="form-select"><option value="">请选择</option>
                            <?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['real_name']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="mb-3"><label class="form-label">总课时</label>
                        <input type="number" name="total_hours" class="form-control" value="0" min="0"></div>
                    <div class="mb-0"><label class="form-label">课程描述</label>
                        <textarea name="description" class="form-control" rows="3"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">添加</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====== 模态框：编辑课程 ====== -->
<div class="modal fade" id="editCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>编辑课程</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_course">
                    <input type="hidden" name="id" id="edit-course-id">
                    <div class="mb-3"><label class="form-label">课程名称 <span class="text-danger">*</span></label>
                        <input type="text" name="course_name" id="edit-course_name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">授课老师</label>
                        <select name="teacher_id" id="edit-teacher_id" class="form-select"><option value="">请选择</option>
                            <?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['real_name']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="mb-3"><label class="form-label">总课时</label>
                        <input type="number" name="total_hours" id="edit-total_hours" class="form-control" min="0"></div>
                    <div class="mb-3"><label class="form-label">状态</label>
                        <select name="status" id="edit-status" class="form-select"><option value="1">启用</option><option value="0">禁用</option></select></div>
                    <div class="mb-0"><label class="form-label">课程描述</label>
                        <textarea name="description" id="edit-description" class="form-control" rows="3"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====== 模态框：新增排课 ====== -->
<div class="modal fade" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>新增排课</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_schedule">
                    <div class="mb-3"><label class="form-label">授课老师 <span class="text-danger">*</span></label>
                        <select name="teacher_id" id="add-teacher_id" class="form-select" required><option value="">请选择</option>
                            <?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['real_name']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="mb-3"><label class="form-label">课程 <span class="text-danger">*</span></label>
                        <select name="course_id" id="add-course_id" class="form-select" required><option value="">请选择</option>
                            <?php foreach ($allCourses as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['course_name']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="mb-3"><label class="form-label">上课日期 <span class="text-danger">*</span></label>
                        <input type="date" name="class_date" class="form-control" required value="<?= $today ?>"></div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label">开始时间 <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control" required value="08:00"></div>
                        <div class="col-6"><label class="form-label">结束时间 <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control" required value="09:40"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">教室</label>
                        <input type="text" name="classroom" class="form-control" list="classroom-list" placeholder="如：教学楼A101">
                        <datalist id="classroom-list">
                            <?php foreach ($classrooms as $cr): ?>
                            <option value="<?= e($cr['classroom']) ?>">
                            <?php endforeach; ?>
                        </datalist></div>
                    <div class="mb-0"><label class="form-label">课时数</label>
                        <input type="number" step="0.5" name="hours" class="form-control" value="2.0" min="0.5"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">确认排课</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====== 模态框：编辑排课 ====== -->
<div class="modal fade" id="editScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>编辑排课</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_schedule">
                    <input type="hidden" name="id" id="edit-s-id">
                    <div class="mb-3"><label class="form-label">授课老师 <span class="text-danger">*</span></label>
                        <select name="teacher_id" id="edit-s-teacher_id" class="form-select" required><option value="">请选择</option>
                            <?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['real_name']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="mb-3"><label class="form-label">课程 <span class="text-danger">*</span></label>
                        <select name="course_id" id="edit-s-course_id" class="form-select" required><option value="">请选择</option>
                            <?php foreach ($allCourses as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['course_name']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="mb-3"><label class="form-label">上课日期 <span class="text-danger">*</span></label>
                        <input type="date" name="class_date" id="edit-s-class_date" class="form-control" required></div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label">开始时间 <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" id="edit-s-start_time" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">结束时间 <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" id="edit-s-end_time" class="form-control" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label">教室</label>
                        <input type="text" name="classroom" id="edit-s-classroom" class="form-control" list="classroom-list"></div>
                    <div class="mb-3"><label class="form-label">课时数</label>
                        <input type="number" step="0.5" name="hours" id="edit-s-hours" class="form-control" min="0.5"></div>
                    <div class="mb-0"><label class="form-label">状态</label>
                        <select name="status" id="edit-s-status" class="form-select"><option value="1">正常</option><option value="0">取消</option></select></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// 老师-课程映射数据
const teacherCourseMap = <?= json_encode($teacherCourseMap) ?>;

// 根据老师ID过滤课程下拉框（只显示该老师教授的课程）
function filterCoursesByTeacher(teacherId, selectElement) {
    const courses = teacherCourseMap[teacherId] || [];
    selectElement.innerHTML = '<option value="">请选择课程</option>';
    if (courses.length === 0) {
        selectElement.disabled = true;
    } else {
        selectElement.disabled = false;
        courses.forEach(function(c) {
            var opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.course_name;
            selectElement.appendChild(opt);
        });
        // 如果只有一门课，自动选中
        if (courses.length === 1) {
            selectElement.value = courses[0].id;
        }
    }
}

// 初始化
document.addEventListener('DOMContentLoaded', function() {
    // 新增排课 - 老师选择联动课程
    var addTeacher = document.getElementById('add-teacher_id');
    var addCourse = document.getElementById('add-course_id');
    if (addTeacher && addCourse) {
        addTeacher.addEventListener('change', function() {
            filterCoursesByTeacher(this.value, addCourse);
        });
    }

    // 编辑排课 - 老师选择联动课程
    var editTeacher = document.getElementById('edit-s-teacher_id');
    var editCourse = document.getElementById('edit-s-course_id');
    if (editTeacher && editCourse) {
        editTeacher.addEventListener('change', function() {
            filterCoursesByTeacher(this.value, editCourse);
        });
    }
});

// 编辑课程
function editCourse(id, courseName, description, teacherId, totalHours, status) {
    document.getElementById('edit-course-id').value = id;
    document.getElementById('edit-course_name').value = courseName;
    document.getElementById('edit-description').value = description;
    document.getElementById('edit-teacher_id').value = teacherId || '';
    document.getElementById('edit-total_hours').value = totalHours;
    document.getElementById('edit-status').value = status;
    new bootstrap.Modal(document.getElementById('editCourseModal')).show();
}

// 编辑排课
function editSchedule(id, courseId, teacherId, classDate, startTime, endTime, classroom, hours, status) {
    document.getElementById('edit-s-id').value = id;
    document.getElementById('edit-s-teacher_id').value = teacherId;
    document.getElementById('edit-s-class_date').value = classDate;
    document.getElementById('edit-s-start_time').value = startTime;
    document.getElementById('edit-s-end_time').value = endTime;
    document.getElementById('edit-s-classroom').value = classroom;
    document.getElementById('edit-s-hours').value = hours;
    document.getElementById('edit-s-status').value = status;
    // 先过滤课程，再设置选中的课程
    filterCoursesByTeacher(teacherId, document.getElementById('edit-s-course_id'));
    document.getElementById('edit-s-course_id').value = courseId;
    new bootstrap.Modal(document.getElementById('editScheduleModal')).show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>