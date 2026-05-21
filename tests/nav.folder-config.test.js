const { spawnSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const ROOT = path.join(__dirname, '..');

test('nav rendering uses folderPoffConfig instead of an undefined config variable', () => {
  const baseDir = path.join(ROOT, 'tests', 'poff-tests');
  const result = spawnSync('php', [path.join(ROOT, 'tests/php_render_nav.php'), baseDir, 'viewer-folder'], {
    cwd: ROOT,
    encoding: 'utf8',
  });

  expect(result.status).toBe(0);
  expect(result.stderr).toBe('');
  expect(result.stdout).toContain('Folder Preview');
  expect(result.stdout).not.toContain('> ./ viewer-folder<');
  expect(result.stdout).toContain('nav-link-up');
});

test('nav edit rendering includes fast hide actions for tree items', () => {
  const baseDir = path.join(ROOT, 'tests', 'poff-tests');
  const result = spawnSync('php', [path.join(ROOT, 'tests/php_render_nav.php'), baseDir, 'viewer-folder', 'edit'], {
    cwd: ROOT,
    encoding: 'utf8',
  });

  expect(result.status).toBe(0);
  expect(result.stderr).toBe('');
  expect(result.stdout).toContain('data-nav-action="toggle-visibility"');
  expect(result.stdout).toContain('data-tree-item="1"');
  expect(result.stdout).toContain('nav-row-action__icon');
  expect(result.stdout).toContain('Hide');
});

test('hidden tree items render an unhide toggle', () => {
  const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-nav-'));
  try {
    fs.writeFileSync(path.join(tempRoot, 'visible.txt'), 'hello', 'utf8');
    fs.writeFileSync(path.join(tempRoot, 'poff.config.json'), JSON.stringify({
      folderName: 'poff-nav',
      slug: 'poff-nav',
      title: 'Poff Nav',
      description: '',
      type: 'folder',
      tree: [
        {
          name: 'visible.txt',
          slug: 'visible-txt',
          type: 'file',
          path: 'visible.txt',
          visible: false,
        },
      ],
    }, null, 2), 'utf8');

    const result = spawnSync('php', [path.join(ROOT, 'tests/php_render_nav.php'), tempRoot, '', 'edit'], {
      cwd: ROOT,
      encoding: 'utf8',
    });

    expect(result.status).toBe(0);
    expect(result.stderr).toBe('');
    expect(result.stdout).toContain('Unhide');
    expect(result.stdout).toContain('data-nav-hidden="1"');
  } finally {
    fs.rmSync(tempRoot, { recursive: true, force: true });
  }
});

test('renders .htaccess as a hidden normal file entry in edit mode', () => {
  const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-nav-htaccess-'));
  try {
    fs.writeFileSync(path.join(tempRoot, 'visible.txt'), 'hello', 'utf8');
    fs.writeFileSync(path.join(tempRoot, '.htaccess'), 'RewriteEngine On', 'utf8');
    fs.writeFileSync(path.join(tempRoot, 'poff.config.json'), JSON.stringify({
      folderName: 'poff-nav',
      slug: 'poff-nav',
      title: 'Poff Nav',
      description: '',
      type: 'folder',
      tree: [
        {
          name: 'visible.txt',
          slug: 'visible-txt',
          type: 'file',
          path: 'visible.txt',
          visible: true,
        },
      ],
    }, null, 2), 'utf8');

    const result = spawnSync('php', [path.join(ROOT, 'tests/php_render_nav.php'), tempRoot, '', 'edit'], {
      cwd: ROOT,
      encoding: 'utf8',
    });

    expect(result.status).toBe(0);
    expect(result.stderr).toBe('');
    expect(result.stdout).toContain('data-file=".htaccess"');
    expect(result.stdout).toContain('data-hidden="true"');
    expect(result.stdout).toContain('nav-link-hidden');
    expect(result.stdout).not.toContain('Create embed policy');
  } finally {
    fs.rmSync(tempRoot, { recursive: true, force: true });
  }
});

test('pending external links stay hidden from visitors and show review state for authenticated editors', () => {
  const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-nav-pending-'));
  try {
    fs.writeFileSync(path.join(tempRoot, 'poff.config.json'), JSON.stringify({
      folderName: 'poff-nav',
      slug: 'poff-nav',
      title: 'Poff Nav',
      description: '',
      type: 'folder',
      tree: [
        {
          name: 'remote-link',
          slug: 'remote-link',
          type: 'link',
          path: 'remote-link',
          linkUrl: 'https://remote.example/index.php?view=1&path=portfolio',
          visible: false,
          externalSubmission: true,
          approvalStatus: 'pending',
        },
      ],
    }, null, 2), 'utf8');

    const publicResult = spawnSync('php', [path.join(ROOT, 'tests/php_render_nav.php'), tempRoot, ''], {
      cwd: ROOT,
      encoding: 'utf8',
    });
    expect(publicResult.status).toBe(0);
    expect(publicResult.stdout).not.toContain('remote-link');

    const editorResult = spawnSync('php', [path.join(ROOT, 'tests/php_render_nav.php'), tempRoot, ''], {
      cwd: ROOT,
      encoding: 'utf8',
      env: { ...process.env, POFF_TEST_AUTH_BYPASS: '1' },
    });
    expect(editorResult.status).toBe(0);
    expect(editorResult.stdout).toContain('nav-link-pending-approval');
    expect(editorResult.stdout).toContain('data-nav-action="review-external"');
    expect(editorResult.stdout).toContain('new</span>');
  } finally {
    fs.rmSync(tempRoot, { recursive: true, force: true });
  }
});
