<?php
return [
    'model' => [
        'type' => 'text',
        'syntax' => 'text/plain',
    ],
    'template' => (string) file_get_contents(__DIR__ . '/templates/text.tpl'),
];
