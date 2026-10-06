<?php
/**
 * 页面底部
 */
?>
    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-white border-top">
        <div class="container text-center text-muted">
            <small>&copy; <?= date('Y') ?> <?= SITE_NAME ?> v<?= SITE_VERSION ?></small>
        </div>
    </footer>

    <!-- Toast 通知容器 -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1060;">
        <div id="toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <i class="bi bi-bell me-2"></i>
                <strong class="me-auto" id="toast-title">通知</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toast-body"></div>
        </div>
    </div>

    <script>
        // 全局提示函数
        function showToast(message, type = 'success', title = '通知') {
            const toast = new bootstrap.Toast(document.getElementById('toast'));
            const toastBody = document.getElementById('toast-body');
            const toastTitle = document.getElementById('toast-title');
            const toastEl = document.getElementById('toast');
            
            toastTitle.textContent = title;
            toastBody.textContent = message;
            
            // 设置颜色
            toastEl.className = 'toast text-white bg-' + type;
            
            toast.show();
        }
        
        // 确认对话框
        function confirmDelete(message = '确定要删除吗？') {
            return confirm(message);
        }
    </script>
</body>
</html>
