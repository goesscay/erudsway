<?php

return [

    /* Instructor earnings wait out the refund window before they can be paid (PLAN §7). */
    'hold_days' => (int) env('FINANCE_HOLD_DAYS', 30),

    /* Balances below this roll over to the next month (paise). */
    'payout_minimum' => (int) env('FINANCE_PAYOUT_MINIMUM', 50000),

    /*
    | TDS withheld from payouts. PLAN §7 assumes section 194-O (0.1% since 1 Oct 2024).
    | Because Erudsway invoices learners in its own name, the CA may instead treat
    | instructor shares as royalty/fees under 194J (10%). Confirm before the first payout.
    */
    'tds' => [
        'section' => env('FINANCE_TDS_SECTION', '194-O'),
        'rate_bps' => (int) env('FINANCE_TDS_RATE_BPS', 10),
    ],

    /* The person who generates a payout batch can't approve it (PLAN §9, recommended). */
    'maker_checker' => (bool) env('FINANCE_MAKER_CHECKER', true),
];
