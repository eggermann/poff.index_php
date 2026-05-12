<?php
return [
    'model' => [
        'type' => 'pdf',
        'viewer' => 'embed',
    ],
    'mimes' => [
        'application/pdf',
        'application/x-pdf',
    ],
    'template' => (string) file_get_contents(__DIR__ . '/templates/pdf.hbs'),
];
