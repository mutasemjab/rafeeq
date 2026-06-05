<?php if(Session::has('error')): ?>
<div class="alert alert-danger" role="alert">
    <?php echo e(Session::get('error')); ?>

  </div>
  <?php endif; ?>
<?php /**PATH /Users/tajawal/Downloads/rafeeq/resources/views/admin/includes/alerts/error.blade.php ENDPATH**/ ?>