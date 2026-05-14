<?php

return [
    'checklist_templates' => [
        'graduation-clearance' => [
            'transcript',
            'clearance-form',
            'diploma-request',
        ],
        'transfer-credentials' => [
            'transcript',
            'honorable-dismissal',
            'request-letter',
        ],
        'records-verification' => [
            'request-letter',
            'valid-id',
        ],
    ],

    'upload_signature_map' => [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'doc' => ['application/msword'],
        'docx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ],
];
