<?php
return [
    'model' => [
        'type' => 'converter',
        'name' => 'Converter',
        'accepts' => ['image/tiff', 'image/*'],
        'outputs' => ['image/webp', 'image/jpeg', 'image/png'],
        'engine' => 'imagemagick',
        'templateFolder' => '.layout/converters/default',
        'defaults' => [
            'quality' => 82,
            'format' => 'webp',
            'resize' => null,
            'stripMetadata' => true,
            'background' => 'white',
        ],
        'ui' => [
            'quality' => [
                'type' => 'select',
                'label' => 'Quality',
                'options' => ['preview', 'default', 'archival', 'small-web'],
            ],
            'format' => [
                'type' => 'select',
                'label' => 'Output format',
                'options' => ['webp', 'jpeg', 'png'],
            ],
        ],
    ],
    'mimes' => [],
    'template' => (string) file_get_contents(__DIR__ . '/templates/converter/default/template.hbs'),
];
