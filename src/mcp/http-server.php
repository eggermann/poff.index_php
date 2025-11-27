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

use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StreamableHttpServerTransport;
use PhpMcp\Schema\Tool\ToolInputString;

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
        description: 'Generate/override work.layout model/template for a file. Input: "path|style prompt".',
        inputSchema: new ToolInputString('File path relative to project root. Optional style after "|"')
    )
    ->build();

// Streamable HTTP transport (SSE by default). Host cannot be 0.0.0.0 per MCP spec.
$transport = new StreamableHttpServerTransport(
    host: '127.0.0.1',
    port: 8080,
    mcpPathPrefix: 'mcp',
    enableJsonResponse: true, // fast responses; switch to false for SSE streaming
    stateless: true
);

$server->listen($transport);
