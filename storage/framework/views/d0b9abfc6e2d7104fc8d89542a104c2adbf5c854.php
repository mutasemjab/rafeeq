<?php $__env->startSection('title', __('messages.users')); ?>
<?php $__env->startSection('page_title', __('messages.users')); ?>

<?php $__env->startSection('content'); ?>
<?php $locale = app()->getLocale(); ?>


<div class="page-header">
    <div class="page-header-left">
        <h1><?php echo e(__('messages.users')); ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="<?php echo e(route('admin.dashboard')); ?>"><?php echo e(__('messages.dashboard')); ?></a>
                </li>
                <li class="breadcrumb-item active"><?php echo e(__('messages.users')); ?></li>
            </ol>
        </nav>
    </div>
    <a href="<?php echo e(route('admin.users.create')); ?>" class="btn btn-primary">
        <i class="fas fa-plus"></i>
        <?php echo e(__('messages.add_user')); ?>

    </a>
</div>


<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fas fa-users"></i>
            <?php echo e(__('messages.users_list')); ?>

            <span style="background:#ede9fe; color:#4f46e5; font-size:0.75rem; padding:2px 10px; border-radius:20px; font-weight:600;">
                <?php echo e($users->total()); ?>

            </span>
        </h3>
        
        <form method="GET" action="<?php echo e(route('admin.users.index')); ?>">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text"
                       name="search"
                       class="form-control"
                       placeholder="<?php echo e(__('messages.search_users')); ?>"
                       value="<?php echo e(request('search')); ?>">
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?php echo e(__('messages.user_name')); ?></th>
                    <th><?php echo e(__('messages.email')); ?></th>
                    <th><?php echo e(__('messages.phone')); ?></th>
                    <th><?php echo e(__('messages.status')); ?></th>
                    <th><?php echo e(__('messages.created_at')); ?></th>
                    <th><?php echo e(__('messages.actions')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td style="color:#94a3b8; font-size:0.80rem;"><?php echo e($user->id); ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="user-avatar-sm">
                                <?php echo e(strtoupper(substr($user->name, 0, 1))); ?>

                            </div>
                            <div>
                                <div class="fw-600"><?php echo e($user->name); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <a href="mailto:<?php echo e($user->email); ?>" style="color:#4f46e5; text-decoration:none;">
                            <?php echo e($user->email); ?>

                        </a>
                    </td>
                    <td><?php echo e($user->phone ?? '—'); ?></td>
                    <td>
                        <?php if($user->is_active): ?>
                            <span class="status-badge active">
                                <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                                <?php echo e(__('messages.active')); ?>

                            </span>
                        <?php else: ?>
                            <span class="status-badge inactive">
                                <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                                <?php echo e(__('messages.inactive')); ?>

                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="color:#64748b; font-size:0.82rem;">
                        <?php echo e($user->created_at->format('d M Y')); ?>

                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            <a href="<?php echo e(route('admin.users.edit', $user->id)); ?>"
                               class="btn-action edit"
                               title="<?php echo e(__('messages.edit')); ?>">
                                <i class="fas fa-pen"></i>
                            </a>
                            <button type="button"
                                    class="btn-action delete"
                                    title="<?php echo e(__('messages.delete')); ?>"
                                    onclick="confirmDelete(<?php echo e($user->id); ?>, '<?php echo e(addslashes($user->name)); ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p><?php echo e(__('messages.no_records')); ?></p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($users->hasPages()): ?>
    <div style="padding: 16px 22px; border-top: 1px solid #f1f5f9;">
        <?php echo e($users->links()); ?>

    </div>
    <?php endif; ?>
</div>


<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-triangle-exclamation text-danger me-2"></i>
                    <?php echo e(__('messages.confirm_delete')); ?>

                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:22px;">
                <p style="color:#475569; margin:0;">
                    <?php echo e(__('messages.delete_user_confirm')); ?>

                    <strong id="deleteUserName"></strong>?
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?php echo e(__('messages.cancel')); ?>

                </button>
                <form id="deleteForm" method="POST">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('DELETE'); ?>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i>
                        <?php echo e(__('messages.delete')); ?>

                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
<script>
function confirmDelete(id, name) {
    document.getElementById('deleteUserName').textContent = name;
    document.getElementById('deleteForm').action = '/<?php echo e(app()->getLocale()); ?>/admin/users/' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\rafeeq\resources\views/admin/users/index.blade.php ENDPATH**/ ?>