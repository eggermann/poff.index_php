#!/usr/bin/env node
const path = require('path');
const { spawnSync } = require('child_process');

const rootDir = path.resolve(__dirname, '..');

function run(cmd, args, label) {
  const result = spawnSync(cmd, args, {
    cwd: rootDir,
    stdio: 'inherit',
  });
  if (result.status !== 0) {
    console.error(`[build] ${label} failed.`);
    process.exit(result.status || 1);
  }
}

run('node', [path.join('scripts', 'build-assets.js')], 'assets');
run('php', [path.join('build', 'build.php')], 'php');
