#!/usr/bin/env php
<?php
declare(strict_types=1);

chdir(dirname(__DIR__, 2)); // project root

require __DIR__ . '/../../vendor/autoload.php';
@require_once __DIR__ . '/../includes/MediaType.php';
@require_once __DIR__ . '/../includes/Worktype.php';
@require_once __DIR__ . '/../includes/PoffConfig.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/routes/workprompt.php';
require_once __DIR__ . '/routes/create.php';

use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StreamableHttpServerTransport;

$rootDir = getcwd();

$server = Server::make()
    ->withServerInfo('poff-mcp', '1.0.0')
    ->withTool(
        function (string $input) use ($rootDir) {
            $parts = explode('|', $input, 2);
            $file = trim($parts[0] ?? '');
            $style = trim($parts[1] ?? '');
            return handleWorkPrompt([
                'rootDir' => $rootDir,
                'file' => $file,
                'style' => $style,
            ]);
        },
        name: 'workprompt',
        description: 'Generate/override work.layout model/template for a file. Input: "path|style prompt".'
    )
    ->withTool(
        function (array $args) use ($rootDir) {
            $poffDirOverride = getenv('POFF_BASE') ? rtrim(getenv('POFF_BASE'), '/\\') : null;
            return handleCreate([
                'rootDir' => $rootDir,
                'dest' => $args['dest'] ?? '',
                'path' => $args['path'] ?? null,
                'url' => $args['url'] ?? null,
                'poffDir' => $poffDirOverride,
            ]);
        },
        name: 'create',
        description: 'Create entry under /poff. Required: dest (relative inside /poff). Optional: path (copy) or url (download).',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'dest' => ['type' => 'string', 'description' => 'Relative destination inside /poff'],
                'path' => ['type' => 'string', 'description' => 'Relative path to copy into /poff/{dest}'],
                'url' => ['type' => 'string', 'description' => 'URL to download into /poff/{dest}'],
            ],
            'required' => ['dest'],
        ]
    )
    ->build();

// Streamable HTTP transport (SSE by default). Host cannot be 0.0.0.0 per MCP spec.
$jsonMode = getenv('MCP_JSON') === '1';
$stateless = getenv('MCP_STATELESS') === '1';

$transport = new StreamableHttpServerTransport(
    '127.0.0.1',
    8080,
    'mcp',
    sslContext: null,
    enableJsonResponse: $jsonMode,
    stateless: $jsonMode ? true : $stateless // JSON mode prefers stateless POST; SSE mode keeps false unless explicitly set
);

$server->listen($transport);
