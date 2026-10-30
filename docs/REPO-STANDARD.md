# Repo standard (Stefan39)

Checklist for every public repository. Source of truth: Confluence "Open-Source-Strategie GitHub".

## Files

- `README.md` (en) and `README.de.md` (de), cross-linked at the top.
- `LICENSE` (MIT for libraries), `CONTRIBUTING.md`, `SECURITY.md`, `CODE_OF_CONDUCT.md`, `CHANGELOG.md`.
- `.github/`: issue forms, pull request template, `workflows/ci.yml`; `renovate.json` in the repo root.
- `docs/GIF-WORKFLOW.md` is copied only if the repo has a demo.

## Badges (only what is true)

| Badge | Add when |
| --- | --- |
| CI | `ci.yml` exists and ran green once |
| Packagist version | package is published on Packagist |
| License | always |
| PHPStan level | CI runs PHPStan; the level is shown from `phpstan.neon`, not guessed |
| Coverage | CI uploads coverage to a service (e.g. Codecov) |

No badge that is not backed by a running check.

## Repository settings (GitHub)

- Description: one sentence, same as in the README.
- Website: https://www.jacomeit.de (or the project page).
- Topics: 5-8, e.g. `php`, `symfony`, `symfony-bundle`, `mcp`, `ai`; set by hand per repo.
- Social preview: export `assets/social-preview.svg` as PNG (1280x640) and upload under Settings -> Social preview.
- Branch protection on `main`: CI required, no force push.
- Private vulnerability reporting: enabled.

## Before the first release

- README has GIF or screenshot, quickstart that was tested on a clean install.
- CI is green on all matrix versions, tag `v0.1.0` or `v1.0.0`, Packagist hook active.
- "Status" section only with verifiable statements.
- No internal workflows, customer names, tokens or real data anywhere, including the git history.
