#!/usr/bin/env node
'use strict';

const crypto = require('node:crypto');
const childProcess = require('node:child_process');
const fs = require('node:fs');
const http = require('node:http');
const https = require('node:https');
const net = require('node:net');
const path = require('node:path');
const { URL } = require('node:url');

const projectRoot = path.resolve(__dirname, '..');
const env = loadEnv(projectRoot);
const config = loadConfig(env);
const sessions = new Map();
const oauthStates = new Map();
const deviceLogins = new Map();

setInterval(() => {
  const now = Date.now();
  for (const [key, value] of sessions.entries()) {
    if (value.expiresAt <= now) {
      sessions.delete(key);
    }
  }
  for (const [key, value] of oauthStates.entries()) {
    if (value.expiresAt <= now) {
      oauthStates.delete(key);
    }
  }
  for (const [key, value] of deviceLogins.entries()) {
    if (value.expiresAt <= now) {
      deviceLogins.delete(key);
    }
  }
}, 60_000).unref();

const server = http.createServer((req, res) => {
  handleRequest(req, res).catch((error) => {
    console.error('[proxy] request failed:', error);
    if (!res.headersSent) {
      res.statusCode = Number.isInteger(error.statusCode) ? error.statusCode : 500;
      res.setHeader('content-type', 'text/plain; charset=utf-8');
    }
    res.end(`${error.publicMessage || error.message || 'Internal proxy error'}\n`);
  });
});

server.on('upgrade', (req, socket, head) => {
  handleUpgrade(req, socket, head).catch((error) => {
    console.error('[proxy] upgrade failed:', error);
    try {
      socket.destroy();
    } catch {}
  });
});

server.listen(config.port, config.host, () => {
  console.log(`[proxy] listening on http://${config.host}:${config.port}`);
  console.log(`[proxy] upstream: ${config.upstreamUrl}`);
});

function loadEnv(rootDir) {
  const candidates = [
    path.join(rootDir, '.env'),
    path.join(__dirname, '.env'),
  ];

  const fileEnv = {};
  for (const candidate of candidates) {
    if (!fs.existsSync(candidate)) {
      continue;
    }

    const lines = fs.readFileSync(candidate, 'utf8').split(/\r?\n/);
    for (const rawLine of lines) {
      const line = rawLine.trim();
      if (!line || line.startsWith('#')) {
        continue;
      }

      const separatorIndex = line.search(/[=:]/);
      if (separatorIndex <= 0) {
        continue;
      }

      const key = line.slice(0, separatorIndex).trim();
      const value = line.slice(separatorIndex + 1).trim().replace(/^['"]|['"]$/g, '');
      if (key && !(key in fileEnv)) {
        fileEnv[key] = value;
      }
    }
  }

  return { ...fileEnv, ...process.env };
}

function envValue(envObject, keys, fallback = '') {
  const keyList = Array.isArray(keys) ? keys : [keys];
  for (const key of keyList) {
    const value = envObject[key];
    if (typeof value === 'string' && value.trim() !== '') {
      return value.trim();
    }
  }
  return fallback;
}

function parseList(value) {
  return String(value || '')
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);
}

function loadConfig(envObject) {
  const upstreamUrl = envValue(
    envObject,
    ['PROXY_UPSTREAM_URL', 'UPSTREAM_URL', 'TARGET_URL'],
    'http://127.0.0.1:8080'
  );
  const authMode = envValue(envObject, ['GITHUB_AUTH_MODE', 'AUTH_MODE'], 'device').toLowerCase();
  const githubClientId = envValue(envObject, ['GITHUB_CLIENT_ID', 'OAUTH_GITHUB_CLIENT_ID']);
  const githubClientSecret = envValue(envObject, ['GITHUB_CLIENT_SECRET', 'OAUTH_GITHUB_CLIENT_SECRET']);
  const allowedUsers = parseList(envValue(envObject, ['GITHUB_ALLOWED_USERS', 'PROXY_ALLOWED_USERS']));
  const allowedOrgs = parseList(envValue(envObject, ['GITHUB_ALLOWED_ORGS', 'PROXY_ALLOWED_ORGS']));
  const basicAuthUser = envValue(envObject, ['BASIC_AUTH_USER', 'PROXY_BASIC_AUTH_USER']);
  const basicAuthPassword = envValue(envObject, ['BASIC_AUTH_PASSWORD', 'PROXY_BASIC_AUTH_PASSWORD']);
  const workspaceRoot = envValue(envObject, ['CHAT_WORKSPACE_ROOT', 'WORKSPACE_ROOT'], projectRoot);
  const ghCopilotBin = envValue(envObject, ['GH_COPILOT_BIN', 'GH_BIN'], '/home/eggman/bin/gh');
  const copilotBin = envValue(envObject, ['COPILOT_BIN'], '');
  const port = Number.parseInt(envValue(envObject, ['PORT'], '3000'), 10) || 3000;
  const host = envValue(envObject, ['BIND_HOST', 'HOST'], '0.0.0.0');
  const callbackPath = envValue(envObject, ['GITHUB_CALLBACK_PATH', 'OAUTH_CALLBACK_PATH'], '/oauth/callback');
  const publicBaseUrl = envValue(envObject, ['PUBLIC_BASE_URL', 'PUBLIC_URL', 'BASE_URL']);
  const sessionTtlMs = Number.parseInt(envValue(envObject, ['SESSION_TTL_MS'], String(1000 * 60 * 60 * 12)), 10)
    || 1000 * 60 * 60 * 12;
  const execTimeoutMs = Number.parseInt(envValue(envObject, ['CHAT_EXEC_TIMEOUT_MS'], '30000'), 10) || 30000;
  const maxOutputBytes = Number.parseInt(envValue(envObject, ['CHAT_MAX_OUTPUT_BYTES'], String(256 * 1024)), 10)
    || 256 * 1024;
  const maxWriteBytes = Number.parseInt(envValue(envObject, ['CHAT_MAX_WRITE_BYTES'], String(512 * 1024)), 10)
    || 512 * 1024;
  const scopeParts = ['read:user'];
  if (allowedOrgs.length > 0) {
    scopeParts.push('read:org');
  }

  if (authMode === 'basic') {
    if (!basicAuthUser || !basicAuthPassword) {
      throw new Error('Set BASIC_AUTH_USER and BASIC_AUTH_PASSWORD in .env for basic auth mode.');
    }
  } else {
    if (!githubClientId || !githubClientSecret) {
      throw new Error('Set GITHUB_CLIENT_ID and GITHUB_CLIENT_SECRET in .env.');
    }
    if (allowedUsers.length === 0 && allowedOrgs.length === 0) {
      throw new Error('Set GITHUB_ALLOWED_USERS or GITHUB_ALLOWED_ORGS in .env.');
    }
  }

  return {
    upstreamUrl,
    authMode,
    githubClientId,
    githubClientSecret,
    allowedUsers,
    allowedOrgs,
    basicAuthUser,
    basicAuthPassword,
    workspaceRoot: path.resolve(workspaceRoot),
    ghCopilotBin,
    copilotBin: copilotBin ? path.resolve(copilotBin) : '',
    port,
    host,
    callbackPath,
    publicBaseUrl,
    sessionTtlMs,
    execTimeoutMs,
    maxOutputBytes,
    maxWriteBytes,
    scope: scopeParts.join(' '),
    sessionCookieName: envValue(envObject, ['SESSION_COOKIE_NAME'], 'pageoff_proxy_session'),
    stateCookieName: envValue(envObject, ['STATE_COOKIE_NAME'], 'pageoff_proxy_state'),
  };
}

function isSecureRequest(req) {
  const proto = String(req.headers['x-forwarded-proto'] || '').toLowerCase();
  if (proto === 'https') {
    return true;
  }
  if (config.publicBaseUrl) {
    return config.publicBaseUrl.startsWith('https://');
  }
  return false;
}

function cookieAttributes(req, expires = '') {
  const attrs = ['Path=/', 'HttpOnly', 'SameSite=Lax'];
  if (isSecureRequest(req)) {
    attrs.push('Secure');
  }
  if (expires) {
    attrs.push(`Expires=${expires}`);
  }
  return attrs.join('; ');
}

function makeSetCookie(req, name, value, maxAgeSeconds) {
  const attrs = [cookieAttributes(req)];
  if (typeof maxAgeSeconds === 'number') {
    attrs.push(`Max-Age=${Math.max(0, Math.floor(maxAgeSeconds))}`);
  }
  return `${name}=${encodeURIComponent(value)}; ${attrs.join('; ')}`;
}

function clearCookie(req, name) {
  return `${name}=; ${cookieAttributes(req, 'Thu, 01 Jan 1970 00:00:00 GMT')}; Max-Age=0`;
}

function timingSafeEqualString(left, right) {
  const leftBuffer = Buffer.from(String(left || ''), 'utf8');
  const rightBuffer = Buffer.from(String(right || ''), 'utf8');
  if (leftBuffer.length !== rightBuffer.length) {
    return false;
  }
  return crypto.timingSafeEqual(leftBuffer, rightBuffer);
}

function basicAuthUserForRequest(req) {
  const authorization = String(req.headers.authorization || '');
  if (!authorization.startsWith('Basic ')) {
    return null;
  }
  try {
    const decoded = Buffer.from(authorization.slice(6), 'base64').toString('utf8');
    const separatorIndex = decoded.indexOf(':');
    if (separatorIndex === -1) {
      return null;
    }
    const username = decoded.slice(0, separatorIndex);
    const password = decoded.slice(separatorIndex + 1);
    if (
      timingSafeEqualString(username, config.basicAuthUser) &&
      timingSafeEqualString(password, config.basicAuthPassword)
    ) {
      return {
        login: username,
        id: username,
        name: username,
      };
    }
  } catch {
    return null;
  }
  return null;
}

function requireBasicAuth(res) {
  res.statusCode = 401;
  res.setHeader('WWW-Authenticate', 'Basic realm="pageoff proxy", charset="UTF-8"');
  res.setHeader('Content-Type', 'text/plain; charset=utf-8');
  res.end('Authentication required.\n');
}

function parseCookies(header) {
  const result = {};
  if (!header) {
    return result;
  }
  const parts = String(header).split(/;\s*/);
  for (const part of parts) {
    const eq = part.indexOf('=');
    if (eq === -1) {
      continue;
    }
    const key = part.slice(0, eq).trim();
    const value = part.slice(eq + 1).trim();
    if (key) {
      try {
        result[key] = decodeURIComponent(value);
      } catch {
        result[key] = value;
      }
    }
  }
  return result;
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    req.on('data', (chunk) => chunks.push(chunk));
    req.on('end', () => resolve(Buffer.concat(chunks)));
    req.on('error', reject);
  });
}

function redirect(res, location, statusCode = 302) {
  res.statusCode = statusCode;
  res.setHeader('Location', location);
  res.end();
}

function html(res, statusCode, body) {
  res.statusCode = statusCode;
  res.setHeader('Content-Type', 'text/html; charset=utf-8');
  res.end(body);
}

function text(res, statusCode, body) {
  res.statusCode = statusCode;
  res.setHeader('Content-Type', 'text/plain; charset=utf-8');
  res.end(body);
}

function buildBaseUrl(req) {
  if (config.publicBaseUrl) {
    return config.publicBaseUrl.replace(/\/+$/, '');
  }
  const proto = String(req.headers['x-forwarded-proto'] || (req.socket.encrypted ? 'https' : 'http')).split(',')[0].trim() || 'http';
  const host = String(req.headers['x-forwarded-host'] || req.headers.host || 'localhost').split(',')[0].trim();
  return `${proto}://${host}`;
}

function buildCallbackUrl(req) {
  return new URL(config.callbackPath, `${buildBaseUrl(req)}/`).toString();
}

function loginUrl(req, state) {
  const url = new URL('https://github.com/login/oauth/authorize');
  url.searchParams.set('client_id', config.githubClientId);
  url.searchParams.set('scope', config.scope);
  url.searchParams.set('state', state);
  return url.toString();
}

async function requestDeviceCode() {
  const params = new URLSearchParams();
  params.set('client_id', config.githubClientId);
  params.set('scope', config.scope);

  const response = await fetch('https://github.com/login/device/code', {
    method: 'POST',
    body: params,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });
  const payload = await readJsonOrForm(response);
  if (!response.ok) {
    throw new Error(`GitHub device code request failed (${response.status}): ${formatOAuthError(payload)}`);
  }
  if (!payload.device_code || !payload.user_code || !payload.verification_uri) {
    throw new Error('GitHub did not return a device authorization payload.');
  }
  return payload;
}

async function pollDeviceToken(deviceCode) {
  const params = new URLSearchParams();
  params.set('client_id', config.githubClientId);
  params.set('device_code', deviceCode);
  params.set('grant_type', 'urn:ietf:params:oauth:grant-type:device_code');

  const response = await fetch('https://github.com/login/oauth/access_token', {
    method: 'POST',
    body: params,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });
  const payload = await readJsonOrForm(response);
  if (payload.access_token) {
    return { status: 'approved', accessToken: payload.access_token };
  }
  if (payload.error === 'authorization_pending') {
    return { status: 'pending' };
  }
  if (payload.error === 'slow_down') {
    return { status: 'slow_down' };
  }
  if (payload.error === 'expired_token') {
    return { status: 'expired' };
  }
  if (!response.ok) {
    throw new Error(`GitHub device token request failed (${response.status}): ${formatOAuthError(payload)}`);
  }
  return { status: 'pending' };
}

function sessionForRequest(req) {
  const cookies = parseCookies(req.headers.cookie || '');
  const sessionId = cookies[config.sessionCookieName];
  if (!sessionId) {
    return null;
  }
  const session = sessions.get(sessionId);
  if (!session || session.expiresAt <= Date.now()) {
    sessions.delete(sessionId);
    return null;
  }
  session.expiresAt = Date.now() + config.sessionTtlMs;
  return { sessionId, ...session };
}

function isAllowed(user, orgMemberships = []) {
  if (config.allowedUsers.length > 0) {
    const login = String(user.login || '').toLowerCase();
    if (!config.allowedUsers.some((candidate) => candidate.toLowerCase() === login)) {
      return false;
    }
  }

  if (config.allowedOrgs.length > 0) {
    const orgSet = new Set(orgMemberships.map((org) => String(org.login || org.name || '').toLowerCase()));
    if (!config.allowedOrgs.some((org) => orgSet.has(org.toLowerCase()))) {
      return false;
    }
  }

  return true;
}

async function fetchJson(url, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: {
      'User-Agent': 'pageoff-proxy',
      Accept: 'application/json',
      ...(options.headers || {}),
    },
  });
  const contentType = response.headers.get('content-type') || '';
  const payload = contentType.includes('application/json')
    ? await response.json()
    : await response.text();
  if (!response.ok) {
    const message = typeof payload === 'string'
      ? payload
      : JSON.stringify(payload);
    throw new Error(`Request failed (${response.status}): ${message}`);
  }
  return payload;
}

async function readJsonOrForm(response) {
  const contentType = response.headers.get('content-type') || '';
  const textBody = await response.text();
  if (!textBody) {
    return {};
  }
  if (contentType.includes('application/json')) {
    try {
      return JSON.parse(textBody);
    } catch {
      return {};
    }
  }
  try {
    return Object.fromEntries(new URLSearchParams(textBody));
  } catch {
    return {};
  }
}

function formatOAuthError(payload) {
  if (!payload || typeof payload !== 'object') {
    return 'unknown error';
  }
  if (payload.error_description) {
    return `${payload.error}: ${payload.error_description}`;
  }
  if (payload.error) {
    return String(payload.error);
  }
  return JSON.stringify(payload);
}

function json(res, statusCode, body) {
  res.statusCode = statusCode;
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.end(JSON.stringify(body));
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

async function exchangeCodeForToken(code) {
  const params = new URLSearchParams();
  params.set('client_id', config.githubClientId);
  params.set('client_secret', config.githubClientSecret);
  params.set('code', code);

  const payload = await fetchJson('https://github.com/login/oauth/access_token', {
    method: 'POST',
    body: params,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });

  if (!payload.access_token) {
    throw new Error('GitHub did not return an access token.');
  }

  return payload.access_token;
}

async function fetchGithubUser(accessToken) {
  return fetchJson('https://api.github.com/user', {
    headers: {
      Authorization: `Bearer ${accessToken}`,
    },
  });
}

async function fetchGithubOrgs(accessToken) {
  return fetchJson('https://api.github.com/user/orgs', {
    headers: {
      Authorization: `Bearer ${accessToken}`,
    },
  });
}

async function handleRequest(req, res) {
  const requestUrl = new URL(req.url, 'http://localhost');

  if (requestUrl.pathname === '/healthz') {
    text(res, 200, 'ok\n');
    return;
  }

  if (requestUrl.pathname === '/chat') {
    const user = await authenticatedUserForRequest(req, res, requestUrl);
    if (!user) {
      return;
    }
    html(res, 200, renderChatPage(user));
    return;
  }

  if (requestUrl.pathname.startsWith('/api/chat/')) {
    const user = await authenticatedUserForRequest(req, res, requestUrl);
    if (!user) {
      return;
    }
    await handleChatApi(req, res, requestUrl, user);
    return;
  }

  if (config.authMode === 'basic') {
    const basicUser = basicAuthUserForRequest(req);
    if (!basicUser) {
      requireBasicAuth(res);
      return;
    }
    await proxyRequest(req, res, basicUser);
    return;
  }

  if (requestUrl.pathname === '/logout') {
    const session = sessionForRequest(req);
    if (session) {
      sessions.delete(session.sessionId);
    }
    res.setHeader('Set-Cookie', [clearCookie(req, config.sessionCookieName)]);
    redirect(res, '/');
    return;
  }

  if (requestUrl.pathname === '/login') {
    if (config.authMode === 'device') {
      await handleDeviceLogin(req, res, requestUrl);
    } else {
      const state = crypto.randomBytes(24).toString('hex');
      oauthStates.set(state, {
        returnTo: normalizeReturnTo(requestUrl.searchParams.get('returnTo')),
        expiresAt: Date.now() + 10 * 60 * 1000,
      });
      res.setHeader('Set-Cookie', [makeSetCookie(req, config.stateCookieName, state, 600)]);
      redirect(res, loginUrl(req, state));
    }
    return;
  }

  if (requestUrl.pathname === '/login/device/status') {
    await handleDeviceStatus(req, res, requestUrl);
    return;
  }

  if (requestUrl.pathname === config.callbackPath) {
    await handleCallback(req, res, requestUrl);
    return;
  }

  const session = sessionForRequest(req);
  if (!session) {
    redirect(res, `/login?returnTo=${encodeURIComponent(requestUrl.pathname + requestUrl.search)}`);
    return;
  }

  await proxyRequest(req, res, session.user);
}

async function authenticatedUserForRequest(req, res, requestUrl) {
  if (config.authMode === 'basic') {
    const basicUser = basicAuthUserForRequest(req);
    if (!basicUser) {
      requireBasicAuth(res);
      return null;
    }
    return basicUser;
  }

  const session = sessionForRequest(req);
  if (!session) {
    redirect(res, `/login?returnTo=${encodeURIComponent(requestUrl.pathname + requestUrl.search)}`);
    return null;
  }
  return session.user;
}

async function handleChatApi(req, res, requestUrl, user) {
  if (requestUrl.pathname === '/api/chat/healthz') {
    json(res, 200, {
      ok: true,
      user: String(user.login || ''),
      workspaceRoot: config.workspaceRoot,
    });
    return;
  }

  if (req.method !== 'POST') {
    json(res, 405, { error: 'Method not allowed.' });
    return;
  }

  const payload = await readJsonBody(req);

  if (requestUrl.pathname === '/api/chat/read-file') {
    await handleReadFile(res, payload);
    return;
  }

  if (requestUrl.pathname === '/api/chat/write-file') {
    await handleWriteFile(res, payload);
    return;
  }

  if (requestUrl.pathname === '/api/chat/exec') {
    await handleExec(res, payload);
    return;
  }

  if (requestUrl.pathname === '/api/chat/message') {
    await handleAssistantMessage(res, payload);
    return;
  }

  json(res, 404, { error: 'Unknown API route.' });
}

async function readJsonBody(req) {
  const chunks = [];
  let totalBytes = 0;
  await new Promise((resolve, reject) => {
    req.on('data', (chunk) => {
      totalBytes += chunk.length;
      if (totalBytes > config.maxWriteBytes) {
        reject(new Error('Request body too large.'));
        req.destroy();
        return;
      }
      chunks.push(chunk);
    });
    req.on('end', resolve);
    req.on('error', reject);
    req.on('aborted', () => reject(new Error('Request aborted.')));
  });

  const rawBody = Buffer.concat(chunks).toString('utf8').trim();
  if (!rawBody) {
    return {};
  }

  try {
    return JSON.parse(rawBody);
  } catch {
    const error = new Error('Invalid JSON body.');
    error.statusCode = 400;
    throw error;
  }
}

async function handleReadFile(res, payload) {
  const relativePath = String(payload.path || '').trim();
  if (!relativePath) {
    json(res, 400, { error: 'Missing path.' });
    return;
  }

  const filePath = resolveWorkspacePath(relativePath);
  const stat = await fs.promises.stat(filePath).catch(() => null);
  if (!stat || !stat.isFile()) {
    json(res, 404, { error: 'File not found.' });
    return;
  }

  const encoding = String(payload.encoding || 'utf8').toLowerCase() === 'base64' ? 'base64' : 'utf8';
  const content = await fs.promises.readFile(filePath, encoding === 'base64' ? undefined : 'utf8');
  json(res, 200, {
    path: path.relative(config.workspaceRoot, filePath) || '.',
    encoding,
    content: encoding === 'base64' ? content.toString('base64') : content,
  });
}

async function handleWriteFile(res, payload) {
  const relativePath = String(payload.path || '').trim();
  if (!relativePath) {
    json(res, 400, { error: 'Missing path.' });
    return;
  }

  const encoding = String(payload.encoding || 'utf8').toLowerCase() === 'base64' ? 'base64' : 'utf8';
  const rawContent = payload.content;
  if (typeof rawContent !== 'string') {
    json(res, 400, { error: 'Missing content.' });
    return;
  }

  const filePath = resolveWorkspacePath(relativePath);
  const contentBuffer = encoding === 'base64'
    ? Buffer.from(rawContent, 'base64')
    : Buffer.from(rawContent, 'utf8');

  if (contentBuffer.length > config.maxWriteBytes) {
    json(res, 413, { error: 'Content too large.' });
    return;
  }

  await fs.promises.mkdir(path.dirname(filePath), { recursive: true });
  await fs.promises.writeFile(filePath, contentBuffer);
  json(res, 200, {
    ok: true,
    path: path.relative(config.workspaceRoot, filePath) || '.',
    bytesWritten: contentBuffer.length,
  });
}

async function handleExec(res, payload) {
  const command = String(payload.command || '').trim();
  if (!command) {
    json(res, 400, { error: 'Missing command.' });
    return;
  }

  const cwd = resolveWorkspaceCwd(payload.cwd);
  const args = Array.isArray(payload.args) ? payload.args.map((item) => String(item)) : [];
  const timeoutMs = clampNumber(payload.timeoutMs, 1_000, config.execTimeoutMs, config.execTimeoutMs);

  const result = await runProcess(command, args, cwd, timeoutMs);
  json(res, 200, result);
}

async function handleAssistantMessage(res, payload) {
  const prompt = String(payload.prompt || '').trim();
  if (!prompt) {
    json(res, 400, { error: 'Missing prompt.' });
    return;
  }

  const cwd = resolveWorkspaceCwd(payload.cwd);
  const timeoutMs = clampNumber(payload.timeoutMs, 5_000, Math.max(config.execTimeoutMs, 120_000), 120_000);
  const usingStandaloneCopilot = Boolean(config.copilotBin);
  const command = usingStandaloneCopilot ? config.copilotBin : config.ghCopilotBin;
  const args = usingStandaloneCopilot ? ['-p', prompt] : ['copilot', '-p', prompt];
  const result = await runProcess(command, args, cwd, timeoutMs, {
    PATH: `/home/eggman/bin:${process.env.PATH || '/usr/bin:/bin:/usr/local/bin'}`,
    GH_FORCE_TTY: '0',
    NO_COLOR: '1',
    FORCE_COLOR: '0',
    CLICOLOR: '0',
  });

  const combined = [result.stdout, result.stderr].filter(Boolean).join('\n').trim();
  const requiresAuth = /not logged into any github hosts/i.test(combined);

  json(res, result.ok ? 200 : (requiresAuth ? 401 : 502), {
    ok: result.ok,
    requiresAuth,
    prompt,
    cwd,
    exitCode: result.exitCode,
    timedOut: result.timedOut,
    answer: result.stdout.trim(),
    stderr: result.stderr.trim(),
    command: result.command,
    args: result.args,
  });
}

function resolveWorkspacePath(inputPath) {
  const resolvedPath = path.resolve(config.workspaceRoot, String(inputPath || ''));
  const relativePath = path.relative(config.workspaceRoot, resolvedPath);
  if (relativePath.startsWith('..') || path.isAbsolute(relativePath)) {
    const error = new Error('Path escapes workspace root.');
    error.statusCode = 403;
    throw error;
  }
  return resolvedPath;
}

function resolveWorkspaceCwd(inputCwd) {
  if (!inputCwd) {
    return config.workspaceRoot;
  }
  return resolveWorkspacePath(String(inputCwd));
}

function clampNumber(value, minimum, maximum, fallback) {
  const parsed = Number.parseInt(String(value ?? ''), 10);
  if (!Number.isFinite(parsed)) {
    return fallback;
  }
  return Math.min(maximum, Math.max(minimum, parsed));
}

async function runProcess(command, args, cwd, timeoutMs, extraEnv = {}) {
  return new Promise((resolve) => {
    const child = childProcess.spawn(command, args, {
      cwd,
      stdio: ['ignore', 'pipe', 'pipe'],
      env: {
        HOME: process.env.HOME || '',
        PATH: process.env.PATH || '/usr/bin:/bin:/usr/local/bin',
        SHELL: process.env.SHELL || '/bin/sh',
        LANG: process.env.LANG || 'C.UTF-8',
        ...extraEnv,
      },
    });

    let stdout = '';
    let stderr = '';
    let stdoutBytes = 0;
    let stderrBytes = 0;
    let killedByTimeout = false;

    const timer = setTimeout(() => {
      killedByTimeout = true;
      child.kill('SIGTERM');
      setTimeout(() => child.kill('SIGKILL'), 1_000).unref();
    }, timeoutMs);

    function appendChunk(target, chunk, currentBytes) {
      if (currentBytes >= config.maxOutputBytes) {
        return { value: target, bytes: currentBytes };
      }
      const slice = chunk.subarray(0, config.maxOutputBytes - currentBytes);
      return {
        value: target + slice.toString('utf8'),
        bytes: currentBytes + slice.length,
      };
    }

    child.stdout.on('data', (chunk) => {
      const next = appendChunk(stdout, Buffer.from(chunk), stdoutBytes);
      stdout = next.value;
      stdoutBytes = next.bytes;
    });

    child.stderr.on('data', (chunk) => {
      const next = appendChunk(stderr, Buffer.from(chunk), stderrBytes);
      stderr = next.value;
      stderrBytes = next.bytes;
    });

    child.on('error', (error) => {
      clearTimeout(timer);
      resolve({
        ok: false,
        command,
        args,
        cwd,
        exitCode: null,
        signal: null,
        timedOut: false,
        stdout,
        stderr: stderr + (stderr ? '\n' : '') + String(error.message || error),
      });
    });

    child.on('close', (exitCode, signal) => {
      clearTimeout(timer);
      resolve({
        ok: exitCode === 0 && !killedByTimeout,
        command,
        args,
        cwd,
        exitCode,
        signal,
        timedOut: killedByTimeout,
        stdout,
        stderr,
      });
    });
  });
}

async function handleDeviceLogin(req, res, requestUrl) {
  const returnTo = normalizeReturnTo(requestUrl.searchParams.get('returnTo'));
  const device = await requestDeviceCode();
  const loginId = crypto.randomBytes(24).toString('hex');
  const expiresInSeconds = Number.parseInt(device.expires_in, 10) || 900;
  const intervalSeconds = Number.parseInt(device.interval, 10) || 5;

  deviceLogins.set(loginId, {
    returnTo,
    deviceCode: String(device.device_code || ''),
    userCode: String(device.user_code || ''),
    verificationUri: String(device.verification_uri || ''),
    verificationUriComplete: String(device.verification_uri_complete || ''),
    expiresAt: Date.now() + expiresInSeconds * 1000,
    intervalSeconds,
  });

  html(res, 200, renderDeviceLoginPage(loginId, device, returnTo));
}

async function handleDeviceStatus(req, res, requestUrl) {
  const loginId = requestUrl.searchParams.get('id');
  if (!loginId) {
    text(res, 400, 'Missing login id.\n');
    return;
  }

  const record = deviceLogins.get(loginId);
  if (!record) {
    json(res, 410, { status: 'expired' });
    return;
  }
  if (record.expiresAt <= Date.now()) {
    deviceLogins.delete(loginId);
    json(res, 410, { status: 'expired' });
    return;
  }

  const tokenResult = await pollDeviceToken(record.deviceCode);
  if (tokenResult.status === 'pending') {
    json(res, 200, { status: 'pending', interval: record.intervalSeconds });
    return;
  }
  if (tokenResult.status === 'slow_down') {
    record.intervalSeconds += 5;
    json(res, 200, { status: 'pending', interval: record.intervalSeconds });
    return;
  }
  if (tokenResult.status === 'expired') {
    deviceLogins.delete(loginId);
    json(res, 410, { status: 'expired' });
    return;
  }

  const accessToken = tokenResult.accessToken;
  const user = await fetchGithubUser(accessToken);
  let orgMemberships = [];
  if (config.allowedOrgs.length > 0) {
    orgMemberships = await fetchGithubOrgs(accessToken);
  }

  if (!isAllowed(user, orgMemberships)) {
    deviceLogins.delete(loginId);
    text(res, 403, 'GitHub account not allowed.\n');
    return;
  }

  const sessionId = crypto.randomBytes(32).toString('hex');
  sessions.set(sessionId, {
    user,
    accessToken,
    expiresAt: Date.now() + config.sessionTtlMs,
  });

  deviceLogins.delete(loginId);
  const cookie = makeSetCookie(req, config.sessionCookieName, sessionId, config.sessionTtlMs / 1000);
  res.setHeader('Set-Cookie', [cookie]);
  json(res, 200, { status: 'done', redirect: record.returnTo || '/' });
}

function normalizeReturnTo(value) {
  if (!value || typeof value !== 'string') {
    return '/';
  }
  if (!value.startsWith('/')) {
    return '/';
  }
  return value;
}

async function handleCallback(req, res, requestUrl) {
  const state = requestUrl.searchParams.get('state');
  const code = requestUrl.searchParams.get('code');

  if (!state) {
    text(res, 400, 'Invalid OAuth state.\n');
    return;
  }
  if (!code) {
    text(res, 400, 'Missing OAuth code.\n');
    return;
  }

  const stateRecord = oauthStates.get(state);
  if (!stateRecord || stateRecord.expiresAt <= Date.now()) {
    text(res, 400, 'OAuth state expired.\n');
    return;
  }

  oauthStates.delete(state);
  const accessToken = await exchangeCodeForToken(code);
  const user = await fetchGithubUser(accessToken);
  let orgMemberships = [];
  if (config.allowedOrgs.length > 0) {
    orgMemberships = await fetchGithubOrgs(accessToken);
  }

  if (!isAllowed(user, orgMemberships)) {
    text(res, 403, 'GitHub account not allowed.\n');
    return;
  }

  const sessionId = crypto.randomBytes(32).toString('hex');
  sessions.set(sessionId, {
    user,
    accessToken,
    expiresAt: Date.now() + config.sessionTtlMs,
  });

  const cookie = makeSetCookie(req, config.sessionCookieName, sessionId, config.sessionTtlMs / 1000);
  const stateCookie = clearCookie(req, config.stateCookieName);
  res.setHeader('Set-Cookie', [cookie, stateCookie]);
  redirect(res, stateRecord.returnTo || '/');
}

function renderDeviceLoginPage(loginId, device, returnTo) {
  const verificationLink = device.verification_uri_complete || device.verification_uri || 'https://github.com/login/device';
  const userCode = escapeHtml(String(device.user_code || ''));
  const linkText = escapeHtml(String(verificationLink));
  const interval = Number.parseInt(device.interval, 10) || 5;

  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="cache-control" content="no-store">
  <title>GitHub Login</title>
  <style>
    body { font-family: system-ui, sans-serif; margin: 2rem; color: #111; }
    .box { max-width: 42rem; line-height: 1.5; }
    .code { font-size: 2rem; letter-spacing: 0.15em; font-weight: 700; margin: 1rem 0; }
    a { color: #0969da; }
  </style>
</head>
<body>
  <div class="box">
    <h1>GitHub login</h1>
    <p>Open this link and approve the app:</p>
    <p><a href="${linkText}" target="_blank" rel="noreferrer">Authorize on GitHub</a></p>
    <p class="code">${userCode}</p>
    <p>This page will continue automatically after approval.</p>
  </div>
  <script>
    const loginId = ${JSON.stringify(loginId)};
    const interval = ${interval} * 1000;
    const returnTo = ${JSON.stringify(returnTo || '/')};
    async function poll() {
      try {
        const response = await fetch('/login/device/status?id=' + encodeURIComponent(loginId), {
          credentials: 'same-origin',
          cache: 'no-store'
        });
        const data = await response.json();
        if (data.status === 'done') {
          window.location.href = data.redirect || returnTo || '/';
          return;
        }
        if (data.status === 'expired') {
          document.body.innerHTML = '<h1>Login expired</h1><p>Reload this page to start again.</p>';
          return;
        }
      } catch (error) {
        console.error(error);
      }
      setTimeout(poll, interval);
    }
    poll();
  </script>
</body>
</html>`;
}

function renderChatPage(user) {
  const username = escapeHtml(String(user.login || user.name || 'user'));
  const workspaceRoot = escapeHtml(config.workspaceRoot);

  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="cache-control" content="no-store">
  <title>pageoff chat bridge</title>
  <style>
    :root {
      color-scheme: light;
      --bg: #f2f0ea;
      --panel: #fffdf7;
      --ink: #1d1b19;
      --muted: #6b655c;
      --line: #d6d0c4;
      --accent: #0f766e;
      --accent-2: #134e4a;
      --danger: #9f1239;
      --shadow: 0 20px 60px rgba(33, 24, 11, 0.08);
      --mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      --sans: Inter, ui-sans-serif, system-ui, sans-serif;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: var(--sans);
      color: var(--ink);
      background:
        radial-gradient(circle at top left, rgba(15, 118, 110, 0.16), transparent 25rem),
        linear-gradient(180deg, #f9f7f1 0%, var(--bg) 100%);
      min-height: 100vh;
    }
    .chat-shell {
      width: min(1120px, calc(100vw - 2rem));
      margin: 1rem auto 3rem;
    }
    .chat-hero {
      padding: 1.5rem 1.75rem 1.25rem;
      border: 1px solid rgba(19, 78, 74, 0.16);
      border-radius: 1.5rem;
      background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(240,253,250,0.88));
      box-shadow: var(--shadow);
      display: grid;
      gap: 0.5rem;
    }
    .chat-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--accent-2);
      font-size: 0.8rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }
    .chat-eyebrow::before {
      content: "";
      width: 0.7rem;
      height: 0.7rem;
      border-radius: 999px;
      background: var(--accent);
      box-shadow: 0 0 0 0.35rem rgba(15, 118, 110, 0.12);
    }
    .chat-title {
      margin: 0;
      font-size: clamp(2rem, 4vw, 3.2rem);
      line-height: 0.95;
      letter-spacing: -0.04em;
    }
    .chat-subtitle {
      margin: 0;
      max-width: 52rem;
      color: var(--muted);
      line-height: 1.55;
    }
    .chat-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      margin-top: 0.5rem;
    }
    .chat-chip {
      display: inline-flex;
      gap: 0.5rem;
      align-items: center;
      padding: 0.55rem 0.8rem;
      border-radius: 999px;
      background: rgba(255,255,255,0.85);
      border: 1px solid rgba(19, 78, 74, 0.14);
      font-size: 0.9rem;
    }
    .chat-chip code {
      font-family: var(--mono);
      font-size: 0.85rem;
    }
    .chat-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.3fr) minmax(320px, 0.9fr);
      gap: 1rem;
      margin-top: 1rem;
      align-items: start;
    }
    .chat-panel {
      border: 1px solid var(--line);
      border-radius: 1.25rem;
      background: var(--panel);
      box-shadow: var(--shadow);
      overflow: hidden;
    }
    .chat-panel-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      padding: 1rem 1.1rem;
      border-bottom: 1px solid var(--line);
      background: rgba(255,255,255,0.8);
    }
    .chat-panel-head h2,
    .chat-panel-head h3 {
      margin: 0;
      font-size: 1rem;
      letter-spacing: -0.02em;
    }
    .chat-panel-head p {
      margin: 0.2rem 0 0;
      color: var(--muted);
      font-size: 0.85rem;
    }
    .chat-log {
      min-height: 24rem;
      max-height: 52vh;
      overflow: auto;
      padding: 1rem;
      display: grid;
      gap: 0.9rem;
      background:
        linear-gradient(180deg, rgba(250,250,247,0.9), rgba(245,242,235,0.92));
    }
    .chat-entry {
      padding: 0.9rem 1rem;
      border-radius: 1rem;
      border: 1px solid rgba(19, 78, 74, 0.12);
      background: rgba(255,255,255,0.9);
    }
    .chat-entry--error {
      border-color: rgba(159, 18, 57, 0.22);
      background: rgba(255, 241, 242, 0.96);
    }
    .chat-entry-head {
      display: flex;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 0.6rem;
      font-size: 0.8rem;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }
    .chat-entry pre {
      margin: 0;
      white-space: pre-wrap;
      word-break: break-word;
      font-family: var(--mono);
      font-size: 0.9rem;
      line-height: 1.45;
    }
    .chat-composer {
      display: grid;
      gap: 0.85rem;
      padding: 1rem;
      border-top: 1px solid var(--line);
      background: rgba(255,255,255,0.86);
    }
    .chat-row {
      display: grid;
      grid-template-columns: repeat(12, minmax(0, 1fr));
      gap: 0.75rem;
    }
    .field {
      display: grid;
      gap: 0.35rem;
    }
    .field label {
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--muted);
    }
    .field--span-4 { grid-column: span 4; }
    .field--span-6 { grid-column: span 6; }
    .field--span-8 { grid-column: span 8; }
    .field--span-12 { grid-column: span 12; }
    input, textarea, select, button {
      font: inherit;
    }
    input, textarea, select {
      width: 100%;
      border: 1px solid #c7c0b4;
      border-radius: 0.9rem;
      padding: 0.8rem 0.9rem;
      background: #fff;
      color: var(--ink);
    }
    textarea {
      min-height: 7rem;
      resize: vertical;
      font-family: var(--mono);
      font-size: 0.9rem;
    }
    .chat-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      align-items: center;
      justify-content: space-between;
    }
    .button-row {
      display: flex;
      gap: 0.65rem;
      flex-wrap: wrap;
    }
    button {
      border: 0;
      border-radius: 999px;
      padding: 0.8rem 1.05rem;
      font-weight: 700;
      cursor: pointer;
      transition: transform 120ms ease, opacity 120ms ease, background 120ms ease;
    }
    button:hover { transform: translateY(-1px); }
    button:disabled { opacity: 0.5; cursor: wait; transform: none; }
    .button-primary { background: var(--accent); color: #fff; }
    .button-secondary { background: #ece7dc; color: var(--ink); }
    .button-danger { background: #fce7f3; color: var(--danger); }
    .chat-status {
      min-height: 1.2rem;
      color: var(--muted);
      font-size: 0.92rem;
    }
    .chat-status--error { color: var(--danger); }
    .chat-side {
      display: grid;
      gap: 1rem;
    }
    .chat-side-body {
      padding: 1rem 1.1rem 1.1rem;
      display: grid;
      gap: 0.85rem;
    }
    .chat-side-body code,
    .chat-side-body pre {
      font-family: var(--mono);
      font-size: 0.86rem;
    }
    .chat-side-body pre {
      margin: 0;
      padding: 0.8rem 0.9rem;
      border-radius: 1rem;
      background: #faf7f0;
      border: 1px solid var(--line);
      white-space: pre-wrap;
      word-break: break-word;
    }
    .chat-quicklist {
      display: grid;
      gap: 0.55rem;
    }
    .chat-quicklist button {
      justify-content: flex-start;
      text-align: left;
      border-radius: 1rem;
      background: #f4efe4;
      color: var(--ink);
    }
    @media (max-width: 900px) {
      .chat-grid {
        grid-template-columns: 1fr;
      }
      .chat-row {
        grid-template-columns: 1fr;
      }
      .field--span-4,
      .field--span-6,
      .field--span-8,
      .field--span-12 {
        grid-column: auto;
      }
    }
  </style>
</head>
<body>
  <main class="chat-shell">
    <section class="chat-hero">
      <span class="chat-eyebrow">pageoff bridge</span>
      <h1 class="chat-title">Workspace chat console</h1>
      <p class="chat-subtitle">A thin browser client for the live bridge API. It can execute commands, read files, write files, and ask <code>gh copilot</code> for natural-language answers inside the configured workspace root.</p>
      <div class="chat-meta">
        <span class="chat-chip">user <code>${username}</code></span>
        <span class="chat-chip">root <code>${workspaceRoot}</code></span>
        <span class="chat-chip">auth inherited from proxy</span>
      </div>
    </section>

    <section class="chat-grid">
      <section class="chat-panel">
        <div class="chat-panel-head">
          <div>
            <h2>Console</h2>
            <p>Results from the bridge API are appended here.</p>
          </div>
        </div>
        <div id="chat-log" class="chat-log"></div>
        <form id="chat-form" class="chat-composer">
          <div class="chat-row">
            <div class="field field--span-4">
              <label for="mode">Mode</label>
              <select id="mode" name="mode">
                <option value="assistant">Assistant reply (gh copilot)</option>
                <option value="exec">Execute command</option>
                <option value="read-file">Read file</option>
                <option value="write-file">Write file</option>
              </select>
            </div>
            <div class="field field--span-8">
              <label for="cwd">Working directory or base path</label>
              <input id="cwd" name="cwd" value="." autocomplete="off">
            </div>
          </div>
          <div class="chat-row" data-mode="assistant">
            <div class="field field--span-12">
              <label for="prompt">Prompt</label>
              <textarea id="prompt" name="prompt">Explain the current folder and what the main files seem to do.</textarea>
            </div>
          </div>
          <div class="chat-row" data-mode="exec">
            <div class="field field--span-4">
              <label for="command">Command</label>
              <input id="command" name="command" value="pwd" autocomplete="off">
            </div>
            <div class="field field--span-8">
              <label for="args">Args JSON array</label>
              <input id="args" name="args" value="[]" autocomplete="off">
            </div>
          </div>
          <div class="chat-row" data-mode="read-file write-file">
            <div class="field field--span-12">
              <label for="path">Relative file path</label>
              <input id="path" name="path" value=".htaccess" autocomplete="off">
            </div>
          </div>
          <div class="chat-row" data-mode="write-file">
            <div class="field field--span-12">
              <label for="content">Content</label>
              <textarea id="content" name="content">hello
</textarea>
            </div>
          </div>
          <div class="chat-actions">
            <div class="button-row">
              <button class="button-primary" id="submit" type="submit">Run</button>
              <button class="button-secondary" id="healthz" type="button">Health</button>
              <button class="button-danger" id="clear-log" type="button">Clear log</button>
            </div>
            <div id="status" class="chat-status"></div>
          </div>
        </form>
      </section>

      <aside class="chat-side">
        <section class="chat-panel">
          <div class="chat-panel-head">
            <div>
              <h3>Quick actions</h3>
              <p>Prefill common calls.</p>
            </div>
          </div>
          <div class="chat-side-body chat-quicklist">
            <button type="button" data-preset="assistant-explain">Explain this folder</button>
            <button type="button" data-preset="pwd">Run <code>pwd</code></button>
            <button type="button" data-preset="list">List current folder</button>
            <button type="button" data-preset="read-htaccess">Read <code>.htaccess</code></button>
            <button type="button" data-preset="write-note">Write <code>notes/test.txt</code></button>
          </div>
        </section>
        <section class="chat-panel">
          <div class="chat-panel-head">
            <div>
              <h3>API notes</h3>
              <p>Exact payload shape for the bridge.</p>
            </div>
          </div>
          <div class="chat-side-body">
            <pre>{
  "cwd": ".",
  "prompt": "Explain this folder"
}</pre>
            <pre>{
  "cwd": ".",
  "command": "ls",
  "args": ["-la"]
}</pre>
            <pre>{
  "path": "notes/test.txt",
  "content": "hello\\n"
}</pre>
          </div>
        </section>
      </aside>
    </section>
  </main>
  <script>
    const logEl = document.getElementById('chat-log');
    const statusEl = document.getElementById('status');
    const formEl = document.getElementById('chat-form');
    const modeEl = document.getElementById('mode');
    const cwdEl = document.getElementById('cwd');
    const promptEl = document.getElementById('prompt');
    const commandEl = document.getElementById('command');
    const argsEl = document.getElementById('args');
    const pathEl = document.getElementById('path');
    const contentEl = document.getElementById('content');
    const submitEl = document.getElementById('submit');
    const visibilityRows = Array.from(document.querySelectorAll('[data-mode]'));

    function setStatus(message, isError = false) {
      statusEl.textContent = message || '';
      statusEl.className = isError ? 'chat-status chat-status--error' : 'chat-status';
    }

    function nowStamp() {
      return new Date().toLocaleTimeString();
    }

    function appendEntry(title, payload, isError = false) {
      const entry = document.createElement('article');
      entry.className = isError ? 'chat-entry chat-entry--error' : 'chat-entry';
      const pre = document.createElement('pre');
      pre.textContent = typeof payload === 'string' ? payload : JSON.stringify(payload, null, 2);
      entry.innerHTML = '<div class="chat-entry-head"><span>' + title + '</span><span>' + nowStamp() + '</span></div>';
      entry.appendChild(pre);
      logEl.prepend(entry);
    }

    function syncVisibleFields() {
      const mode = modeEl.value;
      visibilityRows.forEach((row) => {
        const modes = row.getAttribute('data-mode').split(/\\s+/);
        row.hidden = !modes.includes(mode);
      });
    }

    async function callApi(path, payload, method = 'POST') {
      const response = await fetch(path, {
        method,
        headers: method === 'POST' ? { 'content-type': 'application/json' } : {},
        body: method === 'POST' ? JSON.stringify(payload || {}) : undefined,
        credentials: 'same-origin',
      });

      const text = await response.text();
      let data = text;
      try {
        data = text ? JSON.parse(text) : {};
      } catch {}

      if (!response.ok) {
        const error = new Error(typeof data === 'string' ? data : (data.error || text || 'Request failed.'));
        error.response = data;
        error.status = response.status;
        throw error;
      }
      return data;
    }

    async function runCurrentMode() {
      const mode = modeEl.value;
      if (mode === 'assistant') {
        const payload = {
          cwd: cwdEl.value || '.',
          prompt: promptEl.value,
        };
        appendEntry('prompt', payload.prompt);
        const data = await callApi('/api/chat/message', payload);
        appendEntry('assistant', data.answer || data.stderr || data, !data.ok);
        if (data.requiresAuth) {
          setStatus('gh is not logged in on the server yet.', true);
        }
        return;
      }

      if (mode === 'exec') {
        let args = [];
        try {
          args = JSON.parse(argsEl.value || '[]');
          if (!Array.isArray(args)) {
            throw new Error('Args must be a JSON array.');
          }
        } catch (error) {
          setStatus(error.message, true);
          appendEntry('client error', error.message, true);
          return;
        }
        const payload = {
          cwd: cwdEl.value || '.',
          command: commandEl.value,
          args,
        };
        const data = await callApi('/api/chat/exec', payload);
        appendEntry('exec', data, !data.ok);
        return;
      }

      if (mode === 'read-file') {
        const data = await callApi('/api/chat/read-file', {
          path: pathEl.value,
        });
        appendEntry('read-file', data);
        return;
      }

      if (mode === 'write-file') {
        const data = await callApi('/api/chat/write-file', {
          path: pathEl.value,
          content: contentEl.value,
        });
        appendEntry('write-file', data, !data.ok);
      }
    }

    formEl.addEventListener('submit', async (event) => {
      event.preventDefault();
      setStatus('Running...');
      submitEl.disabled = true;
      try {
        await runCurrentMode();
        setStatus('Done.');
      } catch (error) {
        setStatus(error.message || 'Request failed.', true);
        appendEntry('request error', error.response || error.message || String(error), true);
      } finally {
        submitEl.disabled = false;
      }
    });

    document.getElementById('healthz').addEventListener('click', async () => {
      setStatus('Checking...');
      try {
        const data = await callApi('/api/chat/healthz', undefined, 'GET');
        appendEntry('healthz', data);
        setStatus('Bridge healthy.');
      } catch (error) {
        setStatus(error.message || 'Health check failed.', true);
        appendEntry('healthz error', error.response || error.message || String(error), true);
      }
    });

    document.getElementById('clear-log').addEventListener('click', () => {
      logEl.innerHTML = '';
      setStatus('');
    });

    document.querySelectorAll('[data-preset]').forEach((button) => {
      button.addEventListener('click', () => {
        const preset = button.getAttribute('data-preset');
        if (preset === 'assistant-explain') {
          modeEl.value = 'assistant';
          cwdEl.value = '.';
          promptEl.value = 'Explain the current folder and identify the main files.';
        } else if (preset === 'pwd') {
          modeEl.value = 'exec';
          cwdEl.value = '.';
          commandEl.value = 'pwd';
          argsEl.value = '[]';
        } else if (preset === 'list') {
          modeEl.value = 'exec';
          cwdEl.value = '.';
          commandEl.value = 'ls';
          argsEl.value = '[\"-la\"]';
        } else if (preset === 'read-htaccess') {
          modeEl.value = 'read-file';
          pathEl.value = '.htaccess';
        } else if (preset === 'write-note') {
          modeEl.value = 'write-file';
          pathEl.value = 'notes/test.txt';
          contentEl.value = 'hello\\n';
        }
        syncVisibleFields();
      });
    });

    modeEl.addEventListener('change', syncVisibleFields);
    syncVisibleFields();
    appendEntry('ready', {
      user: ${JSON.stringify(String(user.login || user.name || 'user'))},
      workspaceRoot: ${JSON.stringify(config.workspaceRoot)},
      ghCopilotBin: ${JSON.stringify(config.ghCopilotBin)},
      copilotBin: ${JSON.stringify(config.copilotBin)},
      message: 'Use assistant mode for natural-language replies, or the raw bridge modes for direct file and command access.'
    });
  </script>
</body>
</html>`;
}

function forwardHeaders(req, user) {
  const headers = {};
  for (const [key, value] of Object.entries(req.headers)) {
    if (value == null) {
      continue;
    }
    const normalizedKey = key.toLowerCase();
    if ([
      'host',
      'connection',
      'proxy-connection',
      'keep-alive',
      'transfer-encoding',
      'upgrade',
      'proxy-authorization',
      'proxy-authenticate',
      'te',
      'trailers',
    ].includes(normalizedKey)) {
      continue;
    }
    headers[key] = value;
  }

  const upstream = new URL(config.upstreamUrl);
  headers.host = upstream.host;
  headers['x-forwarded-host'] = String(req.headers.host || upstream.host);
  headers['x-forwarded-proto'] = isSecureRequest(req) ? 'https' : 'http';
  headers['x-forwarded-port'] = upstream.port || (upstream.protocol === 'https:' ? '443' : '80');

  const priorFor = req.headers['x-forwarded-for'];
  const remoteAddress = req.socket.remoteAddress || '';
  headers['x-forwarded-for'] = priorFor ? `${priorFor}, ${remoteAddress}` : remoteAddress;
  headers['x-authenticated-user'] = String(user.login || '');
  headers['x-authenticated-user-id'] = String(user.id || '');
  headers['x-authenticated-user-name'] = String(user.name || '');

  return headers;
}

function proxyResponseHeaders(sourceHeaders) {
  const headers = {};
  for (const [key, value] of Object.entries(sourceHeaders)) {
    if (value == null) {
      continue;
    }
    if (['connection', 'transfer-encoding', 'proxy-authenticate', 'proxy-authorization', 'te', 'trailers', 'upgrade'].includes(key.toLowerCase())) {
      continue;
    }
    headers[key] = value;
  }
  return headers;
}

async function proxyRequest(req, res, user) {
  const upstream = new URL(config.upstreamUrl);
  const client = upstream.protocol === 'https:' ? https : http;
  const headers = forwardHeaders(req, user);
  const options = {
    protocol: upstream.protocol,
    hostname: upstream.hostname,
    port: upstream.port || (upstream.protocol === 'https:' ? 443 : 80),
    method: req.method,
    path: req.url,
    headers,
  };

  await new Promise((resolve, reject) => {
    const proxyReq = client.request(options, (proxyRes) => {
      res.writeHead(proxyRes.statusCode || 502, proxyResponseHeaders(proxyRes.headers));
      proxyRes.pipe(res);
      proxyRes.on('end', resolve);
      proxyRes.on('error', (error) => {
        if (error && error.code === 'ECONNRESET') {
          resolve();
          return;
        }
        reject(error);
      });
    });

    proxyReq.on('error', (error) => {
      if (error && error.code === 'ECONNRESET') {
        resolve();
        return;
      }
      reject(error);
    });
    res.on('error', (error) => {
      if (error && error.code === 'ECONNRESET') {
        resolve();
        return;
      }
      reject(error);
    });
    req.pipe(proxyReq);
    req.on('aborted', () => {
      proxyReq.destroy();
      resolve();
    });
  });
}

async function handleUpgrade(req, socket, head) {
  if (config.authMode === 'basic') {
    const basicUser = basicAuthUserForRequest(req);
    if (!basicUser) {
      socket.write('HTTP/1.1 401 Unauthorized\r\nWWW-Authenticate: Basic realm="pageoff proxy", charset="UTF-8"\r\nConnection: close\r\n\r\n');
      socket.destroy();
      return;
    }

    const upstream = new URL(config.upstreamUrl);
    const client = upstream.protocol === 'https:' ? https : http;
    const headers = forwardHeaders(req, basicUser);
    headers.connection = 'Upgrade';
    headers.upgrade = req.headers.upgrade || 'websocket';

    await new Promise((resolve, reject) => {
      const proxyReq = client.request({
        protocol: upstream.protocol,
        hostname: upstream.hostname,
        port: upstream.port || (upstream.protocol === 'https:' ? 443 : 80),
        method: 'GET',
        path: req.url,
        headers,
      });

      proxyReq.on('upgrade', (proxyRes, proxySocket, proxyHead) => {
        const statusLine = `HTTP/1.1 ${proxyRes.statusCode || 101} ${proxyRes.statusMessage || 'Switching Protocols'}`;
        socket.write(`${statusLine}\r\n`);
        for (const [key, value] of Object.entries(proxyRes.headers)) {
          if (value == null) {
            continue;
          }
          socket.write(`${key}: ${Array.isArray(value) ? value.join(', ') : value}\r\n`);
        }
        socket.write('\r\n');

        if (head && head.length > 0) {
          proxySocket.write(head);
        }
        if (proxyHead && proxyHead.length > 0) {
          socket.write(proxyHead);
        }

        proxySocket.pipe(socket);
        socket.pipe(proxySocket);
        resolve();
      });

      proxyReq.on('response', (proxyRes) => {
        socket.write(`HTTP/1.1 ${proxyRes.statusCode || 502} ${proxyRes.statusMessage || 'Bad Gateway'}\r\n`);
        for (const [key, value] of Object.entries(proxyRes.headers)) {
          if (value == null) {
            continue;
          }
          socket.write(`${key}: ${Array.isArray(value) ? value.join(', ') : value}\r\n`);
        }
        socket.write('\r\n');
        proxyRes.pipe(socket);
        resolve();
      });

      proxyReq.on('error', reject);
      proxyReq.end();
    });
    return;
  }

  const session = sessionForRequest(req);
  if (!session) {
    socket.write('HTTP/1.1 401 Unauthorized\r\nConnection: close\r\n\r\n');
    socket.destroy();
    return;
  }

  const upstream = new URL(config.upstreamUrl);
  const client = upstream.protocol === 'https:' ? https : http;
  const headers = forwardHeaders(req, session.user);
  headers.connection = 'Upgrade';
  headers.upgrade = req.headers.upgrade || 'websocket';

  await new Promise((resolve, reject) => {
    const proxyReq = client.request({
      protocol: upstream.protocol,
      hostname: upstream.hostname,
      port: upstream.port || (upstream.protocol === 'https:' ? 443 : 80),
      method: 'GET',
      path: req.url,
      headers,
    });

    proxyReq.on('upgrade', (proxyRes, proxySocket, proxyHead) => {
      const statusLine = `HTTP/1.1 ${proxyRes.statusCode || 101} ${proxyRes.statusMessage || 'Switching Protocols'}`;
      socket.write(`${statusLine}\r\n`);
      for (const [key, value] of Object.entries(proxyRes.headers)) {
        if (value == null) {
          continue;
        }
        socket.write(`${key}: ${Array.isArray(value) ? value.join(', ') : value}\r\n`);
      }
      socket.write('\r\n');

      if (head && head.length > 0) {
        proxySocket.write(head);
      }
      if (proxyHead && proxyHead.length > 0) {
        socket.write(proxyHead);
      }

      proxySocket.pipe(socket);
      socket.pipe(proxySocket);
      resolve();
    });

    proxyReq.on('response', (proxyRes) => {
      socket.write(`HTTP/1.1 ${proxyRes.statusCode || 502} ${proxyRes.statusMessage || 'Bad Gateway'}\r\n`);
      for (const [key, value] of Object.entries(proxyRes.headers)) {
        if (value == null) {
          continue;
        }
        socket.write(`${key}: ${Array.isArray(value) ? value.join(', ') : value}\r\n`);
      }
      socket.write('\r\n');
      proxyRes.pipe(socket);
      resolve();
    });

    proxyReq.on('error', reject);
    proxyReq.end();
  });
}
