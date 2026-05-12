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
require_once __DIR__ . '/routes/remote-content.php';

use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StreamableHttpServerTransport;

// MCP is an adapter layer for tools/agents. Core layout inheritance and
// rendering live in PoffConfig, Worktype, and viewer/render.
$runtime = mcpRuntimeContext();
$rootDir = $runtime['rootDir'];

$server = Server::make()
    ->withServerInfo('poff-mcp', '1.0.0')
    ->withTool(
        function (string $input) use ($rootDir) {
            $parts = explode('|', $input, 2);
            $file = trim($parts[0] ?? '');
            $style = trim($parts[1] ?? '');
            return handleWorkPrompt(mcpWorkPromptArgs($rootDir, $file, $style));
        },
        name: 'workprompt',
        description: 'Generate/override LightnCandy HBS work.layout model/template for a file. Input: "path|style prompt".'
    )
    ->withTool(
        function (array $args) use ($rootDir) {
            return handleCreate(mcpCreateArgs($rootDir, $args));
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
    ->withTool(
        function (array $args) use ($rootDir) {
            return handleExportContent([
                'rootDir' => $rootDir,
                'path' => $args['path'] ?? '',
                'baseUrl' => $args['baseUrl'] ?? '',
                'sourceId' => $args['sourceId'] ?? '',
            ]);
        },
        name: 'export_content',
        description: 'Export a folder as normalized JSON content for remote CMS nodes. Optional: path, baseUrl, sourceId.',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string', 'description' => 'Relative folder path inside the workspace root'],
                'baseUrl' => ['type' => 'string', 'description' => 'Optional public base URL for absolute viewer and asset links'],
                'sourceId' => ['type' => 'string', 'description' => 'Optional stable source id embedded in the export'],
            ],
        ]
    )
    ->withTool(
        function (array $args) use ($rootDir) {
            return handleImportRemote([
                'rootDir' => $rootDir,
                'path' => $args['path'] ?? '',
                'url' => $args['url'] ?? '',
                'sourceId' => $args['sourceId'] ?? '',
                'replace' => (bool) ($args['replace'] ?? false),
            ]);
        },
        name: 'import_remote',
        description: 'Import a remote export JSON feed into a local folder config as virtual entries. Required: url. Optional: path, sourceId, replace.',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string', 'description' => 'Relative local folder path that should receive imported entries'],
                'url' => ['type' => 'string', 'description' => 'Public export-content URL to fetch'],
                'sourceId' => ['type' => 'string', 'description' => 'Optional stable source id used for imported entries'],
                'replace' => ['type' => 'boolean', 'description' => 'Replace prior imported entries from the same source id before adding the new ones'],
            ],
            'required' => ['url'],
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
