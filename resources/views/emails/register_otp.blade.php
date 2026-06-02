<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - TKT House</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:40px 0;">
        <tr>
            <td align="center">
                <table width="560" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background-color:#1a1a2e;padding:28px 40px;text-align:center;">
                            <h1 style="margin:0;color:#ffffff;font-size:22px;letter-spacing:1px;">TKT House</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:40px 40px 32px;text-align:center;">
                            <h2 style="margin:0 0 16px;color:#1a1a2e;font-size:20px;">Verify Your Email Address</h2>
                            <p style="margin:0 0 32px;color:#555555;font-size:15px;line-height:1.6;">
                                Use the code below to complete your registration. This code is valid for <strong>10 minutes</strong>.
                            </p>
                            <div style="display:inline-block;background-color:#f0f4ff;border:2px dashed #4a6cf7;border-radius:8px;padding:20px 40px;margin-bottom:32px;">
                                <span style="font-size:36px;font-weight:bold;letter-spacing:10px;color:#1a1a2e;font-family:'Courier New',monospace;">{{ $otpCode }}</span>
                            </div>
                            <p style="margin:0;color:#888888;font-size:13px;line-height:1.6;">
                                If you did not request this code, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f9f9f9;padding:20px 40px;text-align:center;border-top:1px solid #eeeeee;">
                            <p style="margin:0;color:#aaaaaa;font-size:12px;">
                                &copy; {{ date('Y') }} TKT House. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
