<?php
$files = [
    'www/dashboard_staff.php',
    'www/dashboard_doctor.php',
    'www/dashboard_patient.php',
    'www/dashboard_guardian.php',
    'www/dashboard_admin.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Remove sidebar inline styles specifically
    $content = preg_replace('/<style>\s*\.sidebar\s*\{[\s\S]*?\s*\.main-content\s*\{[\s\S]*?\s*<\/style>/', '', $content);
    // Remove the extended dashboard_patient.php style block
    $content = preg_replace('/<style>\s*\.sidebar\s*\{[\s\S]*?@media\s*\(max-width:\s*992px\)[\s\S]*?<\/style>/', '', $content);
    
    // Add sidebar overlay
    if (strpos($content, '<div class="sidebar-overlay"') === false) {
        $content = str_replace('<div class="sidebar p-4">', '<div class="sidebar-overlay" onclick="toggleSidebar()"></div>' . "\n" . '    <div class="sidebar p-4">', $content);
    }
    
    // Add mobile header right after <div class="main-content">
    if (strpos($content, '<!-- Mobile Header -->') === false) {
        $mobileHeader = '
        <!-- Mobile Header -->
        <div class="d-flex d-lg-none align-items-center mb-4 pb-3 border-bottom">
            <button class="btn btn-light bg-white border-0 rounded-circle shadow-sm p-2 me-3" onclick="toggleSidebar()">
                <i class="bi bi-list fs-4 text-primary"></i>
            </button>
            <h4 class="fw-bold mb-0 text-primary">GGHMS</h4>
        </div>';
        $content = str_replace('<div class="main-content">', '<div class="main-content">' . $mobileHeader, $content);
    }
    
    // Add Javascript for toggling
    if (strpos($content, 'function toggleSidebar()') === false) {
        $js = '
        function toggleSidebar() {
            document.querySelector(\'.sidebar\').classList.toggle(\'show\');
            document.querySelector(\'.sidebar-overlay\').classList.toggle(\'show\');
        }

        // Auto-close sidebar on mobile link click
        document.querySelectorAll(\'.nav-link-custom\').forEach(link => {
            link.addEventListener(\'click\', () => {
                if (window.innerWidth < 992) {
                    document.querySelector(\'.sidebar\').classList.remove(\'show\');
                    document.querySelector(\'.sidebar-overlay\').classList.remove(\'show\');
                }
            });
        });
        ';
        $content = str_replace('</script>', $js . '</script>', $content);
    }
    
    file_put_contents($file, $content);
    echo "Upgraded $file\n";
}
?>
