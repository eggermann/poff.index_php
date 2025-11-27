<?php
return [
    'model' => [
        'type' => 'pdf',
        'viewer' => 'embed',
    ],
    'template' => (string) file_get_contents(__DIR__ . '/templates/pdf.tpl'),
];
