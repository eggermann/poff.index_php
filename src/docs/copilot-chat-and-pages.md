# Copilot Chat and Public Pages

This repo has two different surfaces:

- source files in `src/`
- generated public output in `pages/`

Do not edit generated `pages/**/*.php` files directly.

## Remote Layout

On `eggman4`, the working split is:

- `~/html/copilot` - private Copilot/code-server workspace
- `~/html/pages/dodi/actual` - public PHP page for `dodi`
- `~/html/pages/dodi/actual` - public PHP page for `dodi1` aliasing the same webspace
- `~/html/pages/customer/actual` - public PHP page for `customer`
- `~/html/pages/pageoff/actual` - public PHP page for `pageoff`

The public page directories are deploy targets. The Copilot workspace is not meant to be public.

## Copilot CLI Flow

Use SSH, then start Copilot in the private workspace:

```sh
ssh eggman4@neso.uberspace.de
cd ~/html/copilot
copilot
```

Then trust the folder if prompted, and type the task as a chat prompt.

## Deploy Targets

The deploy script accepts named targets:

- `dodi`
- `dodi1`
- `customer`
- `pageoff`
- `dodi_2` as a legacy alias for the second target family

Examples:

```sh
npm run deploy -- dodi
npm run deploy -- dodi1
npm run deploy -- customer
npm run deploy -- pageoff
```

Target-specific env vars use the same base keys with an optional suffix:

- `hostcustomer`, `usercustomer`, `destinationPathcustomer`
- `host1`, `user1`, `destinationPath1`
- `hostpageoff`, `userpageoff`, `destinationPathpageoff`

Named profiles require their own complete settings, including source path and SSH authentication. They never inherit the default profile. The current local `customer` and `pageoff` profiles are not configured and abort before connecting. Check a target first with `node scripts/deploy/deploy.js <target> --dry-run`; this prints the source/host/destination without connecting and does not validate credentials.

Numeric suffixes still work too:

- `host2`, `user2`, `destinationPath2`

`dodi1` is a target label for the same public `/dodi/` webspace, not a separate URL.

## Source of Truth

- Edit `src/` for app behavior.
- Edit `scripts/` for deploy behavior.
- Use `pages/` only as generated output.
