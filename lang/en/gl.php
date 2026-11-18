<?php
return [
    'title' => 'General Ledger Trace', 'scope' => 'Read-only, single company',
    'precision' => 'Base amounts use 2 decimal places; foreign snapshots are not summed across currencies.',
    'start' => 'From', 'end' => 'Through', 'apply' => 'Apply period', 'account' => 'Account',
    'opening' => 'Opening balance', 'debit' => 'Debit', 'credit' => 'Credit', 'closing' => 'Closing balance',
    'transactions' => 'Balance transactions', 'page_opening' => 'Page brought forward',
    'date' => 'Date', 'journal' => 'Journal detail', 'reference' => 'Recorded reference', 'running' => 'Running balance',
    'previous' => 'Previous', 'next' => 'Next', 'description' => 'Description / helper code',
    'fx' => 'Currency / transaction amount / snapshot rate', 'total' => 'Journal total', 'source' => 'Source document',
    'unlinked' => 'No supported, accessible source-document relation. A text reference is not document attribution.',
    'missing_account' => 'Account master unavailable',
];