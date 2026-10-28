<?php

return [
    'instructions' => 'IDR journal reconciliation. For returns, explicitly select the return detail ID and quantity. Payment remains blocked until all return quantities and values are validly allocated. Evidence must reference a verifiable internal document, not credentials or public URLs.',
    'purchase_bill_id' => 'Purchase invoice ID', 'journal_id' => 'Source journal ID', 'amount' => 'Allocation amount',
    'reason' => 'Reason', 'evidence' => 'Internal evidence reference', 'purchase_return_detail_id' => 'Return detail ID (optional)',
    'quantity' => 'Return quantity', 'propose' => 'Propose allocation', 'decision_reason' => 'Decision reason',
    'approve' => 'Approve', 'cancel' => 'Cancel linked allocation',
];