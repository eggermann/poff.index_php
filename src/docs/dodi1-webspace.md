# dodi1 Webspace

`dodi1` is the sample webspace for testing image uploads and the remote image converter flow. It aliases the existing `/dodi/` public webspace.

## Files

- Use `archive/scan-001.tiff` as the source image.
- Convert it to `scan-001.png` through the pageoff remote image converter.

## Deploy target

- `npm run deploy -- dodi1`
- The target resolves from the `1` suffix, so env keys can use `host1`, `user1`, and `destinationPath1`.
- Remote path: `/var/www/virtual/eggman4/html/pages/dodi/actual/`
- Local fixture folder: `poff/test-converter/dodi1/`

## Conversion flow

1. Upload the TIFF from `poff/test-converter/dodi1/` into the dodi1 webspace.
2. Open the converter UI.
3. Select the remote pageoff image converter.
4. Choose `png` as the output format.
5. Save the generated PNG back into the same folder.

## Notes

- Keep the converter workflow in `src/includes/worktypes/templates/converter/`.
- Keep generated output files out of `pages/`.
