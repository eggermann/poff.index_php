<?php
return [
    'model' => [
        'type' => 'htaccess',
        'syntax' => 'apacheconf',
        'headerName' => 'Content-Security-Policy',
        'frameAncestors' => ["'self'"],
        'allowAll' => false,
        'extraDirectives' => '',
    ],
    'mimes' => [
        'text/plain',
        'text/*',
        'text/.*',
    ],
    'suffixes' => [
        '.htaccess',
    ],
    'description' => 'Apache access policy and iframe embedding rules.',
    'label' => '.htaccess',
    'preferred' => true,
    'template' => (string) file_get_contents(__DIR__ . '/templates/htaccess.hbs'),
];
