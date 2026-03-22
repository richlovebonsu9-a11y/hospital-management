document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            // Auto dismiss success and danger messages, but leave info alerts
            if (alert.classList.contains('alert-success') || 
                alert.classList.contains('alert-danger') || 
                alert.classList.contains('text-danger') || 
                alert.classList.contains('text-success')) {
                
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        });
    }, 4500); // 4.5 seconds
});
