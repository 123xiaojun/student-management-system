# 学管系统 · 教育培训机构管理系统（student-management-system）

一套面向**教育培训机构 / 学校**的轻量级内部管理系统，覆盖 **排课、考勤、请假审批、课时统计** 四条主线。用原生 **PHP + PDO** 写成，**不依赖任何 PHP 框架和 Composer**，默认使用 **SQLite**，上传即用。

> 首次访问自动建表并写入演示数据，**零配置即可跑起来**。需要上生产时，改一行常量就能切到 **MySQL**。

![登录页](screenshot-login.png)

## 功能总览

系统按角色划分三套工作台，同一套账号体系，菜单和权限自动隔离。

### 学管（管理员 `admin`）

| 模块 | 说明 |
| --- | --- |
| 信息栏 | 全局概览：今日课程、待审批请假、用户与课程规模等关键指标 |
| 教学管理 | 课程 / 排课 / 考勤 / 上课记录的统一工作台，分标签页操作 |
| 请假审批 | 查看并批准 / 拒绝学生的请假申请，状态实时回写考勤 |
| 用户管理 | 学生、老师、学管账号的增删改查、状态启停 |
| 课时统计 | 按课程 / 老师 / 时间区间汇总课时与出勤情况 |
| 批量导入 | 用 CSV 模板批量导入学生、老师、课程，带模板下载与逐行结果反馈 |
| 系统设置 | 机构名称等全局参数（`settings` 表键值存储） |

### 老师（`teacher`）

- **首页** —— 我的课程与今日安排一览
- **今日课程 / 明日课程** —— 按日期查看自己的课表（班级、时间、教室）
- **考勤记录** —— 为学生签到，记录签到 / 签退时间与课时
- **请假申请** —— 提交与查看自己的请假
- **个人中心** —— 修改个人资料与密码

### 学生（`student`）

- **首页** —— 我的课程、待上课程与出勤概览
- **今日课程 / 明日课程** —— 自己已选课程的课表
- **我的考勤** —— 历史签到记录与出勤状态（已签到 / 缺勤 / 请假）
- **请假申请** —— 提交病假 / 事假 / 其他，跟踪审批进度
- **个人中心** —— 修改个人资料与密码

## 技术栈

| 层面 | 选型 |
| --- | --- |
| 后端 | 原生 PHP（无框架、无 Composer） |
| 数据库 | **SQLite（默认）** / MySQL，统一走 PDO 预处理 |
| 前端 | Bootstrap 5.3 + Bootstrap Icons + jQuery 3.7（CDN 引入） |
| 样式 | 一份自定义 `assets/css/style.css` |
| 数据交互 | 表单 POST + JSON 接口（`json_success` / `json_error` 统一响应结构） |

## 快速开始

> **部署位置很重要**：系统内部使用了站点根相对路径（`/pages/...`、`/assets/...`），
> 因此**必须部署到站点的根目录**，不要放在子目录（如 `/sms/`）里，否则会跳转失败、样式丢失。
> 本地测试请用 `http://localhost:8000` 这种根路径。

### 环境要求

- PHP **7.0+**（需启用 `pdo_sqlite`；若用 MySQL 则需 `pdo_mysql`）
- 任意 Web 服务器：Apache / Nginx / PHP 内置服务器
- 无需 Composer、无需 Node.js

### 方式一：PHP 内置服务器（最快）

```bash
cd www.stumgsys.com
php -S localhost:8000
```

浏览器打开 <http://localhost:8000> 即可。

> 用内置服务器时，请从 `www.stumgsys.com` 目录启动，因为代码里的跳转基于站点根路径 `/pages/...`。

### 方式二：Apache / 宝塔面板

把 `www.stumgsys.com` 目录里的内容放到站点根目录，确保：

1. 站点根目录指向该目录（而不是它的上一级）；
2. 开启 `mod_rewrite`（仓库已带 `.htaccess`）；
3. `database/` 目录对 PHP **可写**（SQLite 需要建库写文件）。

### 安装过程是自动的

`index.php` 会 `require` 一次 `install.php`，它检查 `users` 表是否存在：

- **不存在** → 建 6 张表，写入 3 个演示账号、3 门课程、选课关系、一周排课和默认设置；
- **已存在** → 直接跳过，不会覆盖你的数据。

所以**不需要手动执行任何 SQL 脚本**。

### 演示账号

| 角色 | 手机号 | 密码 |
| --- | --- | --- |
| 学管（管理员） | `13800138000` | `admin123` |
| 老师 | `13800138001` | `teacher123` |
| 学生 | `13800138003` | `student123` |

登录页支持**用户名或手机号**登录。完整演示数据：学管 `admin`、老师 `teacher1` / `teacher2`、学生 `student1` ~ `student3`（老师与学生密码分别统一为 `teacher123` / `student123`）。

> ⚠️ **上线前务必删除或修改演示账号**，它们是公开的弱口令。

## 切换到 MySQL

编辑 `config/database.php`：

```php
define('DB_TYPE', 'mysql');

define('MYSQL_HOST', '127.0.0.1');
define('MYSQL_PORT', '3306');
define('MYSQL_DBNAME', 'school_management');
define('MYSQL_USERNAME', 'your_user');
define('MYSQL_PASSWORD', 'your_password');
define('MYSQL_CHARSET', 'utf8mb4');
```

先在 MySQL 里建好空库 `school_management`，再访问站点，`install.php` 会按同样的表结构完成初始化。

## 数据库设计

6 张表，全部带外键约束（SQLite 下显式 `PRAGMA foreign_keys = ON`）：

| 表 | 作用 | 关键字段 |
| --- | --- | --- |
| `users` | 用户主表（三种角色共用） | `username`、`password`(bcrypt)、`real_name`、`role`、`phone`、`status` |
| `courses` | 课程 | `course_name`、`teacher_id`、`total_hours` |
| `schedules` | 排课（某课程某天某时段） | `course_id`、`teacher_id`、`class_date`、`start_time`、`end_time`、`classroom`、`hours` |
| `student_courses` | 学生选课关系 | `student_id`、`course_id`，`UNIQUE(student_id, course_id)` |
| `attendance` | 签到记录 | `schedule_id`、`user_id`、`user_role`、`status`、`check_in_time`、`check_out_time`、`hours` |
| `leave_requests` | 请假申请 | `user_id`、`leave_date`、`leave_type`、`reason`、`status`、审批人信息 |
| `settings` | 全局设置（键值对） | `setting_key`、`setting_value` |

状态常量在 `config/database.php` 中集中定义：请假 `pending` / `approved` / `rejected`；考勤 `pending` / `present` / `absent` / `leave`。

## 项目结构

```
.
├── index.php                  # 登录页（首次访问触发自动安装）
├── install.php                # 建表 + 演示数据（幂等，已被 index.php 引入）
├── logout.php                 # 退出登录
├── .htaccess                  # Apache 重写规则
├── config/
│   ├── database.php           # 数据库类型、连接参数、站点常量、状态常量
│   └── db.php                 # Database 单例：PDO 连接与 query/fetch/insert/execute
├── includes/
│   ├── auth.php               # Auth 类：登录、会话、角色校验、首页路由
│   ├── functions.php          # 公共函数：转义、格式化、状态徽章、分页、JSON 响应
│   ├── header.php             # 页面头部
│   ├── navbar.php             # 按角色渲染的导航栏
│   └── footer.php             # 页面尾部
├── pages/
│   ├── profile.php            # 个人中心（全角色）
│   ├── admin/                 # 学管：信息栏 / 教学管理 / 请假审批 / 用户 / 课时 / 导入 / 设置
│   ├── teacher/               # 老师：首页 / 今日 / 明日 / 考勤 / 请假
│   └── student/               # 学生：首页 / 今日 / 明日 / 我的考勤 / 请假
├── assets/
│   ├── css/style.css
│   └── js/app.js
└── database/
    └── school.db              # SQLite 数据库（演示数据已就绪）
```

## 安全设计

- **密码哈希** —— 一律 `password_hash()` / `password_verify()`（bcrypt），明文不落库；
- **SQL 注入防护** —— 全部数据访问走 PDO 预处理占位符，`ATTR_EMULATE_PREPARES = false`；
- **XSS 防护** —— 输出统一过 `e()`（`htmlspecialchars`，`ENT_QUOTES` + UTF-8）；
- **越权防护** —— 每个页面开头调用 `Auth::requireAdmin()` / `requireTeacher()` / `requireStudent()`，未登录跳登录页并带 `redirect` 回跳，角色不符直接拒绝；
- **会话管理** —— 登录写入 `user_id` / `user_role`，退出时清空并 `session_destroy()`。

> 已知不完善之处：无 CSRF token、无登录失败次数限制。用于正式生产环境前请按需加固。

## 部署到生产

1. 把 `config/database.php` 切到 MySQL 并填写真实凭据；
2. 删除演示账号，创建你自己的学管账号；
3. 确保 `database/` 不在 Web 可直接下载的位置，或改用 MySQL 后删除该目录；
4. 全站启用 HTTPS；
5. 关闭 `display_errors`，把错误写入日志。

## 开源协议

[MIT](LICENSE)
