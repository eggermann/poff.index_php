<?php
return [
    'model' => [
        'type' => 'folder',
        'layout' => [
            'mode' => 'default',
            'name' => 'default-layout',
            'engine' => 'lightncandy',
            'section' => 'works',
        ],
    ],
    'template' => (string) file_get_contents(__DIR__ . '/templates/folder.hbs'),
];
