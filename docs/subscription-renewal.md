# تجديد اشتراك المختبر

من حساب الأدمن: **إدارة المختبرات ← اشتراك المختبر / عرض التفاصيل ← تجديد الاشتراك**، أو من **الاشتراكات ← تجديد الاشتراك**.

اختر شهراً، 3 أشهر، 6 أشهر أو سنة، وراجع تاريخ البداية والنهاية ثم اضغط **تأكيد التجديد**. يبدأ الاشتراك المنتهي من تاريخ اليوم حسب توقيت التطبيق؛ الاشتراك الفعّال أو التجريبي يضيف المدة بعد آخر يوم مشمول بالاشتراك الحالي. الأشهر تقويمية دون تجاوز نهاية الشهر، وتاريخ الانتهاء هو اليوم السابق لنهاية المدة المضافة.

التجديد يحدّث نفس الاشتراك إلى فعّال، ويحتفظ بالخطة والسعر وحدود الاستخدام والملاحظات. لا يسجل تسديداً مالياً ولا يغيّر بيانات دخول المختبر أو حالة حسابه. يحفظ سجل الحركات الأدمن المنفذ والقيم السابقة والجديدة. تعرض الفلاتر والشارات الاشتراكات المنتهية بالتاريخ حتى لو كانت حالتها المخزنة فعّالة أو تجريبية.

لا يمكن تجديد سجل اشتراك قديم مع وجود اشتراك أحدث للمختبر. الاشتراك الموقوف يحتاج إزالة الإيقاف من التعديل أولاً. الاشتراك الذي لم تبدأ مدته يحتاج تعديل تواريخه بدلاً من تجديده. الحساب المحذوف غير قابل للتجديد.

## API

Both endpoints require an authenticated administrator (role 1):

- `GET /api/subscriptions/{id}/renewal`: current subscription, server-calculated renewal start/options, and a version fingerprint. Does not mutate data; uses `Cache-Control: no-store`.
- `POST /api/subscriptions/{id}/renew`: `{ "months": 1|3|6|12, "version": "<preview version>" }`. Locks the row, checks eligibility/version, recalculates dates, and activates the subscription. Rejects stale edits, duplicate confirmations, and previews from a prior day with HTTP 409. Refresh the preview and review the current dates before explicitly confirming another renewal.

No database migration is required. Deployment needs both the backend source and matching compiled frontend. Feature tests cover expired/active/trial renewal, login restoration, permissions, date boundaries, filters, audit, invalid targets, and retries; the browser fixture uses synthetic data for desktop/mobile and both admin entry points.
