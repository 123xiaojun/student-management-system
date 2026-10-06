<?php
/**
 * 用户认证类
 */

class Auth {
    // 启动 session
    public static function init() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    // 检查是否已登录
    public static function check() {
        self::init();
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    // 获取当前登录用户
    public static function user() {
        if (!self::check()) return null;
        
        $db = Database::getInstance();
        return $db->fetchOne("SELECT id, username, real_name, role, email, phone, status FROM users WHERE id = ?", [$_SESSION['user_id']]);
    }

    // 获取用户ID
    public static function id() {
        self::init();
        return $_SESSION['user_id'] ?? null;
    }

    // 获取角色
    public static function role() {
        self::init();
        return $_SESSION['user_role'] ?? null;
    }

    // 登录（支持用户名或手机号）
    public static function login($account, $password) {
        $db = Database::getInstance();
        // 支持用户名或手机号登录
        $user = $db->fetchOne("SELECT * FROM users WHERE (username = ? OR phone = ?) AND status = 1", [$account, $account]);
        
        if (!$user) {
            return ['success' => false, 'message' => '手机号或密码错误'];
        }
        
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => '手机号或密码错误'];
        }
        
        self::init();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['real_name'] = $user['real_name'];
        $_SESSION['user_role'] = $user['role'];
        
        return ['success' => true, 'message' => '登录成功', 'user' => $user];
    }

    // 登出
    public static function logout() {
        self::init();
        $_SESSION = [];
        session_destroy();
    }

    // 检查角色
    public static function requireRole($roles) {
        if (!self::check()) {
            redirect('/index.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        }
        
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        if (!in_array(self::role(), $roles)) {
            die('您没有权限访问此页面');
        }
    }

    // 必须是管理员
    public static function requireAdmin() {
        self::requireRole('admin');
    }

    // 必须是老师
    public static function requireTeacher() {
        self::requireRole('teacher');
    }

    // 必须是学生
    public static function requireStudent() {
        self::requireRole('student');
    }

    // 必须是老师或管理员
    public static function requireTeacherOrAdmin() {
        self::requireRole(['teacher', 'admin']);
    }

    // 获取角色对应的首页
    public static function dashboardUrl() {
        $role = self::role();
        switch ($role) {
            case 'admin':
                return '/pages/admin/index.php';
            case 'teacher':
                return '/pages/teacher/index.php';
            case 'student':
                return '/pages/student/index.php';
            default:
                return '/index.php';
        }
    }
}
