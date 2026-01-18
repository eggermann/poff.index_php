#!/usr/bin/env node
const fs = require('fs');
const path = require('path');
const esbuild = require('esbuild');
const sass = require('sass');

const rootDir = path.resolve(__dirname, '..');
const jsEntry = path.join(rootDir, 'src', 'assets', 'js', 'app.js');
const scssEntry = path.join(rootDir, 'src', 'assets', 'scss', 'main.scss');
const headerPath = path.join(rootDir, 'src', 'includes', 'header.built.php');
const scriptsPath = path.join(rootDir, 'src', 'includes', 'scripts.built.php');
const distDir = path.join(rootDir, 'build', 'assets');

function replaceBetween(content, startMarker, endMarker, replacement) {
  const startIndex = content.indexOf(startMarker);
  if (startIndex === -1) {
    throw new Error(`Missing start marker: ${startMarker}`);
  }
  const endIndex = content.indexOf(endMarker, startIndex + startMarker.length);
  if (endIndex === -1) {
    throw new Error(`Missing end marker: ${endMarker}`);
  }
  const before = content.slice(0, startIndex + startMarker.length);
  const after = content.slice(endIndex);
  return `${before}\n${replacement}\n${after}`;
}

function buildJs() {
  const result = esbuild.buildSync({
    entryPoints: [jsEntry],
    bundle: true,
    write: false,
    format: 'iife',
    platform: 'browser',
    target: ['es2018'],
    sourcemap: false,
    minify: false,
  });
  return result.outputFiles[0].text;
}

function buildCss() {
  const result = sass.compile(scssEntry, {
    style: 'expanded',
  });
  return result.css;
}

function writeIfChanged(targetPath, nextContent) {
  const current = fs.existsSync(targetPath) ? fs.readFileSync(targetPath, 'utf8') : null;
  if (current === nextContent) {
    return false;
  }
  fs.writeFileSync(targetPath, nextContent);
  return true;
}

function writeDist(js, css) {
  fs.mkdirSync(distDir, { recursive: true });
  writeIfChanged(path.join(distDir, 'app.js'), js);
  writeIfChanged(path.join(distDir, 'app.css'), css);
}

function updateHeader(css) {
  const header = fs.readFileSync(headerPath, 'utf8');
  const trimmed = css.trim();
  const updated = replaceBetween(header, '/* POFF_STYLE_START */', '/* POFF_STYLE_END */', trimmed);
  writeIfChanged(headerPath, updated);
}

function updateScripts(js) {
  const scripts = fs.readFileSync(scriptsPath, 'utf8');
  const safeJs = js.replace(/<\/script>/gi, '<\\/script>');
  const trimmed = safeJs.trim();
  const updated = replaceBetween(scripts, '/* POFF_SCRIPT_START */', '/* POFF_SCRIPT_END */', trimmed);
  writeIfChanged(scriptsPath, updated);
}

try {
  const js = buildJs();
  const css = buildCss();
  writeDist(js, css);
  updateHeader(css);
  updateScripts(js);
  console.log('[assets] Built CSS/JS and updated header/scripts placeholders.');
} catch (err) {
  console.error(`[assets] ${err.message}`);
  process.exit(1);
}
