<?php
/**
 * 数据库初始化脚本
 * 自动创建数据表并插入初始数据
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/db.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// 检查是否已初始化
try {
    $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    if ($result->fetch()) {
        // 已初始化，跳过
        return;
    }
} catch (Exception $e) {
    // 表不存在，继续初始化
}

echo "正在初始化数据库...\n";

// 开始事务
$pdo->beginTransaction();

try {
    // 1. 用户表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            real_name VARCHAR(50) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'student',
            email VARCHAR(100),
            phone VARCHAR(20),
            status TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // 2. 课程表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS courses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_name VARCHAR(100) NOT NULL,
            description TEXT,
            teacher_id INTEGER,
            total_hours INTEGER DEFAULT 0,
            status TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (teacher_id) REFERENCES users(id)
        )
    ");

    // 3. 课程排期表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schedules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER NOT NULL,
            teacher_id INTEGER NOT NULL,
            class_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            classroom VARCHAR(50),
            hours DECIMAL(4,2) DEFAULT 1.0,
            status TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES courses(id),
            FOREIGN KEY (teacher_id) REFERENCES users(id)
        )
    ");

    // 4. 学生选课表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_courses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            course_id INTEGER NOT NULL,
            enrolled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            status TINYINT DEFAULT 1,
            UNIQUE(student_id, course_id),
            FOREIGN KEY (student_id) REFERENCES users(id),
            FOREIGN KEY (course_id) REFERENCES courses(id)
        )
    ");

    // 5. 签到表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            schedule_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            user_role VARCHAR(20) NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            check_in_time DATETIME,
            check_out_time DATETIME,
            hours DECIMAL(4,2) DEFAULT 0,
            remark VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (schedule_id) REFERENCES schedules(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");

    // 6. 请假申请表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS leave_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            user_role VARCHAR(20) NOT NULL,
            schedule_id INTEGER,
            leave_date DATE NOT NULL,
            leave_type VARCHAR(50),
            reason TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            approved_by INTEGER,
            approved_at DATETIME,
            reject_reason VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (approved_by) REFERENCES users(id),
            FOREIGN KEY (schedule_id) REFERENCES schedules(id)
        )
    ");

    // 7. 系统设置表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            setting_key VARCHAR(50) UNIQUE NOT NULL,
            setting_value TEXT,
            description VARCHAR(255),
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // 插入默认管理员账号
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("
        INSERT INTO users (username, password, real_name, role, email, phone) 
        VALUES ('admin', '$adminPassword', '系统管理员', 'admin', 'admin@school.com', '13800138000')
    ");

    // 插入默认老师账号
    $teacherPassword = password_hash('teacher123', PASSWORD_DEFAULT);
    $pdo->exec("
        INSERT INTO users (username, password, real_name, role, email, phone) 
        VALUES ('teacher1', '$teacherPassword', '张老师', 'teacher', 'teacher1@school.com', '13800138001')
    ");
    $pdo->exec("
        INSERT INTO users (username, password, real_name, role, email, phone) 
        VALUES ('teacher2', '$teacherPassword', '李老师', 'teacher', 'teacher2@school.com', '13800138002')
    ");

    // 插入默认学生账号
    $studentPassword = password_hash('student123', PASSWORD_DEFAULT);
    $pdo->exec("
        INSERT INTO users (username, password, real_name, role, email, phone) 
        VALUES ('student1', '$studentPassword', '王小明', 'student', 'student1@school.com', '13800138003')
    ");
    $pdo->exec("
        INSERT INTO users (username, password, real_name, role, email, phone) 
        VALUES ('student2', '$studentPassword', '李小红', 'student', 'student2@school.com', '13800138004')
    ");
    $pdo->exec("
        INSERT INTO users (username, password, real_name, role, email, phone) 
        VALUES ('student3', '$studentPassword', '张三', 'student', 'student3@school.com', '13800138005')
    ");

    // 插入示例课程
    $pdo->exec("
        INSERT INTO courses (course_name, description, teacher_id, total_hours) 
        VALUES ('高等数学', '高等数学基础课程', 2, 48)
    ");
    $pdo->exec("
        INSERT INTO courses (course_name, description, teacher_id, total_hours) 
        VALUES ('英语', '大学英语课程', 3, 32)
    ");
    $pdo->exec("
        INSERT INTO courses (course_name, description, teacher_id, total_hours) 
        VALUES ('计算机基础', '计算机科学导论', 2, 36)
    ");

    // 插入学生选课关系
    $pdo->exec("INSERT INTO student_courses (student_id, course_id) VALUES (4, 1)");
    $pdo->exec("INSERT INTO student_courses (student_id, course_id) VALUES (4, 2)");
    $pdo->exec("INSERT INTO student_courses (student_id, course_id) VALUES (5, 1)");
    $pdo->exec("INSERT INTO student_courses (student_id, course_id) VALUES (5, 3)");
    $pdo->exec("INSERT INTO student_courses (student_id, course_id) VALUES (6, 2)");
    $pdo->exec("INSERT INTO student_courses (student_id, course_id) VALUES (6, 3)");

    // 插入示例课程排期（今天和明天）
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    // 今天的课
    $pdo->exec("
        INSERT INTO schedules (course_id, teacher_id, class_date, start_time, end_time, classroom, hours) 
        VALUES (1, 2, '$today', '08:00', '09:40', '教学楼A101', 2.0)
    ");
    $pdo->exec("
        INSERT INTO schedules (course_id, teacher_id, class_date, start_time, end_time, classroom, hours) 
        VALUES (2, 3, '$today', '10:00', '11:40', '教学楼B202', 2.0)
    ");
    $pdo->exec("
        INSERT INTO schedules (course_id, teacher_id, class_date, start_time, end_time, classroom, hours) 
        VALUES (3, 2, '$today', '14:00', '15:40', '教学楼C303', 2.0)
    ");

    // 明天的课
    $pdo->exec("
        INSERT INTO schedules (course_id, teacher_id, class_date, start_time, end_time, classroom, hours) 
        VALUES (1, 2, '$tomorrow', '08:00', '09:40', '教学楼A101', 2.0)
    ");
    $pdo->exec("
        INSERT INTO schedules (course_id, teacher_id, class_date, start_time, end_time, classroom, hours) 
        VALUES (2, 3, '$tomorrow', '14:00', '15:40', '教学楼B202', 2.0)
    ");

    // 昨天的课（用于演示历史数据）
    $pdo->exec("
        INSERT INTO schedules (course_id, teacher_id, class_date, start_time, end_time, classroom, hours) 
        VALUES (1, 2, '$yesterday', '08:00', '09:40', '教学楼A101', 2.0)
    ");
    $pdo->exec("
        INSERT INTO schedules (course_id, teacher_id, class_date, start_time, end_time, classroom, hours) 
        VALUES (3, 2, '$yesterday', '14:00', '15:40', '教学楼C303', 2.0)
    ");

    // 系统设置
    $pdo->exec("
        INSERT INTO settings (setting_key, setting_value, description) 
        VALUES ('site_name', '学管系统', '系统名称')
    ");
    $pdo->exec("
        INSERT INTO settings (setting_key, setting_value, description) 
        VALUES ('check_in_before', '30', '上课前多少分钟可以签到（分钟）')
    ");
    $pdo->exec("
        INSERT INTO settings (setting_key, setting_value, description) 
        VALUES ('check_out_after', '0', '下课后多久可以签退（分钟）')
    ");

    $pdo->commit();
    echo "数据库初始化完成！\n";
    echo "默认账号：\n";
    echo "管理员: admin / admin123\n";
    echo "老师: teacher1 / teacher123\n";
    echo "学生: student1 / student123\n";

} catch (Exception $e) {
    $pdo->rollBack();
    die("数据库初始化失败: " . $e->getMessage());
}
