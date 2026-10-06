<?php
/**
 * 数据库配置文件
 * 支持 MySQL 和 SQLite（默认使用 SQLite 以便快速部署，可切换为 MySQL）
 */

// 数据库类型: mysql 或 sqlite
define('DB_TYPE', 'sqlite');

// SQLite 配置
define('SQLITE_PATH', __DIR__ . '/../database/school.db');

// MySQL 配置（如需使用 MySQL，请将 DB_TYPE 改为 mysql 并填写以下信息）
define('MYSQL_HOST', 'localhost');
define('MYSQL_PORT', '3306');
define('MYSQL_DBNAME', 'school_management');
define('MYSQL_USERNAME', 'root');
define('MYSQL_PASSWORD', '');
define('MYSQL_CHARSET', 'utf8mb4');

// 系统配置
define('SITE_NAME', '学管系统');
define('SITE_VERSION', '1.0.0');

// 角色定义
define('ROLE_STUDENT', 'student');
define('ROLE_TEACHER', 'teacher');
define('ROLE_ADMIN', 'admin');

// 请假状态
define('LEAVE_PENDING', 'pending');
define('LEAVE_APPROVED', 'approved');
define('LEAVE_REJECTED', 'rejected');

// 签到状态
define('ATTENDANCE_PENDING', 'pending');
define('ATTENDANCE_PRESENT', 'present');
define('ATTENDANCE_ABSENT', 'absent');
define('ATTENDANCE_LEAVE', 'leave');

// 时区设置
date_default_timezone_set('Asia/Shanghai');
