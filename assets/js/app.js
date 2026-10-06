/**
 * 学管系统 - 前端交互脚本
 */

$(function() {
    // 自动淡出提示消息
    setTimeout(function() {
        $('.alert-auto-dismiss').alert('close');
    }, 3000);
    
    // 表单验证
    $('form[data-validate]').on('submit', function(e) {
        var required = $(this).find('[required]');
        var valid = true;
        
        required.each(function() {
            if (!$(this).val()) {
                $(this).addClass('is-invalid');
                valid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        if (!valid) {
            e.preventDefault();
            showToast('请填写所有必填项', 'danger', '验证失败');
        }
    });
    
    // 删除确认
    $('.btn-delete').on('click', function(e) {
        if (!confirm('确定要删除吗？此操作不可恢复。')) {
            e.preventDefault();
            return false;
        }
    });
    
    // 日期选择器默认值
    $('.date-today').val(function() {
        var today = new Date();
        return today.toISOString().split('T')[0];
    });
});

// 全局 AJAX 请求封装
function ajaxRequest(url, method, data, successCallback, errorCallback) {
    $.ajax({
        url: url,
        method: method,
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.code === 0) {
                if (typeof successCallback === 'function') {
                    successCallback(response.data, response.message);
                } else {
                    showToast(response.message, 'success');
                }
            } else {
                if (typeof errorCallback === 'function') {
                    errorCallback(response.message, response);
                } else {
                    showToast(response.message, 'danger', '错误');
                }
            }
        },
        error: function(xhr, status, error) {
            showToast('请求失败: ' + error, 'danger', '网络错误');
        }
    });
}
