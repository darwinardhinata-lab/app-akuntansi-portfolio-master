<?php

return [
    'save' => 'Save settings',
    'title' => 'Customs Settings',
    'mode' => 'Integration mode',
    'internal' => 'Without H2H — internal system data',
    'h2h' => 'Use CEISA H2H — locked, not ready',
    'locked' => 'H2H activation is locked until the official API, payload, statuses, and company ownership are verified. Internal mode does not send requests to CEISA.',
    'auto_sync' => 'Automatically populate movement reports when a draft period is created',
    'scope' => 'Raw Material and Finished Goods movements use internal ledgers. Use re-sync to fetch changes. FINAL/UPLOADED periods are not modified. Inbound, Outbound, WIP, Capital Goods, and Reject reports still require input/import or reference data until validated mappings are available; customs registration numbers are never generated.',
    'saved' => 'Customs settings saved.',
    'sync' => 'Re-sync from system',
];