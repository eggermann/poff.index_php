#!/usr/bin/env node
const fs = require('fs');
const path = require('path');
const { spawn, spawnSync } = require('child_process');

const rootDir = path.resolve(__dirname, '..');
const mampHtdocs = '/Applications/MAMP/htdocs';
const defaultMampPort = 8888; // default MAMP port; adjust here if needed

function logBrowseUrl(linkName) {
  if (!linkName) return;
  const url = `http://localhost:${defaultMampPort}/${linkName}/`;
  console.log(`[watch] Open in browser: ${url}`);
}

function readBuildConfig() {
  const result = spawnSync('php', [path.join(rootDir, 'build', 'export-config.php')], {
    encoding: 'utf8',
    cwd: rootDir,
  });

  if (result.status !== 0) {
    const stderr = result.stderr ? result.stderr.trim() : 'unknown error';
    throw new Error(`Could not read build config: ${stderr}`);
  }

  try {
    return JSON.parse(result.stdout);
  } catch (err) {
    throw new Error(`Failed to parse build config JSON: ${err.message}`);
  }
}

function ensureSymlink(outputDir) {
  const resolvedOutput = path.resolve(outputDir);
  const resolvedHtdocs = path.resolve(mampHtdocs);
  const linkName = path.basename(resolvedOutput);

  if (!fs.existsSync(resolvedHtdocs)) {
    console.warn(`[watch] MAMP htdocs not found at ${resolvedHtdocs}, skipping symlink.`);
    return null;
  }

  if (resolvedOutput.startsWith(resolvedHtdocs)) {
    console.log(`[watch] Output already inside ${resolvedHtdocs}, no symlink needed.`);
    logBrowseUrl(path.relative(resolvedHtdocs, resolvedOutput).split(path.sep)[0]);
    return null;
  }

  const linkPath = path.join(resolvedHtdocs, linkName);

  if (fs.existsSync(linkPath)) {
    const stat = fs.lstatSync(linkPath);

    if (!stat.isSymbolicLink()) {
      console.warn(`[watch] ${linkPath} exists and is not a symlink; leaving it alone.`);
      return linkName;
    }

    const currentTarget = path.resolve(path.dirname(linkPath), fs.readlinkSync(linkPath));
    if (currentTarget === resolvedOutput) {
      console.log(`[watch] Symlink already points to ${resolvedOutput}.`);
      logBrowseUrl(linkName);
      return linkName;
    }

    fs.unlinkSync(linkPath);
  }

  fs.symlinkSync(resolvedOutput, linkPath);
  console.log(`[watch] Created symlink ${linkPath} -> ${resolvedOutput}.`);
  logBrowseUrl(linkName);
  return linkName;
}

function refreshBrowser() {
  const scripts = [
    'tell application "Google Chrome" to if exists window 1 then tell the active tab of window 1 to reload',
    'tell application "Safari" to if exists document 1 then tell document 1 to do JavaScript "window.location.reload()"',
  ];

  scripts.forEach((script) => {
    const result = spawnSync('osascript', ['-e', script], { stdio: 'ignore' });
    if (result.status === 0) {
      console.log('[watch] Browser refresh requested.');
    }
  });
}

function startWatcher() {
  const config = readBuildConfig();
  const sourceDir = path.resolve(config.sourceDir);

  if (!fs.existsSync(sourceDir)) {
    throw new Error(`Source directory not found: ${sourceDir}`);
  }

  let debounceTimer = null;
  let building = false;
  let rerun = false;

  const queueBuild = (reason) => {
    if (debounceTimer) {
      clearTimeout(debounceTimer);
    }
    debounceTimer = setTimeout(() => runBuild(reason), 150);
  };

  const runBuild = (reason) => {
    if (building) {
      rerun = true;
      return;
    }

    building = true;
    console.log(`[watch] Running build (${reason || 'change'})...`);

    const proc = spawn('php', [path.join('build', 'build.php')], {
      cwd: rootDir,
      stdio: 'inherit',
    });

    proc.on('exit', (code) => {
      building = false;

      if (code === 0) {
        try {
          const freshConfig = readBuildConfig();
          ensureSymlink(freshConfig.outputDir);
          refreshBrowser();
        } catch (err) {
          console.error(`[watch] Post-build step failed: ${err.message}`);
        }
      } else {
        console.error(`[watch] Build failed with exit code ${code}.`);
      }

      if (rerun) {
        rerun = false;
        runBuild('queued change');
      }
    });
  };

  try {
    fs.watch(sourceDir, { recursive: true }, (_event, fileName) => {
      const label = fileName ? `${fileName.trim() || 'unknown file'}` : 'unknown file';
      queueBuild(`change in ${label}`);
    });
  } catch (err) {
    throw new Error(`Failed to start watcher: ${err.message}`);
  }

  console.log(`[watch] Watching ${sourceDir} for changes...`);
  runBuild('startup');
}

try {
  startWatcher();
} catch (err) {
  console.error(`[watch] ${err.message}`);
  process.exit(1);
}
