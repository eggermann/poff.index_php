<?php
return [
    'model' => [
        'type' => 'image',
        'fit' => 'contain',
        'background' => '#000',
        'caption' => '',
    ],
    'template' => (string) file_get_contents(__DIR__ . '/templates/image.tpl'),
];
