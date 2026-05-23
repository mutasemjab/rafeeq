@php $locale = app()->getLocale(); @endphp

<footer class="admin-footer">
    &copy; {{ date('Y') }}
    {{ $locale === 'ar' ? 'لوحة تحكم المدير — جميع الحقوق محفوظة' : 'Admin Panel — All Rights Reserved' }}
</footer>
