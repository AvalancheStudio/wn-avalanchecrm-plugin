<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Cancelled</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f7f8fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .payment-result {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 48px;
            max-width: 480px;
            width: 90%;
            text-align: center;
        }
        .payment-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin-bottom: 20px;
        }
        .payment-icon--cancel {
            background: #fef2f2;
            color: #e74c3c;
        }
        .payment-result h1 {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        .payment-result p {
            color: #7f8c8d;
            font-size: 15px;
            line-height: 1.6;
        }
        .payment-back {
            display: inline-block;
            margin-top: 24px;
            padding: 10px 28px;
            background: #3498db;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            text-decoration: none;
            transition: background 0.2s;
        }
        .payment-back:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="payment-result">
        <div class="payment-icon payment-icon--cancel">&#10007;</div>
        <h1>Payment Cancelled</h1>
        <p>No payment was taken. You can try again at any time.</p>
        <p style="margin-top: 8px; font-size: 14px; color: #bdc3c7;">
            Invoice <?= e($invoice->invoice_number) ?>
        </p>

        <a href="javascript:history.back()" class="payment-back">Back to Invoice</a>
    </div>
</body>
</html>
