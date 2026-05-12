<?php
return [
    'model' => [
        'type' => 'video',
        'autoplay' => false,
        'loop' => false,
        'muted' => false,
        'poster' => null,
    ],
    'mimes' => [
        'video/*',
        'video/.*',
    ],
    'template' => (string) file_get_contents(__DIR__ . '/templates/video.hbs'),
];
