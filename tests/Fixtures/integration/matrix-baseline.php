<?php

// Synthetic baseline only: no supplier, bank, user, or production transaction data.
return [
    'two-line' => [
        ['account_code' => '111001', 'position' => 'DEBET', 'amount' => '1.250,50'],
        ['account_code' => '211001', 'position' => 'KREDIT', 'amount' => '1.250,50'],
    ],
    'multi-line' => [
        ['account_code' => '111001', 'position' => 'DEBET', 'amount' => '100,10'],
        ['account_code' => '611001', 'position' => 'DEBET', 'amount' => '25,20'],
        ['account_code' => '211001', 'position' => 'KREDIT', 'amount' => '125,30'],
    ],
    'helper' => [
        ['account_code' => '111001', 'helper_code' => 'BASELINE-PARTY', 'position' => 'DEBET', 'amount' => '50,25'],
        ['account_code' => '211001', 'helper_code' => 'BASELINE-PARTY', 'position' => 'KREDIT', 'amount' => '50,25'],
    ],
];