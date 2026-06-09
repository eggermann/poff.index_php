# Proxy app

This folder contains a standalone Node reverse proxy for `code-server` and a small chat bridge API.

## Environment

Set these values in the repo root `.env` or as process environment variables:

- `GITHUB_CLIENT_ID`
- `GITHUB_CLIENT_SECRET`
- `GITHUB_ALLOWED_USERS` or `GITHUB_ALLOWED_ORGS`
- `PROXY_UPSTREAM_URL`
- `AUTH_MODE` set to `basic` or `device`
- `BASIC_AUTH_USER` and `BASIC_AUTH_PASSWORD` when using basic auth
- `CHAT_WORKSPACE_ROOT` absolute path that the chat bridge may read/write/execute in
- `PORT` optional, defaults to `3000`
- `BIND_HOST` optional, defaults to `0.0.0.0`
- `PUBLIC_BASE_URL` optional, but useful if the public callback URL is fixed

Example:

```env
AUTH_MODE=basic
BASIC_AUTH_USER=pageoff
BASIC_AUTH_PASSWORD=replace-me
PROXY_UPSTREAM_URL=http://127.0.0.1:8080
CHAT_WORKSPACE_ROOT=/home/eggman/html
PUBLIC_BASE_URL=https://proxy.pageoff.de
```

## Run locally

```bash
cd proxy
npm start
```

## Uberspace shape

- Run this as a background Node service with `supervisord`
- Point a Uberspace web backend at the chosen port
- Set the GitHub OAuth callback URL to `https://proxy.pageoff.de/oauth/callback`

If the upstream is a local `code-server`, start that separately and set `PROXY_UPSTREAM_URL` to its HTTP endpoint.

## Chat bridge API

All chat endpoints live under `/api/chat/*` and use the same auth as the proxy itself.

- `GET /api/chat/healthz`
- `POST /api/chat/message`
- `POST /api/chat/read-file`
- `POST /api/chat/write-file`
- `POST /api/chat/exec`

All file paths and working directories are resolved relative to `CHAT_WORKSPACE_ROOT`. Escaping that root is rejected.

Examples:

```bash
curl -u pageoff:replace-me https://proxy.pageoff.de/api/chat/healthz
```

```bash
curl -u pageoff:replace-me \
  -H 'content-type: application/json' \
  -d '{"cwd":".","prompt":"Explain the current folder"}' \
  https://proxy.pageoff.de/api/chat/message
```

```bash
curl -u pageoff:replace-me \
  -H 'content-type: application/json' \
  -d '{"path":"index.php"}' \
  https://proxy.pageoff.de/api/chat/read-file
```

```bash
curl -u pageoff:replace-me \
  -H 'content-type: application/json' \
  -d '{"path":"notes/todo.txt","content":"hello\\n"}' \
  https://proxy.pageoff.de/api/chat/write-file
```

```bash
curl -u pageoff:replace-me \
  -H 'content-type: application/json' \
  -d '{"cwd":".","command":"ls","args":["-la"]}' \
  https://proxy.pageoff.de/api/chat/exec
```

`/api/chat/exec` runs commands without a shell by default. Pass the binary in `command` and individual CLI parts in `args`.

`/api/chat/message` uses `gh copilot -p`. It requires `gh` to be logged in on the host with a GitHub account that has Copilot access.
