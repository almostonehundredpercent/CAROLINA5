<?php

return [
    'deposit_percentage' => (float) env('BOOKING_DEPOSIT_PERCENTAGE', 30),
    'minimum_deposit' => (float) env('BOOKING_MINIMUM_DEPOSIT', 500),
    'gcash_recipient_name' => env('GCASH_RECIPIENT_NAME'),
    'gcash_number' => env('GCASH_NUMBER'),
    'gcash_qr_url' => env('GCASH_QR_URL'),
];
