<?php
/**
 * 学管端 - 课程管理
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
        $courseName = trim($_POST['course_name']);
        $description = trim($_POST['description'] ?? '');
        $teacherId = $_POST['teacher_id'] ?? null;
        $totalHours = intval($_POST['total_hours'] ?? 0);
        
        if (empty($courseName)) {
            $message = '请填写课程名称';
            $messageType = 'danger';
        } else {
            $db->query(
                "INSERT INTO courses (course_name, description, teacher_id, total_hours) VALUES (?, ?, ?, ?)",
                [$courseName, $description, $teacherId, $totalHours]
            );
            $message = '课程添加成功';
            $messageType = 'success';
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'];
        $courseName = trim($_POST['course_name']);
        $description = trim($_POST['description'] ?? '');
        $teacherId = $_POST['teacher_id'] ?? null;
        $totalHours = intval($_POST['total_hours'] ?? 0);
        $status = $_POST['status'] ?? 1;
        
        $db->query(
            "UPDATE courses SET course_name = ?, description = ?, teacher_id = ?, total_hours = ?, status = ? WHERE id = ?",
            [$courseName, $description, $teacherId, $totalHours, $status, $id]
        );
        $message = '课程更新成功';
        $messageType = 'success';
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        $db->query("DELETE FROM courses WHERE id = ?", [$id]);
        $message = '课程删除成功';
        $messageType = 'success';
    }
}

// 获取课程列表
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM courses")['cnt'];
$courses = $db->fetchAll("
    SELECT c.*, u.real_name as teacher_name 
    FROM courses c 
    LEFT JOIN users u ON c.teacher_id = u.id 
    ORDER BY c.id ASC 
    LIMIT $offset, $perPage
");

// 获取老师列表
$teachers = $db->fetchAll("SELECT id, real_name FROM users WHERE role = 'teacher' AND status = 1 ORDER BY real_name");

$pageTitle = '课程管理';
$activeMenu = 'courses';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="page-title mb-0">课程管理</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
            <i class="bi bi-plus-lg me-1"></i> 添加课程
        </button>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show alert-auto-dismiss" role="alert">
        <?= e($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
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
                        <?php foreach ($courses as $course): ?>
                        <tr>
                            <td><?= $course['id'] ?></td>
                            <td>
                                <div class="fw-medium"><?= e($course['course_name']) ?></div>
                                <?php if ($course['description']): ?>
                                <small class="text-muted"><?= e(mb_substr($course['description'], 0, 30)) ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td class="hide-mobile"><?= e($course['teacher_name'] ?? '-') ?></td>
                            <td class="hide-mobile"><?= $course['total_hours'] ?>h</td>
                            <td>
                                <?php if ($course['status']): ?>
                                    <span class="badge bg-success">启用</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">禁用</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editCourse(<?= $course['id'] ?>, '<?= e($course['course_name']) ?>', '<?= e($course['description']) ?>', <?= $course['teacher_id'] ?? 'null' ?>, <?= $course['total_hours'] ?>, <?= $course['status'] ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('确定要删除该课程吗？')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $course['id'] ?>">
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
            
            <?= paginate($total, $page, $perPage, '/pages/admin/courses.php') ?>
        </div>
    </div>
</main>

<!-- 添加课程模态框 -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" data-validate>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-book me-2"></i>添加课程</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">课程名称 <span class="text-danger">*</span></label>
                        <input type="text" name="course_name" class="form-control" required placeholder="请输入课程名称">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">授课老师</label>
                        <select name="teacher_id" class="form-select">
                            <option value="">请选择老师</option>
                            <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= e($t['real_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">总课时</label>
                        <input type="number" name="total_hours" class="form-control" value="0" min="0">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">课程描述</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="请输入课程描述"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">添加</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 编辑课程模态框 -->
<div class="modal fade" id="editCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" data-validate>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>编辑课程</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit-course-id">
                    <div class="mb-3">
                        <label class="form-label">课程名称 <span class="text-danger">*</span></label>
                        <input type="text" name="course_name" id="edit-course_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">授课老师</label>
                        <select name="teacher_id" id="edit-teacher_id" class="form-select">
                            <option value="">请选择老师</option>
                            <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= e($t['real_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">总课时</label>
                        <input type="number" name="total_hours" id="edit-total_hours" class="form-control" min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">状态</label>
                        <select name="status" id="edit-status" class="form-select">
                            <option value="1">启用</option>
                            <option value="0">禁用</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">课程描述</label>
                        <textarea name="description" id="edit-description" class="form-control" rows="3"></textarea>
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
function editCourse(id, courseName, description, teacherId, totalHours, status) {
    document.getElementById('edit-course-id').value = id;
    document.getElementById('edit-course_name').value = courseName;
    document.getElementById('edit-description').value = description;
    document.getElementById('edit-teacher_id').value = teacherId || '';
    document.getElementById('edit-total_hours').value = totalHours;
    document.getElementById('edit-status').value = status;
    
    const modal = new bootstrap.Modal(document.getElementById('editCourseModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
