<?php

return [
    // Filament owns panel assets. Legacy Bootstrap layouts do not get Alpine injected.
    'inject_assets' => false,
    // No uploads enabled until private attachment authorization has acceptance evidence.
    'temporary_file_upload' => [
        'middleware' => [\App\Http\Middleware\RejectPanelUploads::class, 'throttle:30,1'],
        'rules' => ['required', 'file', 'max:10240'],
        'disk' => 'local',
    ],
];