# eggman4

## Uberspace 8

- Hostname: `neso.uberspace.de`
- Username: `eggman4`
- Home: `/home/eggman4`
- Public site: `https://eggman4.uber.space/`

## Current proxy setup

- Domain: `proxy.pageoff.de`
- Proxy auth username: `pageoff`
- Proxy auth password: `feePwjJUPpp88fg7Rt8DTevh`

## Services

- `code-server` runs on port `8080`
- `pageoff-proxy` runs on port `19090`

## Useful paths

- `~/.config/code-server/config.yaml`
- `~/.local/share/code-server`
- `~/proxy/.env`
- `~/proxy/server.js`




AUTH_MODE=basic
BASIC_AUTH_USER=pageoff
BASIC_AUTH_PASSWORD=feePwjJUPpp88fg7Rt8DTevh
PROXY_UPSTREAM_URL=http://127.0.0.1:8080
CHAT_WORKSPACE_ROOT=/home/eggman4/html/copilot
PUBLIC_SITE_ROOT=/var/www/virtual/eggman4/html
PUBLIC_SITE_ROUTES=dodi:pages/dodi/actual,dodi2:pages/dodi2/actual,dodi_2:pages/dodi2/actual
PORT=19090
BIND_HOST=0.0.0.0
COPILOT_BIN=/home/eggman4/.local/bin/copilot
