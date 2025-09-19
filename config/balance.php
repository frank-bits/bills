<?php

return [
    // Core IDs (use numeric IDs; update as needed)
    'check_account_type_id' => 3, // 53 Check
    'savings_account_type_id' => env('SAVINGS_TYPE_ID', 13), // 53 Savings (set in .env if you want to exclude)
    'credit_card_type_id' => env('CREDIT_CARD_TYPE_ID', 8),
    'income_type_id' => env('INCOME_TYPE_ID', 3),

    // Specific overrides: when account_type_id => [account_id, ...] should be incremented
    'increment_overrides' => [
        7 => [16, 15],
    ],
];
