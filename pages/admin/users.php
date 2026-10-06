<?php
/**
 * 学管端 - 用户管理（整合批量导入）
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAdmin();

$db = Database::getInstance();

$message = '';
$messageType = '';
$tab = $_GET['tab'] ?? 'list';
$importResults = [];

// ========== 导出模板 ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'export_template' && isset($_GET['type'])) {
    $type = $_GET['type'];
    $templates = [
        'students' => ['headers' => ['姓名', '手机号', '密码'], 'name' => '学生导入模板', 'sample' => ['张三', '13800138001', '123456']],
        'teachers' => ['headers' => ['姓名', '手机号', '密码'], 'name' => '老师导入模板', 'sample' => ['李四', '13800138002', '123456']],
        'courses'  => ['headers' => ['课程名称', '课程描述', '老师用户名', '总课时'], 'name' => '课程导入模板', 'sample' => ['初中数学', '初一数学课程', 'teacher1', '48']]
    ];
    if (!isset($templates[$type])) die('无效的导入类型');
    $tpl = $templates[$type];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $tpl['name'] . '.csv"');
    echo "\xEF\xBB\xBF";
    $fh = fopen('php://output', 'w');
    fputcsv($fh, $tpl['headers']);
    fputcsv($fh, $tpl['sample']);
    fclose($fh);
    exit;
}

// ========== 处理用户管理表单 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 批量导入
    if ($action === 'import') {
        $type = $_POST['type'] ?? '';
        $data = json_decode($_POST['data'] ?? '[]', true);
        if (empty($type) || empty($data)) {
            $message = '请选择导入类型并上传有效数据';
            $messageType = 'danger';
        } else {
            $successCount = 0;
            $failCount = 0;
            $failReasons = [];

            if ($type === 'students' || $type === 'teachers') {
                $role = $type === 'students' ? 'student' : 'teacher';
                foreach ($data as $index => $row) {
                    $row = array_map('trim', $row);
                    $realName = $row[0] ?? '';
                    $phone = $row[1] ?? '';
                    $password = $row[2] ?? '123456';
                    if (empty($realName)) { $failCount++; $failReasons[] = '第' . ($index + 1) . '行：姓名不能为空'; continue; }
                    $username = !empty($phone) ? $phone : 'user_' . time() . '_' . $index;
                    $existing = $db->fetchOne("SELECT id FROM users WHERE username = ?", [$username]);
                    if ($existing) { $failCount++; $failReasons[] = '第' . ($index + 1) . '行：手机号 ' . e($phone) . ' 已存在'; continue; }
                    if (!empty($phone)) {
                        $existingPhone = $db->fetchOne("SELECT id FROM users WHERE phone = ?", [$phone]);
                        if ($existingPhone) { $failCount++; $failReasons[] = '第' . ($index + 1) . '行：手机号 ' . e($phone) . ' 已存在'; continue; }
                    }
                    $db->query("INSERT INTO users (username, password, real_name, role, phone) VALUES (?, ?, ?, ?, ?)",
                        [$username, password_hash($password, PASSWORD_DEFAULT), $realName, $role, $phone]);
                    $successCount++;
                }
            } elseif ($type === 'courses') {
                foreach ($data as $index => $row) {
                    $row = array_map('trim', $row);
                    $courseName = $row[0] ?? '';
                    $description = $row[1] ?? '';
                    $teacherUsername = $row[2] ?? '';
                    $totalHours = intval($row[3] ?? 0);
                    if (empty($courseName)) { $failCount++; $failReasons[] = '第' . ($index + 1) . '行：课程名称不能为空'; continue; }
                    $teacherId = null;
                    if (!empty($teacherUsername)) {
                        $teacher = $db->fetchOne("SELECT id FROM users WHERE username = ? AND role = 'teacher'", [$teacherUsername]);
                        if ($teacher) { $teacherId = $teacher['id']; }
                        else { $failCount++; $failReasons[] = '第' . ($index + 1) . '行：老师 ' . e($teacherUsername) . ' 不存在'; continue; }
                    }
                    $db->query("INSERT INTO courses (course_name, description, teacher_id, total_hours, status) VALUES (?, ?, ?, ?, 1)",
                        [$courseName, $description, $teacherId, $totalHours]);
                    $successCount++;
                }
            }
            $importResults = ['success' => $successCount, 'fail' => $failCount, 'reasons' => $failReasons];
            $message = "导入完成：成功 {$successCount} 条，失败 {$failCount} 条";
            $messageType = $failCount > 0 ? 'warning' : 'success';
            $tab = 'import';
        }
    }

    // 添加用户
    elseif ($action === 'add') {
        $password = $_POST['password'];
        $realName = trim($_POST['real_name']);
        $role = $_POST['role'];
        $phone = trim($_POST['phone'] ?? '');
        if (empty($password) || empty($realName) || empty($role) || empty($phone)) {
            $message = '请填写必填项';
            $messageType = 'danger';
        } else {
            $username = $phone;
            $exists = $db->fetchOne("SELECT id FROM users WHERE username = ?", [$username]);
            if ($exists) { $message = '手机号已存在'; $messageType = 'danger'; }
            else {
                $db->query("INSERT INTO users (username, password, real_name, role, phone) VALUES (?, ?, ?, ?, ?)",
                    [$username, password_hash($password, PASSWORD_DEFAULT), $realName, $role, $phone]);
                $message = '用户添加成功'; $messageType = 'success';
            }
        }
    }

    // 编辑用户
    elseif ($action === 'edit') {
        $id = $_POST['id'];
        $realName = trim($_POST['real_name']);
        $role = $_POST['role'];
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $status = $_POST['status'] ?? 1;
        $password = $_POST['password'] ?? '';
        if (!empty($password)) {
            $db->query("UPDATE users SET real_name=?, role=?, email=?, phone=?, status=?, password=? WHERE id=?",
                [$realName, $role, $email, $phone, $status, password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            $db->query("UPDATE users SET real_name=?, role=?, email=?, phone=?, status=? WHERE id=?",
                [$realName, $role, $email, $phone, $status, $id]);
        }
        $message = '用户更新成功'; $messageType = 'success';
    }

    // 删除用户
    elseif ($action === 'delete') {
        $id = $_POST['id'];
        if ($id == Auth::id()) { $message = '不能删除当前登录用户'; $messageType = 'danger'; }
        else {
            // 先清理关联记录，避免外键约束
            // 清理该用户作为老师的排课（连带考勤和请假）
            $schedules = $db->fetchAll("SELECT id FROM schedules WHERE teacher_id = ?", [$id]);
            foreach ($schedules as $s) {
                $db->query("DELETE FROM attendance WHERE schedule_id = ?", [$s['id']]);
                $db->query("DELETE FROM leave_requests WHERE schedule_id = ?", [$s['id']]);
            }
            $db->query("DELETE FROM schedules WHERE teacher_id = ?", [$id]);
            // 清理其他关联
            $db->query("DELETE FROM attendance WHERE user_id = ?", [$id]);
            $db->query("DELETE FROM student_courses WHERE student_id = ?", [$id]);
            $db->query("DELETE FROM leave_requests WHERE user_id = ?", [$id]);
            $db->query("UPDATE leave_requests SET approved_by = NULL WHERE approved_by = ?", [$id]);
            $db->query("UPDATE courses SET teacher_id = NULL WHERE teacher_id = ?", [$id]);
            $db->query("DELETE FROM users WHERE id = ?", [$id]);
            $message = '用户删除成功'; $messageType = 'success';
        }
    }
}

// ========== 用户列表数据 ==========
$role = $_GET['role'] ?? '';
$keyword = $_GET['keyword'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = []; $params = [];
if ($role) { $where[] = "role = ?"; $params[] = $role; }
if ($keyword) {
    $where[] = "(real_name LIKE ? OR phone LIKE ?)";
    $params[] = "%$keyword%"; $params[] = "%$keyword%";
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM users $whereSql", $params)['cnt'];
$users = $db->fetchAll("SELECT id, real_name, role, phone, password, created_at FROM users $whereSql ORDER BY created_at DESC LIMIT $offset, $perPage", $params);

$pageTitle = '用户管理';
$activeMenu = 'users';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<main class="container py-4">
    <h2 class="page-title mb-4">
        <i class="bi bi-people me-2"></i>用户管理
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
            <a class="nav-link <?= $tab === 'list' ? 'active' : '' ?>" href="?tab=list">
                <i class="bi bi-list-ul me-1"></i> 用户列表
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'import' ? 'active' : '' ?>" href="?tab=import">
                <i class="bi bi-cloud-upload me-1"></i> 批量导入
            </a>
        </li>
    </ul>

    <!-- ============================================================ -->
    <!-- Tab: 用户列表 -->
    <!-- ============================================================ -->
    <?php if ($tab === 'list'): ?>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <span class="text-muted">共 <?= $total ?> 个用户</span>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-plus-lg me-1"></i> 添加用户
        </button>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="list">
                <div class="col-md-3">
                    <label class="form-label">角色</label>
                    <select name="role" class="form-select">
                        <option value="">全部角色</option>
                        <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>学生</option>
                        <option value="teacher" <?= $role === 'teacher' ? 'selected' : '' ?>>老师</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>学管</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">关键词</label>
                    <input type="text" name="keyword" class="form-control" placeholder="搜索姓名、手机号" value="<?= e($keyword) ?>">
                </div>
                <div class="col-md-3">
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
                            <th>ID</th>
                            <th>姓名</th>
                            <th>角色</th>
                            <th class="hide-mobile">手机号</th>
                            <th>密码</th>
                            <th>注册日期</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= e($user['real_name']) ?></td>
                            <td>
                                <?php $roleBadge = ['student' => 'bg-success', 'teacher' => 'bg-info', 'admin' => 'bg-danger']; ?>
                                <span class="badge <?= $roleBadge[$user['role']] ?? 'bg-secondary' ?>"><?= getRoleName($user['role']) ?></span>
                            </td>
                            <td class="hide-mobile"><?= e($user['phone']) ?></td>
                            <td><code>●●●●●●</code></td>
                            <td><?= $user['created_at'] ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?= $user['id'] ?>, '<?= e($user['real_name']) ?>', '<?= $user['role'] ?>', '<?= e($user['phone']) ?>')">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('确定要删除该用户吗？')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= paginate($total, $page, $perPage, '?tab=list&role=' . urlencode($role) . '&keyword=' . urlencode($keyword)) ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- Tab: 批量导入 -->
    <!-- ============================================================ -->
    <?php elseif ($tab === 'import'): ?>

    <?php if (!empty($importResults)): ?>
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-check2-circle me-1"></i>导入结果</div>
        <div class="card-body">
            <div class="row text-center mb-3">
                <div class="col-6">
                    <div class="text-success" style="font-size:2rem;font-weight:700;"><?= $importResults['success'] ?></div>
                    <div class="text-muted">成功</div>
                </div>
                <div class="col-6">
                    <div class="text-<?= $importResults['fail'] > 0 ? 'danger' : 'muted' ?>" style="font-size:2rem;font-weight:700;"><?= $importResults['fail'] ?></div>
                    <div class="text-muted">失败</div>
                </div>
            </div>
            <?php if (!empty($importResults['reasons'])): ?>
            <div class="alert alert-warning mb-0">
                <strong>失败详情：</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach (array_slice($importResults['reasons'], 0, 10) as $reason): ?>
                    <li><?= $reason ?></li>
                    <?php endforeach; ?>
                    <?php if (count($importResults['reasons']) > 10): ?>
                    <li>... 还有 <?= count($importResults['reasons']) - 10 ?> 条错误</li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
            <div class="mt-3">
                <a href="?tab=import" class="btn btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i>继续导入</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-cloud-upload me-1"></i>上传文件</div>
                <div class="card-body">
                    <form id="uploadForm" method="POST">
                        <input type="hidden" name="action" value="import">
                        <input type="hidden" name="data" id="importData">
                        <input type="hidden" name="type" id="importType">
                        <div class="mb-3">
                            <label class="form-label">导入类型</label>
                            <div class="d-flex gap-2">
                                <select class="form-select" id="typeSelect" required>
                                    <option value="">请选择</option>
                                    <option value="students">学生</option>
                                    <option value="teachers">老师</option>
                                    <option value="courses">课程</option>
                                </select>
                                <button type="button" class="btn btn-outline-secondary text-nowrap" id="exportTemplateBtn" disabled onclick="exportTemplate()">
                                    <i class="bi bi-download"></i> 导出模板
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">选择文件</label>
                            <input type="file" class="form-control" id="fileInput" accept=".xlsx,.xls,.csv">
                            <div class="form-text">支持 .xlsx, .xls, .csv</div>
                        </div>
                        <button type="button" class="btn btn-primary w-100" id="previewBtn" disabled>
                            <i class="bi bi-eye me-1"></i> 预览数据
                        </button>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><i class="bi bi-info-circle me-1"></i>格式说明</div>
                <div class="card-body">
                    <div id="formatGuide"><p class="text-muted mb-0">请先选择导入类型查看格式说明</p></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-table me-1"></i>数据预览</span>
                    <span id="previewCount" class="badge bg-secondary">0 条</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                        <table class="table table-sm mb-0" id="previewTable">
                            <thead class="sticky-top"><tr><td class="text-center text-muted">请上传文件后预览数据</td></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-success w-100" id="confirmImportBtn" disabled>
                        <i class="bi bi-check2-circle me-1"></i> 确认导入
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</main>

<!-- ====== 模态框：添加用户 ====== -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" data-validate>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>添加用户</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">手机号 <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" required placeholder="请输入手机号（同时作为登录账号）">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">密码 <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required placeholder="请输入密码">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">姓名 <span class="text-danger">*</span></label>
                        <input type="text" name="real_name" class="form-control" required placeholder="请输入真实姓名">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">角色 <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="student">学生</option>
                            <option value="teacher">老师</option>
                            <option value="admin">学管</option>
                        </select>
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

<!-- ====== 模态框：编辑用户 ====== -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" data-validate>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>编辑用户</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="mb-3">
                        <label class="form-label">姓名 <span class="text-danger">*</span></label>
                        <input type="text" name="real_name" id="edit-real_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">角色 <span class="text-danger">*</span></label>
                        <select name="role" id="edit-role" class="form-select" required>
                            <option value="student">学生</option>
                            <option value="teacher">老师</option>
                            <option value="admin">学管</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">手机号</label>
                        <input type="text" name="phone" id="edit-phone" class="form-control">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">新密码（不修改请留空）</label>
                        <input type="password" name="password" class="form-control" placeholder="留空则不修改密码">
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

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
function editUser(id, realName, role, phone) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-real_name').value = realName;
    document.getElementById('edit-role').value = role;
    document.getElementById('edit-phone').value = phone;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

// --- 批量导入相关 ---
const typeSelect = document.getElementById('typeSelect');
const fileInput = document.getElementById('fileInput');
const previewBtn = document.getElementById('previewBtn');
const confirmImportBtn = document.getElementById('confirmImportBtn');
const previewTable = document.getElementById('previewTable');
const previewCount = document.getElementById('previewCount');
const importData = document.getElementById('importData');
const importType = document.getElementById('importType');
const formatGuide = document.getElementById('formatGuide');
let importedData = [];

const formatGuides = {
    students: '<p><strong>学生导入格式：</strong></p><ol class="mb-0"><li><strong>姓名</strong><span class="text-danger">*</span></li><li><strong>手机号</strong>（自动作为登录账号）</li><li><strong>初始密码</strong>（默认123456）</li></ol><p class="mt-2 mb-0 text-muted small">第一行可以是表头，会自动跳过。</p>',
    teachers: '<p><strong>老师导入格式：</strong></p><ol class="mb-0"><li><strong>姓名</strong><span class="text-danger">*</span></li><li><strong>手机号</strong>（自动作为登录账号）</li><li><strong>初始密码</strong>（默认123456）</li></ol><p class="mt-2 mb-0 text-muted small">第一行可以是表头，会自动跳过。</p>',
    courses: '<p><strong>课程导入格式：</strong></p><ol class="mb-0"><li><strong>课程名称</strong><span class="text-danger">*</span></li><li><strong>课程描述</strong></li><li><strong>授课老师用户名</strong>（需已存在）</li><li><strong>总课时</strong></li></ol><p class="mt-2 mb-0 text-muted small">第一行可以是表头，会自动跳过。</p>'
};

typeSelect.addEventListener('change', function() {
    formatGuide.innerHTML = this.value ? formatGuides[this.value] : '<p class="text-muted mb-0">请先选择导入类型查看格式说明</p>';
    document.getElementById('exportTemplateBtn').disabled = !this.value;
    checkReady();
});

fileInput.addEventListener('change', function() {
    if (!typeSelect.value) { alert('请先选择导入类型'); this.value = ''; return; }
    checkReady();
});

previewBtn.addEventListener('click', function() {
    const file = fileInput.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, { type: 'array' });
        const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
        const jsonData = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });
        if (jsonData.length > 0) {
            const firstRow = jsonData[0];
            const isHeader = firstRow.every(c => typeof c === 'string' && isNaN(c) && !/^\d{11}$/.test(c));
            importedData = isHeader && jsonData.length > 1
                ? jsonData.slice(1).filter(r => r.some(c => c !== '' && c !== null && c !== undefined))
                : jsonData.filter(r => r.some(c => c !== '' && c !== null && c !== undefined));
        }
        renderPreview();
    };
    reader.readAsArrayBuffer(file);
});

function renderPreview() {
    const type = typeSelect.value;
    const headers = { students: ['姓名','手机号','密码'], teachers: ['姓名','手机号','密码'], courses: ['课程名称','描述','老师用户名','总课时'] };
    const thead = '<tr>' + headers[type].map(h => '<th>' + h + '</th>').join('') + '</tr>';
    let tbody = '';
    importedData.slice(0, 50).forEach(row => {
        tbody += '<tr>' + headers[type].map((_, i) => '<td>' + (row[i] || '') + '</td>').join('') + '</tr>';
    });
    if (importedData.length === 0) tbody = '<tr><td colspan="100" class="text-center text-muted">没有数据</td></tr>';
    previewTable.querySelector('thead').innerHTML = thead;
    previewTable.querySelector('tbody').innerHTML = tbody;
    previewCount.textContent = importedData.length + ' 条';
    confirmImportBtn.disabled = importedData.length === 0;
}

confirmImportBtn.addEventListener('click', function() {
    if (!confirm('确认导入 ' + importedData.length + ' 条数据吗？')) return;
    importData.value = JSON.stringify(importedData);
    importType.value = typeSelect.value;
    document.getElementById('uploadForm').submit();
});

function exportTemplate() {
    const type = typeSelect.value;
    if (!type) return;
    const templates = {
        students: { headers: ['姓名','手机号','密码'], name: '学生导入模板', sample: ['张三','13800138001','123456'] },
        teachers: { headers: ['姓名','手机号','密码'], name: '老师导入模板', sample: ['李四','13800138002','123456'] },
        courses:  { headers: ['课程名称','课程描述','老师用户名','总课时'], name: '课程导入模板', sample: ['初中数学','初一数学课程','teacher1','48'] }
    };
    const tpl = templates[type];
    const ws = XLSX.utils.aoa_to_sheet([tpl.headers, tpl.sample]);
    ws['!cols'] = tpl.headers.map(h => ({ wch: Math.max(h.length * 2, 12) }));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, '模板');
    XLSX.writeFile(wb, tpl.name + '.xlsx');
}

function checkReady() {
    previewBtn.disabled = !typeSelect.value || !fileInput.files.length;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>