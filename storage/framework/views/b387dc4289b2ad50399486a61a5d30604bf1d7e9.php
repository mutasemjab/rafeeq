<?php $locale = app()->getLocale(); ?>

<header class="top-navbar">

    
    <button class="navbar-toggle-btn d-none d-lg-flex" id="sidebarToggleDesktop" title="Toggle sidebar">
        <i class="fas fa-bars"></i>
    </button>

    
    <button class="navbar-toggle-btn d-flex d-lg-none" id="sidebarToggleMobile" title="Open menu">
        <i class="fas fa-bars"></i>
    </button>

    
    <span class="navbar-page-title d-none d-sm-block"><?php echo $__env->yieldContent('page_title', __('messages.dashboard')); ?></span>

    <div class="navbar-spacer"></div>

    <div class="navbar-actions">

        
        <?php if($locale === 'ar'): ?>
            <a href="<?php echo e(LaravelLocalization::getLocalizedURL('en')); ?>"
               class="navbar-btn lang-btn"
               title="Switch to English">
                <i class="fas fa-language"></i> EN
            </a>
        <?php else: ?>
            <a href="<?php echo e(LaravelLocalization::getLocalizedURL('ar')); ?>"
               class="navbar-btn lang-btn"
               title="التبديل إلى العربية">
                <i class="fas fa-language"></i> AR
            </a>
        <?php endif; ?>

        
        <div class="dropdown">
            <a href="#" class="user-dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar">
                    <?php echo e(strtoupper(substr(auth()->user()->name ?? auth()->user()->username ?? 'A', 0, 1))); ?>

                </div>
                <div class="d-none d-md-block text-start">
                    <div class="user-name"><?php echo e(auth()->user()->name ?? auth()->user()->username); ?></div>
                    <div class="user-role"><?php echo e($locale === 'ar' ? 'مدير النظام' : 'Administrator'); ?></div>
                </div>
                <i class="fas fa-chevron-down" style="font-size:0.65rem; color:#94a3b8;"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="<?php echo e(route('admin.login.edit', auth()->user()->id)); ?>">
                        <i class="fas fa-user-gear"></i>
                        <?php echo e($locale === 'ar' ? 'إعدادات الحساب' : 'Account Settings'); ?>

                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="#"
                       onclick="event.preventDefault(); document.getElementById('navbar-logout-form').submit()">
                        <i class="fas fa-right-from-bracket"></i>
                        <?php echo e($locale === 'ar' ? 'تسجيل الخروج' : 'Logout'); ?>

                    </a>
                </li>
            </ul>
        </div>

    </div>

    
    <form id="navbar-logout-form" action="<?php echo e(route('admin.logout')); ?>" method="POST" class="d-none">
        <?php echo csrf_field(); ?>
    </form>

</header>
<?php /**PATH /Users/tajawal/Downloads/rafeeq/resources/views/admin/includes/navbar.blade.php ENDPATH**/ ?>