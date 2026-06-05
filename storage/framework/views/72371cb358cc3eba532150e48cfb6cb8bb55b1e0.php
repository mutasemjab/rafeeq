<?php $__env->startSection('title', __('messages.dashboard')); ?>
<?php $__env->startSection('page_title', __('messages.dashboard')); ?>

<?php $__env->startSection('content'); ?>
<?php $locale = app()->getLocale(); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1><?php echo e(__('messages.dashboard')); ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item active"><?php echo e(__('messages.dashboard')); ?></li>
            </ol>
        </nav>
    </div>
</div>


<div class="row g-4 mb-4">

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrap bg-primary-soft">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo e($usersCount ?? 0); ?></div>
                <div class="stat-label"><?php echo e(__('messages.users')); ?></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <a href="<?php echo e(route('admin.appointments.index')); ?>" class="stat-card text-decoration-none h-100" style="display:block;">
            <div class="stat-icon-wrap bg-success-soft">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo e($appointmentsCount ?? 0); ?></div>
                <div class="stat-label"><?php echo e($locale === 'ar' ? 'المواعيد' : 'Appointments'); ?></div>
                <div style="font-size:0.75rem; color:#2563eb; margin-top:6px;">
                    <?php echo e($locale === 'ar' ? 'فتح المواعيد' : 'Open Appointments'); ?>

                </div>
            </div>
        </a>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrap bg-warning-soft">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number">0</div>
                <div class="stat-label"><?php echo e(__('messages.messages')); ?></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrap bg-info-soft">
                <i class="fas fa-book-open"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number">0</div>
                <div class="stat-label"><?php echo e(__('messages.catalogs')); ?></div>
            </div>
        </div>
    </div>

</div>


<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-users"></i>
                    <?php echo e(__('messages.recent_users')); ?>

                </h3>
                <a href="<?php echo e(route('admin.users.index')); ?>" class="btn btn-sm btn-outline-primary">
                    <?php echo e(__('messages.view_all')); ?>

                </a>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?php echo e(__('messages.user_name')); ?></th>
                            <th><?php echo e(__('messages.email')); ?></th>
                            <th><?php echo e(__('messages.phone')); ?></th>
                            <th><?php echo e(__('messages.created_at')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $recentUsers ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><?php echo e($user->id); ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar-sm">
                                        <?php echo e(strtoupper(substr($user->name, 0, 1))); ?>

                                    </div>
                                    <span class="fw-600"><?php echo e($user->name); ?></span>
                                </div>
                            </td>
                            <td><?php echo e($user->email); ?></td>
                            <td><?php echo e($user->phone ?? '—'); ?></td>
                            <td><?php echo e($user->created_at->format('d M Y')); ?></td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <p><?php echo e(__('messages.no_users_yet')); ?></p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="admin-card h-100">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-circle-info"></i>
                    <?php echo e($locale === 'ar' ? 'معلومات النظام' : 'System Info'); ?>

                </h3>
            </div>
            <div class="admin-card-body">
                <ul class="list-unstyled" style="display:flex; flex-direction:column; gap:14px;">
                    <li class="d-flex justify-content-between align-items-center">
                        <span style="color:#64748b; font-size:0.85rem;">
                            <i class="fas fa-code-branch me-2 text-primary"></i>
                            <?php echo e($locale === 'ar' ? 'إطار العمل' : 'Framework'); ?>

                        </span>
                        <span class="fw-600">Laravel 9</span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                        <span style="color:#64748b; font-size:0.85rem;">
                            <i class="fas fa-calendar me-2 text-success"></i>
                            <?php echo e($locale === 'ar' ? 'التاريخ' : 'Date'); ?>

                        </span>
                        <span class="fw-600"><?php echo e(now()->format('d M Y')); ?></span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                        <span style="color:#64748b; font-size:0.85rem;">
                            <i class="fas fa-globe me-2 text-info"></i>
                            <?php echo e($locale === 'ar' ? 'اللغة' : 'Language'); ?>

                        </span>
                        <span class="fw-600"><?php echo e($locale === 'ar' ? 'العربية' : 'English'); ?></span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                        <span style="color:#64748b; font-size:0.85rem;">
                            <i class="fas fa-user-shield me-2 text-warning"></i>
                            <?php echo e($locale === 'ar' ? 'المدير' : 'Admin'); ?>

                        </span>
                        <span class="fw-600"><?php echo e(auth()->user()->name ?? auth()->user()->username); ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/tajawal/Downloads/rafeeq/resources/views/admin/dashboard.blade.php ENDPATH**/ ?>