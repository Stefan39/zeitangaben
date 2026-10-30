# Demo assets

| Asset | Tool | Rule |
| --- | --- | --- |
| `demo.gif` (terminal) | [vhs](https://github.com/charmbracelet/vhs): `vhs docs/demo/demo.tape` from the repository root | 10-20 s, under 5 MB |
| `screenshot.png` | macOS Shift-Cmd-4 | Crop to the relevant part |
| `social-preview.png` | 1280x640, rendered from `assets/social-preview.svg` | Upload under Settings, Social preview |

The tape uses `Set Shell bash` and a short prompt so no local path appears. vhs cannot type umlauts, so the inputs use the ASCII spelling.

Before committing a GIF: check every frame for API keys, e-mail addresses, local paths and real names.
