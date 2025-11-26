<?php
/**
 * Exposes the build configuration as JSON for helper scripts.
 */

$config = require __DIR__ . '/BuildConfig.php';

echo json_encode($config);
