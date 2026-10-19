<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RehabPlus password reset code</title>
</head>
<body style="margin:0; padding:24px; background:#f4f7fb; font-family:Arial, Helvetica, sans-serif; color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background:#ffffff; border-radius:16px; padding:32px;">
                    <tr>
                        <td>
                            <p style="margin:0 0 24px; font-size:22px; font-weight:800;">Rehab<span style="color:#14b8a6;">Plus</span></p>

                            <p style="margin:0 0 16px; font-size:15px;">Hi {{ $name !== '' ? $name : 'there' }},</p>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.5;">
                                Use this code to reset your RehabPlus dashboard password:
                            </p>

                            <p style="margin:0 0 16px; padding:16px; background:#f0fdfa; border-radius:12px; text-align:center; font-size:32px; font-weight:800; letter-spacing:8px;">
                                {{ $code }}
                            </p>

                            <p style="margin:0 0 16px; font-size:14px; color:#475569; line-height:1.5;">
                                The code expires in {{ $minutes }} minutes and can only be used once.
                                Never share it with anyone, including RehabPlus staff.
                            </p>

                            <p style="margin:0; font-size:14px; color:#475569; line-height:1.5;">
                                If you didn't ask to reset your password, you can ignore this email. Your password stays the same.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
