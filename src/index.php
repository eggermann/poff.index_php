<?php
/**
 * File: index.php
 * Desc: Simple PHP file browser with sidebar navigation and an iframe preview pane.
 *       Displays folder metadata (title, description, link) from an optional
 *       poff.config.json file in each directory, rendered as a header above the
 *       iframe. The title becomes a hyperlink (if a link/url field exists) that
 *       loads in the preview pane.
 */

// Include required files
require_once __DIR__ . '/includes/functions.php';

// Initialize variables and process path
$baseDir = realpath(__DIR__);
$requestedRelativePath = isset($_GET['path']) ? trim($_GET['path'], "\\/") : '';
$currentAbsolutePath = realpath($baseDir . DIRECTORY_SEPARATOR . $requestedRelativePath);

// Validate and secure the path
if (!$currentAbsolutePath || strpos($currentAbsolutePath, $baseDir) !== 0 || !is_dir($currentAbsolutePath)) {
    $currentAbsolutePath = $baseDir;
    $currentRelativePath = '';
} else {
    $currentRelativePath = strlen($currentAbsolutePath) > strlen($baseDir)
        ? ltrim(substr($currentAbsolutePath, strlen($baseDir)), DIRECTORY_SEPARATOR)
        : '';
}

// Get current script name
$currentScript = basename(__FILE__);

// Load poff.config.json if it exists
$folderPoffConfig = null;
$poffConfigPath = $currentAbsolutePath . DIRECTORY_SEPARATOR . 'poff.config.json';
if (is_file($poffConfigPath) && is_readable($poffConfigPath)) {
    $configJson = file_get_contents($poffConfigPath);
    $decoded = json_decode($configJson, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $folderPoffConfig = $decoded;
    }
}

// Include template parts
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/scripts.php';

