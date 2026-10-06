<?php
/**
 * 学管端 - 排课管理
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAdmin();

$db = Database::getInstance();

$message = '';
$messageType = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
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
            $db->query(
                "INSERT INTO schedules (course_id, teacher_id, class_date, start_time, end_time, classroom, hours) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$courseId, $teacherId, $classDate, $startTime, $endTime, $classroom, $hours]
            );
            $message = '排课成功';
            $messageType = 'success';
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'];
        $courseId = $_POST['course_id'];
        $teacherId = $_POST['teacher_id'];
        $classDate = $_POST['class_date'];
        $startTime = $_POST['start_time'];
        $endTime = $_POST['end_time'];
        $classroom = trim($_POST['classroom'] ?? '');
        $hours = floatval($_POST['hours'] ?? 1.0);
        $status = $_POST['status'] ?? 1;
        
        $db->query(
            "UPDATE schedules SET course_id = ?, teacher_id = ?, class_date = ?, start_time = ?, end_time = ?, classroom = ?, hours = ?, status = ? WHERE id = ?",
            [$courseId, $teacherId, $classDate, $startTime, $endTime, $classroom, $hours, $status, $id]
        );
        $message = '排课更新成功';
        $messageType = 'success';
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        $db->query("DELETE FROM schedules WHERE id = ?", [$id]);
        $message = '排课删除成功';
        $messageType = 'success';
    }
}

// 筛选
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d', strtotime('+30 days'));
$courseId = $_GET['course_id'] ?? '';
$teacherId = $_GET['teacher_id'] ?? '';

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = ["class_date BETWEEN ? AND ?"];
$params = [$dateFrom, $dateTo];

if ($courseId) {
    $where[] = "s.course_id = ?";
    $params[] = $courseId;
}
if ($teacherId) {
    $where[] = "s.teacher_id = ?";
    $params[] = $teacherId;
}

$whereSql = implode(' AND ', $where);

$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM schedules s WHERE $whereSql", $params)['cnt'];
$schedules = $db->fetchAll("
    SELECT s.*, c.course_name, u.real_name as teacher_name 
    FROM schedules s 
    LEFT JOIN courses c ON s.course_id = c.id 
    LEFT JOIN users u ON s.teacher_id = u.id 
    WHERE $whereSql 
    ORDER BY s.class_date DESC, s.start_time ASC 
    LIMIT $offset, $perPage
", $params);

// 获取课程和老师列表
$courses = $db->fetchAll("SELECT id, course_name FROM courses WHERE status = 1 ORDER BY course_name");
$teachers = $db->fetchAll("SELECT id, real_name FROM users WHERE role = 'teacher' AND status = 1 ORDER BY real_name");

// 教室历史记录
$classrooms = $db->fetchAll("SELECT DISTINCT classroom FROM schedules WHERE classroom IS NOT NULL AND classroom != '' ORDER BY classroom");

// 老师-课程映射（用于自动填写课程）
$teacherCourse = $db->fetchAll("SELECT teacher_id, id, course_name FROM courses WHERE status = 1 AND teacher_id IS NOT NULL");
$teacherCourseMap = [];
foreach ($teacherCourse as $tc) {
    $teacherCourseMap[$tc['teacher_id']][] = ['id' => $tc['id'], 'course_name' => $tc['course_name']];
}

$pageTitle = '排课管理';
$activeMenu = 'schedules';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="page-title mb-0">排课管理</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
            <i class="bi bi-plus-lg me-1"></i> 新增排课
        </button>
    </div>
    
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
                    <select name="course_id" class="form-select">
                        <option value="">全部课程</option>
                        <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $courseId == $c['id'] ? 'selected' : '' ?>><?= e($c['course_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">老师</label>
                    <select name="teacher_id" class="form-select">
                        <option value="">全部老师</option>
                        <?php foreach ($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $teacherId == $t['id'] ? 'selected' : '' ?>><?= e($t['real_name']) ?></option>
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
                        <?php foreach ($schedules as $schedule): ?>
                        <tr>
                            <td>
                                <?= e($schedule['class_date']) ?><br>
                                <small class="text-muted"><?= e(getWeekDay($schedule['class_date'])) ?></small>
                            </td>
                            <td class="text-primary fw-bold">
                                <?= e($schedule['start_time']) ?> - <?= e($schedule['end_time']) ?>
                            </td>
                            <td><?= e($schedule['course_name']) ?></td>
                            <td><?= e($schedule['teacher_name']) ?></td>
                            <td class="hide-mobile"><?= e($schedule['classroom'] ?? '-') ?></td>
                            <td><?= $schedule['hours'] ?>h</td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editSchedule(<?= $schedule['id'] ?>, <?= $schedule['course_id'] ?>, <?= $schedule['teacher_id'] ?>, '<?= $schedule['class_date'] ?>', '<?= $schedule['start_time'] ?>', '<?= $schedule['end_time'] ?>', '<?= e($schedule['classroom']) ?>', <?= $schedule['hours'] ?>, <?= $schedule['status'] ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('确定要删除该排课吗？')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $schedule['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php 
            $queryStr = http_build_query(['date_from' => $dateFrom, 'date_to' => $dateTo, 'course_id' => $courseId, 'teacher_id' => $teacherId]);
            echo paginate($total, $page, $perPage, '/pages/admin/schedules.php?' . $queryStr); 
            ?>
        </div>
    </div>
</main>

<!-- 新增排课模态框 -->
<div class="modal fade" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" data-validate>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>新增排课</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">授课老师 <span class="text-danger">*</span></label>
                        <select name="teacher_id" id="add-teacher_id" class="form-select" required>
                            <option value="">请选择老师</option>
                            <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= e($t['real_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">课程 <span class="text-danger">*</span></label>
                        <select name="course_id" id="add-course_id" class="form-select" required>
                            <option value="">请选择课程</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['course_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">上课日期 <span class="text-danger">*</span></label>
                        <input type="date" name="class_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">开始时间 <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control" required value="08:00">
                        </div>
                        <div class="col-6">
                            <label class="form-label">结束时间 <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control" required value="09:40">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">教室</label>
                        <input type="text" name="classroom" class="form-control" list="classroom-list" placeholder="如：教学楼A101">
                        <datalist id="classroom-list">
                            <?php foreach ($classrooms as $cr): ?>
                            <option value="<?= e($cr['classroom']) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">课时数</label>
                        <input type="number" step="0.5" name="hours" class="form-control" value="2.0" min="0.5">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">确认排课</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 编辑排课模态框 -->
<div class="modal fade" id="editScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" data-validate>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>编辑排课</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit-s-id">
                    <div class="mb-3">
                        <label class="form-label">授课老师 <span class="text-danger">*</span></label>
                        <select name="teacher_id" id="edit-s-teacher_id" class="form-select" required>
                            <option value="">请选择老师</option>
                            <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= e($t['real_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">课程 <span class="text-danger">*</span></label>
                        <select name="course_id" id="edit-s-course_id" class="form-select" required>
                            <option value="">请选择课程</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['course_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">上课日期 <span class="text-danger">*</span></label>
                        <input type="date" name="class_date" id="edit-s-class_date" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">开始时间 <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" id="edit-s-start_time" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">结束时间 <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" id="edit-s-end_time" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">教室</label>
                        <input type="text" name="classroom" id="edit-s-classroom" class="form-control" list="classroom-list">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">课时数</label>
                        <input type="number" step="0.5" name="hours" id="edit-s-hours" class="form-control" min="0.5">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">状态</label>
                        <select name="status" id="edit-s-status" class="form-select">
                            <option value="1">正常</option>
                            <option value="0">取消</option>
                        </select>
                    </div>
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
    
    const modal = new bootstrap.Modal(document.getElementById('editScheduleModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
