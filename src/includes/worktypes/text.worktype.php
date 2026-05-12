<?php
return [
    'model' => [
        'type' => 'text',
        'syntax' => 'text/plain',
    ],
    'mimes' => [
        'text/*',
        'text/.*',
        'application/json',
        'application/xml',
        'application/javascript',
        'application/rtf',
    ],
    'template' => (string) file_get_contents(__DIR__ . '/templates/text.hbs'),
];
