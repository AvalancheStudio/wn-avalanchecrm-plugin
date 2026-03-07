<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Successful</title>
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
        .payment-icon--success {
            background: #e8f8f0;
            color: #27ae60;
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
            margin-bottom: 6px;
        }
        .payment-detail {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
            margin: 20px 0;
        }
        .payment-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 14px;
        }
        .payment-detail-label { color: #95a5a6; }
        .payment-detail-value { color: #2c3e50; font-weight: 600; }
        .payment-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            background: #e8f8f0;
            color: #27ae60;
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
        <div class="payment-icon payment-icon--success">&#10003;</div>
        <h1>Payment Successful</h1>
        <p>Thank you! Your payment has been received.</p>

        <div class="payment-detail">
            <div class="payment-detail-row">
                <span class="payment-detail-label">Invoice</span>
                <span class="payment-detail-value"><?= e($invoice->invoice_number) ?></span>
            </div>
            <div class="payment-detail-row">
                <span class="payment-detail-label">Amount</span>
                <span class="payment-detail-value"><?= e($currencySymbol) ?><?= number_format($invoice->amount, 2) ?></span>
            </div>
            <div class="payment-detail-row">
                <span class="payment-detail-label">Method</span>
                <span class="payment-detail-value"><?= e(ucfirst($method)) ?></span>
            </div>
            <div class="payment-detail-row">
                <span class="payment-detail-label">Status</span>
                <span class="payment-badge"><?= $invoice->status === 'paid' ? 'Paid' : 'Processing' ?></span>
            </div>
        </div>

        <?php if ($invoice->status !== 'paid'): ?>
            <p style="font-size: 13px; color: #95a5a6;">
                Your payment is being processed and will be confirmed shortly.
            </p>
        <?php endif; ?>

        <a href="javascript:history.back()" class="payment-back">Back to Invoices</a>
    </div>
</body>
</html>
