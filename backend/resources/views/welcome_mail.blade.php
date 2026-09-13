<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Digital Lab</title>
</head>
<body style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0fdfa; margin: 0; padding: 20px;">
    <div style="max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); overflow: hidden;">

        <!-- Header -->
        <div style="background: linear-gradient(135deg, #0d9488, #0f766e); padding: 30px 20px; text-align: center;">
            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700;">Digital Lab</h1>
            <p style="color: #ccfbf1; margin: 5px 0 0; font-size: 14px;">المختبر الرقمي</p>
        </div>

        <!-- Body -->
        <div style="padding: 30px 25px;">

            <!-- English Section -->
            <h2 style="color: #0d9488; font-size: 20px; margin: 0 0 10px;">Welcome, {{ $user->name }}!</h2>
            <p style="color: #374151; font-size: 15px; line-height: 1.6; margin: 0 0 8px;">
                Your account has been created successfully. You can now log in and start using Digital Lab.
            </p>
            <p style="color: #6b7280; font-size: 14px; margin: 0 0 20px;">
                <strong>Email:</strong> {{ $user->email }}
            </p>

            <!-- Login Button -->
            <div style="text-align: center; margin: 20px 0;">
                <a href="{{ env('FRONTEND_URL', 'https://digitals-labs.com') }}/login"
                   style="display: inline-block; padding: 12px 32px; background-color: #0d9488; color: #ffffff; text-decoration: none; border-radius: 8px; font-size: 15px; font-weight: 600;">
                    Log In / تسجيل الدخول
                </a>
            </div>

            <!-- Divider -->
            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 25px 0;">

            <!-- Arabic Section -->
            <div dir="rtl" style="text-align: right;">
                <h2 style="color: #0d9488; font-size: 20px; margin: 0 0 10px;">مرحباً، {{ $user->name }}!</h2>
                <p style="color: #374151; font-size: 15px; line-height: 1.6; margin: 0 0 8px;">
                    تم إنشاء حسابك بنجاح. يمكنك الآن تسجيل الدخول والبدء في استخدام المختبر الرقمي.
                </p>
                <p style="color: #6b7280; font-size: 14px; margin: 0;">
                    <strong>البريد الإلكتروني:</strong> {{ $user->email }}
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div style="background-color: #f9fafb; padding: 18px 25px; text-align: center; border-top: 1px solid #e5e7eb;">
            <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                Digital Lab &copy; {{ date('Y') }} | Info@digitals-labs.com
            </p>
        </div>
    </div>
</body>
</html>
