<?php $__env->startSection('title', 'Appointment Details'); ?>
<?php $__env->startSection('page_title', 'Appointment Details'); ?>

<?php $__env->startSection('content'); ?>
<?php $locale = app()->getLocale(); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1><?php echo e($locale === 'ar' ? 'تفاصيل الموعد' : 'Appointment Details'); ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="<?php echo e(route('admin.dashboard')); ?>"><?php echo e($locale === 'ar' ? 'لوحة البيانات' : 'Dashboard'); ?></a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?php echo e(route('admin.appointments.index')); ?>"><?php echo e($locale === 'ar' ? 'المواعيد' : 'Appointments'); ?></a>
                </li>
                <li class="breadcrumb-item active"><?php echo e($appointment->booking_reference); ?></li>
            </ol>
        </nav>
    </div>
    <a href="<?php echo e(route('admin.appointments.index')); ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i>
        <?php echo e($locale === 'ar' ? 'رجوع' : 'Back'); ?>

    </a>
</div>

<?php if(session('success')): ?>
    <div class="alert alert-success mb-4"><?php echo e(session('success')); ?></div>
<?php endif; ?>

<div class="row g-4">
    
    <div class="col-lg-8">
        <div class="admin-card mb-4">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-calendar-check"></i>
                    <?php echo e($locale === 'ar' ? 'معلومات الحجز' : 'Booking Information'); ?>

                </h3>
                <span class="fw-600 font-monospace" style="color:#4f46e5;">
                    <?php echo e($appointment->booking_reference); ?>

                </span>
            </div>
            <div class="p-4">
                <?php
                    $startTime = $appointment->start_time ? \Carbon\Carbon::parse($appointment->start_time) : null;
                    $endTime = $appointment->end_time ? \Carbon\Carbon::parse($appointment->end_time) : null;
                    $durationMinutes = ($startTime && $endTime) ? $endTime->diffInMinutes($startTime) : null;
                    $priceAmount = $appointment->payment->amount ?? $appointment->specialist->session_fee ?? null;
                    $priceCurrency = $appointment->payment->currency ?? $appointment->specialist->currency ?? null;
                ?>
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-muted fw-500" style="width:200px;"><?php echo e($locale === 'ar' ? 'التاريخ' : 'Date'); ?></td>
                        <td class="fw-600">
                            <?php echo e(isset($appointment->scheduled_date) ? \Carbon\Carbon::parse($appointment->scheduled_date)->format('l, d F Y') : '—'); ?>

                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500"><?php echo e($locale === 'ar' ? 'الوقت' : 'Time'); ?></td>
                        <td class="fw-600">
                            <?php if($startTime && $endTime): ?>
                                <?php echo e($startTime->format('H:i')); ?> - <?php echo e($endTime->format('H:i')); ?>

                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500"><?php echo e($locale === 'ar' ? 'المدة' : 'Duration'); ?></td>
                        <td><?php echo e($durationMinutes ? $durationMinutes . ' min' : '—'); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500"><?php echo e($locale === 'ar' ? 'السعر' : 'Price'); ?></td>
                        <td class="fw-600">
                            <?php echo e($priceAmount !== null ? number_format((float) $priceAmount, 2) . ' ' . ($priceCurrency ?? '') : '—'); ?>

                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500"><?php echo e($locale === 'ar' ? 'نوع الجلسة' : 'Session Type'); ?></td>
                        <td><?php echo e($appointment->appointment_type ?? '—'); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500"><?php echo e($locale === 'ar' ? 'المنطقة الزمنية' : 'Timezone'); ?></td>
                        <td><?php echo e($appointment->timezone ?? '—'); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500"><?php echo e($locale === 'ar' ? 'رابط الاجتماع' : 'Meeting Link'); ?></td>
                        <td>
                            <?php if($appointment->join_url): ?>
                                <a href="<?php echo e($appointment->join_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo e($appointment->join_url); ?></a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500"><?php echo e($locale === 'ar' ? 'إتاحة الرابط' : 'Link Available At'); ?></td>
                        <td>
                            <?php echo e($appointment->join_available_at ? $appointment->join_available_at->format('d M Y H:i') : '—'); ?>

                        </td>
                    </tr>
                    <?php if($appointment->notes): ?>
                    <tr>
                        <td class="text-muted fw-500"><?php echo e($locale === 'ar' ? 'ملاحظات' : 'Notes'); ?></td>
                        <td><?php echo e($appointment->notes); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        
        <?php if($appointment->review): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-star"></i>
                    <?php echo e($locale === 'ar' ? 'التقييم' : 'Review'); ?>

                </h3>
            </div>
            <div class="p-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <?php for($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star" style="color:<?php echo e($i <= $appointment->review->rating ? '#f59e0b' : '#e2e8f0'); ?>;"></i>
                    <?php endfor; ?>
                    <span class="fw-600"><?php echo e($appointment->review->rating); ?>/5</span>
                </div>
                <?php if($appointment->review->comment): ?>
                    <p class="text-muted mb-0"><?php echo e($appointment->review->comment); ?></p>
                <?php endif; ?>
                <small class="text-muted"><?php echo e($appointment->review->created_at->format('d M Y')); ?></small>
            </div>
        </div>
        <?php endif; ?>
    </div>

    
    <div class="col-lg-4">
        
        <div class="admin-card mb-4">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-arrows-rotate"></i>
                    <?php echo e($locale === 'ar' ? 'تحديث الحالة' : 'Update Status'); ?>

                </h3>
            </div>
            <div class="p-4">
                <?php
                    $statusColors = [
                        'pending_payment' => 'status-badge pending',
                        'confirmed'       => 'badge rounded-pill bg-info text-white',
                        'upcoming'        => 'badge rounded-pill bg-info text-white',
                        'completed'       => 'status-badge active',
                        'canceled'        => 'status-badge inactive',
                        'missed'          => 'status-badge inactive',
                    ];
                    $cls = $statusColors[$appointment->status] ?? 'status-badge pending';
                ?>
                <div class="mb-3">
                    <span class="text-muted fw-500 me-2"><?php echo e($locale === 'ar' ? 'الحالة الحالية:' : 'Current:'); ?></span>
                    <span class="<?php echo e($cls); ?>"><?php echo e(ucwords(str_replace('_', ' ', $appointment->status))); ?></span>
                </div>
                <form action="<?php echo e(route('admin.appointments.status', $appointment->id)); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label fw-500"><?php echo e($locale === 'ar' ? 'الحالة الجديدة' : 'New Status'); ?></label>
                        <select name="status" class="form-select">
                            <?php $__currentLoopData = ['pending_payment','confirmed','upcoming','completed','canceled','missed']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($s); ?>" <?php echo e($appointment->status === $s ? 'selected' : ''); ?>>
                                <?php echo e(ucwords(str_replace('_', ' ', $s))); ?>

                            </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                        <?php $__errorArgs = ['status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <div class="text-danger small mt-1"><?php echo e($message); ?></div>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-500"><?php echo e($locale === 'ar' ? 'رابط الاجتماع' : 'Meeting Link'); ?></label>
                        <input
                            type="url"
                            name="join_url"
                            class="form-control"
                            value="<?php echo e(old('join_url', $appointment->join_url)); ?>"
                            placeholder="https://meet.google.com/..."
                        >
                        <small class="text-muted d-block mt-1">
                            <?php echo e($locale === 'ar' ? 'اختياري. اتركه فارغاً لحذف الرابط.' : 'Optional. Leave blank to remove the link.'); ?>

                        </small>
                        <?php $__errorArgs = ['join_url'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <div class="text-danger small mt-1"><?php echo e($message); ?></div>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-500"><?php echo e($locale === 'ar' ? 'إتاحة الرابط' : 'Link Available At'); ?></label>
                        <input
                            type="datetime-local"
                            name="join_available_at"
                            class="form-control"
                            value="<?php echo e(old('join_available_at', $appointment->join_available_at?->format('Y-m-d\\TH:i'))); ?>"
                        >
                        <?php $__errorArgs = ['join_available_at'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <div class="text-danger small mt-1"><?php echo e($message); ?></div>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-check me-1"></i>
                        <?php echo e($locale === 'ar' ? 'حفظ الحالة والرابط' : 'Save Status & Link'); ?>

                    </button>
                </form>
            </div>
        </div>

        
        <?php if($appointment->user): ?>
        <div class="admin-card mb-4">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-user"></i>
                    <?php echo e($locale === 'ar' ? 'المستخدم' : 'User'); ?>

                </h3>
            </div>
            <div class="p-4">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="user-avatar-sm">
                        <?php echo e(strtoupper(substr($appointment->user->name, 0, 1))); ?>

                    </div>
                    <div>
                        <div class="fw-600"><?php echo e($appointment->user->name); ?></div>
                        <small class="text-muted"><?php echo e($appointment->user->email); ?></small>
                    </div>
                </div>
                <?php if($appointment->user->phone): ?>
                    <small class="text-muted"><i class="fas fa-phone me-1"></i><?php echo e($appointment->user->phone); ?></small>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        
        <?php if($appointment->specialist): ?>
        <div class="admin-card mb-4">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-user-doctor"></i>
                    <?php echo e($locale === 'ar' ? 'المتخصص' : 'Specialist'); ?>

                </h3>
            </div>
            <div class="p-4">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <?php if($appointment->specialist->avatar): ?>
                        <img src="<?php echo e(asset('storage/' . $appointment->specialist->avatar)); ?>"
                             style="width:44px; height:44px; border-radius:50%; object-fit:cover;">
                    <?php else: ?>
                        <div class="user-avatar-sm" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);">
                            <?php echo e(strtoupper(substr($appointment->specialist->name, 0, 1))); ?>

                        </div>
                    <?php endif; ?>
                    <div>
                        <div class="fw-600"><?php echo e($appointment->specialist->name); ?></div>
                        <small class="text-muted"><?php echo e($appointment->specialist->title); ?></small>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        
        <?php if($appointment->child): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-child"></i>
                    <?php echo e($locale === 'ar' ? 'الطفل' : 'Child'); ?>

                </h3>
            </div>
            <div class="p-4">
                <div class="fw-600"><?php echo e($appointment->child->name); ?></div>
                <?php if($appointment->child->birth_date): ?>
                    <small class="text-muted">
                        <?php echo e(\Carbon\Carbon::parse($appointment->child->birth_date)->age); ?>

                        <?php echo e($locale === 'ar' ? 'سنة' : 'yrs'); ?>

                    </small>
                <?php endif; ?>
                <?php if($appointment->child->diagnosis): ?>
                    <div class="mt-1">
                        <small class="text-muted"><?php echo e($appointment->child->diagnosis); ?></small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/tajawal/Downloads/rafeeq/resources/views/admin/appointments/show.blade.php ENDPATH**/ ?>