<!DOCTYPE html>
<html>
<head>
    <title>Reset Your Password</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background-color: #f9f9f9; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; padding: 30px 40px; border-radius: 8px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo img { max-height: 60px; }
        h2 { color: #1a1a1a; font-size: 24px; font-weight: 600; margin-bottom: 20px; }
        p { font-size: 16px; margin-bottom: 20px; }
        .button {
            display: inline-block;
            padding: 12px 25px;
            background-color: #003366;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
        }
        .footer {
            margin-top: 40px;
            font-size: 13px;
            color: #888;
            text-align: center;
        }
        .footer small { color: #aaa; font-size: 12px; word-break: break-all; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="{{ url('assets/admin/images/logo.png') }}" alt="Accretion Aviation Logo">
        </div>

        <h2>Password Reset Request</h2>

        <p>We received a request to reset the password for your Accretion Aviation account.</p>

        <p>If you initiated this request, please click the button below to securely reset your password:</p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $resetUrl }}" class="button">Reset Your Password</a>
        </div>

        <p>This secure link will remain active for the next 60 minutes.</p>

        <p>If you did not request a password reset, you may safely disregard this email — no changes have been made to your account.</p>

        <div class="footer">
            <p>Warm regards,<br><strong>The Accretion Aviation Team</strong></p>
            <p>If the button above does not work, please copy and paste the link below into your browser:</p>
            <small>{{ $resetUrl }}</small>
        </div>
    </div>
</body>
</html>
