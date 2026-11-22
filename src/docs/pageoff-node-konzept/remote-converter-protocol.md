# Remote Converter Protocol: `poff-converter/1`

Status: implementation-backed contract from the current source tree. This document records current behavior, including unsafe or incomplete behavior; it does not describe every current behavior as a recommended policy. New proposals are labeled separately. No protocol-version change is defined here.

## Executive summary

The three documented JSON Node API operations exist today. `converter-offers` publishes provider-side offers, `remote-converters` queries one URL supplied by the authenticated editor, and `convert-offered` executes an offered converter on the provider. They are wired through `src/includes/viewer.php`, `src/mcp/server.php`, and `build/build.php`. They are not missing routes and must not be recreated.

The existing editor already connects provider discovery to selection and conversion. Local execution is a separate `converters` → `convert` flow. Results return to the consumer and are saved through `save-converted-work`; the remote provider does not write into the consumer's filesystem.

Security boundary after the current repair: provider discovery remains public by design but lists only explicitly opted-in capabilities. Offered execution requires a signed, fresh, non-replayed trusted-peer request. Outbound requests reject private/reserved A or AAAA answers and pin the selected public address to the connection; hostname transport requires PHP cURL. Remote conversion requires HTTPS apart from the built-in trusted local converter endpoints.

## Components and full lifecycle

Recipe reconciliation (22 November 2026):

| Recipe phase | Verified implementation / evidence | Remaining work |
| --- | --- | --- |
| Route reconciliation and discovery | Three existing JSON routes in source, standalone router and Builder; routing contract tests | Bundled runtime equivalence is not asserted |
| Work Editor | Existing selection and explicit provider URL; editor regression tests | Full browser interaction with a local provider remains unverified |
| Execution and local save | HMAC peer execution, bounded transport and guarded save modes; converter tests | Local two-node HTTP provider lifecycle remains unverified |
| Output validation | Offered MIME and body bounds; save validates optional integer size against actual decoded/downloaded bytes | MIME declaration is not proof of file content; broader malformed response tests remain |
| Operations | Explicit per-definition publication and out-of-band peer credentials | Key rotation/handshake and per-peer quotas remain open |

Next slices: declared-size persistence regression, then controlled provider transport integration and malformed-response coverage. The recipe is not complete while those verification gaps remain.

| Stage | Current implementation | Behavior |
| --- | --- | --- |
| Local catalog | `Converter::definitions()`, `discover()` | Reads `poff/converters`, `converters`, `.converters` in that order; later duplicate IDs win. Bundled grains and legacy definitions fill IDs not already found. |
| Compatibility filter | `Converter::matches()` | If MIME is present, MIME matching decides. Otherwise checks kind, literal extension, then MIME inferred from extension. Supports exact MIME and `type/*`. |
| Provider offer | `handleConverterOffers()` | Calls `Converter::offerableFor()`, serializes public metadata and returns protocol `poff-converter/1`. |
| Consumer discovery | `handleRemoteConverters()` | Requires local editor access to the selected source path; posts source MIME/kind/extension to the explicitly supplied `nodeUrl`; validates protocol and filters offers again. |
| Editor selection | `src/includes/viewer/edit/core/config.php`, `src/assets/js/edit/controller.js`, `src/assets/js/edit/panel/inline.js` | Adds compatible remote definitions to the existing converter catalog. The editor labels remote entries with provider identity and stores endpoint/provider converter ID in the selected Work config. |
| Consumer request | `handleConvert()` → `Converter::convert()` → `convertRemote()` | Requires editor access to an in-root source file; checks source MIME and size; reads bytes and posts Base64 source data to the selected provider endpoint. |
| Provider execution | `handleConvertOffered()` → `Converter::convertOffered()` | Resolves a local offered definition, checks offerability, decoded source size, input match and declared output MIME, then runs a local supported engine. It does not install or execute provider code on the consumer. |
| Consumer result validation | `Converter::convertRemote()` | Requires HTTP success, JSON `ok: true`, and output MIME declared by the selected offer; adds consumer-side `generatedBy` provenance. |
| Local storage | `handleSaveConvertedWork()` → `Converter::saveGeneratedWork()` | Requires consumer editor access; validates source path, output MIME/name/body and stores the generated file and metadata beside the source. |

The local consumer's Work Editor also supports preview and bulk conversion paths. Keep those on the existing editor/save logic; do not add a second editor or persistence route.

## Provider discovery

### Request

The consumer derives the catalog endpoint from the provider URL and posts JSON:

```json
{
  "mimeType": "image/tiff",
  "kind": "image",
  "extension": "tif"
}
```

The provider accepts MIME, kind and extension as strings. MIME is lowercased and trimmed. If MIME is present, it has precedence over kind and extension during matching. The operation is not method-restricted by its handler.

### Response

Current successful shape:

```json
{
  "ok": true,
  "route": "converter-offers",
  "protocol": "poff-converter/1",
  "node": {
    "id": "provider-node-id-or-host-fallback",
    "url": "https://provider.example/index.php",
    "endpoint": "https://provider.example/index.php?mcp=1&route=convert-offered"
  },
  "converters": [
    {
      "id": "converter/image-converter",
      "type": "converter",
      "name": "image-converter",
      "label": "Image Converter",
      "description": "...",
      "accepts": ["image/tiff", "image/*"],
      "outputs": ["image/png"],
      "formats": ["png"],
      "defaults": {"format": "png"},
      "ui": {"format": {"type": "select", "options": ["png"]}}
    }
  ]
}
```

The converter serializer intentionally emits descriptive fields only; it omits local folder paths, PHP files and engine implementation details. `node.id` currently comes from the supplied/internal node ID or falls back to the request host; it is not cryptographically authenticated. The consumer currently trusts this returned label for display, but derives the execution endpoint from the URL entered by the editor rather than accepting a provider-supplied execution URL.

Provider publication requires `network.publish: true` in the converter definition as well as an enabled, locally executable `text-copy`, `imagemagick` or `simple-image` engine. Provider-supplied `remote-node` engines are not re-published. Disabled definitions are excluded from discovery and execution. The ImageMagick CLI remains disabled for local converter availability unless the operator sets `POFF_ENABLE_IMAGEMAGICK_CLI=1`; an installed but disabled CLI is not an executable offer. Peer execution requires configured HMAC credentials; see the security section.

The Work Editor now defaults to an empty provider URL and `autoLoadRemote: false`; it queries a provider only after the user changes the URL. A previously selected remote converter retains its provider metadata and remains available for that Work. There is no recursive discovery or central registry lookup in these routes.

### Consumer validation and compatibility

The consumer requires an exact `protocol` value of `poff-converter/1`; otherwise it returns an error. Malformed individual offer entries are skipped. It validates the derived catalog and execution endpoint, builds a remote definition from each offer, then re-applies MIME/kind/extension matching. It does not validate an authenticated stable provider identity, a capability signature, response content length, or every nested metadata field.

Discovery errors are handler JSON objects such as `{ "ok": false, "route": "remote-converters", "error": "..." }`, commonly sent with HTTP 200 by the existing router. Consumers should rely on `ok` and preserve the editor's actionable error text. The protocol handler has no dedicated HTTP status mapping today.

## Conversion request

The consumer starts from `Converter::buildPayload()`. Current fields include:

```json
{
  "source": {
    "name": "photo.tif",
    "path": "photo.tif",
    "mimeType": "image/tiff",
    "extension": "tif",
    "size": 12345,
    "srcUrl": ""
  },
  "converter": {
    "type": "converter",
    "name": "remote-provider-converter",
    "id": "converter/remote-provider-image-converter",
    "quality": "default",
    "format": "png",
    "node": {
      "id": "provider-node-id",
      "baseUrl": "https://provider.example/index.php",
      "endpoint": "https://provider.example/index.php?mcp=1&route=convert-offered",
      "converterId": "converter/image-converter"
    }
  },
  "target": {
    "folder": "",
    "saveAs": "external-photo.png",
    "mode": "new-hidden-work"
  },
  "requestingNode": {"id": "local", "baseUrl": ""}
}
```

Before remote POST, `convert()` reads the source from inside the consumer Node and adds `source.bodyBase64`. This is the source content encoded as Base64. The current source-size maximum is 100 MiB before encoding. The consumer now sends only the source basename in `source.path`, clears `source.srcUrl`, and clears `target.folder`; this keeps the v1 field shape while omitting the consumer's internal relative path and folder. The provider needs only source metadata/bytes and `target.saveAs` for its current execution path. The requesting Node fields remain for compatibility and do not currently contain a generated public URL by default.

`convertRemote()` replaces the selected converter ID/name with the provider's advertised converter ID, sets `converter.node` to `provider`, then POSTs JSON to `convert-offered`. The provider chooses the converter by its local ID, and independently validates input match and output MIME. Quality, format and converter UI options are passed through in `converter`. After a successful response the consumer restores its local source path for the existing editor/save response; that private path is not sent to the provider.

The `poff-converter/1` JSON request shape remains unchanged. Transport authentication is additive in HTTP headers: the sender signs a canonical JSON digest with HMAC-SHA256; `convert-offered` accepts POST only, checks a five-minute timestamp window, verifies the configured peer signature and records a one-use nonce. No protocol v2 is introduced.

## Conversion response

Current provider success shape:

```json
{
  "ok": true,
  "type": "converted-work",
  "source": {"path": "private-folder/photo.tif", "mimeType": "image/tiff"},
  "output": {
    "name": "external-photo.png",
    "mimeType": "image/png",
    "kind": "image",
    "size": 23456,
    "bodyBase64": "..."
  },
  "generatedBy": {
    "type": "converter",
    "name": "image-converter",
    "id": "converter/image-converter",
    "node": "provider",
    "engine": "imagemagick",
    "quality": "default",
    "format": "png",
    "convertedAt": "..."
  }
}
```

Provider failures use `{ "ok": false, "error": "..." }`; failure categories are not a stable machine-readable enum. The consumer rejects failed HTTP status, oversized HTTP response bodies, malformed JSON, `ok !== true`, and output MIME not in the selected offer. Catalog responses are streamed up to 1 MiB, conversion JSON responses up to 150,000,000 bytes, and downloaded output up to 100 MiB. Inline Base64 and save payloads are bounded to 100 MiB decoded output (with an encoded-length precheck), and the save path rejects declared output sizes above that cap. It does not currently require declared `output.size` to equal the decoded byte count. Provenance fields include selected converter, provider node, endpoint, quality, format and consumer timestamp.

Output bytes are returned inline as Base64 in the current path. The consumer save operation permits a fixed list of image/text MIME values and sanitizes the output basename. `new-hidden-work` now fails on any existing target and opens a new file exclusively, preventing source or unrelated file replacement. `replace-generated-work` can replace an existing result only when its sidecar marks it generated from the same source. `temporary-preview-only` is rejected by the save route; the editor continues to handle preview without a save call. `replace-source-file` remains a separate explicit text-like branch requiring the source basename. Successful generated output is stored beside the source with `.works/<filename>.config.json`, folder tree metadata, `generatedBy` and a `converted-from` relation.

## Authentication, permissions and network boundary

| Operation | Permission | Network behavior |
| --- | --- | --- |
| `converter-offers` | Public discovery; each definition requires `network.publish: true` and an available local engine | Returns only opted-in, locally executable offers. No caller identity is required to read this public catalog. |
| `remote-converters` | Local editor access to the selected Work | Outbound POST to editor-supplied provider URL; 20-second timeout, redirects disabled, catalog response capped at 1 MiB. |
| `convert` | Local editor access and in-root physical source check | Outbound POST to selected offer endpoint; requires configured per-provider peer credentials; 30-second timeout, redirects disabled, JSON response capped at 150,000,000 bytes. |
| `convert-offered` | Trusted peer only | Request JSON is capped at 150,000,000 bytes before parsing. Requires HMAC-SHA256 signature, a trusted peer ID, a timestamp within ±300 seconds, a 128-bit nonce, and a nonce not used in the last ten minutes. Execution requires the converter's explicit network publication opt-in. Decoded input and output are capped at 100 MiB. |
| `save-converted-work` | Local editor access and source-in-root check | May GET `downloadUrl` returned by a converter; redirects disabled, 30-second timeout, download capped at 100 MiB. Current main remote path uses inline Base64. |

`Converter::httpRequestPinned()` is the outbound transport for catalog POSTs, conversion POSTs and download GETs. It resolves A and AAAA records, rejects the hostname if any answer is private or reserved, then pins the first accepted answer with cURL `CURLOPT_RESOLVE`. cURL keeps the original hostname for HTTP Host, TLS SNI and certificate verification; proxy use and redirects are disabled. If cURL is unavailable, hostname requests fail closed. Direct IP URLs can use the bounded PHP stream fallback with TLS peer verification. `POFF_ALLOW_PRIVATE_CONVERTER_HOSTS=1` is a development-only escape hatch for local tests. Endpoint validation rejects URL credentials/fragments and unsupported schemes. Discovery permits HTTP for compatibility; remote conversion requires HTTPS except for the built-in trusted local converter endpoints. Response size caps apply while reading, before parsing or saving.

Provider-supplied converter PHP/JS is never installed or evaluated by the consumer; local ImageMagick/text conversion runs on the provider itself. Public discovery does not grant execution permission. Existing definitions are unpublished unless their `converter.json` contains:

```json
{
  "network": { "publish": true }
}
```

The provider configures `POFF_CONVERTER_TRUSTED_PEERS` as a JSON object mapping peer IDs to shared secrets of at least 32 bytes, for example `{"consumer-node":"<random-secret>"}`. The consumer configures `POFF_CONVERTER_PEER_CREDENTIALS` as a JSON object keyed by provider Node ID, with its local peer ID, provider origin and the same secret, for example `{"provider-node":{"peerId":"consumer-node","origin":"https://provider.example","secret":"<random-secret>"}}`. PageOff matches both the catalog Node ID and the request origin to this entry, so an arbitrary catalog cannot borrow another peer's key by claiming its Node ID. The operator provisions both sides out of band; PageOff does not transmit or expose the secret. Generate secrets with a cryptographically secure random generator and use HTTPS for remote conversion.

Signature headers are `X-Poff-Node-Id`, `X-Poff-Timestamp`, `X-Poff-Nonce`, and `X-Poff-Signature`. The signature is HMAC-SHA256 over the UTF-8 string `poff-converter/1\nPOST\nconvert-offered\n{timestamp}\n{nonce}\n{sha256(canonical-json)}`. Canonical JSON is `json_encode(payload, JSON_UNESCAPED_SLASHES)`. The receiver uses constant-time comparison and a locked replay file in a private temporary directory; `POFF_CONVERTER_REPLAY_FILE` may point to an operator-managed persistent file. Missing credentials fail closed. Remote conversion requires HTTPS, except for the built-in trusted local converter PHP endpoints. `POFF_ALLOW_PRIVATE_CONVERTER_HOSTS=1` remains intended only for local development endpoint validation and does not disable the HTTPS requirement. `poff-converter/1` JSON payload shapes and route names remain unchanged; authentication is additive HTTP metadata.

Remaining limitations: there is no peer-key rotation/handshake UI or distributed rate limit; catalog reads are public by design. The transport pins one validated address per request, so other public addresses from the same DNS answer are not retried if that address is unavailable. Authentication protects execution from untrusted callers; endpoint allowlisting remains an operator-level policy for trusted providers.

### Operator procedure: rotating a peer key

The current implementation has one secret per trusted peer ID. To overlap old and new credentials, use a new peer ID; there is no multi-key lookup for one ID.

1. Generate a new random secret of at least 32 bytes using a cryptographically secure generator. Transfer it over the operator's trusted channel; never put it into Converter definitions, URLs, logs or public Node metadata.
2. Add the new peer ID and secret to the provider's `POFF_CONVERTER_TRUSTED_PEERS` while retaining the old peer entry. Apply the environment change to every provider process serving the endpoint.
3. Update the consumer's entry for that provider in `POFF_CONVERTER_PEER_CREDENTIALS` to the new peer ID and secret, keeping the verified provider origin. Apply it to every consumer process. Test a small authorized conversion with controlled non-private input.
4. After all consumers have switched and in-flight requests have finished, remove the old peer ID from every provider process. Do not retain an old key merely to accommodate the five-minute signature window: removing it revokes new authentication immediately on updated processes.
5. If a key is compromised, revoke it first. Availability may be interrupted until replacement credentials are installed. No handshake or automatic peer enrollment is implemented.

### Operator procedure: replay storage and resource limits

Set `POFF_CONVERTER_REPLAY_FILE` to a private, persistent file outside the public content root, writable only by the PHP service account and protected from other local users. Keep its containing directory private. All processes accepting requests for the same peer keys must share this replay store and honor its file locks. Independent replay files on multiple servers do not provide global replay protection. Do not clear or rotate away the store while signed requests can still be fresh. Maintain synchronized clocks; requests outside the ±300-second window are rejected. Nonces are retained for ten minutes after acceptance because a request first accepted with a timestamp five minutes ahead can remain valid for ten minutes in total.

The built-in byte limits and timeouts do not constitute per-peer rate or compute quotas. A publicly reachable provider needs operator-managed concurrency and request-rate limits at its ingress, or restricted network reachability. Trusting a peer authorizes that peer to invoke its published converters repeatedly; distributed quotas remain unimplemented. An ingress header alone is not proof of peer identity before PageOff verifies the HMAC.

## Single-file and runtime packaging

- `viewer.php` includes `converter-network.php` and dispatches all three JSON routes.
- `src/mcp/server.php` also includes and dispatches them. It builds/updates `poff.config.toon` before normal route dispatch; do not run it as a test entrypoint.
- `build/build.php` includes and dispatches all three routes into the single-file artifact. Static `tests/mcp.routing-contract.test.js` protects source/Builder route registration; it does not prove runtime equivalence.
- `src/mcp/http-server.php` is a separate optional Streamable-MCP transport and does not expose these converter-network JSON routes as MCP tools. Remote conversion must continue to work without that transport.
- Converter JSON/PHP/template/style grains are runtime sidecars mirrored by `FileCopier`; they are not all compiled into the single PHP file. Builder inclusion and sidecar copy must be tested independently.

## Known gaps and prioritized repair backlog

1. **P0 Endpoint policy — implemented:** validate every A/AAAA answer, reject private/reserved destinations, pin the selected address to each outbound connection, reject URL credentials/fragments, restrict schemes, disable redirects/proxies and retain controlled local test support. Keep tests for mixed public/private answers and a hostname pinned to a deterministic local test server.
2. **P1 Payload/privacy:** inline requests omit consumer-relative paths, source URL and destination folder; network reads and decoded/saved results have bounded byte limits. Persistence rejects malformed or mismatched declared sizes before writing. Outgoing request-size regression and response validation before preview remain follow-up.
3. **P1 Peer operations:** add a key rotation/handshake procedure, persistent replay storage guidance and a distributed per-peer rate/compute quota. Current keys are provisioned out of band.
4. **P1 Work Editor:** provider URL defaults empty and no catalog query runs on Work open. Add a regression proving the default server config keeps auto-load disabled and explicit URL entry loads offers.
5. **P1 Persistence:** save-mode collision rules, preview rejection and declared output-size equality are covered. Size may be omitted; if provided, it must be a non-negative JSON integer equal to decoded/downloaded byte length. Add broader write-failure coverage.
6. **P1 Compatibility tests:** mocked discovery → selected offer → remote result → local generated-work save is now covered as one flow; signed sender, provider auth, replay/expiry, DNS pinning and persistence also have focused regressions. Still add a local PHP two-node HTTP integration that exercises the provider handler over the pinned transport. No live public endpoint is used in tests.
7. **P2 Docs/build:** architecture, workplan, routing and extension baselines are updated. Keep JSON API routing in source and Builder; no production `npm run build` without separate authorization.

## Test evidence and limits

Existing deterministic tests live mainly in `tests/mcp.create.test.js`, `tests/edit-auth-menu.test.js`, `tests/edit-panel-inline.test.js`, `tests/php_converter_routes.php`, and `tests/mcp.routing-contract.test.js`. They cover catalog discovery, matching, provider execution using HTTP mocks, generated result metadata, remote selection UI and source/Builder route names. The complete test suite and specific uncovered cases must be rerun/added during implementation. No live production endpoint is a valid regression fixture.
