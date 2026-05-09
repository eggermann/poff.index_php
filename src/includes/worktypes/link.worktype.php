<?php
return [
    'model' => [
        'type' => 'link',
        'target' => '_blank',
    ],
    'mimes' => [
        'text/uri-list',
        'application/internet-shortcut',
    ],
    'template' => (string) file_get_contents(__DIR__ . '/templates/link.hbs'),
];
