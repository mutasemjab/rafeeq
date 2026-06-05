<?php $locale = app()->getLocale(); $cur = Route::currentRouteName(); ?>

<aside class="sidebar" id="adminSidebar">

    <a href="<?php echo e(route('admin.dashboard')); ?>" class="sidebar-brand">
        <div class="brand-icon"><i class="fas fa-shield-halved"></i></div>
        <span class="brand-name"><?php echo e($locale === 'ar' ? 'رفيق - لوحة التحكم' : 'Rafiq Admin'); ?></span>
    </a>

    <ul class="sidebar-nav">

        <li><span class="nav-section-label"><?php echo e($locale === 'ar' ? 'الرئيسية' : 'Main'); ?></span></li>
        <li class="nav-item">
            <a href="<?php echo e(route('admin.dashboard')); ?>" class="nav-link <?php echo e(Str::startsWith($cur,'admin.dashboard') ? 'active':''); ?>">
                <i class="nav-icon fas fa-gauge-high"></i>
                <span class="nav-text"><?php echo e($locale === 'ar' ? 'لوحة البيانات' : 'Dashboard'); ?></span>
            </a>
        </li>

        <li><span class="nav-section-label"><?php echo e($locale === 'ar' ? 'المستخدمون' : 'Users'); ?></span></li>
        <li class="nav-item">
            <a href="<?php echo e(route('admin.users.index')); ?>" class="nav-link <?php echo e(Str::startsWith($cur,'admin.users') ? 'active':''); ?>">
                <i class="nav-icon fas fa-users"></i>
                <span class="nav-text"><?php echo e($locale === 'ar' ? 'المستخدمون' : 'Users'); ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo e(route('admin.children.index')); ?>" class="nav-link <?php echo e(Str::startsWith($cur,'admin.children') ? 'active':''); ?>">
                <i class="nav-icon fas fa-child"></i>
                <span class="nav-text"><?php echo e($locale === 'ar' ? 'الأطفال' : 'Children'); ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo e(route('admin.subscriptions.index')); ?>" class="nav-link <?php echo e(Str::startsWith($cur,'admin.subscriptions') ? 'active':''); ?>">
                <i class="nav-icon fas fa-credit-card"></i>
                <span class="nav-text"><?php echo e($locale === 'ar' ? 'الاشتراكات' : 'Subscriptions'); ?></span>
            </a>
        </li>

        <li><span class="nav-section-label"><?php echo e($locale === 'ar' ? 'المتخصصون' : 'Specialists'); ?></span></li>
        <li class="nav-item">
            <a href="<?php echo e(route('admin.specialists.index')); ?>" class="nav-link <?php echo e(Str::startsWith($cur,'admin.specialists') ? 'active':''); ?>">
                <i class="nav-icon fas fa-user-doctor"></i>
                <span class="nav-text"><?php echo e($locale === 'ar' ? 'المتخصصون' : 'Specialists'); ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo e(route('admin.appointments.index')); ?>" class="nav-link <?php echo e(Str::startsWith($cur,'admin.appointments') ? 'active':''); ?>">
                <i class="nav-icon fas fa-calendar-check"></i>
                <span class="nav-text"><?php echo e($locale === 'ar' ? 'المواعيد' : 'Appointments'); ?></span>
            </a>
        </li>

        <li><span class="nav-section-label"><?php echo e($locale === 'ar' ? 'الخطط والمحتوى' : 'Plans & Content'); ?></span></li>
        <li class="nav-item">
            <a href="<?php echo e(route('admin.plans.index')); ?>" class="nav-link <?php echo e(Str::startsWith($cur,'admin.plans') ? 'active':''); ?>">
                <i class="nav-icon fas fa-tags"></i>
                <span class="nav-text"><?php echo e($locale === 'ar' ? 'خطط الاشتراك' : 'Plans'); ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo e(route('admin.knowledge.index')); ?>" class="nav-link <?php echo e(Str::startsWith($cur,'admin.knowledge') ? 'active':''); ?>">
                <i class="nav-icon fas fa-book-open"></i>
                <span class="nav-text"><?php echo e($locale === 'ar' ? 'قاعدة المعرفة' : 'Knowledge Base'); ?></span>
            </a>
        </li>

        <li><span class="nav-section-label"><?php echo e($locale === 'ar' ? 'النظام' : 'System'); ?></span></li>
        <li class="nav-item">
            <a href="<?php echo e(route('admin.activity.index')); ?>" class="nav-link <?php echo e(Str::startsWith($cur,'admin.activity') ? 'active':''); ?>">
                <i class="nav-icon fas fa-clock-rotate-left"></i>
                <span class="nav-text"><?php echo e($locale === 'ar' ? 'سجل النشاط' : 'Activity Log'); ?></span>
            </a>
        </li>

    </ul>

    <div class="sidebar-footer">
        <form id="sidebar-logout-form" action="<?php echo e(route('admin.logout')); ?>" method="POST" class="d-none"><?php echo csrf_field(); ?></form>
        <a href="#" class="nav-link" onclick="event.preventDefault(); document.getElementById('sidebar-logout-form').submit()">
            <i class="nav-icon fas fa-right-from-bracket"></i>
            <span class="nav-text"><?php echo e($locale === 'ar' ? 'تسجيل الخروج' : 'Logout'); ?></span>
        </a>
    </div>

</aside>
<?php /**PATH /Users/tajawal/Downloads/rafeeq/resources/views/admin/includes/sidebar.blade.php ENDPATH**/ ?>