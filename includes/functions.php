<?php
/**
 * 公共函数库
 */

// 安全输出 HTML
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// 全局变量：星期
$weekdays = ['日', '一', '二', '三', '四', '五', '六'];

// 全局变量：请假类型
$leaveTypes = [
    'sick' => '病假',
    'personal' => '事假',
    'other' => '其他',
];

// 格式化日期
function formatDate($date) {
    if (!$date) return '';
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date('Y-m-d', $timestamp);
}

// 格式化日期时间
function formatDateTime($datetime) {
    if (!$datetime) return '';
    $timestamp = is_numeric($datetime) ? $timestamp : strtotime($datetime);
    return date('Y-m-d H:i', $timestamp);
}

// 获取角色名称
function getRoleName($role) {
    $roles = [
        'student' => '学生',
        'teacher' => '老师',
        'admin' => '学管',
    ];
    return $roles[$role] ?? $role;
}

// 获取请假状态文本
function getLeaveStatusText($status) {
    $statuses = [
        'pending' => '<span class="badge bg-warning">待审批</span>',
        'approved' => '<span class="badge bg-success">已批准</span>',
        'rejected' => '<span class="badge bg-danger">已拒绝</span>',
    ];
    return $statuses[$status] ?? $status;
}

// 获取签到状态文本
function getAttendanceStatusText($status) {
    $statuses = [
        'pending' => '<span class="badge bg-secondary">未签到</span>',
        'present' => '<span class="badge bg-success">已签到</span>',
        'absent' => '<span class="badge bg-danger">缺勤</span>',
        'leave' => '<span class="badge bg-info">请假</span>',
    ];
    return $statuses[$status] ?? $status;
}

// 重定向
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// 获取当前页面 URL
function current_url() {
    $url = $_SERVER['REQUEST_URI'];
    return $url;
}

// 检查是否为 AJAX 请求
function is_ajax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// JSON 响应
function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// JSON 成功响应
function json_success($data = [], $message = 'success') {
    json_response([
        'code' => 0,
        'message' => $message,
        'data' => $data,
    ]);
}

// JSON 错误响应
function json_error($message = 'error', $code = 1, $data = []) {
    json_response([
        'code' => $code,
        'message' => $message,
        'data' => $data,
    ]);
}

// 获取系统设置
function get_setting($key, $default = '') {
    $db = Database::getInstance();
    $row = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    return $row ? $row['setting_value'] : $default;
}

// 获取星期几
function getWeekDay($date) {
    $days = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'];
    return $days[date('w', strtotime($date))];
}

// 分页函数
function paginate($total, $page, $perPage, $url, $paramName = 'page') {
    $totalPages = ceil($total / $perPage);
    if ($totalPages <= 1) return '';
    
    $q = strpos($url, '?') !== false ? '&' : '?';
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // 上一页
    $prevPage = $page - 1;
    $disabled = $page <= 1 ? ' disabled' : '';
    $html .= "<li class=\"page-item$disabled\"><a class=\"page-link\" href=\"{$url}{$q}{$paramName}=$prevPage\">上一页</a></li>";
    
    // 页码
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    
    if ($start > 1) {
        $html .= "<li class=\"page-item\"><a class=\"page-link\" href=\"{$url}{$q}{$paramName}=1\">1</a></li>";
        if ($start > 2) {
            $html .= "<li class=\"page-item disabled\"><span class=\"page-link\">...</span></li>";
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $page ? ' active' : '';
        $html .= "<li class=\"page-item$active\"><a class=\"page-link\" href=\"{$url}{$q}{$paramName}=$i\">$i</a></li>";
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= "<li class=\"page-item disabled\"><span class=\"page-link\">...</span></li>";
        }
        $html .= "<li class=\"page-item\"><a class=\"page-link\" href=\"{$url}{$q}{$paramName}=$totalPages\">$totalPages</a></li>";
    }
    
    // 下一页
    $nextPage = $page + 1;
    $disabled = $page >= $totalPages ? ' disabled' : '';
    $html .= "<li class=\"page-item$disabled\"><a class=\"page-link\" href=\"{$url}{$q}{$paramName}=$nextPage\">下一页</a></li>";
    
    $html .= '</ul></nav>';
    return $html;
}
