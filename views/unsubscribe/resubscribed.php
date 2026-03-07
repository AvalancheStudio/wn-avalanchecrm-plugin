<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Re-subscribed</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; margin: 0; padding: 40px 20px; }
        .container { max-width: 500px; margin: 60px auto; background: #fff; border-radius: 8px; padding: 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); text-align: center; }
        h1 { color: #333; font-size: 24px; margin-bottom: 16px; }
        p { color: #666; font-size: 16px; line-height: 1.6; }
        .icon { font-size: 48px; margin-bottom: 20px; }
        a { color: #4a90d9; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .small { font-size: 13px; color: #999; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">📬</div>
        <h1>Welcome back!</h1>
        <p>
            <strong><?= e($client->name) ?></strong>, you have been re-subscribed to our marketing emails.
        </p>
        <p class="small">
            You can unsubscribe at any time from your account settings or via the link in any marketing email.
        </p>
    </div>
</body>
</html>
