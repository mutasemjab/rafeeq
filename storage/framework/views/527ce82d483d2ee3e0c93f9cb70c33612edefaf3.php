<?php $__env->startSection('title', __('messages.add_user')); ?>
<?php $__env->startSection('page_title', __('messages.add_user')); ?>

<?php $__env->startSection('content'); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1><?php echo e(__('messages.add_user')); ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="<?php echo e(route('admin.dashboard')); ?>"><?php echo e(__('messages.dashboard')); ?></a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?php echo e(route('admin.users.index')); ?>"><?php echo e(__('messages.users')); ?></a>
                </li>
                <li class="breadcrumb-item active"><?php echo e(__('messages.add_user')); ?></li>
            </ol>
        </nav>
    </div>
    <a href="<?php echo e(route('admin.users.index')); ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i>
        <?php echo e(__('messages.back')); ?>

    </a>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-xl-8">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-user-plus"></i>
                    <?php echo e(__('messages.user_information')); ?>

                </h3>
            </div>
            <div class="admin-card-body">
                <form action="<?php echo e(route('admin.users.store')); ?>" method="POST" novalidate>
                    <?php echo csrf_field(); ?>

                    <div class="row g-4">

                        
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="name">
                                <?php echo e(__('messages.user_name')); ?>

                                <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   id="name"
                                   name="name"
                                   class="form-control <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                   value="<?php echo e(old('name')); ?>"
                                   placeholder="<?php echo e(__('messages.enter_name')); ?>">
                            <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <div class="invalid-feedback"><?php echo e($message); ?></div>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>

                        
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="email">
                                <?php echo e(__('messages.email')); ?>

                                <span class="text-danger">*</span>
                            </label>
                            <input type="email"
                                   id="email"
                                   name="email"
                                   class="form-control <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                   value="<?php echo e(old('email')); ?>"
                                   placeholder="<?php echo e(__('messages.enter_email')); ?>">
                            <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <div class="invalid-feedback"><?php echo e($message); ?></div>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>

                        
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="phone">
                                <?php echo e(__('messages.phone')); ?>

                            </label>
                            <input type="text"
                                   id="phone"
                                   name="phone"
                                   class="form-control <?php $__errorArgs = ['phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                   value="<?php echo e(old('phone')); ?>"
                                   placeholder="<?php echo e(__('messages.enter_phone')); ?>">
                            <?php $__errorArgs = ['phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <div class="invalid-feedback"><?php echo e($message); ?></div>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>

                        
                        <div class="col-12 col-md-6">
                            <label class="form-label"><?php echo e(__('messages.status')); ?></label>
                            <select name="is_active" class="form-select <?php $__errorArgs = ['is_active'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                                <option value="1" <?php echo e(old('is_active', '1') == '1' ? 'selected' : ''); ?>>
                                    <?php echo e(__('messages.active')); ?>

                                </option>
                                <option value="0" <?php echo e(old('is_active') == '0' ? 'selected' : ''); ?>>
                                    <?php echo e(__('messages.inactive')); ?>

                                </option>
                            </select>
                            <?php $__errorArgs = ['is_active'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <div class="invalid-feedback"><?php echo e($message); ?></div>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>

                        
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="password">
                                <?php echo e(__('messages.password')); ?>

                                <span class="text-danger">*</span>
                            </label>
                            <input type="password"
                                   id="password"
                                   name="password"
                                   class="form-control <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                   placeholder="<?php echo e(__('messages.enter_password')); ?>">
                            <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <div class="invalid-feedback"><?php echo e($message); ?></div>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>

                        
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="password_confirmation">
                                <?php echo e(__('messages.confirm_password')); ?>

                                <span class="text-danger">*</span>
                            </label>
                            <input type="password"
                                   id="password_confirmation"
                                   name="password_confirmation"
                                   class="form-control"
                                   placeholder="<?php echo e(__('messages.confirm_password')); ?>">
                        </div>

                    </div>

                    
                    <div class="d-flex justify-content-end gap-2 mt-4" style="padding-top:16px; border-top:1px solid #f1f5f9;">
                        <a href="<?php echo e(route('admin.users.index')); ?>" class="btn btn-secondary">
                            <?php echo e(__('messages.cancel')); ?>

                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i>
                            <?php echo e(__('messages.save')); ?>

                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\rafeeq\resources\views/admin/users/create.blade.php ENDPATH**/ ?>