<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Converter.php';

return array_values(array_filter(
    Converter::definitions(),
    static fn(array $definition): bool => ($definition['engine'] ?? '') === 'imagemagick'
));
