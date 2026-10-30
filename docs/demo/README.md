# Demo assets

| Asset | Tool | Rule |
| --- | --- | --- |
| `demo.gif` (terminal) | [vhs](https://github.com/charmbracelet/vhs): `vhs docs/demo/demo.tape` | 10–20 s, < 5 MB, 1200 px wide |
| `demo.gif` (UI, e.g. Claude Desktop) | Screen recording → [gifski](https://gif.ski) `gifski --fps 12 --width 1200 -o demo.gif rec.mov` | Demo data only — never real customer or account data |
| `screenshot.png` | macOS ⇧⌘4 | Crop to the relevant part |
| `social-preview.png` | 1280×640, jacomeit.de style guide | Upload under Settings → Social preview |

Before committing a GIF: check every frame for API keys, e-mail addresses and real names.
