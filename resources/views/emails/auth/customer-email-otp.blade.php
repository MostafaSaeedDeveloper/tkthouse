<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verification Code</title>
</head>
<body style="font-family:Arial,sans-serif;background:#f7f7f8;padding:24px;">
<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:10px;padding:24px;border:1px solid #ececf0;">
    <h2 style="margin:0 0 12px;color:#111;">Verify your email</h2>
    <p style="margin:0 0 14px;color:#555;line-height:1.6;">Use the code below to verify your TKTHouse account. The code is valid for 10 minutes.</p>
    <div style="font-size:32px;font-weight:700;letter-spacing:10px;color:#111;text-align:center;background:#f4f4f6;border-radius:8px;padding:14px 8px;margin:10px 0 14px;">{{ $otpCode }}</div>
    <p style="margin:0;color:#777;line-height:1.6;">If you did not create this account, you can ignore this message.</p>
</div>
</body>
</html>
