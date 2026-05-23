<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>" dir="<?php echo e(app()->getLocale() === 'ar' ? 'rtl' : 'ltr'); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', __('messages.dashboard')); ?> — <?php echo e(__('messages.admin_panel')); ?></title>

    
    <?php if(app()->getLocale() === 'ar'): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <?php else: ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <?php endif; ?>

    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    
    <link rel="stylesheet" href="<?php echo e(asset('assets/admin/css/style.css')); ?>">

    <?php echo $__env->yieldContent('css'); ?>
</head>
<body class="<?php echo e(app()->getLocale() === 'ar' ? 'rtl' : ''); ?>">

<div class="admin-wrapper">

    
    <?php echo $__env->make('admin.includes.sidebar', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    
    <div class="main-content" id="mainContent">

        
        <?php echo $__env->make('admin.includes.navbar', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        
        <main class="page-content">
            <?php echo $__env->make('admin.includes.content', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </main>

        
        <?php echo $__env->make('admin.includes.footer', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function () {
    const sidebar  = document.getElementById('adminSidebar');
    const main     = document.getElementById('mainContent');
    const overlay  = document.getElementById('sidebarOverlay');
    const toggleDesktop = document.getElementById('sidebarToggleDesktop');
    const toggleMobile  = document.getElementById('sidebarToggleMobile');

    function closeMobileSidebar() {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
    }

    // Desktop: collapse / expand
    if (toggleDesktop) {
        toggleDesktop.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
            main.classList.toggle('expanded');
        });
    }

    // Mobile: slide in / out
    if (toggleMobile) {
        toggleMobile.addEventListener('click', function () {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeMobileSidebar);
    }

    // Auto-close mobile sidebar on nav click
    document.querySelectorAll('.sidebar .nav-link').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth < 992) closeMobileSidebar();
        });
    });
})();
</script>

<?php echo $__env->yieldContent('script'); ?>

</body>
</html>
<?php /**PATH C:\xampp\htdocs\rafeeq\resources\views/layouts/admin.blade.php ENDPATH**/ ?>