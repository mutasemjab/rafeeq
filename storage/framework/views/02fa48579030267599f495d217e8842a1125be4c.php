<?php $locale = app()->getLocale(); $currentRoute = Route::currentRouteName(); ?>

<aside class="sidebar" id="adminSidebar">

    
    <a href="<?php echo e(route('admin.dashboard')); ?>" class="sidebar-brand">
        <div class="brand-icon">
            <i class="fas fa-shield-halved"></i>
        </div>
        <span class="brand-name"><?php echo e($locale === 'ar' ? 'لوحة التحكم' : 'Admin Panel'); ?></span>
    </a>

    
    <ul class="sidebar-nav">

        
        <li><span class="nav-section-label"><?php echo e($locale === 'ar' ? 'الرئيسية' : 'Main'); ?></span></li>

        <li class="nav-item">
            <a href="<?php echo e(route('admin.dashboard')); ?>"
               class="nav-link <?php echo e(Str::startsWith($currentRoute, 'admin.dashboard') ? 'active' : ''); ?>">
                <i class="nav-icon fas fa-gauge-high"></i>
                <span class="nav-text"><?php echo e(__('messages.dashboard')); ?></span>
            </a>
        </li>

        
        <li><span class="nav-section-label"><?php echo e($locale === 'ar' ? 'المستخدمون' : 'Users'); ?></span></li>

        <li class="nav-item">
            <a href="<?php echo e(route('admin.users.index')); ?>"
               class="nav-link <?php echo e(Str::startsWith($currentRoute, 'admin.users') ? 'active' : ''); ?>">
                <i class="nav-icon fas fa-users"></i>
                <span class="nav-text"><?php echo e(__('messages.users')); ?></span>
            </a>
        </li>

        
        <li><span class="nav-section-label"><?php echo e($locale === 'ar' ? 'المحتوى' : 'Content'); ?></span></li>

        <li class="nav-item">
            <a href="#"
               class="nav-link <?php echo e(Str::startsWith($currentRoute, 'admin.products') ? 'active' : ''); ?>">
                <i class="nav-icon fas fa-box-open"></i>
                <span class="nav-text"><?php echo e(__('messages.products')); ?></span>
            </a>
        </li>

        <li class="nav-item">
            <a href="#"
               class="nav-link <?php echo e(Str::startsWith($currentRoute, 'admin.categories') ? 'active' : ''); ?>">
                <i class="nav-icon fas fa-layer-group"></i>
                <span class="nav-text"><?php echo e(__('messages.categories')); ?></span>
            </a>
        </li>

        <li class="nav-item">
            <a href="#"
               class="nav-link <?php echo e(Str::startsWith($currentRoute, 'admin.services') ? 'active' : ''); ?>">
                <i class="nav-icon fas fa-concierge-bell"></i>
                <span class="nav-text"><?php echo e(__('messages.services')); ?></span>
            </a>
        </li>

        <li class="nav-item">
            <a href="#"
               class="nav-link <?php echo e(Str::startsWith($currentRoute, 'admin.catalogs') ? 'active' : ''); ?>">
                <i class="nav-icon fas fa-book-open"></i>
                <span class="nav-text"><?php echo e(__('messages.catalogs')); ?></span>
            </a>
        </li>

        <li class="nav-item">
            <a href="#"
               class="nav-link <?php echo e(Str::startsWith($currentRoute, 'admin.works') ? 'active' : ''); ?>">
                <i class="nav-icon fas fa-briefcase"></i>
                <span class="nav-text"><?php echo e(__('messages.works')); ?></span>
            </a>
        </li>

        <li class="nav-item">
            <a href="#"
               class="nav-link <?php echo e(Str::startsWith($currentRoute, 'admin.clients') ? 'active' : ''); ?>">
                <i class="nav-icon fas fa-handshake"></i>
                <span class="nav-text"><?php echo e(__('messages.clients')); ?></span>
            </a>
        </li>

        <li class="nav-item">
            <a href="#"
               class="nav-link <?php echo e(Str::startsWith($currentRoute, 'admin.certificates') ? 'active' : ''); ?>">
                <i class="nav-icon fas fa-certificate"></i>
                <span class="nav-text"><?php echo e(__('messages.certificates')); ?></span>
            </a>
        </li>

        
        <li><span class="nav-section-label"><?php echo e($locale === 'ar' ? 'النظام' : 'System'); ?></span></li>

        <li class="nav-item">
            <a href="#"
               class="nav-link <?php echo e(Str::startsWith($currentRoute, 'admin.messages') ? 'active' : ''); ?>">
                <i class="nav-icon fas fa-envelope"></i>
                <span class="nav-text"><?php echo e(__('messages.messages')); ?></span>
            </a>
        </li>

        <li class="nav-item">
            <a href="#"
               class="nav-link <?php echo e(Str::startsWith($currentRoute, 'admin.settings') ? 'active' : ''); ?>">
                <i class="nav-icon fas fa-gear"></i>
                <span class="nav-text"><?php echo e(__('messages.settings')); ?></span>
            </a>
        </li>

    </ul>

    
    <div class="sidebar-footer">
        <form id="sidebar-logout-form" action="<?php echo e(route('admin.logout')); ?>" method="POST" class="d-none">
            <?php echo csrf_field(); ?>
        </form>
        <a href="#"
           class="nav-link"
           onclick="event.preventDefault(); document.getElementById('sidebar-logout-form').submit()">
            <i class="nav-icon fas fa-right-from-bracket"></i>
            <span class="nav-text"><?php echo e($locale === 'ar' ? 'تسجيل الخروج' : 'Logout'); ?></span>
        </a>
    </div>

</aside>
<?php /**PATH C:\xampp\htdocs\rafeeq\resources\views/admin/includes/sidebar.blade.php ENDPATH**/ ?>