# Demo assets

| Asset | Tool | Rule |
| --- | --- | --- |
| `demo.gif` (terminal) | [vhs](https://github.com/charmbracelet/vhs): `vhs docs/demo/demo.tape` from the repository root | 10-20 s, under 5 MB |
| `social-preview.png` | 1280x640, rendered from `assets/social-preview.svg` | Upload under Settings, Social preview |

The tape uses `Set Shell zsh`, `Env LC_ALL "de_DE.UTF-8"` so vhs types umlauts, and a short prompt so no local path appears.

Before committing a GIF: check every frame for API keys, e-mail addresses, local paths and real names.
