<?php $__env->startSection('title', 'Notifications'); ?>
<?php $__env->startSection('page_title', 'Notifications'); ?>

<?php $__env->startSection('content'); ?>
<?php $locale = app()->getLocale(); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1><?php echo e($locale === 'ar' ? 'الإشعارات' : 'Notifications'); ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="<?php echo e(route('admin.dashboard')); ?>"><?php echo e($locale === 'ar' ? 'لوحة البيانات' : 'Dashboard'); ?></a>
                </li>
                <li class="breadcrumb-item active"><?php echo e($locale === 'ar' ? 'الإشعارات' : 'Notifications'); ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-lg-8">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-paper-plane"></i>
                    <?php echo e($locale === 'ar' ? 'إرسال إشعار جديد' : 'Send New Notification'); ?>

                </h3>
            </div>
            <div class="admin-card-body">
                <form method="POST" action="<?php echo e(route('admin.notifications.store')); ?>" class="row g-3">
                    <?php echo csrf_field(); ?>

                    <div class="col-md-4">
                        <label class="form-label"><?php echo e($locale === 'ar' ? 'الجمهور' : 'Audience'); ?></label>
                        <select name="audience" class="form-select">
                            <option value="all" <?php echo e(old('audience', 'all') === 'all' ? 'selected' : ''); ?>>
                                <?php echo e($locale === 'ar' ? 'كل المستخدمين النشطين' : 'All Active Users'); ?>

                            </option>
                            <option value="user" <?php echo e(old('audience') === 'user' ? 'selected' : ''); ?>>
                                <?php echo e($locale === 'ar' ? 'مستخدم محدد' : 'Specific User'); ?>

                            </option>
                        </select>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label"><?php echo e($locale === 'ar' ? 'المستخدم المحدد' : 'Specific User'); ?></label>
                        <select name="user_id" class="form-select">
                            <option value=""><?php echo e($locale === 'ar' ? 'اختر مستخدماً' : 'Choose a user'); ?></option>
                            <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($user->id); ?>" <?php echo e((string) old('user_id') === (string) $user->id ? 'selected' : ''); ?>>
                                <?php echo e($user->name); ?> (<?php echo e($user->email); ?>)
                            </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                        <small class="text-muted">
                            <?php echo e($locale === 'ar' ? 'يستخدم هذا الحقل فقط عند اختيار "مستخدم محدد".' : 'Used only when Audience is set to Specific User.'); ?>

                        </small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label"><?php echo e($locale === 'ar' ? 'النوع' : 'Type'); ?></label>
                        <input type="text" name="type" class="form-control" value="<?php echo e(old('type', 'admin_broadcast')); ?>" placeholder="admin_broadcast">
                    </div>

                    <div class="col-md-8">
                        <label class="form-label"><?php echo e($locale === 'ar' ? 'العنوان' : 'Title'); ?></label>
                        <input type="text" name="title" class="form-control" value="<?php echo e(old('title')); ?>" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label"><?php echo e($locale === 'ar' ? 'الرسالة' : 'Message'); ?></label>
                        <textarea name="body" rows="4" class="form-control" required><?php echo e(old('body')); ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label"><?php echo e($locale === 'ar' ? 'بيانات إضافية JSON (اختياري)' : 'Extra JSON Data (Optional)'); ?></label>
                        <textarea name="data_json" rows="6" class="form-control font-monospace" placeholder='{"screen":"appointments","appointment_id":"123"}'><?php echo e(old('data_json', "{\n  \"screen\": \"appointments\"\n}")); ?></textarea>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="send_push" value="1" id="send_push" <?php echo e(old('send_push', '1') ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="send_push">
                                <?php echo e($locale === 'ar' ? 'إرسال Push عبر Firebase أيضاً' : 'Also send a Firebase push notification'); ?>

                            </label>
                        </div>
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i>
                            <?php echo e($locale === 'ar' ? 'إرسال الإشعار' : 'Send Notification'); ?>

                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="admin-card h-100">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-bell"></i>
                    <?php echo e($locale === 'ar' ? 'حالة Firebase' : 'Firebase Status'); ?>

                </h3>
            </div>
            <div class="admin-card-body">
                <div style="display:flex; flex-direction:column; gap:14px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="color:#64748b;"><?php echo e($locale === 'ar' ? 'المشروع' : 'Project'); ?></span>
                        <span class="fw-600"><?php echo e($firebaseProjectId ?: '—'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="color:#64748b;"><?php echo e($locale === 'ar' ? 'Push Tokens' : 'Push Tokens'); ?></span>
                        <span class="fw-600"><?php echo e($registeredDevicesCount); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="color:#64748b;"><?php echo e($locale === 'ar' ? 'الإرسال من الخادم' : 'Server Sending'); ?></span>
                        <span class="fw-600 <?php echo e($firebaseConfigured ? 'text-success' : 'text-danger'); ?>">
                            <?php echo e($firebaseConfigured ? ($locale === 'ar' ? 'جاهز' : 'Configured') : ($locale === 'ar' ? 'غير مهيأ' : 'Not Configured')); ?>

                        </span>
                    </div>
                </div>

                <?php if(! $firebaseConfigured): ?>
                <div class="alert alert-warning mt-3 mb-0" style="font-size:0.85rem;">
                    <?php echo e($locale === 'ar' ? 'لإرسال Push من لوحة التحكم، أضف FIREBASE_PROJECT_ID و FIREBASE_SERVICE_ACCOUNT_JSON في الخادم.' : 'To send push from the dashboard, add FIREBASE_PROJECT_ID and FIREBASE_SERVICE_ACCOUNT_JSON on the server.'); ?>

                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fas fa-clock-rotate-left"></i>
            <?php echo e($locale === 'ar' ? 'آخر الإشعارات' : 'Recent Notifications'); ?>

            <span style="background:#ede9fe; color:#4f46e5; font-size:0.75rem; padding:2px 10px; border-radius:20px; font-weight:600;">
                <?php echo e($recentNotifications->total()); ?>

            </span>
        </h3>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?php echo e($locale === 'ar' ? 'المستخدم' : 'User'); ?></th>
                    <th><?php echo e($locale === 'ar' ? 'النوع' : 'Type'); ?></th>
                    <th><?php echo e($locale === 'ar' ? 'العنوان' : 'Title'); ?></th>
                    <th><?php echo e($locale === 'ar' ? 'الرسالة' : 'Message'); ?></th>
                    <th><?php echo e($locale === 'ar' ? 'القراءة' : 'Read'); ?></th>
                    <th><?php echo e($locale === 'ar' ? 'الوقت' : 'Created'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $recentNotifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td>
                        <?php if($notification->user): ?>
                            <div class="fw-600"><?php echo e($notification->user->name); ?></div>
                            <small class="text-muted"><?php echo e($notification->user->email); ?></small>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge rounded-pill bg-light text-dark"><?php echo e($notification->type); ?></span></td>
                    <td class="fw-600"><?php echo e($notification->title); ?></td>
                    <td style="max-width:320px; white-space:normal;"><?php echo e($notification->body); ?></td>
                    <td>
                        <?php if($notification->read_at): ?>
                            <span class="badge rounded-pill bg-success-subtle text-success"><?php echo e($locale === 'ar' ? 'مقروء' : 'Read'); ?></span>
                        <?php else: ?>
                            <span class="badge rounded-pill bg-warning-subtle text-warning"><?php echo e($locale === 'ar' ? 'غير مقروء' : 'Unread'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="color:#64748b; font-size:0.82rem;"><?php echo e($notification->created_at?->format('d M Y H:i')); ?></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fas fa-bell"></i>
                            <p><?php echo e($locale === 'ar' ? 'لا توجد إشعارات بعد' : 'No notifications yet'); ?></p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($recentNotifications->hasPages()): ?>
    <div style="padding: 16px 22px; border-top: 1px solid #f1f5f9;">
        <?php echo e($recentNotifications->appends(request()->query())->links()); ?>

    </div>
    <?php endif; ?>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/tajawal/Downloads/rafeeq/resources/views/admin/notifications/index.blade.php ENDPATH**/ ?>