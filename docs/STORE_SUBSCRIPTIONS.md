# تفعيل اشتراكات رفيق

خطتا رفيق هما `pro-monthly` بسعر أساسي 10 دولارات شهريًا و`pro-yearly` بسعر أساسي 100 دولار سنويًا. تعرض واجهة الشراء السعر الذي يعيده متجر المستخدم. تغيير سعر خطة Laravel لا يغيّر سعر المتجر.

1. في App Store Connect، أنشئ اشتراكين متجددين تلقائيًا في مجموعة اشتراك واحدة للتطبيق `com.rafiq.user`. في Google Play Console، فعّل الخطتين وأدخل السعر والفترة ومناطق البيع. تدعم Google منتجين منفصلين أو منتجًا له خطتا أساس.
2. انسخ معرّفات المنتجات الفعلية إلى متغيرات `APPLE_STORE_PRO_MONTHLY` و`APPLE_STORE_PRO_YEARLY` و`GOOGLE_PLAY_PRO_MONTHLY` و`GOOGLE_PLAY_PRO_YEARLY` في بيئة السيرفر. إذا استُخدم منتج Google واحد، استخدم صيغة `product_id:base_plan_id` لكل خطة. لكل إعداد هوية متجر فريدة.
3. لربط Apple، عيّن `APPLE_STORE_ISSUER_ID` و`APPLE_STORE_KEY_ID` و`APPLE_STORE_KEY_PATH` لمفتاح In-App Purchase بامتداد p8. عيّن `APPLE_STORE_ROOT_PEM_PATH` لنسخة PEM من شهادة Apple Root CA G3 الرسمية. لربط Google، فعّل Android Publisher API وامنح حساب الخدمة صلاحيات قراءة الاشتراكات وإدارتها في Play Console، ثم عيّن `GOOGLE_PLAY_CREDENTIALS_PATH` لمسار ملف JSON. احفظ الملفات خارج مجلد الويب.
4. طبّق ترحيلات Laravel وحدّث كاش الإعدادات بعد ضبط البيئة. شغّل مجدول Laravel المعتاد `php artisan schedule:run` كل دقيقة. يعيد الأمر `subscriptions:refresh-stores` التحقق كل 15 دقيقة لرصد التجديد والانتهاء والاسترداد. تحقق من تفعيل الدفع في لوحة الإدارة قبل اختبار الشراء.
5. في بيئة اختبار مستقلة، فعّل `APPLE_STORE_SANDBOX=true` أو `GOOGLE_PLAY_ALLOW_TEST=true` بحسب المتجر. استخدم مستخدم Sandbox أو مستخدم اختبار Play. جرّب الشهري والسنوي، والاستعادة بعد إعادة التثبيت بنفس حساب رفيق، والإلغاء أو الاسترداد. إعدادات الإنتاج الافتراضية تستخدم معاملات الإنتاج فقط. يلزم استخدام قاعدة بيانات منفصلة لحسابات الاختبار.

تنشئ واجهة المتجر معرّفًا لكل حساب رفيق وتربطه بالشراء. يرسل iOS رقم المعاملة، ويرسل Android رمز الشراء، ويتولى Laravel التحقق لدى المتجر قبل إتاحة Pro. يمكن استعادة المشتريات من صفحة الباقات في الملف الشخصي.

تفاصيل واجهات التحقق لدى [Apple](https://developer.apple.com/documentation/appstoreserverapi/get-all-subscription-statuses) و[Google Play](https://developers.google.com/android-publisher/api-ref/rest/v3/purchases.subscriptionsv2/get). تتوفر شهادة الجذر من [Apple PKI](https://www.apple.com/certificateauthority/).
