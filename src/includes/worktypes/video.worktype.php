<?php
return [
    'model' => [
        'type' => 'video',
        'autoplay' => false,
        'loop' => false,
        'muted' => false,
        'poster' => null,
    ],
    'template' => (string) file_get_contents(__DIR__ . '/templates/video.tpl'),
];
