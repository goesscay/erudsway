<?php

return [

    'currency' => 'INR',

    /*
    | Payment gateway. "razorpay" for real (test or live) keys; "fake" completes payments
    | locally without a gateway and is refused in production.
    */
    'gateway' => env('PAYMENT_GATEWAY', env('RAZORPAY_KEY_ID') ? 'razorpay' : 'fake'),

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'base_url' => 'https://api.razorpay.com/v1',
        'timeout' => 15,
    ],

    /*
    | GST. Listed prices include GST; tax is worked out backwards from the amount paid.
    | Rate and SAC code to be confirmed with the company's CA (PLAN §7).
    */
    'gst' => [
        'rate_bps' => (int) env('GST_RATE_BPS', 1800),
        'sac' => env('GST_SAC', '999293'),
    ],

    /* The seller, as printed on tax invoices. */
    'seller' => [
        'name' => env('SELLER_NAME', 'Erudsway Educorp LLP'),
        'gstin' => env('SELLER_GSTIN'),
        'state_code' => env('SELLER_STATE_CODE', '33'),
        'address' => env('SELLER_ADDRESS'),
        'email' => env('SELLER_EMAIL', 'support@erudsway.com'),
    ],

    'invoice_prefix' => env('INVOICE_PREFIX', 'ERW'),

    /* Decision D4. */
    'refunds' => [
        // Course purchases are final (policy of 1 Oct 2026): learners can't request refunds
        // themselves; staff refund genuine problems (double charge, no access) from Orders.
        'self_service' => false,
        'window_days' => 30,
        'auto_approve_below_percent' => 30,
    ],

    /*
    | Decision D2: the instructor's share of net revenue per sale channel, in basis points.
    | Snapshotted on each order item; Phase 6 turns it into ledger entries.
    */
    'commission' => [
        'organic' => 5000,
        'instructor_referral' => 9000,
        'platform_promoted' => 3000,
    ],

    'cart' => [
        'max_items' => 50,
    ],
];
