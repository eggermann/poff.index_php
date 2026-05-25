#!/usr/bin/env node
const fs = require('fs');
const path = require('path');
const { spawn, spawnSync } = require('child_process');

const rootDir = path.resolve(__dirname, '..');
const mampHtdocs = '/Applications/MAMP/htdocs';
const defaultMampPort = 8888; // default MAMP port; adjust here if needed
const standaloneCopyTargets = ['MAUSMAUS'];
const bundledLayoutAsset = path.join(rootDir, 'src', 'includes', 'worktypes', 'templates', 'layout', 'default', 'poff.profile.jpg');

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

function ensureStandaloneCopies(outputDir, outputFile) {
  const resolvedHtdocs = path.resolve(mampHtdocs);
  if (!fs.existsSync(resolvedHtdocs)) {
    console.warn(`[watch] MAMP htdocs not found at ${resolvedHtdocs}, skipping standalone copies.`);
    return;
  }

  const sourceFile = path.join(path.resolve(outputDir), outputFile);
  if (!fs.existsSync(sourceFile)) {
    console.warn(`[watch] Built file not found at ${sourceFile}, skipping standalone copies.`);
    return;
  }

  standaloneCopyTargets.forEach((targetName) => {
    const targetDir = path.join(resolvedHtdocs, targetName);
    const targetFile = path.join(targetDir, outputFile);
    fs.mkdirSync(targetDir, { recursive: true });
    fs.copyFileSync(sourceFile, targetFile);
    removeGeneratedEntrypointsFromSubdirectories(targetDir, outputFile);
    if (fs.existsSync(bundledLayoutAsset)) {
      copyFileToLayoutDirectories(bundledLayoutAsset, targetDir);
    }
    console.log(`[watch] Copied standalone build ${sourceFile} -> ${targetFile}.`);
    logBrowseUrl(targetName);
  });
}

function findAllDirectories(rootDir) {
  const dirs = [];
  const stack = [rootDir];

  while (stack.length > 0) {
    const currentDir = stack.pop();
    const entries = fs.readdirSync(currentDir, { withFileTypes: true });

    entries.forEach((entry) => {
      if (!entry.isDirectory()) {
        return;
      }

      const fullPath = path.join(currentDir, entry.name);
      if (
        entry.name.startsWith('.')
        || entry.name === 'build'
        || entry.name === 'vendor'
        || entry.name === 'node_modules'
      ) {
        return;
      }

      dirs.push(fullPath);
      stack.push(fullPath);
    });
  }

  return dirs;
}

function copyFileToLayoutDirectories(sourceFile, targetRoot, layoutDirName = '.layout') {
  [targetRoot, ...findAllDirectories(targetRoot)].forEach((dir) => {
    const destinationDir = path.join(dir, layoutDirName);
    fs.mkdirSync(destinationDir, { recursive: true });
    fs.copyFileSync(sourceFile, path.join(destinationDir, path.basename(sourceFile)));
  });
}

function removeGeneratedEntrypointsFromSubdirectories(targetRoot, fileName = 'index.php') {
  [targetRoot, ...findAllDirectories(targetRoot)].forEach((dir) => {
    const candidate = path.join(dir, fileName);
    if (dir === targetRoot || !fs.existsSync(candidate)) {
      return;
    }

    const stat = fs.statSync(candidate);
    if (!stat.isFile()) {
      return;
    }

    fs.unlinkSync(candidate);
    console.log(`[watch] Removed subdirectory entrypoint: ${candidate}`);
  });
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

    const proc = spawn('npm', ['run', 'build'], {
      cwd: rootDir,
      stdio: 'inherit',
    });

    proc.on('exit', (code) => {
      building = false;

      if (code === 0) {
        try {
          const freshConfig = readBuildConfig();
          ensureSymlink(freshConfig.outputDir);
          ensureStandaloneCopies(freshConfig.outputDir, freshConfig.outputFile);
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
    const ignoredFiles = new Set(['includes/header.built.php', 'includes/scripts.built.php']);
    fs.watch(sourceDir, { recursive: true }, (_event, fileName) => {
      const normalized = fileName ? fileName.replace(/\\/g, '/') : '';
      if (normalized && ignoredFiles.has(normalized)) {
        return;
      }
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
