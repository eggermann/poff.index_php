const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'src/includes/viewer.php'), 'utf8');
const standaloneRouter = fs.readFileSync(path.join(root, 'src/mcp/server.php'), 'utf8');
const builder = fs.readFileSync(path.join(root, 'build/build.php'), 'utf8');

function routeHandlers(text) {
  const routes = new Map();
  for (const match of text.matchAll(/case '([^']+)':([\s\S]*?)(?=\n\s*(?:case |default:))/g)) {
    const handler = match[2].match(/\b(handle[A-Z]\w*)\s*\(/);
    if (handler) routes.set(match[1], handler[1]);
  }
  return routes;
}

function routeBodies(text) {
  return new Map([...text.matchAll(/case '([^']+)':([\s\S]*?)(?=\n\s*(?:case |default:))/g)]
    .map((match) => [match[1], match[2].replace(/\$baseDir/g, '$rootDir').replace(/\s+/g, ' ').trim()]));
}

test('source and single-file build recipe dispatch the same named routes to the same handlers', () => {
  const sourceRoutes = [...routeHandlers(source)].sort();
  const buildRoutes = [...routeHandlers(builder)].sort();
  expect(sourceRoutes.length).toBeGreaterThan(0);
  expect(buildRoutes).toEqual(sourceRoutes);
});

test('shared route arguments and defaults stay aligned, apart from the documented create and style adapters', () => {
  const sourceRoutes = routeBodies(source);
  const buildRoutes = routeBodies(builder);
  for (const [route, body] of sourceRoutes) {
    if (route === 'create') continue;
    expect(buildRoutes.get(route)).toBe(body);
  }
  expect(sourceRoutes.get('create')).toContain("'poffDir' => $runtime['poffDir']");
  expect(buildRoutes.get('create')).toContain("'poffDir' => mcpPoffDirOverride()");
  expect(sourceRoutes.get('style')).toContain('$rootDir . DIRECTORY_SEPARATOR . \'poff.config.toon\'');
  expect(buildRoutes.get('style')).toContain("$rootDir . DIRECTORY_SEPARATOR . 'poff.config.toon'");
});

test('source and build keep their current distinct initialization and error handling', () => {
  expect(source).toContain('register_shutdown_function(static function (): void');
  expect(source).toContain('catch (Throwable $error)');
  expect(source).not.toContain('mcpEnsureConfig($configPath, $tree)');
  expect(standaloneRouter).toContain('mcpEnsureConfig($configPath, $tree)');
  expect(standaloneRouter).not.toContain('catch (Throwable $error)');
  expect(builder).toContain('register_shutdown_function(static function (): void');
  expect(builder).toContain('catch (Throwable $error)');
});
