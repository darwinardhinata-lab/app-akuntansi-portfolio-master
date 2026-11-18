<?php

return [
    // Filament owns panel assets. Legacy Bootstrap layouts do not get Alpine injected.
    'inject_assets' => false,
    // Reconciliation proposers OR scoped material-preview readers may stage private temporary bytes.
    // Each consumer enforces its own authorization and file budget; AP rights are not inherited.
    'temporary_file_upload' => [
        'middleware' => [\App\Http\Middleware\RejectPanelUploads::class, 'throttle:30,1'],
        'rules' => ['required', 'file', 'max:10240'],
        'disk' => 'local',
    ],
];