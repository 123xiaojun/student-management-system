<?php
/**
 * 主导航栏 - 根据角色显示不同菜单
 */
$currentUser = Auth::user();
$currentRole = Auth::role();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?= Auth::dashboardUrl() ?>">
            <i class="bi bi-mortarboard-fill me-2"></i>
            <span class="d-none d-sm-inline"><?= SITE_NAME ?></span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="mainNavbar">
            <?php if ($currentUser): ?>
                <!-- 左侧菜单 -->
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php if ($currentRole === 'admin'): ?>
                        <!-- 管理员菜单 -->
                        <li class="nav-item">
                            <a class="nav-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>" href="/pages/admin/index.php">
                                <i class="bi bi-speedometer2 me-1"></i> 信息栏
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeMenu === 'teaching' ? 'active' : '' ?>" href="/pages/admin/teaching.php?tab=today">
                                <i class="bi bi-mortarboard me-1"></i> 教学管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeMenu === 'leaves' ? 'active' : '' ?>" href="/pages/admin/leaves.php">
                                <i class="bi bi-envelope me-1"></i> 请假审批
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeMenu === 'users' ? 'active' : '' ?>" href="/pages/admin/users.php">
                                <i class="bi bi-people me-1"></i> 用户管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeMenu === 'settings' ? 'active' : '' ?>" href="/pages/admin/settings.php">
                                <i class="bi bi-gear me-1"></i> 系统设置
                            </a>
                        </li>
                    <?php elseif ($currentRole === 'teacher'): ?>
                        <!-- 老师菜单 -->
                        <li class="nav-item">
                            <a class="nav-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>" href="/pages/teacher/index.php">
                                <i class="bi bi-house me-1"></i> 首页
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeMenu === 'today' ? 'active' : '' ?>" href="/pages/teacher/today.php">
                                <i class="bi bi-calendar-day me-1"></i> 今日课程
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeMenu === 'tomorrow' ? 'active' : '' ?>" href="/pages/teacher/tomorrow.php">
                                <i class="bi bi-calendar2-plus me-1"></i> 明日课程
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeMenu === 'attendance' ? 'active' : '' ?>" href="/pages/teacher/attendance.php">
                                <i class="bi bi-check2-square me-1"></i> 考勤记录
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeMenu === 'leaves' ? 'active' : '' ?>" href="/pages/teacher/leaves.php">
                                <i class="bi bi-envelope me-1"></i> 请假申请
                            </a>
                        </li>
                    <?php elseif ($currentRole === 'student'): ?>
                        <!-- 学生菜单 -->
                        <li class="nav-item">
                            <a class="nav-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>" href="/pages/student/index.php">
                                <i class="bi bi-house me-1"></i> 首页
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeMenu === 'today' ? 'active' : '' ?>" href="/pages/student/today.php">
                                <i class="bi bi-calendar-day me-1"></i> 今日课程
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeMenu === 'tomorrow' ? 'active' : '' ?>" href="/pages/student/tomorrow.php">
                                <i class="bi bi-calendar2-plus me-1"></i> 明日课程
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeMenu === 'attendance' ? 'active' : '' ?>" href="/pages/student/attendance.php">
                                <i class="bi bi-check2-square me-1"></i> 我的考勤
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeMenu === 'leaves' ? 'active' : '' ?>" href="/pages/student/leaves.php">
                                <i class="bi bi-envelope me-1"></i> 请假申请
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <!-- 右侧用户菜单 -->
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <span class="d-inline d-lg-none me-1"><?= e($currentUser['real_name']) ?></span>
                            <span class="badge bg-light text-primary d-none d-lg-inline me-1"><?= getRoleName($currentUser['role']) ?></span>
                            <span class="d-none d-lg-inline"><?= e($currentUser['real_name']) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/pages/profile.php">
                                <i class="bi bi-person me-2"></i> 个人中心
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i> 退出登录
                            </a></li>
                        </ul>
                    </li>
                </ul>
            <?php else: ?>
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="/index.php">登录</a>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>
