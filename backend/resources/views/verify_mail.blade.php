<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Code</title>
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
            <h2 style="color: #333333; font-size: 18px; margin: 0 0 10px; text-align: center;">Your Verification Code</h2>
            <p style="color: #666666; font-size: 15px; line-height: 1.6; margin: 0 0 15px; text-align: center;">
                Please use the following code to reset your password:
            </p>

            <!-- Code Box -->
            <div style="text-align: center; margin: 20px 0;">
                <span style="display: inline-block; padding: 14px 28px; font-size: 28px; font-weight: bold; color: #ffffff; background-color: #0d9488; border-radius: 8px; letter-spacing: 4px;">{{ $code }}</span>
            </div>

            <p style="color: #9ca3af; font-size: 13px; text-align: center; margin: 0 0 20px;">
                This code expires in 30 minutes.
            </p>

            <!-- Divider -->
            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">

            <!-- Arabic Section -->
            <div dir="rtl" style="text-align: center;">
                <h2 style="color: #333333; font-size: 18px; margin: 0 0 10px;">رمز التحقق الخاص بك</h2>
                <p style="color: #666666; font-size: 15px; line-height: 1.6; margin: 0 0 10px;">
                    يرجى استخدام الرمز أعلاه لإعادة تعيين كلمة المرور.
                </p>
                <p style="color: #9ca3af; font-size: 13px; margin: 0;">
                    ينتهي صلاحية هذا الرمز خلال ٣٠ دقيقة.
                </p>
            </div>

            <!-- Warning -->
            <p style="color: #9ca3af; font-size: 12px; text-align: center; margin: 25px 0 0;">
                If you did not request this code, please ignore this message.<br>
                إذا لم تطلب هذا الرمز، يرجى تجاهل هذه الرسالة.
            </p>
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
