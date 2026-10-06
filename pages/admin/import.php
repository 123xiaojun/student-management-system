<?php
require_once '../../config/database.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

Auth::requireRole('admin');

$pageTitle = '批量导入';
$activeMenu = 'import';

$message = '';
$messageType = '';
$importResults = [];

// 导出模板
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'export_template' && isset($_GET['type'])) {
    $type = $_GET['type'];
    
    $templates = [
        'students' => [
            'headers' => ['用户名', '姓名', '手机号', '邮箱', '密码'],
            'name' => '学生导入模板',
            'sample' => ['zhangsan', '张三', '13800138001', 'zhangsan@example.com', '123456']
        ],
        'teachers' => [
            'headers' => ['用户名', '姓名', '手机号', '邮箱', '密码'],
            'name' => '老师导入模板',
            'sample' => ['lisi', '李四', '13800138002', 'lisi@example.com', '123456']
        ],
        'courses' => [
            'headers' => ['课程名称', '课程描述', '老师用户名', '总课时'],
            'name' => '课程导入模板',
            'sample' => ['初中数学', '初一数学课程', 'teacher1', '48']
        ]
    ];
    
    if (!isset($templates[$type])) {
        die('无效的导入类型');
    }
    
    $tpl = $templates[$type];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $tpl['name'] . '.csv"');
    header('Content-Encoding: UTF-8');
    echo "\xEF\xBB\xBF"; // BOM
    
    $fh = fopen('php://output', 'w');
    fputcsv($fh, $tpl['headers']);
    fputcsv($fh, $tpl['sample']);
    fclose($fh);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import') {
    $type = $_POST['type'] ?? '';
    $data = json_decode($_POST['data'] ?? '[]', true);
    
    if (empty($type) || empty($data)) {
        $message = '请选择导入类型并上传有效数据';
        $messageType = 'danger';
    } else {
        $db = Database::getInstance();
        $successCount = 0;
        $failCount = 0;
        $failReasons = [];
        
        if ($type === 'students' || $type === 'teachers') {
            $role = $type === 'students' ? 'student' : 'teacher';
            foreach ($data as $index => $row) {
                $row = array_map('trim', $row);
                $username = $row[0] ?? '';
                $realName = $row[1] ?? '';
                $phone = $row[2] ?? '';
                $email = $row[3] ?? '';
                $password = $row[4] ?? '123456';
                
                if (empty($username) || empty($realName)) {
                    $failCount++;
                    $failReasons[] = '第' . ($index + 1) . '行：用户名或姓名不能为空';
                    continue;
                }
                
                // 检查用户名是否存在
                $existing = $db->fetchOne("SELECT id FROM users WHERE username = ?", [$username]);
                if ($existing) {
                    $failCount++;
                    $failReasons[] = '第' . ($index + 1) . '行：用户名 ' . e($username) . ' 已存在';
                    continue;
                }
                
                // 检查手机号
                if (!empty($phone)) {
                    $existingPhone = $db->fetchOne("SELECT id FROM users WHERE phone = ?", [$phone]);
                    if ($existingPhone) {
                        $failCount++;
                        $failReasons[] = '第' . ($index + 1) . '行：手机号 ' . e($phone) . ' 已存在';
                        continue;
                    }
                }
                
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $db->query(
                    "INSERT INTO users (username, password, real_name, role, phone, email, status) VALUES (?, ?, ?, ?, ?, ?, 1)",
                    [$username, $hashedPassword, $realName, $role, $phone, $email]
                );
                $successCount++;
            }
        } elseif ($type === 'courses') {
            foreach ($data as $index => $row) {
                $row = array_map('trim', $row);
                $courseName = $row[0] ?? '';
                $description = $row[1] ?? '';
                $teacherUsername = $row[2] ?? '';
                $totalHours = intval($row[3] ?? 0);
                
                if (empty($courseName)) {
                    $failCount++;
                    $failReasons[] = '第' . ($index + 1) . '行：课程名称不能为空';
                    continue;
                }
                
                $teacherId = null;
                if (!empty($teacherUsername)) {
                    $teacher = $db->fetchOne("SELECT id FROM users WHERE username = ? AND role = 'teacher'", [$teacherUsername]);
                    if ($teacher) {
                        $teacherId = $teacher['id'];
                    } else {
                        $failCount++;
                        $failReasons[] = '第' . ($index + 1) . '行：老师 ' . e($teacherUsername) . ' 不存在';
                        continue;
                    }
                }
                
                $db->query(
                    "INSERT INTO courses (course_name, description, teacher_id, total_hours, status) VALUES (?, ?, ?, ?, 1)",
                    [$courseName, $description, $teacherId, $totalHours]
                );
                $successCount++;
            }
        } else {
            $message = '未知的导入类型';
            $messageType = 'danger';
        }
        
        $importResults = [
            'success' => $successCount,
            'fail' => $failCount,
            'reasons' => $failReasons,
            'type' => $type
        ];
        
        $message = "导入完成：成功 {$successCount} 条，失败 {$failCount} 条";
        $messageType = $failCount > 0 ? 'warning' : 'success';
    }
}

require '../../includes/header.php';
require '../../includes/navbar.php';
?>

<main class="container py-4">
    <h1 class="page-title"><i class="bi bi-file-earmark-spreadsheet"></i> 批量导入</h1>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo e($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($importResults)): ?>
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-check2-circle"></i> 导入结果
        </div>
        <div class="card-body">
            <div class="row text-center mb-3">
                <div class="col-6">
                    <div class="text-success" style="font-size: 2rem; font-weight: 700;"><?php echo $importResults['success']; ?></div>
                    <div class="text-muted">成功</div>
                </div>
                <div class="col-6">
                    <div class="text-<?php echo $importResults['fail'] > 0 ? 'danger' : 'muted'; ?>" style="font-size: 2rem; font-weight: 700;"><?php echo $importResults['fail']; ?></div>
                    <div class="text-muted">失败</div>
                </div>
            </div>
            <?php if (!empty($importResults['reasons'])): ?>
            <div class="alert alert-warning mb-0">
                <strong>失败详情：</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach (array_slice($importResults['reasons'], 0, 10) as $reason): ?>
                    <li><?php echo e($reason); ?></li>
                    <?php endforeach; ?>
                    <?php if (count($importResults['reasons']) > 10): ?>
                    <li>... 还有 <?php echo count($importResults['reasons']) - 10; ?> 条错误</li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-cloud-upload"></i> 上传文件
                </div>
                <div class="card-body">
                    <form id="uploadForm" method="POST">
                        <input type="hidden" name="action" value="import">
                        <input type="hidden" name="data" id="importData">
                        <input type="hidden" name="type" id="importType">
                        
                        <div class="mb-3">
                            <label class="form-label">导入类型</label>
                            <div class="d-flex gap-2">
                                <select class="form-select" id="typeSelect" required>
                                    <option value="">请选择导入类型</option>
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
                            <div class="form-text">支持 .xlsx, .xls, .csv 格式</div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary" id="previewBtn" disabled>
                                <i class="bi bi-eye"></i> 预览数据
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <i class="bi bi-info-circle"></i> 格式说明
                </div>
                <div class="card-body">
                    <div id="formatGuide">
                        <p class="text-muted mb-2">请先选择导入类型查看格式说明</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-table"></i> 数据预览</span>
                    <span id="previewCount" class="badge bg-secondary">0 条</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm mb-0" id="previewTable">
                            <thead class="sticky-top">
                                <tr><td class="text-center text-muted">请上传文件后预览数据</td></tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-success w-100" id="confirmImportBtn" disabled>
                        <i class="bi bi-check2-circle"></i> 确认导入
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
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
    students: `
        <p><strong>学生导入格式（按列顺序）：</strong></p>
        <ol class="mb-0">
            <li><strong>用户名</strong><span class="text-danger">*</span> - 登录账号</li>
            <li><strong>姓名</strong><span class="text-danger">*</span> - 真实姓名</li>
            <li><strong>手机号</strong> - 11位手机号</li>
            <li><strong>邮箱</strong> - 电子邮箱</li>
            <li><strong>初始密码</strong> - 默认 123456</li>
        </ol>
        <p class="mt-2 mb-0 text-muted small">第一行可以是表头，会自动跳过。</p>
    `,
    teachers: `
        <p><strong>老师导入格式（按列顺序）：</strong></p>
        <ol class="mb-0">
            <li><strong>用户名</strong><span class="text-danger">*</span> - 登录账号</li>
            <li><strong>姓名</strong><span class="text-danger">*</span> - 真实姓名</li>
            <li><strong>手机号</strong> - 11位手机号</li>
            <li><strong>邮箱</strong> - 电子邮箱</li>
            <li><strong>初始密码</strong> - 默认 123456</li>
        </ol>
        <p class="mt-2 mb-0 text-muted small">第一行可以是表头，会自动跳过。</p>
    `,
    courses: `
        <p><strong>课程导入格式（按列顺序）：</strong></p>
        <ol class="mb-0">
            <li><strong>课程名称</strong><span class="text-danger">*</span> - 课程名</li>
            <li><strong>课程描述</strong> - 简介</li>
            <li><strong>授课老师用户名</strong> - 需已存在</li>
            <li><strong>总课时</strong> - 数字</li>
        </ol>
        <p class="mt-2 mb-0 text-muted small">第一行可以是表头，会自动跳过。</p>
    `
};

typeSelect.addEventListener('change', function() {
    const type = this.value;
    formatGuide.innerHTML = type ? formatGuides[type] : '<p class="text-muted mb-2">请先选择导入类型查看格式说明</p>';
    document.getElementById('exportTemplateBtn').disabled = !type;
    checkReady();
});

fileInput.addEventListener('change', function() {
    if (!typeSelect.value) {
        alert('请先选择导入类型');
        this.value = '';
        return;
    }
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
        
        // 跳过可能的表头（第一行全是文字）
        if (jsonData.length > 0) {
            const firstRow = jsonData[0];
            const isHeader = firstRow.every(cell => 
                typeof cell === 'string' && 
                isNaN(cell) && 
                !/^\d{11}$/.test(cell)
            );
            if (isHeader && jsonData.length > 1) {
                importedData = jsonData.slice(1).filter(row => row.some(cell => cell !== '' && cell !== null && cell !== undefined));
            } else {
                importedData = jsonData.filter(row => row.some(cell => cell !== '' && cell !== null && cell !== undefined));
            }
        }
        
        renderPreview();
    };
    reader.readAsArrayBuffer(file);
});

function renderPreview() {
    const type = typeSelect.value;
    const headers = {
        students: ['用户名', '姓名', '手机号', '邮箱', '密码'],
        teachers: ['用户名', '姓名', '手机号', '邮箱', '密码'],
        courses: ['课程名称', '描述', '老师用户名', '总课时']
    };
    
    const thead = `<tr>${headers[type].map(h => `<th>${h}</th>`).join('')}</tr>`;
    let tbody = '';
    
    importedData.slice(0, 50).forEach(row => {
        const cells = headers[type].map((_, i) => `<td>${row[i] || ''}</td>`).join('');
        tbody += `<tr>${cells}</tr>`;
    });
    
    if (importedData.length === 0) {
        tbody = '<tr><td colspan="100" class="text-center text-muted">没有数据</td></tr>';
    }
    
    previewTable.querySelector('thead').innerHTML = thead;
    previewTable.querySelector('tbody').innerHTML = tbody;
    previewCount.textContent = importedData.length + ' 条';
    
    confirmImportBtn.disabled = importedData.length === 0;
}

confirmImportBtn.addEventListener('click', function() {
    if (!confirm(`确认导入 ${importedData.length} 条数据吗？`)) return;
    
    importData.value = JSON.stringify(importedData);
    importType.value = typeSelect.value;
    document.getElementById('uploadForm').submit();
});

// 导出模板
function exportTemplate() {
    const type = typeSelect.value;
    if (!type) return;
    
    const templates = {
        students: {
            headers: ['用户名', '姓名', '手机号', '邮箱', '密码'],
            name: '学生导入模板',
            sample: ['zhangsan', '张三', '13800138001', 'zhangsan@example.com', '123456']
        },
        teachers: {
            headers: ['用户名', '姓名', '手机号', '邮箱', '密码'],
            name: '老师导入模板',
            sample: ['lisi', '李四', '13800138002', 'lisi@example.com', '123456']
        },
        courses: {
            headers: ['课程名称', '课程描述', '老师用户名', '总课时'],
            name: '课程导入模板',
            sample: ['初中数学', '初一数学课程', 'teacher1', '48']
        }
    };
    
    const tpl = templates[type];
    const ws = XLSX.utils.aoa_to_sheet([tpl.headers, tpl.sample]);
    
    // 设置列宽
    ws['!cols'] = tpl.headers.map(h => ({ wch: Math.max(h.length * 2, 12) }));
    
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, '模板');
    XLSX.writeFile(wb, tpl.name + '.xlsx');
}

function checkReady() {
    previewBtn.disabled = !typeSelect.value || !fileInput.files.length;
}
</script>

<?php require '../../includes/footer.php'; ?>
